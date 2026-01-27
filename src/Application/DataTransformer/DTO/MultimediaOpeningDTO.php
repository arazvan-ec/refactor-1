<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Domain\Model\Photo\Photo;

/**
 * Opening multimedia data with photo resource.
 *
 * Contains the multimedia photo metadata and the actual photo resource
 * needed for generating Thumbor URLs.
 */
final readonly class MultimediaOpeningDTO
{
    public function __construct(
        public MultimediaPhoto $opening,
        public Photo $resource,
    ) {}

    /**
     * Create from legacy array format.
     *
     * @param array{opening: MultimediaPhoto, resource: Photo} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            opening: $data['opening'],
            resource: $data['resource'],
        );
    }

    /**
     * Convert to array for backward compatibility.
     *
     * @return array{opening: MultimediaPhoto, resource: Photo}
     */
    public function toArray(): array
    {
        return [
            'opening' => $this->opening,
            'resource' => $this->resource,
        ];
    }
}
