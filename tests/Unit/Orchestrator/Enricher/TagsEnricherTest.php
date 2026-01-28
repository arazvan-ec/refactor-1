<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Enricher;

use App\Application\DTO\BatchResult;
use App\Application\DTO\EmbeddedContentDTO;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\DTO\EditorialContext;
use App\Orchestrator\Enricher\ContentEnricherInterface;
use App\Orchestrator\Enricher\TagsEnricher;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\Tag as EditorialTag;
use Ec\Editorial\Domain\Model\Tags;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use Ec\Tag\Domain\Model\Tag;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(TagsEnricher::class)]
class TagsEnricherTest extends TestCase
{
    private TagsEnricher $enricher;
    private MockObject&QueryTagClient $queryTagClient;
    private MockObject&PromiseResolverInterface $promiseResolver;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->queryTagClient = $this->createMock(QueryTagClient::class);
        $this->promiseResolver = $this->createMock(PromiseResolverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->enricher = new TagsEnricher(
            $this->queryTagClient,
            $this->promiseResolver,
            $this->logger
        );
    }

    #[Test]
    public function it_implements_content_enricher_interface(): void
    {
        self::assertInstanceOf(ContentEnricherInterface::class, $this->enricher);
    }

    #[Test]
    public function it_has_priority_100(): void
    {
        self::assertSame(100, $this->enricher->getPriority());
    }

    #[Test]
    public function it_supports_editorials_with_tags(): void
    {
        $editorial = $this->createEditorialWithTags(['tag-1']);

        self::assertTrue($this->enricher->supports($editorial));
    }

    #[Test]
    public function it_does_not_support_editorials_without_tags(): void
    {
        $editorial = $this->createEditorialWithTags([]);

        self::assertFalse($this->enricher->supports($editorial));
    }

    #[Test]
    public function it_fetches_tags_in_parallel_and_sets_them_on_context(): void
    {
        $tag1 = $this->createMock(Tag::class);
        $tag2 = $this->createMock(Tag::class);

        $promise1 = new FulfilledPromise($tag1);
        $promise2 = new FulfilledPromise($tag2);

        $this->queryTagClient
            ->expects(self::exactly(2))
            ->method('findTagById')
            ->willReturnCallback(function (string $id, bool $async) use ($promise1, $promise2): PromiseInterface {
                self::assertTrue($async, 'Expected async: true parameter');

                return $id === 'tag-1' ? $promise1 : $promise2;
            });

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->willReturn(new BatchResult([
                'tag-1' => $tag1,
                'tag-2' => $tag2,
            ], []));

        $context = $this->createContext(['tag-1', 'tag-2']);

        $this->enricher->enrich($context);

        self::assertSame([$tag1, $tag2], $context->getTags());
    }

    #[Test]
    public function it_handles_tag_fetch_failure_gracefully(): void
    {
        $tag1 = $this->createMock(Tag::class);
        $promise1 = new FulfilledPromise($tag1);
        $promise2 = new FulfilledPromise(null);

        $this->queryTagClient
            ->expects(self::exactly(2))
            ->method('findTagById')
            ->willReturnCallback(fn(string $id): PromiseInterface => $id === 'tag-1' ? $promise1 : $promise2);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->willReturn(new BatchResult(
                ['tag-1' => $tag1],
                ['tag-2' => new \RuntimeException('Tag not found')]
            ));

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to fetch tag',
                self::callback(fn(array $ctx): bool => $ctx['tag_id'] === 'tag-2')
            );

        $context = $this->createContext(['tag-1', 'tag-2']);

        $this->enricher->enrich($context);

        // Only the successful tag should be in context
        self::assertSame([$tag1], $context->getTags());
    }

    #[Test]
    public function it_sets_empty_array_when_no_tags_are_fetched(): void
    {
        $this->queryTagClient
            ->expects(self::never())
            ->method('findTagById');

        $this->promiseResolver
            ->expects(self::never())
            ->method('resolveAll');

        $context = $this->createContext([]);

        $this->enricher->enrich($context);

        self::assertSame([], $context->getTags());
    }

    #[Test]
    public function it_creates_promises_with_async_parameter(): void
    {
        $tag = $this->createMock(Tag::class);
        $promise = new FulfilledPromise($tag);

        $this->queryTagClient
            ->expects(self::once())
            ->method('findTagById')
            ->with('tag-1', true)
            ->willReturn($promise);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->with(self::callback(fn(array $promises): bool => isset($promises['tag-1'])))
            ->willReturn(new BatchResult(['tag-1' => $tag], []));

        $context = $this->createContext(['tag-1']);

        $this->enricher->enrich($context);

        self::assertSame([$tag], $context->getTags());
    }

    /**
     * @param array<int, string> $tagIds
     */
    private function createContext(array $tagIds): EditorialContext
    {
        return new EditorialContext(
            $this->createEditorialWithTags($tagIds),
            $this->createMock(Section::class),
            new EmbeddedContentDTO()
        );
    }

    /**
     * @param array<int, string> $tagIds
     */
    private function createEditorialWithTags(array $tagIds): Editorial
    {
        $body = $this->createMock(Body::class);

        $tagMocks = [];
        foreach ($tagIds as $tagId) {
            $tagMock = $this->createMock(EditorialTag::class);
            $tagMock->method('id')->willReturn($tagId);
            $tagMocks[] = $tagMock;
        }

        $tags = $this->createMock(Tags::class);
        $tags->method('getArrayCopy')->willReturn($tagMocks);
        $tags->method('isEmpty')->willReturn(empty($tagIds));

        $editorialId = $this->createMock(EditorialId::class);
        $editorialId->method('id')->willReturn('test-editorial-id');

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);
        $editorial->method('tags')->willReturn($tags);
        $editorial->method('id')->willReturn($editorialId);

        return $editorial;
    }
}
