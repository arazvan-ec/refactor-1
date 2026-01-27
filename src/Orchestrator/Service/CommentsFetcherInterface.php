<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

/**
 * Fetches comment count for editorials.
 */
interface CommentsFetcherInterface
{
    /**
     * Fetch the number of comments for an editorial.
     */
    public function fetchCommentsCount(string $editorialId): int;
}
