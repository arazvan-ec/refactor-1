<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Pipeline\Step;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\Step\FetchCommentsStep;
use App\Orchestrator\Service\CommentsFetcherInterface;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(FetchCommentsStep::class)]
class FetchCommentsStepTest extends TestCase
{
    private FetchCommentsStep $step;
    private MockObject&CommentsFetcherInterface $commentsFetcher;

    protected function setUp(): void
    {
        $this->commentsFetcher = $this->createMock(CommentsFetcherInterface::class);

        $this->step = new FetchCommentsStep($this->commentsFetcher);
    }

    #[Test]
    public function it_implements_editorial_pipeline_step_interface(): void
    {
        self::assertInstanceOf(EditorialPipelineStepInterface::class, $this->step);
    }

    #[Test]
    public function it_has_priority_510(): void
    {
        self::assertSame(510, $this->step->getPriority());
    }

    #[Test]
    public function it_has_name_fetch_comments(): void
    {
        self::assertSame('FetchComments', $this->step->getName());
    }

    #[Test]
    public function it_skips_when_editorial_is_missing(): void
    {
        $context = new EditorialPipelineContext(Request::create('/test'));

        $result = $this->step->process($context);

        self::assertTrue($result->shouldSkip());
    }

    #[Test]
    public function it_fetches_comments_count_and_sets_on_context(): void
    {
        $this->commentsFetcher
            ->expects(self::once())
            ->method('fetchCommentsCount')
            ->with('test-id')
            ->willReturn(42);

        $context = $this->createContextWithEditorial('test-id');

        $result = $this->step->process($context);

        self::assertFalse($result->shouldSkip());
        self::assertFalse($result->shouldTerminate());
        self::assertSame(42, $context->getCommentsCount());
    }

    #[Test]
    public function it_handles_zero_comments(): void
    {
        $this->commentsFetcher
            ->expects(self::once())
            ->method('fetchCommentsCount')
            ->willReturn(0);

        $context = $this->createContextWithEditorial('test-id');

        $result = $this->step->process($context);

        self::assertFalse($result->shouldSkip());
        self::assertSame(0, $context->getCommentsCount());
    }

    private function createContextWithEditorial(string $editorialId): EditorialPipelineContext
    {
        $context = new EditorialPipelineContext(Request::create('/test'));
        $context->setEditorial($this->createEditorial($editorialId));

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
