<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline;

/**
 * Interface for editorial pipeline steps.
 *
 * Each step processes the context and returns a StepResult indicating
 * whether to continue, skip, or terminate the pipeline.
 *
 * Steps are auto-registered via the 'app.editorial_pipeline_step' service tag
 * and executed in priority order (higher priority = executed first).
 *
 * Example implementation:
 *
 *     #[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 500])]
 *     final class MyStep implements EditorialPipelineStepInterface
 *     {
 *         public function process(EditorialPipelineContext $context): StepResult
 *         {
 *             // Do work...
 *             return StepResult::continue();
 *         }
 *     }
 */
interface EditorialPipelineStepInterface
{
    /**
     * Process this step of the pipeline.
     *
     * @param EditorialPipelineContext $context The mutable context
     *
     * @return StepResult Continue, Skip, or Terminate with response
     */
    public function process(EditorialPipelineContext $context): StepResult;

    /**
     * Get the priority of this step.
     *
     * Higher priority = executed first.
     * Recommended ranges:
     * - 1000-900: Fetch/initialization steps
     * - 800-600: Processing/enrichment steps
     * - 500-200: External data fetching
     * - 100: Final aggregation step
     */
    public function getPriority(): int;

    /**
     * Get a human-readable name for logging/debugging.
     */
    public function getName(): string;
}
