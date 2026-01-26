<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;

/**
 * Data Transfer Object for fetched editorial data.
 *
 * Contains the editorial and its associated section.
 * Immutable after construction.
 */
final readonly class FetchedEditorialDTO
{
    public function __construct(
        public NewsBase $editorial,
        public Section $section,
        public bool $isLegacy = false,
    ) {
    }

    /**
     * Create a DTO for legacy editorial response.
     *
     * @param array<string, mixed> $legacyData
     */
    public static function createLegacy(array $legacyData): self
    {
        // For legacy responses, we don't have typed objects
        // This is a marker to indicate legacy handling is needed
        throw new \RuntimeException('Legacy editorial requires special handling');
    }
}
