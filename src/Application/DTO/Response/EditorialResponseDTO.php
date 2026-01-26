<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * DTO for the complete editorial API response.
 *
 * Provides type safety for the editorial response structure.
 * Replaces array<string, mixed> with typed properties.
 */
final readonly class EditorialResponseDTO
{
    /**
     * @param array<int, TagResponseDTO> $tags
     * @param array<int, array<string, mixed>> $signatures
     * @param array<string, mixed> $body
     * @param array<string, mixed>|null $multimedia
     * @param array<string, mixed> $standfirst
     * @param array<int, array<string, mixed>> $recommendedEditorials
     * @param array<int, string> $adsOptions
     * @param array<int, string> $analiticsOptions
     */
    public function __construct(
        public string $id,
        public string $url,
        public TitlesDTO $titles,
        public string $lead,
        public string $publicationDate,
        public string $updatedOn,
        public string $endOn,
        public EditorialTypeDTO $type,
        public bool $indexable,
        public bool $deleted,
        public bool $published,
        public string $closingModeId,
        public bool $commentable,
        public bool $isBrand,
        public bool $isAmazonOnsite,
        public string $contentType,
        public string $canonicalEditorialId,
        public string $urlDate,
        public int $countWords,
        public int $countComments,
        public SectionResponseDTO $section,
        public array $tags,
        public array $signatures,
        public array $body,
        public ?array $multimedia,
        public array $standfirst,
        public array $recommendedEditorials,
        public array $adsOptions,
        public array $analiticsOptions,
    ) {
    }

    /**
     * Create from legacy array response.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $titles = new TitlesDTO(
            title: $data['titles']['title'] ?? '',
            preTitle: $data['titles']['preTitle'] ?? '',
            urlTitle: $data['titles']['urlTitle'] ?? '',
            mobileTitle: $data['titles']['mobileTitle'] ?? '',
        );

        $type = new EditorialTypeDTO(
            id: $data['type']['id'] ?? '',
            name: $data['type']['name'] ?? '',
        );

        $section = new SectionResponseDTO(
            id: $data['section']['id'] ?? '',
            name: $data['section']['name'] ?? '',
            url: $data['section']['url'] ?? '',
            encodeName: $data['section']['encodeName'] ?? '',
        );

        $tags = array_map(
            static fn (array $tag) => new TagResponseDTO(
                id: $tag['id'] ?? '',
                name: $tag['name'] ?? '',
                url: $tag['url'] ?? '',
            ),
            $data['tags'] ?? []
        );

        return new self(
            id: $data['id'] ?? '',
            url: $data['url'] ?? '',
            titles: $titles,
            lead: $data['lead'] ?? '',
            publicationDate: $data['publicationDate'] ?? '',
            updatedOn: $data['updatedOn'] ?? '',
            endOn: $data['endOn'] ?? '',
            type: $type,
            indexable: $data['indexable'] ?? false,
            deleted: $data['deleted'] ?? false,
            published: $data['published'] ?? false,
            closingModeId: $data['closingModeId'] ?? '',
            commentable: $data['commentable'] ?? false,
            isBrand: $data['isBrand'] ?? false,
            isAmazonOnsite: $data['isAmazonOnsite'] ?? false,
            contentType: $data['contentType'] ?? '',
            canonicalEditorialId: $data['canonicalEditorialId'] ?? '',
            urlDate: $data['urlDate'] ?? '',
            countWords: $data['countWords'] ?? 0,
            countComments: $data['countComments'] ?? 0,
            section: $section,
            tags: $tags,
            signatures: $data['signatures'] ?? [],
            body: $data['body'] ?? [],
            multimedia: $data['multimedia'] ?? null,
            standfirst: $data['standfirst'] ?? [],
            recommendedEditorials: $data['recommendedEditorials'] ?? [],
            adsOptions: $data['adsOptions'] ?? [],
            analiticsOptions: $data['analiticsOptions'] ?? [],
        );
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'titles' => $this->titles->toArray(),
            'lead' => $this->lead,
            'publicationDate' => $this->publicationDate,
            'updatedOn' => $this->updatedOn,
            'endOn' => $this->endOn,
            'type' => $this->type->toArray(),
            'indexable' => $this->indexable,
            'deleted' => $this->deleted,
            'published' => $this->published,
            'closingModeId' => $this->closingModeId,
            'commentable' => $this->commentable,
            'isBrand' => $this->isBrand,
            'isAmazonOnsite' => $this->isAmazonOnsite,
            'contentType' => $this->contentType,
            'canonicalEditorialId' => $this->canonicalEditorialId,
            'urlDate' => $this->urlDate,
            'countWords' => $this->countWords,
            'countComments' => $this->countComments,
            'section' => $this->section->toArray(),
            'tags' => array_map(
                static fn (TagResponseDTO $tag) => $tag->toArray(),
                $this->tags
            ),
            'signatures' => $this->signatures,
            'body' => $this->body,
            'multimedia' => $this->multimedia,
            'standfirst' => $this->standfirst,
            'recommendedEditorials' => $this->recommendedEditorials,
            'adsOptions' => $this->adsOptions,
            'analiticsOptions' => $this->analiticsOptions,
        ];
    }
}
