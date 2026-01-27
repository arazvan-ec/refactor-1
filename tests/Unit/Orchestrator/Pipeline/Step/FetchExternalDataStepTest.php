<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Pipeline\Step;

use App\Application\DTO\BatchResult;
use App\Application\DTO\PreFetchedDataDTO;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\Step\FetchExternalDataStep;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\CommentsFetcherInterface;
use App\Orchestrator\Service\SignatureFetcherInterface;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Section\Domain\Model\Section;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(FetchExternalDataStep::class)]
class FetchExternalDataStepTest extends TestCase
{
    private FetchExternalDataStep $step;
    private MockObject&CommentsFetcherInterface $commentsFetcher;
    private MockObject&SignatureFetcherInterface $signatureFetcher;
    private MockObject&PromiseResolverInterface $promiseResolver;

    protected function setUp(): void
    {
        $this->commentsFetcher = $this->createMock(CommentsFetcherInterface::class);
        $this->signatureFetcher = $this->createMock(SignatureFetcherInterface::class);
        $this->promiseResolver = $this->createMock(PromiseResolverInterface::class);

        $this->step = new FetchExternalDataStep(
            $this->commentsFetcher,
            $this->signatureFetcher,
            $this->promiseResolver
        );
    }

    #[Test]
    public function it_implements_editorial_pipeline_step_interface(): void
    {
        self::assertInstanceOf(EditorialPipelineStepInterface::class, $this->step);
    }

    #[Test]
    public function it_has_priority_500(): void
    {
        self::assertSame(500, $this->step->getPriority());
    }

    #[Test]
    public function it_has_name_fetch_external_data(): void
    {
        self::assertSame('FetchExternalData', $this->step->getName());
    }

    #[Test]
    public function it_skips_when_editorial_is_missing(): void
    {
        $context = new EditorialPipelineContext(Request::create('/test'));

        $result = $this->step->process($context);

        self::assertTrue($result->shouldSkip());
    }

    #[Test]
    public function it_skips_when_section_is_missing(): void
    {
        $context = new EditorialPipelineContext(Request::create('/test'));
        $context->setEditorial($this->createEditorial('test-id'));

        $result = $this->step->process($context);

        self::assertTrue($result->shouldSkip());
    }

    #[Test]
    public function it_fetches_external_data_in_parallel(): void
    {
        $commentsPromise = new FulfilledPromise(42);
        $signaturesPromise = new FulfilledPromise([
            ['id' => 'sig-1', 'name' => 'Author 1', 'picture' => null, 'url' => '/author-1'],
        ]);

        $this->commentsFetcher
            ->expects(self::once())
            ->method('fetchCommentsCountAsync')
            ->with('test-id')
            ->willReturn($commentsPromise);

        $this->signatureFetcher
            ->expects(self::once())
            ->method('fetchSignaturesAsync')
            ->willReturn($signaturesPromise);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->with(self::callback(fn(array $promises): bool => isset($promises['comments'], $promises['signatures'])))
            ->willReturn(new BatchResult([
                'comments' => 42,
                'signatures' => [
                    ['id' => 'sig-1', 'name' => 'Author 1', 'picture' => null, 'url' => '/author-1'],
                ],
            ], []));

        $context = $this->createContextWithEditorialAndSection();

        $result = $this->step->process($context);

        self::assertFalse($result->shouldSkip());
        self::assertFalse($result->shouldTerminate());

        $preFetchedData = $context->getPreFetchedData();
        self::assertInstanceOf(PreFetchedDataDTO::class, $preFetchedData);
        self::assertSame(42, $preFetchedData->commentsCount);
        self::assertCount(1, $preFetchedData->signatures);
    }

    #[Test]
    public function it_handles_comments_fetch_failure_gracefully(): void
    {
        $commentsPromise = new FulfilledPromise(0);
        $signaturesPromise = new FulfilledPromise([]);

        $this->commentsFetcher
            ->expects(self::once())
            ->method('fetchCommentsCountAsync')
            ->willReturn($commentsPromise);

        $this->signatureFetcher
            ->expects(self::once())
            ->method('fetchSignaturesAsync')
            ->willReturn($signaturesPromise);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->willReturn(new BatchResult(
                ['signatures' => []],
                ['comments' => new \RuntimeException('Comments service unavailable')]
            ));

        $context = $this->createContextWithEditorialAndSection();

        $result = $this->step->process($context);

        self::assertFalse($result->shouldSkip());

        $preFetchedData = $context->getPreFetchedData();
        self::assertSame(0, $preFetchedData->commentsCount); // Default value
        self::assertSame([], $preFetchedData->signatures);
    }

    #[Test]
    public function it_handles_signatures_fetch_failure_gracefully(): void
    {
        $commentsPromise = new FulfilledPromise(10);
        $signaturesPromise = new FulfilledPromise([]);

        $this->commentsFetcher
            ->expects(self::once())
            ->method('fetchCommentsCountAsync')
            ->willReturn($commentsPromise);

        $this->signatureFetcher
            ->expects(self::once())
            ->method('fetchSignaturesAsync')
            ->willReturn($signaturesPromise);

        $this->promiseResolver
            ->expects(self::once())
            ->method('resolveAll')
            ->willReturn(new BatchResult(
                ['comments' => 10],
                ['signatures' => new \RuntimeException('Journalist service unavailable')]
            ));

        $context = $this->createContextWithEditorialAndSection();

        $result = $this->step->process($context);

        self::assertFalse($result->shouldSkip());

        $preFetchedData = $context->getPreFetchedData();
        self::assertSame(10, $preFetchedData->commentsCount);
        self::assertSame([], $preFetchedData->signatures); // Default value
    }

    private function createContextWithEditorialAndSection(): EditorialPipelineContext
    {
        $context = new EditorialPipelineContext(Request::create('/test'));
        $context->setEditorial($this->createEditorial('test-id'));
        $context->setSection($this->createMock(Section::class));

        return $context;
    }

    private function createEditorial(string $id): Editorial
    {
        $editorialId = $this->createMock(EditorialId::class);
        $editorialId->method('id')->willReturn($id);

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('id')->willReturn($editorialId);

        return $editorial;
    }
}
