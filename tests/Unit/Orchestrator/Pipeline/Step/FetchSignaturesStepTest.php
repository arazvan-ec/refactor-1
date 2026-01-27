<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Pipeline\Step;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\Step\FetchSignaturesStep;
use App\Orchestrator\Service\SignatureFetcherInterface;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(FetchSignaturesStep::class)]
class FetchSignaturesStepTest extends TestCase
{
    private FetchSignaturesStep $step;
    private MockObject&SignatureFetcherInterface $signatureFetcher;

    protected function setUp(): void
    {
        $this->signatureFetcher = $this->createMock(SignatureFetcherInterface::class);

        $this->step = new FetchSignaturesStep($this->signatureFetcher);
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
    public function it_has_name_fetch_signatures(): void
    {
        self::assertSame('FetchSignatures', $this->step->getName());
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
    public function it_fetches_signatures_and_sets_on_context(): void
    {
        $signatures = [
            ['id' => 'sig-1', 'name' => 'Author 1', 'picture' => null, 'url' => '/author-1'],
            ['id' => 'sig-2', 'name' => 'Author 2', 'picture' => '/pic.jpg', 'url' => '/author-2'],
        ];

        $this->signatureFetcher
            ->expects(self::once())
            ->method('fetchSignatures')
            ->willReturn($signatures);

        $context = $this->createContextWithEditorialAndSection();

        $result = $this->step->process($context);

        self::assertFalse($result->shouldSkip());
        self::assertFalse($result->shouldTerminate());
        self::assertSame($signatures, $context->getSignatures());
    }

    #[Test]
    public function it_handles_empty_signatures(): void
    {
        $this->signatureFetcher
            ->expects(self::once())
            ->method('fetchSignatures')
            ->willReturn([]);

        $context = $this->createContextWithEditorialAndSection();

        $result = $this->step->process($context);

        self::assertFalse($result->shouldSkip());
        self::assertSame([], $context->getSignatures());
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
