<?php

declare(strict_types=1);

namespace App\Orchestrator\Enricher;

use App\Orchestrator\DTO\EditorialContext;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Tag\Domain\Model\QueryTagClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Enricher that fetches full tag data for an editorial.
 *
 * Iterates over the editorial's tag references and fetches
 * complete tag information from the Tag service.
 */
#[AutoconfigureTag('app.content_enricher', ['priority' => 100])]
final class TagsEnricher implements ContentEnricherInterface
{
    public function __construct(
        private readonly QueryTagClient $queryTagClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enrich(EditorialContext $context): void
    {
        $tags = [];

        foreach ($context->editorial->tags()->getArrayCopy() as $tag) {
            try {
                $tags[] = $this->queryTagClient->findTagById($tag->id());
            } catch (\Throwable $exception) {
                $this->logger->warning(
                    'Failed to fetch tag',
                    [
                        'tag_id' => $tag->id(),
                        'editorial_id' => $context->editorial->id()->id(),
                        'error' => $exception->getMessage(),
                    ]
                );
            }
        }

        $context->withTags($tags);
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
