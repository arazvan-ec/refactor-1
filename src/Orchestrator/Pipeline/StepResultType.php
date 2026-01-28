<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline;

/**
 * Enum representing the type of result from a pipeline step.
 */
enum StepResultType: string
{
    /**
     * Continue to the next step in the pipeline.
     */
    case CONTINUE = 'continue';

    /**
     * Skip this step (used when step doesn't apply).
     */
    case SKIP = 'skip';

    /**
     * Terminate the pipeline and return the response immediately.
     */
    case TERMINATE = 'terminate';
}
