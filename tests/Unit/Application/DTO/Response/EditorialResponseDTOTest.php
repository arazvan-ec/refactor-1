<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DTO\Response;

use App\Application\DTO\Response\EditorialResponseDTO;
use App\Application\DTO\Response\EditorialTypeDTO;
use App\Application\DTO\Response\SectionResponseDTO;
use App\Application\DTO\Response\TagResponseDTO;
use App\Application\DTO\Response\TitlesDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditorialResponseDTO::class)]
#[CoversClass(TitlesDTO::class)]
#[CoversClass(EditorialTypeDTO::class)]
#[CoversClass(SectionResponseDTO::class)]
#[CoversClass(TagResponseDTO::class)]
class EditorialResponseDTOTest extends TestCase
{
    #[Test]
    public function it_creates_dto_with_all_properties(): void
    {
        $titles = new TitlesDTO('Title', 'Pre', 'Url', 'Mobile');
        $type = new EditorialTypeDTO('type-1', 'Type Name');
        $section = new SectionResponseDTO('sec-1', 'Section', 'http://example.com', 'section');
        $tags = [new TagResponseDTO('tag-1', 'Tag', 'http://example.com/tag')];

        $dto = new EditorialResponseDTO(
            id: 'edit-1',
            url: 'http://example.com/article',
            titles: $titles,
            lead: 'Lead text',
            publicationDate: '2026-01-26 10:00:00',
            updatedOn: '2026-01-26 10:00:00',
            endOn: '2026-12-31 23:59:59',
            type: $type,
            indexable: true,
            deleted: false,
            published: true,
            closingModeId: 'open',
            commentable: true,
            isBrand: false,
            isAmazonOnsite: false,
            contentType: 'article',
            canonicalEditorialId: 'edit-1',
            urlDate: '2026-01-26 10:00:00',
            countWords: 500,
            countComments: 10,
            section: $section,
            tags: $tags,
            signatures: [],
            body: [],
            multimedia: null,
            standfirst: [],
            recommendedEditorials: [],
            adsOptions: [],
            analiticsOptions: [],
        );

        self::assertSame('edit-1', $dto->id);
        self::assertSame('Title', $dto->titles->title);
        self::assertTrue($dto->published);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'id' => 'edit-1',
            'url' => 'http://example.com/article',
            'titles' => [
                'title' => 'Test Title',
                'preTitle' => 'Pre Title',
                'urlTitle' => 'test-title',
                'mobileTitle' => 'Mobile Title',
            ],
            'lead' => 'Lead text',
            'publicationDate' => '2026-01-26 10:00:00',
            'updatedOn' => '2026-01-26 10:00:00',
            'endOn' => '2026-12-31 23:59:59',
            'type' => [
                'id' => 'type-1',
                'name' => 'Article',
            ],
            'indexable' => true,
            'deleted' => false,
            'published' => true,
            'closingModeId' => 'open',
            'commentable' => true,
            'isBrand' => false,
            'isAmazonOnsite' => false,
            'contentType' => 'article',
            'canonicalEditorialId' => 'edit-1',
            'urlDate' => '2026-01-26 10:00:00',
            'countWords' => 500,
            'countComments' => 10,
            'section' => [
                'id' => 'sec-1',
                'name' => 'Section',
                'url' => 'http://example.com/section',
                'encodeName' => 'section',
            ],
            'tags' => [
                ['id' => 'tag-1', 'name' => 'Tag 1', 'url' => 'http://example.com/tag1'],
                ['id' => 'tag-2', 'name' => 'Tag 2', 'url' => 'http://example.com/tag2'],
            ],
            'signatures' => [],
            'body' => [],
            'multimedia' => null,
            'standfirst' => [],
            'recommendedEditorials' => [],
            'adsOptions' => [],
            'analiticsOptions' => [],
        ];

        $dto = EditorialResponseDTO::fromArray($data);

        self::assertSame('edit-1', $dto->id);
        self::assertSame('Test Title', $dto->titles->title);
        self::assertSame('Article', $dto->type->name);
        self::assertSame('Section', $dto->section->name);
        self::assertCount(2, $dto->tags);
        self::assertSame('Tag 1', $dto->tags[0]->name);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $titles = new TitlesDTO('Title', 'Pre', 'url-title', 'Mobile');
        $type = new EditorialTypeDTO('type-1', 'Article');
        $section = new SectionResponseDTO('sec-1', 'Section', 'http://example.com', 'section');
        $tags = [new TagResponseDTO('tag-1', 'Tag', 'http://example.com/tag')];

        $dto = new EditorialResponseDTO(
            id: 'edit-1',
            url: 'http://example.com/article',
            titles: $titles,
            lead: 'Lead',
            publicationDate: '2026-01-26 10:00:00',
            updatedOn: '2026-01-26 10:00:00',
            endOn: '2026-12-31 23:59:59',
            type: $type,
            indexable: true,
            deleted: false,
            published: true,
            closingModeId: 'open',
            commentable: true,
            isBrand: false,
            isAmazonOnsite: false,
            contentType: 'article',
            canonicalEditorialId: 'edit-1',
            urlDate: '2026-01-26 10:00:00',
            countWords: 500,
            countComments: 10,
            section: $section,
            tags: $tags,
            signatures: [],
            body: [],
            multimedia: null,
            standfirst: [],
            recommendedEditorials: [],
            adsOptions: [],
            analiticsOptions: [],
        );

        $array = $dto->toArray();

        self::assertSame('edit-1', $array['id']);
        self::assertSame('Title', $array['titles']['title']);
        self::assertSame('Article', $array['type']['name']);
        self::assertIsArray($array['tags']);
        self::assertSame('Tag', $array['tags'][0]['name']);
    }

    #[Test]
    public function titles_dto_converts_to_array(): void
    {
        $titles = new TitlesDTO('Main', 'Pre', 'url', 'Mobile');

        $array = $titles->toArray();

        self::assertSame('Main', $array['title']);
        self::assertSame('Pre', $array['preTitle']);
        self::assertSame('url', $array['urlTitle']);
        self::assertSame('Mobile', $array['mobileTitle']);
    }

    #[Test]
    public function type_dto_converts_to_array(): void
    {
        $type = new EditorialTypeDTO('id-1', 'Name');

        $array = $type->toArray();

        self::assertSame('id-1', $array['id']);
        self::assertSame('Name', $array['name']);
    }

    #[Test]
    public function section_dto_converts_to_array(): void
    {
        $section = new SectionResponseDTO('id-1', 'Name', 'http://url', 'encode');

        $array = $section->toArray();

        self::assertSame('id-1', $array['id']);
        self::assertSame('Name', $array['name']);
        self::assertSame('http://url', $array['url']);
        self::assertSame('encode', $array['encodeName']);
    }

    #[Test]
    public function tag_dto_converts_to_array(): void
    {
        $tag = new TagResponseDTO('id-1', 'Name', 'http://url');

        $array = $tag->toArray();

        self::assertSame('id-1', $array['id']);
        self::assertSame('Name', $array['name']);
        self::assertSame('http://url', $array['url']);
    }
}
