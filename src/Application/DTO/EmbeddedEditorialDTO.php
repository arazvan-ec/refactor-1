<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Ec\Editorial\Domain\Model\Editorial;
use Ec\Section\Domain\Model\Section;

/**
 * DTO for embedded editorial (inserted news or recommended).
 *
 * Represents an editorial embedded within another editorial's body
 * or recommended section, with all its associated data.
 */
final readonly class EmbeddedEditorialDTO
{
    /**
     * @param array<int, array<string, mixed>> $signatures Formatted signature data
     */
    public function __construct(
        public string $id,
        public Editorial $editorial,
        public Section $section,
        public array $signatures,
        public ?string $multimediaId,
    ) {
    }

    /**
     * Convert to array format for backwards compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'editorial' => $this->editorial,
            'section' => $this->section,
            'signatures' => $this->signatures,
            'multimediaId' => $this->multimediaId,
        ];
    }
}
