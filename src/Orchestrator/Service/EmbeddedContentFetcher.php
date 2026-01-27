<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Application\DTO\EmbeddedContentDTO;
use App\Orchestrator\Service\EmbeddedContent\InsertedNewsFetcherInterface;
use App\Orchestrator\Service\EmbeddedContent\MainMultimediaFetcherInterface;
use App\Orchestrator\Service\EmbeddedContent\OpeningMultimediaFetcherInterface;
use App\Orchestrator\Service\EmbeddedContent\RecommendedEditorialsFetcherInterface;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;

/**
 * Coordinator for fetching embedded content.
 *
 * Delegates to specialized fetchers, each with a single responsibility:
 * - InsertedNewsFetcher: fetches inserted news from body
 * - RecommendedEditorialsFetcher: fetches recommended editorials
 * - OpeningMultimediaFetcher: fetches opening multimedia
 * - MainMultimediaFetcher: fetches main multimedia
 *
 * This class is now a simple coordinator with no direct HTTP client dependencies.
 */
final class EmbeddedContentFetcher implements EmbeddedContentFetcherInterface
{
    public function __construct(
        private readonly InsertedNewsFetcherInterface $insertedNewsFetcher,
        private readonly RecommendedEditorialsFetcherInterface $recommendedEditorialsFetcher,
        private readonly OpeningMultimediaFetcherInterface $openingMultimediaFetcher,
        private readonly MainMultimediaFetcherInterface $mainMultimediaFetcher,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(NewsBase $editorial, Section $section): EmbeddedContentDTO
    {
        $insertedNews = $this->insertedNewsFetcher->fetch($editorial);
        $recommendedContent = $this->recommendedEditorialsFetcher->fetch($editorial);
        $openingData = $this->openingMultimediaFetcher->fetch($editorial);
        $mainMultimedia = $this->mainMultimediaFetcher->fetch($editorial);

        return new EmbeddedContentDTO(
            insertedNews: $insertedNews['editorials'],
            recommendedEditorials: $recommendedContent['editorials'],
            recommendedNews: $recommendedContent['news'],
            multimediaPromises: array_merge(
                $insertedNews['promises'],
                $recommendedContent['promises'],
                $mainMultimedia['promises']
            ),
            multimediaOpening: array_merge($openingData, $mainMultimedia['opening']),
        );
    }
}
