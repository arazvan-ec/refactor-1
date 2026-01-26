<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Chain;

use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use App\Application\Service\Editorial\EditorialFetcherInterface;
use App\Application\Service\Editorial\EmbeddedContentFetcherInterface;
use App\Application\Service\Editorial\ResponseAggregatorInterface;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\Chain\EditorialOrchestrator;
use App\Orchestrator\Chain\EditorialOrchestratorInterface;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\RecommendedEditorials;
use Ec\Editorial\Domain\Model\Tags;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(EditorialOrchestrator::class)]
class EditorialOrchestratorTest extends TestCase
{
    private EditorialOrchestratorInterface $orchestrator;
    private MockObject&EditorialFetcherInterface $editorialFetcher;
    private MockObject&EmbeddedContentFetcherInterface $embeddedContentFetcher;
    private MockObject&PromiseResolverInterface $promiseResolver;
    private MockObject&ResponseAggregatorInterface $responseAggregator;
    private MockObject&QueryTagClient $queryTagClient;
    private MockObject&QueryMembershipClient $queryMembershipClient;
    private MockObject&QueryMultimediaClient $queryMultimediaClient;
    private MockObject&UriFactoryInterface $uriFactory;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->editorialFetcher = $this->createMock(EditorialFetcherInterface::class);
        $this->embeddedContentFetcher = $this->createMock(EmbeddedContentFetcherInterface::class);
        $this->promiseResolver = $this->createMock(PromiseResolverInterface::class);
        $this->responseAggregator = $this->createMock(ResponseAggregatorInterface::class);
        $this->queryTagClient = $this->createMock(QueryTagClient::class);
        $this->queryMembershipClient = $this->createMock(QueryMembershipClient::class);
        $this->queryMultimediaClient = $this->createMock(QueryMultimediaClient::class);
        $this->uriFactory = $this->createMock(UriFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->orchestrator = new EditorialOrchestrator(
            $this->editorialFetcher,
            $this->embeddedContentFetcher,
            $this->promiseResolver,
            $this->responseAggregator,
            $this->queryTagClient,
            $this->queryMembershipClient,
            $this->queryMultimediaClient,
            $this->uriFactory,
            $this->logger,
        );
    }

    #[Test]
    public function it_implements_interface(): void
    {
        self::assertInstanceOf(EditorialOrchestratorInterface::class, $this->orchestrator);
    }

    #[Test]
    public function can_orchestrate_returns_editorial(): void
    {
        self::assertSame('editorial', $this->orchestrator->canOrchestrate());
    }

    #[Test]
    public function execute_returns_legacy_response_when_should_use_legacy(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $editorial = $this->createEditorialMock();
        $section = $this->createMock(Section::class);

        $fetchedEditorial = new FetchedEditorialDTO($editorial, $section);
        $legacyResponse = ['legacy' => 'data'];

        $this->editorialFetcher
            ->expects(self::once())
            ->method('fetch')
            ->with('test-id')
            ->willReturn($fetchedEditorial);

        $this->editorialFetcher
            ->expects(self::once())
            ->method('shouldUseLegacy')
            ->with($editorial)
            ->willReturn(true);

        $this->editorialFetcher
            ->expects(self::once())
            ->method('fetchLegacy')
            ->with('test-id')
            ->willReturn($legacyResponse);

        $result = $this->orchestrator->execute($request);

        self::assertSame($legacyResponse, $result);
    }

    #[Test]
    public function execute_delegates_to_services_and_returns_aggregated_response(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $editorial = $this->createEditorialMock();
        $section = $this->createMock(Section::class);
        $section->method('siteId')->willReturn('site-1');

        $fetchedEditorial = new FetchedEditorialDTO($editorial, $section);
        $embeddedContent = new EmbeddedContentDTO();
        $expectedResponse = ['id' => 'test-id', 'title' => 'Test'];

        $this->editorialFetcher
            ->expects(self::once())
            ->method('fetch')
            ->with('test-id')
            ->willReturn($fetchedEditorial);

        $this->editorialFetcher
            ->expects(self::once())
            ->method('shouldUseLegacy')
            ->with($editorial)
            ->willReturn(false);

        $this->embeddedContentFetcher
            ->expects(self::once())
            ->method('fetch')
            ->with($editorial, $section)
            ->willReturn($embeddedContent);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveMultimedia')
            ->willReturn([]);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveMembershipLinks')
            ->willReturn([]);

        $this->responseAggregator
            ->expects(self::once())
            ->method('aggregate')
            ->willReturn($expectedResponse);

        $result = $this->orchestrator->execute($request);

        self::assertSame($expectedResponse, $result);
    }

