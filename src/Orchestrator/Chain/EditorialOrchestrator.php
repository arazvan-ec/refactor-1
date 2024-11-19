<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Infrastructure\Enum\SitesEnum;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Editorial\Domain\Model\Signatures;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\Journalists;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use App\Exception\EditorialNotPublishedYetException;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Tag\Domain\Model\QueryTagClient;
use Http\Promise\Promise;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements Orchestrator
{
    use UrlGeneratorTrait;

    public function __construct(
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QuerySectionClient $querySectionClient,
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly AppsDataTransformer $detailsAppsDataTransformer,
        private readonly QueryTagClient $queryTagClient,
        private readonly BodyDataTransformer $bodyDataTransformer,
        private readonly UriFactoryInterface $uriFactory,
        private readonly QueryMembershipClient $queryMembershipClient,
        private readonly LoggerInterface $logger,
        private readonly JournalistsDataTransformer $journalistsDataTransformer,
        private readonly QueryJournalistClient $queryJournalistClient,
        private readonly JournalistFactory $journalistFactory,
        string $extension,
    ) {
        $this->setExtension($extension);
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

        $section = $this->querySectionClient->findSectionById($editorial->sectionId());

        [$promise, $links] = $this->getPromiseMembershipLinks($editorial, $section->siteId());

        $editorialSignatures = [];
        /** @var Signature $signature */
        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $id = $signature->id()->id();
            $editorialSignatures[$id] = $id;
        }

        /** @var BodyTagInsertedNews[] $insertedNews */
        $insertedNews = $editorial->body()->bodyElementsOf(BodyTagInsertedNews::class);

        /** @var BodyTagInsertedNews $insertedNews */
        foreach ($insertedNews as $insertedNew) {
            $id = $insertedNew->editorialId()->id();

            /** @var Editorial $editorialinserted */
            $editorialinserted = $this->queryEditorialClient->findEditorialById($id);
            $sectionInserted = $this->querySectionClient->findSectionById($editorialinserted->sectionId());

            $resolveData['insertedNews'][$id]['editorial'] = $editorialinserted;
            $resolveData['insertedNews'][$id]['section'] = $sectionInserted;

            /** @var Signature $signature */
            foreach ($editorialinserted->signatures() as $signature) {
                $aliasId = $signature->id()->id();
                $resolveData['insertedNews'][$id]['signatures'][] = $aliasId;
                $editorialSignatures[$aliasId] = $aliasId;
            }

            $resolveData['insertedNews'][$id]['photo'] = 'cacatua';

        }

        /** @var Journalists $journalists */
        $journalists = [];

        foreach ($editorialSignatures as $signatureId) {
            $aliasId = $this->journalistFactory->buildAliasId($signatureId);

            /** @var Journalist $journalist */
            $journalist = $this->queryJournalistClient->findJournalistByAliasId($aliasId);

            if ($journalist->isActive() && $journalist->isVisible()) {
                // Todo: fix index object
                $journalists[$aliasId->id()] = $journalist;
            }
        }


        $journalists = $this->journalistsDataTransformer->write($journalists, $section)->read();

        $tags = [];
        foreach ($editorial->tags() as $tag) {
            try {
                $tags[] = $this->queryTagClient->findTagById($tag->id());
            } catch (\Throwable $exception) {
                continue;
            }
        }

        $editorialResult = $this->detailsAppsDataTransformer->write(
            $editorial,
            $section,
            $tags
        )->read();

        $comments = $this->queryLegacyClient->findCommentsByEditorialId($id);
        $editorialResult['countComments'] = (isset($comments['options']['totalrecords']))
            ? $comments['options']['totalrecords'] : 0;


        $resolveData['photoFromBodyTags'] = $this->retrievePhotosFromBodyTags($editorial->body());

        $resolveData['membershipLinkCombine'] = $this->resolvePromiseMembershipLinks($promise, $links);

        $editorialResult['signatures'] = $this->retrieveJournalists($editorial, $journalists);
        $resolveData['signatures'] = $journalists;

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

    /**
     * @return array<mixed>
     */
    private function retrievePhotosFromBodyTags(Body $body): array
    {
        $result = [];
        /** @var BodyTagPicture[] $arrayOfBodyTagPicture */
        $arrayOfBodyTagPicture = $body->bodyElementsOf(BodyTagPicture::class);
        foreach ($arrayOfBodyTagPicture as $bodyTagPicture) {
            $result = $this->addPhotoToArray($bodyTagPicture->id()->id(), $result);
        }

        /** @var BodyTagMembershipCard[] $arrayOfBodyTagMembershipCard */
        $arrayOfBodyTagMembershipCard = $body->bodyElementsOf(BodyTagMembershipCard::class);
        foreach ($arrayOfBodyTagMembershipCard as $bodyTagMembershipCard) {
            $id = $bodyTagMembershipCard->bodyTagPictureMembership()->id()->id();
            $result = $this->addPhotoToArray($id, $result);
        }

        return $result;
    }

    /**
     * @param array<mixed> $result
     *
     * @return array<mixed>
     */
    private function addPhotoToArray(string $id, array $result): array
    {
        try {
            $photo = $this->queryMultimediaClient->findPhotoById($id);
            $result[$id] = $photo;
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage(), $throwable->getTrace());
        }

        return $result;
    }

    /**
     * @return array<mixed>
     */
    private function getLinksOfBodyTagMembership(Body $body): array
    {
        $linksData = [];

        $bodyElementsMembership = $body->bodyElementsOf(BodyTagMembershipCard::class);
        /** @var BodyTagMembershipCard $bodyElement */
        foreach ($bodyElementsMembership as $bodyElement) {
            /** @var MembershipCardButton $button */
            foreach ($bodyElement->buttons()->buttons() as $button) {
                $linksData[] = $button->url();
                $linksData[] = $button->urlMembership();
            }
        }

        return $linksData;
    }

    /**
     * @return array<mixed>
     */
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

    /**
     * @return array<mixed>
     */
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

    /**
     * @param array<string, string> $links
     *
     * @return array<mixed>
     */
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

    private function retrieveJournalists(Editorial $editorial, array $journalists): array
    {
        $result = [];

        /** @var Signature $signature */
        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $result[] = $this->getJournalistByAliasId($signature->id()->id(), $journalists);
        }

        return $result;
    }

    private function getJournalistByAliasId(string $aliasId, array $journalists): array
    {
        return $journalists[$aliasId];
    }

    private function hasSignature(Signatures $signatures, string $aliasId): bool
    {
        // TODO
        foreach ($signatures->getArrayCopy() as $signature) {
            if ($signature->id()->id() == $aliasId) {
                return true;
            }
        }

        return false;
    }
}
