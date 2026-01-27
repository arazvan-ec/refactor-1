<?php

/**
 * @copyright
 */

declare(strict_types=1);

namespace App\Orchestrator\Chain;

use App\Orchestrator\Pipeline\EditorialPipelineContext;
use App\Orchestrator\Pipeline\EditorialPipelineHandler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Minimal orchestrator that delegates all logic to the pipeline.
 *
 * All processing is done by pipeline steps registered via service tags.
 * This class is just an entry point that invokes the pipeline.
 *
 * To add new behavior:
 * - Create a step implementing EditorialPipelineStepInterface
 * - Tag it with 'app.editorial_pipeline_step'
 * - No changes to this class are needed
 *
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestrator implements EditorialOrchestratorInterface
{
    public function __construct(
        private readonly EditorialPipelineHandler $pipeline,
    ) {
    }

    /**
     * Execute the editorial orchestration via the pipeline.
     *
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function execute(Request $request): array
    {
        $context = new EditorialPipelineContext($request);

        return $this->pipeline->execute($context);
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
