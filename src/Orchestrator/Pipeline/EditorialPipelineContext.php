<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline;

use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use App\Application\DTO\PreFetchedDataDTO;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\Tag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mutable context passed through the editorial pipeline.
 *
 * Each step can read from and write to this context.
 * The context accumulates data as it passes through steps.
 */
final class EditorialPipelineContext
{
    // Input (set at creation)
    public readonly Request $request;
    public readonly string $editorialId;

    // Populated by steps
    private ?FetchedEditorialDTO $fetchedEditorial = null;
    private ?Editorial $editorial = null;
    private ?Section $section = null;
    private ?EmbeddedContentDTO $embeddedContent = null;

    // Enriched data
    /** @var array<int, Tag> */
    private array $tags = [];

    /** @var array<string, string> */
    private array $membershipLinks = [];

    /** @var array<string, mixed> */
    private array $photoBodyTags = [];

    /** @var array<string, Multimedia> */
    private array $resolvedMultimedia = [];

    private ?PreFetchedDataDTO $preFetchedData = null;

    // Extensible custom data
    /** @var array<string, mixed> */
    private array $customData = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->editorialId = (string) $request->get('id');
    }

    // === Setters (called by steps) ===

    public function setFetchedEditorial(FetchedEditorialDTO $dto): void
    {
        $this->fetchedEditorial = $dto;
        $this->editorial = $dto->editorial;
        $this->section = $dto->section;
    }

    public function setEmbeddedContent(EmbeddedContentDTO $content): void
    {
        $this->embeddedContent = $content;
    }

    /**
     * @param array<int, Tag> $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @param array<string, string> $links
     */
    public function setMembershipLinks(array $links): void
    {
        $this->membershipLinks = $links;
    }

    /**
     * @param array<string, mixed> $photos
     */
    public function setPhotoBodyTags(array $photos): void
    {
        $this->photoBodyTags = $photos;
    }

    /**
     * @param array<string, Multimedia> $multimedia
     */
    public function setResolvedMultimedia(array $multimedia): void
    {
        $this->resolvedMultimedia = $multimedia;
    }

    public function setPreFetchedData(PreFetchedDataDTO $data): void
    {
        $this->preFetchedData = $data;
    }

    public function setCustomData(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;
    }

    // === Getters ===

    public function getFetchedEditorial(): ?FetchedEditorialDTO
    {
        return $this->fetchedEditorial;
    }

    public function getEditorial(): ?Editorial
    {
        return $this->editorial;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function getEmbeddedContent(): ?EmbeddedContentDTO
    {
        return $this->embeddedContent;
    }

    /**
     * @return array<int, Tag>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return array<string, string>
     */
    public function getMembershipLinks(): array
    {
        return $this->membershipLinks;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPhotoBodyTags(): array
    {
        return $this->photoBodyTags;
    }

    /**
     * @return array<string, Multimedia>
     */
    public function getResolvedMultimedia(): array
    {
        return $this->resolvedMultimedia;
    }

    public function getPreFetchedData(): ?PreFetchedDataDTO
    {
        return $this->preFetchedData;
    }

    public function getCustomData(string $key, mixed $default = null): mixed
    {
        return $this->customData[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllCustomData(): array
    {
        return $this->customData;
    }

    // === Convenience checks ===

    public function hasEditorial(): bool
    {
        return $this->editorial !== null;
    }

    public function hasSection(): bool
    {
        return $this->section !== null;
    }

    public function hasEmbeddedContent(): bool
    {
        return $this->embeddedContent !== null;
    }

    public function hasFetchedEditorial(): bool
    {
        return $this->fetchedEditorial !== null;
    }
}
