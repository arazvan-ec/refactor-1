<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

use Ec\Editorial\Domain\Model\Editorial;
use Ec\Section\Domain\Model\Section;

/**
 * Data for an inserted editorial within body content.
 *
 * Contains the editorial, its section, signatures, and optional multimedia reference.
 */
final readonly class InsertedEditorialDTO
{
    /**
     * @param Editorial $editorial The inserted editorial
     * @param Section $section The editorial's section
     * @param list<array<int, array<string, mixed>>> $signatures Author signatures
     * @param string|null $multimediaId Optional multimedia ID for lookup
     */
    public function __construct(
        public Editorial $editorial,
        public Section $section,
        public array $signatures = [],
        public ?string $multimediaId = null,
    ) {}

    /**
     * Create from legacy array format.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            editorial: $data['editorial'],
            section: $data['section'],
            signatures: $data['signatures'] ?? [],
            multimediaId: $data['multimediaId'] ?? null,
        );
    }

    /**
     * Convert to array for backward compatibility.
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
