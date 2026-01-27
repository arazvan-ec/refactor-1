<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\EditorialFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that fetches the editorial and section.
 *
 * This is typically the first step in the pipeline.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 1000])]
final class FetchEditorialStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly EditorialFetcherInterface $editorialFetcher,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        $fetchedEditorial = $this->editorialFetcher->fetch($context->editorialId);
        $context->setFetchedEditorial($fetchedEditorial);

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 1000;
    }

    public function getName(): string
    {
        return 'FetchEditorial';
    }
}
