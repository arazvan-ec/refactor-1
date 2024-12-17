<?php
/**
 * @copyright
 */

namespace App\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\Apps\StandfirstDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Infrastructure\Enum\SitesEnum;
use App\Infrastructure\Trait\MultimediaTrait;
use App\Infrastructure\Trait\UrlGeneratorTrait;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Multimedia\Multimedia;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
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
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use GuzzleHttp\Promise\Utils;
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
    use MultimediaTrait;

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
        private readonly MultimediaDataTransformer $multimediaDataTransformer,
        private readonly StandfirstDataTransformer $standFirstDataTransformer,
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

        /** @var array<string, array<string, mixed>> $resolveData */
        $resolveData = [];

        [$promise, $links] = $this->getPromiseMembershipLinks($editorial, $section->siteId());

        /** @var BodyTagInsertedNews[] $insertedNews */
        $insertedNews = $editorial->body()->bodyElementsOf(BodyTagInsertedNews::class);

        $resolveData['multimedia'] = [];
        foreach ($insertedNews as $insertedNew) {
            $idInserted = $insertedNew->editorialId()->id();

            /** @var Editorial $insertedEditorials */
            $insertedEditorials = $this->queryEditorialClient->findEditorialById($idInserted);
            if ($insertedEditorials->isVisible()) {

                $sectionInserted = $this->querySectionClient->findSectionById($insertedEditorials->sectionId());

                $resolveData['insertedNews'][$idInserted]['editorial'] = $insertedEditorials;
                $resolveData['insertedNews'][$idInserted]['section'] = $sectionInserted;
                $resolveData['insertedNews'][$idInserted]['signatures'] = [];
                /** @var Signature $signature */
                foreach ($insertedEditorials->signatures()->getArrayCopy() as $signature) {
                    $resolveData['insertedNews'][$idInserted]['signatures'][] = $this->retriveAliasFormat($signature->id()->id(), $sectionInserted);
                }

                /** @var array<string, array<string, mixed>> $resolveData */
                $resolveData = $this->getAsyncMultimedia($insertedEditorials->multimedia(), $resolveData);

                $resolveData['insertedNews'][$idInserted]['multimediaId'] = $insertedEditorials->multimedia()->id()->id();
            }
        }

        $resolveData = $this->getAsyncMultimedia($editorial->multimedia(), $resolveData);
        if (!empty($resolveData['multimedia'])) {
            $resolveData['multimedia'] = Utils::settle($resolveData['multimedia'])
                ->then($this->createCallback([$this, 'fulfilledMultimedia']))
                ->wait(true);
        }

        $resolveData['photoFromBodyTags'] = $this->retrievePhotosFromBodyTags($editorial->body());

        $tags = [];
        foreach ($editorial->tags()->getArrayCopy() as $tag) {
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
        $editorialResult['countComments'] = $comments['options']['totalrecords'] ?? 0;
        $editorialResult['signatures'] = [];

        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $editorialResult['signatures'][] = $this->retriveAliasFormat($signature->id()->id(), $section);
        }

        $resolveData['membershipLinkCombine'] = $this->resolvePromiseMembershipLinks($promise, $links);

        $editorialResult['body'] = $this->bodyDataTransformer->execute(
            $editorial->body(),
            $resolveData
        );

        $editorialResult['multimedia'] = $this->multimediaDataTransformer
            ->write($resolveData['multimedia'], $editorial->multimedia())
            ->read();

        $editorialResult['standfirst'] = $this->standFirstDataTransformer
            ->write($editorial->standFirst())
            ->read();

        return $editorialResult;
    }

    /**
     * @return array<mixed>
     */
    private function retriveAliasFormat(string $aliasId, Section $section): array
    {

        $signature = [];

        $aliasIdModel = $this->journalistFactory->buildAliasId($aliasId);

        try {
            /** @var Journalist $journalist */
            $journalist = $this->queryJournalistClient->findJournalistByAliasId($aliasIdModel);

            if ($journalist->isActive() &&  $journalist->isVisible()) {
                $signature = $this->journalistsDataTransformer->write($aliasId, $journalist, $section)->read();
            }
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage(), $throwable->getTrace());
        }

        return $signature;

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
                $linksData[] = $button->urlMembership();
                $linksData[] = $button->url();
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

    /**
     * @param array<string, mixed> $resolveData
     *
     * @return array<string, array<int, Promise>>
     */
    private function getAsyncMultimedia(Multimedia $multimedia, array $resolveData): array
    {
        $multimediaId = $this->getMultimediaId($multimedia);

        if (null !== $multimediaId) {
            $resolveData['multimedia'][] = $this->queryMultimediaClient->findMultimediaById($multimediaId, true);
        }

        return $resolveData;
    }

    /**
     * @param array<string, string> ...$parameters
     */
    protected function createCallback(callable $callable, ...$parameters): \Closure
    {
        return static function ($element) use ($callable, $parameters) {
            return $callable($element, ...$parameters);
        };
    }

    /**
     * @param array<string, mixed> $promises
     *
     * @return array<string, \Ec\Multimedia\Domain\Model\Multimedia>
     */
    protected function fulfilledMultimedia(array $promises): array
    {
        $result = [];
        foreach ($promises as $promise) {
            if (Promise::FULFILLED === $promise['state']) {
                /** @var \Ec\Multimedia\Domain\Model\Multimedia $multimedia */
                $multimedia = $promise['value'];
                $result[$multimedia->id()] = $multimedia;
            }
        }

        return $result;
    }
}
