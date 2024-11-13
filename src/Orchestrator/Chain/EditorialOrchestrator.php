<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Infrastructure\Enum\SitesEnum;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use App\Exception\EditorialNotPublishedYetException;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Tag\Domain\Model\QueryTagClient;
use Http\Promise\Promise;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements Orchestrator
{
    public function __construct(
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QueryJournalistClient $queryJournalistClient,
        private readonly QuerySectionClient $querySectionClient,
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly AppsDataTransformer $detailsAppsDataTransformer,
        private readonly QueryTagClient $queryTagClient,
        private readonly BodyDataTransformer $bodyDataTransformer,
        private readonly UriFactoryInterface $uriFactory,
        private readonly QueryMembershipClient $queryMembershipClient,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function execute(Request $request): array
    {
        $id = $request->get('id');

        /** @var Editorial $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($id);

        if (null === $editorial->sourceEditorial()) {
            return $this->queryLegacyClient->findEditorialById($id);
        }

        if (!$editorial->isVisible()) {
            throw new EditorialNotPublishedYetException();
        }

        $journalists = [];
        foreach ($editorial->signatures() as $signature) {
            $aliasId = $this->journalistFactory->buildAliasId($signature->id()->id());
            /** @var Journalist $journalist */
            $journalist = $this->queryJournalistClient->findJournalistByAliasId($aliasId);

            if ($journalist->isActive() && $journalist->isVisible()) {
                $journalists[$aliasId->id()] = $journalist;
            }
        }

        $section = $this->querySectionClient->findSectionById($editorial->sectionId());

        $tags = [];
        foreach ($editorial->tags() as $tag) {
            try {
                $tags[] = $this->queryTagClient->findTagById($tag->id());
            } catch (\Throwable $exception) {
                continue;
            }
        }

        $editorialResult =  $this->detailsAppsDataTransformer->write($editorial, $journalists, $section, $tags)->read();

        $comments = $this->queryLegacyClient->findCommentsByEditorialId($id);
        $editorialResult['countComments'] = (isset($comments['options']['totalrecords']))
            ? $comments['options']['totalrecords'] : 0;

        $resolveData['photoFromBodyTags'] = $this->retrievePhotosFromBodyTags($editorial);

        [$promise, $links] = $this->getPromiseMembershipLinks($editorial, $section->siteId());
        $resolveData['membershipLinkCombine'] = $this->resolvePromiseMembershipLinks($promise, $links);

        $editorialResult['body'] = $this->bodyDataTransformer->execute(
            $editorial->body(),
            $resolveData
        );

        return $editorialResult;
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }

    protected function retrievePhotosFromBodyTags(Editorial $editorial): array
    {
        $result = [];
        /** @var BodyTagPicture[] $arrayOfBodyTagPicture */
        $arrayOfBodyTagPicture = $editorial->body()->bodyElementsOf(BodyTagPicture::class);
        foreach ($arrayOfBodyTagPicture as $bodyTagPicture) {
            $result = $this->addPhotoToArray($bodyTagPicture->id()->id(), $result);
        }

        /** @var BodyTagMembershipCard[] $arrayOfBodyTagMembershipCard */
        $arrayOfBodyTagMembershipCard = $editorial->body()->bodyElementsOf(BodyTagMembershipCard::class);
        foreach ($arrayOfBodyTagMembershipCard as $bodyTagMembershipCard) {
            $id = $bodyTagMembershipCard->bodyTagPictureMembership()->id()->id();
            $result = $this->addPhotoToArray($id, $result);
        }

        return $result;
    }

    protected function addPhotoToArray(string $id, array $result): array
    {
        try {
            $photo = $this->queryMultimediaClient->findPhotoById($id);
            $result[$id] = $photo;
        } catch (\Throwable $throwable) {
            // $this->logger()->error($throwable->getMessage(), $throwable->getTrace());
        }

        return $result;
    }

    private function getLinksOfBodyTagMembership(Body $body): array
    {
        $linksData = [];

        $bodyElementsMembership = $body->bodyElementsOf(BodyTagMembershipCard::class);
        /** @var BodyTagMembershipCard $bodyElement */
        foreach ($bodyElementsMembership as $bodyElement) {
            /** @var MembershipCardButton $button */
            foreach ($bodyElement->buttons()->buttons() as $button) {
                $linksData[] = $button->urlMembership();
                $linksData[] = $button->url();
            }
        }

        return $linksData;
    }

    private function getLinksFromBody(Body $body): array
    {
        // $linksOfElementsContentWithLinks = $this->getLinksOfElementContentWithLinks($body);
        $linksOfBodyTagMembership = $this->getLinksOfBodyTagMembership($body);
        // $linksOfBodyTagPicture = $this->getLinksOfBodyTagPicture($body);

        return \array_merge(
            // $linksOfElementsContentWithLinks,
            $linksOfBodyTagMembership,
            // $linksOfBodyTagPicture
        );
    }

    private function getPromiseMembershipLinks(Editorial $editorial, string $siteId): array
    {
        $linksData = $this->getLinksFromBody($editorial->body());

        $links = [];
        $uris = [];
        foreach ($linksData as $membershipLink) {
            $uris[] = $this->uriFactory->createUri($membershipLink);
            $links[] = $membershipLink;
        }

        $promise = $this->queryMembershipClient->getMembershipUrl(
            $editorial->id()->id(),
            $uris,
            SitesEnum::getEncodenameById($siteId),
            true
        );

        return [$promise, $links];
    }

    private function resolvePromiseMembershipLinks(?Promise $promise, array $links): array
    {
        $membershipLinkResult = [];
        if ($promise) {
            try {
                $membershipLinkResult = $promise->wait();
            } catch (\Throwable $throwable) {
                return [];
            }
        }

        if (empty($membershipLinkResult)) {
            return [];
        }

        return \array_combine($links, $membershipLinkResult);
    }
}
