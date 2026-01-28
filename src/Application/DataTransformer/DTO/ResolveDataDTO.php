<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\DTO;

use Ec\Multimedia\Domain\Model\Multimedia as MultimediaModel;
use Ec\Multimedia\Domain\Model\Photo\Photo;

/**
 * Data required for body element transformation.
 *
 * Replaces array<string, mixed> $resolveData parameter with typed structure.
 * Provides type-safe access to pre-fetched data needed by transformers.
 */
final readonly class ResolveDataDTO
{
    /**
     * @param array<string, InsertedEditorialDTO> $insertedNews Pre-fetched inserted news editorials
     * @param array<string, MultimediaModel> $multimedia Resolved multimedia by ID
     * @param array<string, MultimediaOpeningDTO> $multimediaOpening Opening multimedia data
     * @param array<string, Photo> $photoBodyTags Photos for body tag pictures
     * @param array<string, string> $membershipLinks URL mappings for membership
     * @param list<mixed> $recommendedNews Recommended news editorials
     */
    public function __construct(
        public array $insertedNews = [],
        public array $multimedia = [],
        public array $multimediaOpening = [],
        public array $photoBodyTags = [],
        public array $membershipLinks = [],
        public array $recommendedNews = [],
    ) {}

    public function hasInsertedNews(string $editorialId): bool
    {
        return isset($this->insertedNews[$editorialId]);
    }

    public function getInsertedNews(string $editorialId): ?InsertedEditorialDTO
    {
        return $this->insertedNews[$editorialId] ?? null;
    }

    public function hasMultimedia(string $multimediaId): bool
    {
        return isset($this->multimedia[$multimediaId]);
    }

    public function getMultimedia(string $multimediaId): ?MultimediaModel
    {
        return $this->multimedia[$multimediaId] ?? null;
    }

    public function hasMultimediaOpening(string $multimediaId): bool
    {
        return isset($this->multimediaOpening[$multimediaId]);
    }

    public function getMultimediaOpening(string $multimediaId): ?MultimediaOpeningDTO
    {
        return $this->multimediaOpening[$multimediaId] ?? null;
    }

    public function hasPhotoForBodyTag(string $photoId): bool
    {
        return isset($this->photoBodyTags[$photoId]);
    }

    public function getPhotoForBodyTag(string $photoId): ?Photo
    {
        return $this->photoBodyTags[$photoId] ?? null;
    }

    public function hasMembershipLink(string $url): bool
    {
        return isset($this->membershipLinks[$url]);
    }

    public function getMembershipLink(string $url): ?string
    {
        return $this->membershipLinks[$url] ?? null;
    }

    /**
     * Create from legacy array format.
     *
     * @param array<string, mixed> $legacyData
     *
     * @deprecated Use constructor directly. Will be removed once all callers migrate.
     */
    public static function fromLegacyArray(array $legacyData): self
    {
        $insertedNews = [];
        foreach ($legacyData['insertedNews'] ?? [] as $id => $data) {
            $insertedNews[$id] = InsertedEditorialDTO::fromArray($data);
        }

        $multimediaOpening = [];
        foreach ($legacyData['multimediaOpening'] ?? [] as $id => $data) {
            $multimediaOpening[$id] = MultimediaOpeningDTO::fromArray($data);
        }

        return new self(
            insertedNews: $insertedNews,
            multimedia: $legacyData['multimedia'] ?? [],
            multimediaOpening: $multimediaOpening,
            photoBodyTags: $legacyData['photoFromBodyTags'] ?? [],
            membershipLinks: $legacyData['membershipLinkCombine'] ?? [],
            recommendedNews: $legacyData['recommendedNews'] ?? [],
        );
    }

    /**
     * Convert back to legacy array format for backward compatibility.
     *
     * @return array<string, mixed>
     *
     * @deprecated Use DTO properties directly. Will be removed once all consumers migrate.
     */
    public function toLegacyArray(): array
    {
        $insertedNews = [];
        foreach ($this->insertedNews as $id => $dto) {
            $insertedNews[$id] = $dto->toArray();
        }

        $multimediaOpening = [];
        foreach ($this->multimediaOpening as $id => $dto) {
            $multimediaOpening[$id] = $dto->toArray();
        }

        return [
            'insertedNews' => $insertedNews,
            'multimedia' => $this->multimedia,
            'multimediaOpening' => $multimediaOpening,
            'photoFromBodyTags' => $this->photoBodyTags,
            'membershipLinkCombine' => $this->membershipLinks,
            'recommendedNews' => $this->recommendedNews,
        ];
    }
}
