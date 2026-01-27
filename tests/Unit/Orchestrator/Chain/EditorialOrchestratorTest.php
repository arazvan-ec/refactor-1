<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Chain;

use App\Orchestrator\Chain\EditorialOrchestrator;
use App\Orchestrator\Chain\EditorialOrchestratorInterface;
use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(EditorialOrchestrator::class)]
class EditorialOrchestratorTest extends TestCase
{
    private EditorialOrchestratorInterface $orchestrator;
    private MockObject&EditorialPipelineHandler $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = $this->createMock(EditorialPipelineHandler::class);

        $this->orchestrator = new EditorialOrchestrator(
            $this->pipeline,
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
    public function execute_creates_context_and_delegates_to_pipeline(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $expectedResponse = ['id' => 'test-id', 'title' => 'Test'];

        $this->pipeline
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (EditorialPipelineContext $context): bool {
                return $context->editorialId === 'test-id'
                    && $context->request instanceof Request;
            }))
            ->willReturn($expectedResponse);

        $result = $this->orchestrator->execute($request);

        self::assertSame($expectedResponse, $result);
    }

    #[Test]
    public function execute_passes_request_to_context(): void
    {
        $request = new Request([], [], ['id' => 'another-id']);

        $capturedContext = null;
        $this->pipeline
            ->method('execute')
            ->willReturnCallback(function (EditorialPipelineContext $context) use (&$capturedContext): array {
                $capturedContext = $context;
                return [];
            });

        $this->orchestrator->execute($request);

        self::assertNotNull($capturedContext);
        self::assertSame('another-id', $capturedContext->editorialId);
        self::assertSame($request, $capturedContext->request);
    }

    #[Test]
    public function execute_returns_pipeline_response(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);
        $pipelineResponse = [
            'id' => 'test-id',
            'url' => 'https://example.com/article',
            'titles' => ['title' => 'Test Article'],
        ];

        $this->pipeline
            ->method('execute')
            ->willReturn($pipelineResponse);

        $result = $this->orchestrator->execute($request);

        self::assertSame($pipelineResponse, $result);
    }

    #[Test]
    public function execute_propagates_pipeline_exception(): void
    {
        $request = new Request([], [], ['id' => 'test-id']);

        $this->pipeline
            ->method('execute')
            ->willThrowException(new \RuntimeException('Pipeline failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline failed');

        $this->orchestrator->execute($request);
    }
}
