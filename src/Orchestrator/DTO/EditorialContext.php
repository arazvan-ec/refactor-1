<?php

declare(strict_types=1);

namespace App\Orchestrator\DTO;

use App\Application\DTO\EmbeddedContentDTO;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Multimedia\Domain\Model\Photo;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\Tag;

/**
 * Context object that holds editorial data during enrichment.
 *
 * Input data (editorial, section, embeddedContent) is readonly.
 * Enriched data is mutable and filled by ContentEnrichers.
 *
 * This object is passed through the enricher chain and collects
 * all additional data needed for the response aggregation.
 */
final class EditorialContext
{
    /** @var array<int, Tag> */
    private array $tags = [];

    /** @var array<string, string> */
    private array $membershipLinks = [];

    /** @var array<string, Photo> */
    private array $photoBodyTags = [];

    /** @var array<string, mixed> */
    private array $customData = [];

    public function __construct(
        public readonly Editorial $editorial,
        public readonly Section $section,
        public readonly EmbeddedContentDTO $embeddedContent,
    ) {
    }

    /**
     * Set the fetched tags.
     *
     * @param array<int, Tag> $tags
     */
    public function withTags(array $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * Get the fetched tags.
     *
     * @return array<int, Tag>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Set the resolved membership links.
     *
     * @param array<string, string> $links URL => resolved URL mapping
     */
    public function withMembershipLinks(array $links): void
    {
        $this->membershipLinks = $links;
    }

    /**
     * Get the resolved membership links.
     *
     * @return array<string, string>
     */
    public function getMembershipLinks(): array
    {
        return $this->membershipLinks;
    }

    /**
     * Set the photos fetched from body tags.
     *
     * @param array<string, Photo> $photos ID => Photo mapping
     */
    public function withPhotoBodyTags(array $photos): void
    {
        $this->photoBodyTags = $photos;
    }

    /**
     * Get the photos from body tags.
     *
     * @return array<string, Photo>
     */
    public function getPhotoBodyTags(): array
    {
        return $this->photoBodyTags;
    }

    /**
     * Add custom data from an enricher.
     *
     * Use this for data that doesn't fit the standard fields.
     */
    public function addCustomData(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;
    }

    /**
     * Get custom data by key.
     */
    public function getCustomData(string $key): mixed
    {
        return $this->customData[$key] ?? null;
    }

    /**
     * Get all custom data.
     *
     * @return array<string, mixed>
     */
    public function getAllCustomData(): array
    {
        return $this->customData;
    }

    /**
     * Check if the context has any enriched data.
     */
    public function hasEnrichedData(): bool
    {
        return !empty($this->tags)
            || !empty($this->membershipLinks)
            || !empty($this->photoBodyTags)
            || !empty($this->customData);
    }
}
