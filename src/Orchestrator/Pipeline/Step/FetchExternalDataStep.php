<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Application\DTO\PreFetchedDataDTO;
use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\CommentsFetcherInterface;
use App\Orchestrator\Service\SignatureFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that fetches external data in parallel.
 *
 * Retrieves comments count and journalist signatures concurrently
 * using async promises to improve performance.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 500])]
final class FetchExternalDataStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly CommentsFetcherInterface $commentsFetcher,
        private readonly SignatureFetcherInterface $signatureFetcher,
        private readonly PromiseResolverInterface $promiseResolver,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEditorial() || !$context->hasSection()) {
            return StepResult::skip();
        }

        $editorial = $context->getEditorial();
        $section = $context->getSection();

        // Create promises for parallel execution
        $promises = [
            'comments' => $this->commentsFetcher->fetchCommentsCountAsync(
                $editorial->id()->id()
            ),
            'signatures' => $this->signatureFetcher->fetchSignaturesAsync(
                $editorial,
                $section
            ),
        ];

        // Resolve all promises in parallel
        $result = $this->promiseResolver->resolveAll($promises);

        // Extract results with defaults for failures
        $commentsCount = $result->fulfilled['comments'] ?? 0;
        $signatures = $result->fulfilled['signatures'] ?? [];

        $preFetchedData = new PreFetchedDataDTO(
            commentsCount: $commentsCount,
            signatures: $signatures,
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
