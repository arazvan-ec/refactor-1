<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient as QueryMultimediaOpeningClient;
use Psr\Log\LoggerInterface;

/**
 * Fetches opening multimedia for an editorial.
 *
 * Single responsibility: fetch the opening multimedia from the editorial's opening.
 */
final class OpeningMultimediaFetcher implements OpeningMultimediaFetcherInterface
{
    public function __construct(
        private readonly QueryMultimediaOpeningClient $multimediaOpeningClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(NewsBase $editorial): array
    {
        $opening = $editorial->opening();

        if (empty($opening->multimediaId())) {
            return [];
        }

        try {
            /** @var Multimedia $multimedia */
            $multimedia = $this->multimediaOpeningClient->findMultimediaById($opening->multimediaId());

            return ['opening' => ['multimedia' => $multimedia]];
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch opening multimedia: ' . $e->getMessage(), [
                'multimedia_id' => $opening->multimediaId(),
            ]);

            return [];
        }
    }
}
