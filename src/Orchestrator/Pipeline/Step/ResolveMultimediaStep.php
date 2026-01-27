<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline\Step;

use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineStepInterface;
use App\Orchestrator\Pipeline\StepResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step that resolves multimedia promises.
 *
 * Waits for all multimedia async operations to complete
 * and stores the resolved data in the context.
 */
#[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 600])]
final class ResolveMultimediaStep implements EditorialPipelineStepInterface
{
    public function __construct(
        private readonly PromiseResolverInterface $promiseResolver,
    ) {
    }

    public function process(EditorialPipelineContext $context): StepResult
    {
        if (!$context->hasEmbeddedContent()) {
            return StepResult::skip();
        }

        $embeddedContent = $context->getEmbeddedContent();

        $resolvedMultimedia = $this->promiseResolver->resolveMultimedia(
            $embeddedContent->multimediaPromises
        );

        $context->setResolvedMultimedia($resolvedMultimedia);

        return StepResult::continue();
    }

    public function getPriority(): int
    {
        return 600;
    }

    public function getName(): string
    {
        return 'ResolveMultimedia';
    }
}
