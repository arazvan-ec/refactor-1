<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Application\DTO\PreFetchedDataDTO;
use App\Application\Service\Editorial\ResponseAggregatorInterface;
use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that aggregates the final response.
 *
 * This is the final step that terminates the pipeline
 * by returning the complete editorial response.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 100])]
final class AggregateResponseStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly ResponseAggregatorInterface $responseAggregator,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasFetchedEditorial() || !$context->hasEmbeddedContent()) {
            throw new \RuntimeException(
                'Cannot aggregate response: missing required data (fetchedEditorial or embeddedContent)'
            );
        }

        // Build PreFetchedDataDTO from context (populated by FetchCommentsStep and FetchSignaturesStep)
        $preFetchedData = new PreFetchedDataDTO(
            commentsCount: $context->getCommentsCount(),
            signatures: $context->getSignatures(),
        );

        $response = $this->responseAggregator->aggregate(
            $context->getFetchedEditorial(),
            $context->getEmbeddedContent(),
            $context->getTags(),
            $context->getResolvedMultimedia(),
            $context->getMembershipLinks(),
            $context->getPhotoBodyTags(),
            $preFetchedData,
        );

        return StepResult::terminate($response);
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getName(): string
    {
        return 'AggregateResponse';
    }
}
