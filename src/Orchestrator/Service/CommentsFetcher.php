<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use App\Infrastructure\Client\Legacy\QueryLegacyClient;

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
}
