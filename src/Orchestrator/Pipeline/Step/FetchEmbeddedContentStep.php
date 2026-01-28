<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\EmbeddedContentFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that fetches embedded content.
 *
 * Retrieves inserted news, recommended editorials, and multimedia content.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 800])]
final class FetchEmbeddedContentStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly EmbeddedContentFetcherInterface $embeddedContentFetcher,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEditorial() || !$context->hasSection()) {
            return StepResult::skip();
        }

        $embeddedContent = $this->embeddedContentFetcher->fetch(
            $context->getEditorial(),
            $context->getSection()
        );

        $context->setEmbeddedContent($embeddedContent);

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 800;
    }

    public function getName(): string
    {
        return 'FetchEmbeddedContent';
    }
}
