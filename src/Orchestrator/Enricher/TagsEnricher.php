<?php

declare(strict_types=1);

namespace App\Orchestrator\Enricher;

use App\Application\Service\Promise\PromiseResolverInterface;
use App\Orchestrator\DTO\EditorialContext;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Tag\Domain\Model\QueryTagClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Enricher that fetches full tag data for an editorial in parallel.
 *
 * Uses async promises to fetch all tags concurrently instead of
 * sequential requests, significantly improving performance for
 * editorials with multiple tags.
 */
#[AutoconfigureTag('app.content_enricher', ['priority' => 100])]
final class TagsEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryTagClient $queryTagClient,
        private readonly PromiseResolverInterface $promiseResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enrich(EditorialContext $context): void
    {
        $tagRefs = $context->editorial->tags()->getArrayCopy();

        if (empty($tagRefs)) {
            $context->withTags([]);

            return;
        }

        // Create promises for all tags (parallel execution)
        $promises = [];
        foreach ($tagRefs as $tagRef) {
            $promises[$tagRef->id()] = $this->queryTagClient->findTagById(
                $tagRef->id(),
                async: true
            );
        }

        // Resolve all promises in parallel
        $result = $this->promiseResolver->resolveAll($promises);

        // Log rejected tags
        foreach ($result->rejected as $tagId => $error) {
            $this->logger->warning(
                'Failed to fetch tag',
                [
                    'tag_id' => $tagId,
                    'editorial_id' => $context->editorial->id()->id(),
                    'error' => $error->getMessage(),
                ]
            );
        }

        $context->withTags(array_values($result->fulfilled));
    }

    public function supports(Editorial $editorial): bool
    {
        return !$editorial->tags()->isEmpty();
    }

    public function getPriority(): int
    {
        return 100;
    }
}
