<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline;

use Psr\Log\LoggerInterface;

/**
 * Executes editorial pipeline steps in priority order.
 *
 * Steps are registered via EditorialPipelineCompiler using the
 * 'app.editorial_pipeline_step' service tag.
 *
 * The pipeline executes steps from highest to lowest priority.
 * Each step can:
 * - Continue: proceed to the next step
 * - Skip: no-op, proceed to next step
 * - Terminate: stop pipeline and return response immediately
 */
final class EditorialPipelineHandler
{
    /** @var array<int, EditorialPipelineStepInterface> */
    private array $steps = [];

    private bool $sorted = false;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Add a step to the pipeline.
     *
     * Called by the EditorialPipelineCompiler during container compilation.
     */
    public function addStep(EditorialPipelineStepInterface $step): void
    {
        $this->steps[] = $step;
        $this->sorted = false;
    }

    /**
     * Execute the pipeline and return the final response.
     *
     * @return array<string, mixed> The API response
     *
     * @throws \RuntimeException If pipeline completes without a response
     */
    public function execute(EditorialPipelineContext $context): array
    {
        $this->sortSteps();

        $this->logger->debug('Starting editorial pipeline', [
            'editorial_id' => $context->editorialId,
            'step_count' => count($this->steps),
        ]);

        foreach ($this->steps as $step) {
            $stepName = $step->getName();
            $priority = $step->getPriority();

            $this->logger->debug('Executing pipeline step', [
                'step' => $stepName,
                'priority' => $priority,
                'editorial_id' => $context->editorialId,
            ]);

            try {
                $result = $step->process($context);

                if ($result->shouldTerminate()) {
                    $this->logger->debug('Pipeline terminated by step', [
                        'step' => $stepName,
                        'editorial_id' => $context->editorialId,
                    ]);

                    return $result->getResponse() ?? [];
                }

                if ($result->shouldSkip()) {
                    $this->logger->debug('Step skipped', [
                        'step' => $stepName,
                        'editorial_id' => $context->editorialId,
                    ]);
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Pipeline step failed', [
                    'step' => $stepName,
                    'editorial_id' => $context->editorialId,
                    'error' => $exception->getMessage(),
                    'exception' => $exception,
                ]);

                throw $exception;
            }
        }

        throw new \RuntimeException(sprintf(
            'Editorial pipeline completed without producing a response for editorial "%s". ' .
            'Ensure a terminal step (like AggregateResponseStep) is registered.',
            $context->editorialId
        ));
    }

    /**
     * Get the number of registered steps.
     */
    public function count(): int
    {
        return count($this->steps);
    }

    /**
     * Get registered step names (for debugging).
     *
     * @return array<int, string>
     */
    public function getStepNames(): array
    {
        $this->sortSteps();

        return array_map(
            fn(EditorialPipelineStepInterface $step) => $step->getName(),
            $this->steps
        );
    }

    /**
     * Sort steps by priority (higher first).
     */
    private function sortSteps(): void
    {
        if ($this->sorted) {
            return;
        }

        usort(
            $this->steps,
            fn(EditorialPipelineStepInterface $a, EditorialPipelineStepInterface $b) => $b->getPriority() <=> $a->getPriority()
        );

        $this->sorted = true;
    }
}
