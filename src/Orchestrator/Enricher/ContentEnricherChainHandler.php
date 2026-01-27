<?php

declare(strict_types=1);

namespace App\Orchestrator\Enricher;

use App\Orchestrator\DTO\EditorialContext;
use Psr\Log\LoggerInterface;

/**
 * Executes all registered content enrichers on an editorial context.
 *
 * Enrichers are registered via the ContentEnricherCompiler and executed
 * in priority order (higher priority = first).
 *
 * Each enricher can add data to the context. Failures in individual
 * enrichers are logged but don't stop the chain (fail-safe pattern).
 */
final class ContentEnricherChainHandler
{
    /** @var array<int, ContentEnricherInterface> */
    private array $enrichers = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Add an enricher to the chain.
     *
     * Called by the ContentEnricherCompiler during container compilation.
     */
    public function addEnricher(ContentEnricherInterface $enricher): void
    {
        $this->enrichers[] = $enricher;
    }

    /**
     * Execute all enrichers on the context.
     *
     * Enrichers that don't support the editorial are skipped.
     * Enrichers that throw exceptions are logged but don't stop the chain.
     */
    public function enrichAll(EditorialContext $context): void
    {
        foreach ($this->enrichers as $enricher) {
            if (!$enricher->supports($context->editorial)) {
                continue;
            }

            try {
                $enricher->enrich($context);
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf(
                        'Content enricher %s failed: %s',
                        $enricher::class,
                        $exception->getMessage()
                    ),
                    [
                        'enricher' => $enricher::class,
                        'editorial_id' => $context->editorial->id()->id(),
                        'exception' => $exception,
                    ]
                );
            }
        }
    }

    /**
     * Get the number of registered enrichers.
     *
     * Useful for testing and debugging.
     */
    public function count(): int
    {
        return count($this->enrichers);
    }
}
