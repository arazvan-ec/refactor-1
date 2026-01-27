<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Chain;

use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use App\Application\DTO\PreFetchedDataDTO;
use App\Application\Service\Editorial\ResponseAggregatorInterface;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\Chain\EditorialOrchestrator;
use App\Orchestrator\Chain\EditorialOrchestratorInterface;
use App\Orchestrator\DTO\EditorialContext;
use App\Orchestrator\Enricher\ContentEnricherChainHandler;
use App\Orchestrator\Service\CommentsFetcherInterface;
use App\Orchestrator\Service\EditorialFetcherInterface;
use App\Orchestrator\Service\EmbeddedContentFetcherInterface;
use App\Orchestrator\Service\SignatureFetcherInterface;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\RecommendedEditorials;
use Ec\Editorial\Domain\Model\Tags;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(EditorialOrchestrator::class)]
class EditorialOrchestratorTest extends TestCase
{
    private EditorialOrchestratorInterface $orchestrator;
    private MockObject&EditorialFetcherInterface $editorialFetcher;
    private MockObject&EmbeddedContentFetcherInterface $embeddedContentFetcher;
    private MockObject&PromiseResolverInterface $promiseResolver;
    private MockObject&ResponseAggregatorInterface $responseAggregator;
    private MockObject&ContentEnricherChainHandler $enricherChain;
    private MockObject&SignatureFetcherInterface $signatureFetcher;
    private MockObject&CommentsFetcherInterface $commentsFetcher;

    protected function setUp(): void
    {
        $this->editorialFetcher = $this->createMock(EditorialFetcherInterface::class);
        $this->embeddedContentFetcher = $this->createMock(EmbeddedContentFetcherInterface::class);
        $this->promiseResolver = $this->createMock(PromiseResolverInterface::class);
        $this->responseAggregator = $this->createMock(ResponseAggregatorInterface::class);
        $this->enricherChain = $this->createMock(ContentEnricherChainHandler::class);
        $this->signatureFetcher = $this->createMock(SignatureFetcherInterface::class);
        $this->commentsFetcher = $this->createMock(CommentsFetcherInterface::class);

        $this->orchestrator = new EditorialOrchestrator(
            $this->editorialFetcher,
            $this->embeddedContentFetcher,
            $this->promiseResolver,
            $this->responseAggregator,
            $this->enricherChain,
            $this->signatureFetcher,
            $this->commentsFetcher,
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

        // Enricher chain is called
        $this->enricherChain
            ->expects(self::once())
            ->method('enrichAll')
            ->with(self::isInstanceOf(EditorialContext::class));

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveMultimedia')
            ->willReturn([]);

        $this->commentsFetcher
            ->expects(self::once())
            ->method('fetchCommentsCount')
            ->with('test-id')
            ->willReturn(0);

        $this->signatureFetcher
            ->expects(self::once())
            ->method('fetchSignatures')
            ->willReturn([]);

        $this->responseAggregator
            ->expects(self::once())
            ->method('aggregate')
            ->willReturn($expectedResponse);

        $result = $this->orchestrator->execute($request);

        self::assertSame($expectedResponse, $result);
    }

    #[Test]
    public function execute_creates_editorial_context_with_correct_data(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $editorial = $this->createEditorialMock();
        $section = $this->createMock(Section::class);
        $section->method('siteId')->willReturn('site-1');

        $fetchedEditorial = new FetchedEditorialDTO($editorial, $section);
        $embeddedContent = new EmbeddedContentDTO();

        $this->editorialFetcher->method('fetch')->willReturn($fetchedEditorial);
        $this->editorialFetcher->method('shouldUseLegacy')->willReturn(false);
        $this->embeddedContentFetcher->method('fetch')->willReturn($embeddedContent);
        $this->promiseResolver->method('resolveMultimedia')->willReturn([]);
        $this->commentsFetcher->method('fetchCommentsCount')->willReturn(0);
        $this->signatureFetcher->method('fetchSignatures')->willReturn([]);
        $this->responseAggregator->method('aggregate')->willReturn([]);

        // Capture the context passed to enricherChain
        $capturedContext = null;
        $this->enricherChain
            ->expects(self::once())
            ->method('enrichAll')
            ->willReturnCallback(function (EditorialContext $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->orchestrator->execute($request);

        self::assertNotNull($capturedContext);
        self::assertSame($editorial, $capturedContext->editorial);
        self::assertSame($section, $capturedContext->section);
        self::assertSame($embeddedContent, $capturedContext->embeddedContent);
    }

    #[Test]
    public function execute_passes_enriched_data_to_aggregator(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $editorial = $this->createEditorialMock();
        $section = $this->createMock(Section::class);
        $section->method('siteId')->willReturn('site-1');

        $fetchedEditorial = new FetchedEditorialDTO($editorial, $section);
        $embeddedContent = new EmbeddedContentDTO();

        $this->editorialFetcher->method('fetch')->willReturn($fetchedEditorial);
        $this->editorialFetcher->method('shouldUseLegacy')->willReturn(false);
        $this->embeddedContentFetcher->method('fetch')->willReturn($embeddedContent);
        $this->promiseResolver->method('resolveMultimedia')->willReturn(['multimedia-data']);
        $this->commentsFetcher->method('fetchCommentsCount')->willReturn(5);
        $this->signatureFetcher->method('fetchSignatures')->willReturn([['name' => 'Author']]);

        // Simulate enrichers populating the context
        $this->enricherChain
            ->method('enrichAll')
            ->willReturnCallback(function (EditorialContext $context): void {
                $context->withTags(['tag1', 'tag2']);
                $context->withMembershipLinks(['link1' => 'resolved1']);
                $context->withPhotoBodyTags(['photo1' => 'data1']);
            });

        // Verify the aggregator receives the enriched data
        $this->responseAggregator
            ->expects(self::once())
            ->method('aggregate')
            ->with(
                $fetchedEditorial,
                $embeddedContent,
                ['tag1', 'tag2'],                    // tags from context
                ['multimedia-data'],                 // resolved multimedia
                ['link1' => 'resolved1'],            // membership links from context
                ['photo1' => 'data1'],               // photo body tags from context
                self::isInstanceOf(PreFetchedDataDTO::class)
            )
            ->willReturn(['response']);

        $this->orchestrator->execute($request);
    }

    private function createEditorialMock(): Editorial&MockObject
    {
        $body = $this->createMock(Body::class);
        $body->method('bodyElementsOf')->willReturn([]);

        $tags = $this->createMock(Tags::class);
        $tags->method('getArrayCopy')->willReturn([]);
        $tags->method('isEmpty')->willReturn(true);

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
