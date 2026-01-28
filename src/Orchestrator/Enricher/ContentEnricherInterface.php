<?php

declare(strict_types=1);

namespace App\Orchestrator\Enricher;

use App\Orchestrator\DTO\EditorialContext;
use Ec\Editorial\Domain\Model\Editorial;

/**
 * Interface for content enrichers that add data to an editorial context.
 *
 * Enrichers are auto-registered via the 'app.content_enricher' service tag
 * and executed by the ContentEnricherChainHandler.
 *
 * To add a new enricher, simply create a class implementing this interface
 * and tag it - no changes needed to EditorialOrchestrator.
 *
 * @example
 * ```php
 * #[AutoconfigureTag('app.content_enricher', ['priority' => 50])]
 * class MyEnricher implements ContentEnricherInterface
 * {
 *     public function enrich(EditorialContext $context): void
 *     {
 *         $context->addCustomData('myKey', $this->fetchSomething());
 *     }
 * }
 * ```
 */
interface ContentEnricherInterface
{
    /**
     * Enrich the editorial context with additional data.
     *
     * HTTP calls are allowed within enrichers - this is the designated
     * place for fetching external data.
     */
    public function enrich(EditorialContext $context): void;

    /**
     * Check if this enricher supports the given editorial.
     *
     * Return false to skip enrichment for certain editorial types.
     */
    public function supports(Editorial $editorial): bool;

    /**
     * Get the priority for ordering enrichers.
     *
     * Higher priority = executed first.
     * Default priorities:
     * - 100: Tags
     * - 90: Membership links
     * - 80: Photo body tags
     *
     * @return int Priority value (higher = first)
     */
    public function getPriority(): int;
}