    #[Test]
    public function execute_fetches_tags_for_editorial(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $editorial = $this->createEditorialMockWithTags(['tag-1', 'tag-2']);
        $section = $this->createMock(Section::class);
        $section->method('siteId')->willReturn('site-1');

        $fetchedEditorial = new FetchedEditorialDTO($editorial, $section);
        $embeddedContent = new EmbeddedContentDTO();

        $this->editorialFetcher->method('fetch')->willReturn($fetchedEditorial);
        $this->editorialFetcher->method('shouldUseLegacy')->willReturn(false);
        $this->embeddedContentFetcher->method('fetch')->willReturn($embeddedContent);
        $this->promiseResolver->method('resolveMultimedia')->willReturn([]);
        $this->promiseResolver->method('resolveMembershipLinks')->willReturn([]);
        $this->responseAggregator->method('aggregate')->willReturn([]);

        // Expect tag client to be called for each tag
        $this->queryTagClient
            ->expects(self::exactly(2))
            ->method('findTagById');

        $this->orchestrator->execute($request);
    }

    #[Test]
    public function execute_handles_tag_fetch_failure_gracefully(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $editorial = $this->createEditorialMockWithTags(['tag-1']);
        $section = $this->createMock(Section::class);
        $section->method('siteId')->willReturn('site-1');

        $fetchedEditorial = new FetchedEditorialDTO($editorial, $section);
        $embeddedContent = new EmbeddedContentDTO();

        $this->editorialFetcher->method('fetch')->willReturn($fetchedEditorial);
        $this->editorialFetcher->method('shouldUseLegacy')->willReturn(false);
        $this->embeddedContentFetcher->method('fetch')->willReturn($embeddedContent);
        $this->promiseResolver->method('resolveMultimedia')->willReturn([]);
        $this->promiseResolver->method('resolveMembershipLinks')->willReturn([]);
        $this->responseAggregator->method('aggregate')->willReturn([]);

        // Tag client throws exception
        $this->queryTagClient
            ->method('findTagById')
            ->willThrowException(new \RuntimeException('Tag not found'));

        // Logger should receive warning
        $this->logger
            ->expects(self::once())
            ->method('warning');

        // Should not throw, execution continues
        $result = $this->orchestrator->execute($request);

        self::assertIsArray($result);
    }

    private function createEditorialMock(): Editorial&MockObject
    {
        $body = $this->createMock(Body::class);
        $body->method('bodyElementsOf')->willReturn([]);

        $tags = $this->createMock(Tags::class);
        $tags->method('getArrayCopy')->willReturn([]);

        $editorialId = $this->createMock(EditorialId::class);
        $editorialId->method('id')->willReturn('test-id');

        $recommendedEditorials = $this->createMock(RecommendedEditorials::class);
        $recommendedEditorials->method('editorialIds')->willReturn([]);

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);
        $editorial->method('tags')->willReturn($tags);
        $editorial->method('id')->willReturn($editorialId);
        $editorial->method('recommendedEditorials')->willReturn($recommendedEditorials);

        return $editorial;
    }

    /**
     * @param array<int, string> $tagIds
     */
    private function createEditorialMockWithTags(array $tagIds): Editorial&MockObject
    {
        $body = $this->createMock(Body::class);
        $body->method('bodyElementsOf')->willReturn([]);

        $tagMocks = [];
        foreach ($tagIds as $tagId) {
            $tagMock = $this->createMock(\Ec\Editorial\Domain\Model\Tag::class);
            $tagMock->method('id')->willReturn($tagId);
            $tagMocks[] = $tagMock;
        }

        $tags = $this->createMock(Tags::class);
        $tags->method('getArrayCopy')->willReturn($tagMocks);

        $editorialId = $this->createMock(EditorialId::class);
        $editorialId->method('id')->willReturn('test-id');

        $recommendedEditorials = $this->createMock(RecommendedEditorials::class);
        $recommendedEditorials->method('editorialIds')->willReturn([]);

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);
        $editorial->method('tags')->willReturn($tags);
        $editorial->method('id')->willReturn($editorialId);
        $editorial->method('recommendedEditorials')->willReturn($recommendedEditorials);

        return $editorial;
    }
}
