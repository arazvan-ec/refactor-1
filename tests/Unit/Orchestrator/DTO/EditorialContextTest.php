<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\DTO;

use App\Application\DTO\EmbeddedContentDTO;
use App\Orchestrator\DTO\EditorialContext;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\Tags;
use Ec\Multimedia\Domain\Model\Photo;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditorialContext::class)]
class EditorialContextTest extends TestCase
{
    #[Test]
    public function it_stores_readonly_input_data(): void
    {
        $editorial = $this->createEditorialMock();
        $section = $this->createMock(Section::class);
        $embeddedContent = new EmbeddedContentDTO();

        $context = new EditorialContext($editorial, $section, $embeddedContent);

        self::assertSame($editorial, $context->editorial);
        self::assertSame($section, $context->section);
        self::assertSame($embeddedContent, $context->embeddedContent);
    }

    #[Test]
    public function it_starts_with_empty_enriched_data(): void
    {
        $context = $this->createContext();

        self::assertSame([], $context->getTags());
        self::assertSame([], $context->getMembershipLinks());
        self::assertSame([], $context->getPhotoBodyTags());
        self::assertSame([], $context->getAllCustomData());
        self::assertFalse($context->hasEnrichedData());
    }

    #[Test]
    public function it_can_set_and_get_tags(): void
    {
        $context = $this->createContext();
        $tag1 = $this->createMock(Tag::class);
        $tag2 = $this->createMock(Tag::class);

        $context->withTags([$tag1, $tag2]);

        self::assertSame([$tag1, $tag2], $context->getTags());
        self::assertTrue($context->hasEnrichedData());
    }

    #[Test]
    public function it_can_set_and_get_membership_links(): void
    {
        $context = $this->createContext();
        $links = ['https://example.com' => 'https://resolved.com'];

        $context->withMembershipLinks($links);

        self::assertSame($links, $context->getMembershipLinks());
        self::assertTrue($context->hasEnrichedData());
    }

    #[Test]
    public function it_can_set_and_get_photo_body_tags(): void
    {
        $context = $this->createContext();
        $photo = $this->createMock(Photo::class);
        $photos = ['photo-1' => $photo];

        $context->withPhotoBodyTags($photos);

        self::assertSame($photos, $context->getPhotoBodyTags());
        self::assertTrue($context->hasEnrichedData());
    }

    #[Test]
    public function it_can_add_and_get_custom_data(): void
    {
        $context = $this->createContext();

        $context->addCustomData('relatedArticles', ['article1', 'article2']);
        $context->addCustomData('ratings', 4.5);

        self::assertSame(['article1', 'article2'], $context->getCustomData('relatedArticles'));
        self::assertSame(4.5, $context->getCustomData('ratings'));
        self::assertNull($context->getCustomData('nonexistent'));
        self::assertTrue($context->hasEnrichedData());
    }

    #[Test]
    public function it_returns_all_custom_data(): void
    {
        $context = $this->createContext();

        $context->addCustomData('key1', 'value1');
        $context->addCustomData('key2', 'value2');

        self::assertSame(
            ['key1' => 'value1', 'key2' => 'value2'],
            $context->getAllCustomData()
        );
    }

    #[Test]
    public function has_enriched_data_returns_true_when_any_data_is_set(): void
    {
        $context = $this->createContext();
        self::assertFalse($context->hasEnrichedData());

        // Only tags
        $context->withTags([$this->createMock(Tag::class)]);
        self::assertTrue($context->hasEnrichedData());

        // Reset and test membership links
        $context2 = $this->createContext();
        $context2->withMembershipLinks(['link' => 'resolved']);
        self::assertTrue($context2->hasEnrichedData());

        // Reset and test photos
        $context3 = $this->createContext();
        $context3->withPhotoBodyTags(['id' => $this->createMock(Photo::class)]);
        self::assertTrue($context3->hasEnrichedData());

        // Reset and test custom data
        $context4 = $this->createContext();
        $context4->addCustomData('key', 'value');
        self::assertTrue($context4->hasEnrichedData());
    }

    private function createContext(): EditorialContext
    {
        return new EditorialContext(
            $this->createEditorialMock(),
            $this->createMock(Section::class),
            new EmbeddedContentDTO()
        );
    }

    private function createEditorialMock(): Editorial
    {
        $body = $this->createMock(Body::class);
        $tags = $this->createMock(Tags::class);
        $editorialId = $this->createMock(EditorialId::class);
        $editorialId->method('id')->willReturn('test-id');

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);
        $editorial->method('tags')->willReturn($tags);
        $editorial->method('id')->willReturn($editorialId);

        return $editorial;
    }
}
