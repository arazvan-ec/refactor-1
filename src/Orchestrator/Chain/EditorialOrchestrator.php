<?php

/**
 * @copyright
 */

declare(strict_types=1);

namespace App\Orchestrator\Chain;

use App\Application\Service\Editorial\EditorialFetcherInterface;
use App\Application\Service\Editorial\EmbeddedContentFetcherInterface;
use App\Application\Service\Editorial\ResponseAggregatorInterface;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Infrastructure\Enum\SitesEnum;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use Ec\Tag\Domain\Model\Tag;
use Http\Promise\Promise;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Orchestrates the complete editorial response.
 *
 * Coordinates fetching, promise resolution, and response aggregation
 * by delegating to specialized services.
 *
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements EditorialOrchestratorInterface
{
    public function __construct(
        private readonly EditorialFetcherInterface $editorialFetcher,
        private readonly EmbeddedContentFetcherInterface $embeddedContentFetcher,
        private readonly PromiseResolverInterface $promiseResolver,
        private readonly ResponseAggregatorInterface $responseAggregator,
        private readonly QueryTagClient $queryTagClient,
        private readonly QueryMembershipClient $queryMembershipClient,
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly UriFactoryInterface $uriFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute the editorial orchestration.
     *
     * @return array{
     *   id: string,
     *   url: string,
     *   titles: array{title: string, preTitle: string, urlTitle: string, mobileTitle: string},
     *   lead: string,
     *   publicationDate: string,
     *   updatedOn: string,
     *   endOn: string,
     *   type: array{id: string, name: string},
     *   indexable: bool,
     *   deleted: bool,
     *   published: bool,
     *   closingModeId: string,
     *   commentable: bool,
     *   isBrand: bool,
     *   isAmazonOnsite: bool,
     *   contentType: string,
     *   canonicalEditorialId: string,
     *   urlDate: string,
     *   countWords: int,
     *   countComments: int,
     *   section: array{id: string, name: string, url: string, encodeName: string},
     *   tags: list<array{id: string, name: string, url: string}>,
     *   signatures: list<array{id: string, name: string, picture: string|null, url: string, twitter?: string}>,
     *   body: list<array{type: string, content?: string}>,
     *   multimedia: array{id: string, type: string, caption: string, shots: object}|null,
     *   standfirst: list<array{type: string, content: string}>,
     *   recommendedEditorials: list<array{id: string, title: string, url: string, image?: string}>,
     *   adsOptions: list<string>,
     *   analiticsOptions: list<string>
     * }
     *
     * @throws \Throwable
     */
    public function execute(Request $request): array
    {
        /** @var string $id */
        $id = $request->get('id');

        // Fetch editorial and section
        $fetchedEditorial = $this->editorialFetcher->fetch($id);

        // Handle legacy editorial
        if ($this->editorialFetcher->shouldUseLegacy($fetchedEditorial->editorial)) {
            return $this->editorialFetcher->fetchLegacy($id);
        }

        $editorial = $fetchedEditorial->editorial;
        $section = $fetchedEditorial->section;

        // Fetch all embedded content (inserted news, recommended, multimedia)
        $embeddedContent = $this->embeddedContentFetcher->fetch($editorial, $section);

        // Fetch tags
        $tags = $this->fetchTags($editorial);

        // Get membership links promise
        [$membershipPromise, $membershipLinks] = $this->getPromiseMembershipLinks($editorial, $section);

        // Resolve multimedia promises
        $resolvedMultimedia = $this->promiseResolver->resolveMultimedia(
            $embeddedContent->multimediaPromises
        );

        // Resolve membership links
        $resolvedMembershipLinks = $this->promiseResolver->resolveMembershipLinks(
            $membershipPromise,
            $membershipLinks
        );

        // Get photos from body tags
        $photoBodyTags = $this->retrievePhotosFromBodyTags($editorial->body());

        // Aggregate final response
        return $this->responseAggregator->aggregate(
            $fetchedEditorial,
            $embeddedContent,
            $tags,
            $resolvedMultimedia,
            $resolvedMembershipLinks,
            $photoBodyTags,
        );
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }

    /**
     * Fetch tags for the editorial.
     *
     * @return array<int, Tag>
     */
    private function fetchTags(Editorial $editorial): array
    {
        $tags = [];

        foreach ($editorial->tags()->getArrayCopy() as $tag) {
            try {
                $tags[] = $this->queryTagClient->findTagById($tag->id());
            } catch (\Throwable $exception) {
                $this->logger->warning('Failed to fetch tag: ' . $exception->getMessage());
            }
        }

        return $tags;
    }

    /**
     * Get membership links promise from editorial body.
     *
     * @return array{0: Promise|null, 1: array<int, string>}
     */
    private function getPromiseMembershipLinks(Editorial $editorial, Section $section): array
    {
        $linksData = $this->getLinksFromBody($editorial->body());

        $links = [];
        $uris = [];

        foreach ($linksData as $membershipLink) {
            $uris[] = $this->uriFactory->createUri($membershipLink);
            $links[] = $membershipLink;
        }

        if (empty($uris)) {
            return [null, []];
        }

        /** @var Promise $promise */
        $promise = $this->queryMembershipClient->getMembershipUrl(
            $editorial->id()->id(),
            $uris,
            SitesEnum::getEncodenameById($section->siteId()),
            true
        );

        return [$promise, $links];
    }

    /**
     * Get membership links from editorial body.
     *
     * @return array<int, string>
     */
    private function getLinksFromBody(Body $body): array
    {
        $linksData = [];

        /** @var BodyTagMembershipCard[] $bodyElementsMembership */
        $bodyElementsMembership = $body->bodyElementsOf(BodyTagMembershipCard::class);

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
     * Retrieve photos from body tags.
     *
     * @return array<string, mixed>
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
     * Add a photo to the result array.
     *
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function addPhotoToArray(string $id, array $result): array
    {
        try {
            $photo = $this->queryMultimediaClient->findPhotoById($id);
            $result[$id] = $photo;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to fetch photo: ' . $throwable->getMessage());
        }

        return $result;
    }
}
