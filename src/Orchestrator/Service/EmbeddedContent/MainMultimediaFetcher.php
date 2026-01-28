<?php

declare(strict_types=1);

namespace App\Orchestrator\Service\EmbeddedContent;

use App\Infrastructure\Trait\MultimediaTrait;
use Ec\Editorial\Domain\Model\Multimedia\Widget;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient as QueryMultimediaOpeningClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Psr\Log\LoggerInterface;

/**
 * Fetches main multimedia for an editorial.
 *
 * Single responsibility: fetch the main multimedia and handle meta image fallback.
 */
final class MainMultimediaFetcher implements MainMultimediaFetcherInterface
{
    use MultimediaTrait;

    private const ASYNC = true;

    public function __construct(
        private readonly QueryMultimediaClient $multimediaClient,
        private readonly QueryMultimediaOpeningClient $multimediaOpeningClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(NewsBase $editorial): array
    {
        $promises = [];
        $opening = [];

        $multimedia = $editorial->multimedia();

        if ($multimedia instanceof Widget) {
            return ['promises' => [], 'opening' => []];
        }

        $id = $this->getMultimediaId($multimedia);

        if (null !== $id) {
            $promises[] = $this->multimediaClient->findMultimediaById($id, self::ASYNC);
        }

        // Handle meta image fallback
        if (empty($id) && !empty($editorial->metaImage())) {
            $opening = $this->fetchMetaImageFallback($editorial->metaImage());
        }

        return ['promises' => $promises, 'opening' => $opening];
    }

    /**
     * Fetch meta image as fallback when main multimedia is empty.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchMetaImageFallback(string $metaImage): array
    {
        try {
            /** @var Multimedia $metaMultimedia */
            $metaMultimedia = $this->multimediaOpeningClient->findMultimediaById($metaImage);

            if ($metaMultimedia instanceof MultimediaPhoto) {
                $resource = $this->multimediaOpeningClient->findPhotoById($metaMultimedia->resourceId());

                return [
                    $metaImage => [
                        'resource' => $resource,
                        'opening' => $metaMultimedia,
                    ],
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch meta image fallback: ' . $e->getMessage(), [
                'meta_image' => $metaImage,
            ]);
        }

        return [];
    }
}
