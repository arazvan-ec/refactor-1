<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\EditorialFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that checks if the editorial should use legacy processing.
 *
 * If the editorial is a legacy type, this step terminates the pipeline
 * and returns the legacy response immediately.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 900])]
final class LegacyCheckStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly EditorialFetcherInterface $editorialFetcher,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEditorial()) {
            return StepResult::skip();
        }

        $editorial = $context->getEditorial();

        if ($this->editorialFetcher->shouldUseLegacy($editorial)) {
            $legacyResponse = $this->editorialFetcher->fetchLegacy($context->editorialId);

            return StepResult::terminate($legacyResponse);
        }

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 900;
    }

    public function getName(): string
    {
        return 'LegacyCheck';
    }
}
