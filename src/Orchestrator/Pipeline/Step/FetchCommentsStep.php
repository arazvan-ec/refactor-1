<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use App\Orchestrator\Service\CommentsFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that fetches comments count for an editorial.
 *
 * Single responsibility: retrieve comments count from the legacy system.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 510])]
final class FetchCommentsStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly CommentsFetcherInterface $commentsFetcher,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEditorial()) {
            return StepResult::skip();
        }

        $editorialId = $context->getEditorial()->id()->id();
        $commentsCount = $this->commentsFetcher->fetchCommentsCount($editorialId);

        $context->setCommentsCount($commentsCount);

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 510;
    }

    public function getName(): string
    {
        return 'FetchComments';
    }
}
