<?php

/**
 * @copyright
 */

namespace App\DependencyInjection\Compiler;

use App\Orchestrator\Chain\MultimediaType\MultimediaTypeOrchestratorHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class MultimediaTypeOrchestratorCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $orchestrators = $container->findTaggedServiceIds('app.multimedia.orchestrators');
        $orchestratorsHandler = $container->findDefinition(MultimediaTypeOrchestratorHandler::class);

        foreach ($orchestrators as $idService => $parameters) {
            $definition = $container->getDefinition($idService);
            $orchestratorsHandler->addMethodCall('addOrchestrator', [$definition]);
        }
    }
}
