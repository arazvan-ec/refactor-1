<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * Result of batch promise resolution.
 *
 * Contains fulfilled results and rejected errors from parallel promise execution.
 */
final readonly class BatchResult
{
    /**
     * @param array<string, mixed> $fulfilled Successfully resolved values keyed by promise key
     * @param array<string, \Throwable> $rejected Failed promises with their exceptions
     */
    public function __construct(
        public array $fulfilled,
        public array $rejected = [],
    ) {
    }

    /**
     * Check if any promises were rejected.
     */
    public function hasRejected(): bool
    {
        return !empty($this->rejected);
    }

    /**
     * Check if all promises succeeded.
     */
    public function allFulfilled(): bool
    {
        return empty($this->rejected);
    }

    /**
     * Get count of fulfilled promises.
     */
    public function fulfilledCount(): int
    {
        return count($this->fulfilled);
    }

    /**
     * Get count of rejected promises.
     */
    public function rejectedCount(): int
    {
        return count($this->rejected);
    }
}
