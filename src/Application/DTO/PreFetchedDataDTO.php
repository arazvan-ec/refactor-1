<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO containing data that must be fetched externally before response aggregation.
 *
 * This DTO ensures that HTTP calls happen in the Orchestrator layer,
 * not in the transformation/aggregation layer.
 */
final readonly class PreFetchedDataDTO
{
    /**
     * @param int $commentsCount Number of comments from legacy system
     * @param array<int, array{id: string, name: string, picture: string|null, url: string, twitter?: string}> $signatures Transformed journalist signatures
     */
    public function __construct(
        public int $commentsCount,
        public array $signatures,
    ) {
    }

    /**
     * Create with default/empty values.
     */
    public static function empty(): self
    {
        return new self(
            commentsCount: 0,
            signatures: [],
        );
    }
}
