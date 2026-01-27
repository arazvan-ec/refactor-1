<?php

declare(strict_types=1);

namespace App\Orchestrator\Pipeline;

/**
 * Result of a pipeline step execution.
 *
 * Each step returns a StepResult indicating whether to:
 * - Continue to the next step
 * - Skip this step (no-op)
 * - Terminate the pipeline with a response
 */
final class StepResult
{
    /**
     * @param array<string, mixed>|null $response
     */
    private function __construct(
        private readonly StepResultType $type,
        private readonly ?array $response = null,
    ) {
    }

    /**
     * Continue to the next step in the pipeline.
     */
    public static function continue(): self
    {
        return new self(StepResultType::CONTINUE);
    }

    /**
     * Skip this step (used when step conditions aren't met).
     */
    public static function skip(): self
    {
        return new self(StepResultType::SKIP);
    }

    /**
     * Terminate the pipeline and return the response immediately.
     *
     * @param array<string, mixed> $response
     */
    public static function terminate(array $response): self
    {
        return new self(StepResultType::TERMINATE, $response);
    }

    public function getType(): StepResultType
    {
        return $this->type;
    }

    public function shouldContinue(): bool
    {
        return $this->type === StepResultType::CONTINUE;
    }

    public function shouldSkip(): bool
    {
        return $this->type === StepResultType::SKIP;
    }

    public function shouldTerminate(): bool
    {
        return $this->type === StepResultType::TERMINATE;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}
