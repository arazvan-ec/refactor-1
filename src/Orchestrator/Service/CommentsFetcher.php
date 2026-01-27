<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Infrastructure\Client\Legacy\QueryLegacyClient;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Fetches comment count for editorials from the legacy system.
 *
 * This service lives in the Orchestrator layer because it makes HTTP calls
 * to the legacy service. The count is then passed to the ResponseAggregator
 * which only does data transformation.
 */
final class CommentsFetcher implements CommentsFetcherInterface
{
    public function __construct(
        private readonly QueryLegacyClient $legacyClient,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetchCommentsCount(string $editorialId): int
    {
        /** @var array{options: array{totalrecords?: int}} $comments */
        $comments = $this->legacyClient->findCommentsByEditorialId($editorialId);

        return $comments['options']['totalrecords'] ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchCommentsCountAsync(string $editorialId): PromiseInterface
    {
        return $this->legacyClient
            ->findCommentsByEditorialId($editorialId, async: true)
            ->then(function (array $comments): int {
                /** @var array{options: array{totalrecords?: int}} $comments */
                return $comments['options']['totalrecords'] ?? 0;
            });
    }
}
