<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\NewsBase;
use Psr\Log\LoggerInterface;

/**
 * Fetches recommended editorials for an editorial.
 *
 * Single responsibility: extract and fetch all recommended editorial references.
 */
final class RecommendedEditorialsFetcher implements RecommendedEditorialsFetcherInterface
{
    public function __construct(
        private readonly EmbeddedEditorialFetcherInterface $embeddedEditorialFetcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(NewsBase $editorial): array
    {
        $editorials = [];
        $news = [];
        $promises = [];

        /** @var EditorialId $recommendedId */
        foreach ($editorial->recommendedEditorials()->editorialIds() as $recommendedId) {
            try {
                $result = $this->embeddedEditorialFetcher->fetch($recommendedId->id());

                if (null !== $result) {
                    $editorials[$result['dto']->id] = $result['dto'];
                    $news[] = $result['dto']->editorial;
                    $promises = array_merge($promises, $result['promises']);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to fetch recommended editorial: ' . $e->getMessage(), [
                    'editorial_id' => $recommendedId->id(),
                ]);
            }
        }

        return ['editorials' => $editorials, 'news' => $news, 'promises' => $promises];
    }
}
