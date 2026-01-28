<?php

declare(strict_types=1);

namespace App\Orchestrator\Service;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Fetches comment count for editorials.
 */
interface CommentsFetcherInterface
{
    /**
     * Fetch the number of comments for an editorial.
     */
    public function fetchCommentsCount(string $editorialId): int;

    /**
     * Fetch the number of comments for an editorial asynchronously.
     *
     * @return PromiseInterface<int>
     */
    public function fetchCommentsCountAsync(string $editorialId): PromiseInterface;
}
