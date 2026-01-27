<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Orchestrator\Pipeline\EditorialPipelineHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers editorial pipeline steps with the pipeline handler.
 *
 * Steps are tagged with 'app.editorial_pipeline_step' and automatically
 * registered with the EditorialPipelineHandler.
 *
 * Example service configuration:
 *
 *     App\Orchestrator\Pipeline\Step\FetchEditorialStep:
 *         tags:
 *             - { name: 'app.editorial_pipeline_step', priority: 1000 }
 *
 * Or using PHP attributes:
 *
 *     #[AutoconfigureTag('app.editorial_pipeline_step', ['priority' => 1000])]
 */
class EditorialPipelineCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(EditorialPipelineHandler::class)) {
            return;
        }

        $handlerDefinition = $container->findDefinition(EditorialPipelineHandler::class);
        $taggedServices = $container->findTaggedServiceIds('app.editorial_pipeline_step');

        foreach ($taggedServices as $serviceId => $tags) {
            $stepDefinition = $container->getDefinition($serviceId);
            $handlerDefinition->addMethodCall('addStep', [$stepDefinition]);
        }
    }
}
