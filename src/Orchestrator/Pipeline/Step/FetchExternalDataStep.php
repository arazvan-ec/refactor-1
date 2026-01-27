<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Application\DTO\PreFetchedDataDTO;
use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\CommentsFetcherInterface;
use App\Orchestrator\Service\SignatureFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that fetches external data.
 *
 * Retrieves comments count and journalist signatures.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 500])]
final class FetchExternalDataStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly CommentsFetcherInterface $commentsFetcher,
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

        $preFetchedData = new PreFetchedDataDTO(
            commentsCount: $this->commentsFetcher->fetchCommentsCount($editorial->id()->id()),
            signatures: $this->signatureFetcher->fetchSignatures($editorial, $section),
        );

        $context->setPreFetchedData($preFetchedData);

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 500;
    }

    public function getName(): string
    {
        return 'FetchExternalData';
    }
}
