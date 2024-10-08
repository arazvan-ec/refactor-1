<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\DependencyInjection\Compiler;

use App\Application\Translator\Apps\Body\TranslatorStrategy;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Antonio Jose Cerezo Aranda <acerezo@elconfidencial.com>
 */
class BodyTranslator implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $translators = $container->findTaggedServiceIds('app.translators');
        $translatorsHandler = $container->findDefinition(TranslatorStrategy::class);

        foreach ($translators as $idService => $parameters) {
            $definition = $container->getDefinition($idService);
            $translatorsHandler->addMethodCall('addTranslator', [$definition]);
        }
    }
}
