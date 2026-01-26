<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Ec\Editorial\Domain\Model\Editorial;
use GuzzleHttp\Promise\PromiseInterface;
use Http\Promise\Promise;

/**
 * DTO containing all embedded content for an editorial.
 *
 * Includes inserted news, recommended editorials, and multimedia promises.
 */
final readonly class EmbeddedContentDTO
{
    /**
     * @param array<string, EmbeddedEditorialDTO> $insertedNews Keyed by editorial ID
     * @param array<string, EmbeddedEditorialDTO> $recommendedEditorials Keyed by editorial ID
     * @param array<int, Editorial> $recommendedNews Raw editorial objects for transformer
     * @param array<int, PromiseInterface|Promise> $multimediaPromises Promises to resolve
     * @param array<string, array<string, mixed>> $multimediaOpening Opening multimedia data
     */
    public function __construct(
        public array $insertedNews = [],
        public array $recommendedEditorials = [],
        public array $recommendedNews = [],
        public array $multimediaPromises = [],
        public array $multimediaOpening = [],
    ) {
    }

    /**
     * Merge with another EmbeddedContentDTO.
     */
    public function merge(self $other): self
    {
        return new self(
            insertedNews: array_merge($this->insertedNews, $other->insertedNews),
            recommendedEditorials: array_merge($this->recommendedEditorials, $other->recommendedEditorials),
            recommendedNews: array_merge($this->recommendedNews, $other->recommendedNews),
            multimediaPromises: array_merge($this->multimediaPromises, $other->multimediaPromises),
            multimediaOpening: array_merge($this->multimediaOpening, $other->multimediaOpening),
        );
    }

    /**
     * Convert to legacy array format for backwards compatibility.
     *
     * @return array<string, mixed>
     */
    public function toResolveDataArray(): array
    {
        $insertedNews = [];
        foreach ($this->insertedNews as $id => $dto) {
            $insertedNews[$id] = $dto->toArray();
        }

        $recommendedEditorials = [];
        foreach ($this->recommendedEditorials as $id => $dto) {
            $recommendedEditorials[$id] = $dto->toArray();
        }

        return [
            'insertedNews' => $insertedNews,
            'recommendedEditorials' => $recommendedEditorials,
            'multimedia' => $this->multimediaPromises,
            'multimediaOpening' => $this->multimediaOpening,
        ];
    }
}
