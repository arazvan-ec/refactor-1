<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\SignatureFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that fetches journalist signatures for an editorial.
 *
 * Single responsibility: retrieve and transform journalist signatures.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 500])]
final class FetchSignaturesStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly SignatureFetcherInterface $signatureFetcher,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEditorial() || !$context->hasSection()) {
            return StepResult::skip();
        }

        $editorial = $context->getEditorial();
        $section = $context->getSection();

        $signatures = $this->signatureFetcher->fetchSignatures($editorial, $section);

        $context->setSignatures($signatures);

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 500;
    }

    public function getName(): string
    {
        return 'FetchSignatures';
    }
}
