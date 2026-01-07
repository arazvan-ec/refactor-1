<?php

namespace App\DependencyInjection\Compiler;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
use Ec\Widget\Application\Service\WidgetLegacyCreatorHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class WidgetLegacyCreatorHandlerCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definedServiceTags = $container->findTaggedServiceIds('ec.widget.legacyCreator');
        $definedHandler = $container->findDefinition(WidgetLegacyCreatorHandler::class);

        foreach ($definedServiceTags as $idService => $parameters) {
            $serviceDefinition = $container->getDefinition($idService);
            $definedHandler->addMethodCall('addCreator', [$serviceDefinition]);
        }
    }
}
