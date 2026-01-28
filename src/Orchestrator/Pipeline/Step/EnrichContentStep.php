<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Orchestrator\DTO\EditorialContext;
use App\Orchestrator\Enricher\ContentEnricherChainHandler;
use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that runs content enrichers.
 *
 * Delegates to ContentEnricherChainHandler to fetch tags,
 * membership links, and photos from body tags.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 700])]
final class EnrichContentStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly ContentEnricherChainHandler $enricherChain,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEditorial() || !$context->hasSection() || !$context->hasEmbeddedContent()) {
            return StepResult::skip();
        }

        // Create enricher context from pipeline context
        $enricherContext = new EditorialContext(
            $context->getEditorial(),
            $context->getSection(),
            $context->getEmbeddedContent(),
        );

        // Run all registered enrichers
        $this->enricherChain->enrichAll($enricherContext);

        // Copy enriched data back to pipeline context
        $context->setTags($enricherContext->getTags());
        $context->setMembershipLinks($enricherContext->getMembershipLinks());
        $context->setPhotoBodyTags($enricherContext->getPhotoBodyTags());

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 700;
    }

    public function getName(): string
    {
        return 'EnrichContent';
    }
}
