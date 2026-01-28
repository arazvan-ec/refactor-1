<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Orchestrator\Enricher\ContentEnricherChainHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers content enrichers tagged with 'app.content_enricher'.
 *
 * Enrichers are sorted by priority (higher = first) and injected
 * into the ContentEnricherChainHandler.
 *
 * To add a new enricher, simply create a class implementing
 * ContentEnricherInterface and tag it:
 *
 * ```yaml
 * App\Orchestrator\Enricher\MyEnricher:
 *     tags:
 *         - { name: 'app.content_enricher', priority: 50 }
 * ```
 *
 * Or with PHP attributes:
 * ```php
 * #[AutoconfigureTag('app.content_enricher', ['priority' => 50])]
 * class MyEnricher implements ContentEnricherInterface
 * ```
 */
class ContentEnricherCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ContentEnricherChainHandler::class)) {
            return;
        }

        $handlerDefinition = $container->findDefinition(ContentEnricherChainHandler::class);
        $taggedServices = $container->findTaggedServiceIds('app.content_enricher');

        // Group by priority
        $enrichersByPriority = [];
        foreach ($taggedServices as $serviceId => $tags) {
            $priority = $tags[0]['priority'] ?? 0;
            $enrichersByPriority[$priority][] = new Reference($serviceId);
        }

        // Sort by priority descending (higher = first)
        krsort($enrichersByPriority);

        // Flatten into single array
        $sortedEnrichers = [];
        foreach ($enrichersByPriority as $enrichers) {
            foreach ($enrichers as $enricher) {
                $sortedEnrichers[] = $enricher;
            }
        }

        // Inject enrichers via addEnricher method calls
        foreach ($sortedEnrichers as $enricherReference) {
            $handlerDefinition->addMethodCall('addEnricher', [$enricherReference]);
        }
    }
}
