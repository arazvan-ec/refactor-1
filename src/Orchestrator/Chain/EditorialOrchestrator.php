<?php

/**
 * @copyright
 */

declare(strict_types=1);

namespace App\Orchestrator\Chain;

use App\Application\DTO\PreFetchedDataDTO;
use App\Application\Service\Editorial\ResponseAggregatorInterface;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\DTO\EditorialContext;
use App\Orchestrator\Enricher\ContentEnricherChainHandler;
use App\Orchestrator\Service\CommentsFetcherInterface;
use App\Orchestrator\Service\EditorialFetcherInterface;
use App\Orchestrator\Service\EmbeddedContentFetcherInterface;
use App\Orchestrator\Service\SignatureFetcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Orchestrates the complete editorial response.
 *
 * Coordinates fetching, content enrichment, promise resolution, and response aggregation
 * by delegating to specialized services.
 *
 * HTTP calls are made in the Orchestrator layer via:
 * - EditorialFetcher (editorial + section)
 * - EmbeddedContentFetcher (inserted news, recommended, multimedia)
 * - ContentEnricherChainHandler (tags, membership links, photos from body)
 * - SignatureFetcher (journalist signatures)
 * - CommentsFetcher (comment count)
 *
 * To add new data to the editorial response, create a ContentEnricher
 * implementing ContentEnricherInterface with the 'app.content_enricher' tag.
 * No changes to this class are needed.
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
        private readonly ContentEnricherChainHandler $enricherChain,
        private readonly SignatureFetcherInterface $signatureFetcher,
        private readonly CommentsFetcherInterface $commentsFetcher,
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

        // Create context for enrichers
        $context = new EditorialContext(
            editorial: $editorial,
            section: $section,
            embeddedContent: $embeddedContent,
        );

        // Enrich context with additional data (tags, membership links, photos)
        // New enrichers can be added by simply creating a class with the tag
        $this->enricherChain->enrichAll($context);

        // Resolve multimedia promises
        $resolvedMultimedia = $this->promiseResolver->resolveMultimedia(
            $embeddedContent->multimediaPromises
        );

        // Fetch external data (HTTP calls happen here in the Orchestrator layer)
        $preFetchedData = new PreFetchedDataDTO(
            commentsCount: $this->commentsFetcher->fetchCommentsCount($editorial->id()->id()),
            signatures: $this->signatureFetcher->fetchSignatures($editorial, $section),
        );

        // Aggregate final response (no HTTP calls in aggregator)
        return $this->responseAggregator->aggregate(
            $fetchedEditorial,
            $embeddedContent,
            $context->getTags(),
            $resolvedMultimedia,
            $context->getMembershipLinks(),
            $context->getPhotoBodyTags(),
            $preFetchedData,
        );
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
