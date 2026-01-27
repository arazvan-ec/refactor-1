<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Service;

use App\Application\DataTransformer\DTO\MultimediaShotsCollectionDTO;
use App\Application\DataTransformer\DTO\ResolveDataDTO;

/**
 * Resolves multimedia shots from pre-fetched data.
 *
 * Centralizes logic previously duplicated across transformers:
 * - BodyTagInsertedNewsDataTransformer
 * - RecommendedEditorialsDataTransformer
 *
 * The resolution strategy:
 * 1. Check if opening multimedia exists (higher quality)
 * 2. Fall back to body multimedia
 * 3. Return empty collection if neither exists
 */
final readonly class MultimediaShotResolver
{
    public function __construct(
        private MultimediaShotGenerator $shotGenerator,
    ) {}

    /**
     * Resolve shots for an inserted editorial.
     *
     * Looks up the multimedia ID from inserted news data,
     * then generates shots preferring opening multimedia.
     */
    public function resolveForInsertedEditorial(
        string $editorialId,
        ResolveDataDTO $resolveData,
    ): MultimediaShotsCollectionDTO {
        $insertedNews = $resolveData->getInsertedNews($editorialId);
        if ($insertedNews === null || $insertedNews->multimediaId === null) {
            return new MultimediaShotsCollectionDTO();
        }

        return $this->resolveByMultimediaId($insertedNews->multimediaId, $resolveData);
    }

    /**
     * Resolve shots by multimedia ID.
     *
     * Prefers opening multimedia over body multimedia for higher quality.
     */
    public function resolveByMultimediaId(
        string $multimediaId,
        ResolveDataDTO $resolveData,
    ): MultimediaShotsCollectionDTO {
        // Prefer opening multimedia (higher quality)
        $openingShots = $this->resolveFromOpening($multimediaId, $resolveData);
        if (!$openingShots->isEmpty()) {
            return $openingShots;
        }

        // Fallback to body multimedia
        return $this->resolveFromMultimedia($multimediaId, $resolveData);
    }

    /**
     * Resolve shots from opening multimedia.
     */
    private function resolveFromOpening(
        string $multimediaId,
        ResolveDataDTO $resolveData,
    ): MultimediaShotsCollectionDTO {
        $opening = $resolveData->getMultimediaOpening($multimediaId);
        if ($opening === null) {
            return new MultimediaShotsCollectionDTO();
        }

        return $this->shotGenerator->generateLandscapeShotsFromOpening($opening);
    }

    /**
     * Resolve shots from body multimedia.
     */
    private function resolveFromMultimedia(
        string $multimediaId,
        ResolveDataDTO $resolveData,
    ): MultimediaShotsCollectionDTO {
        $multimedia = $resolveData->getMultimedia($multimediaId);
        if ($multimedia === null) {
            return new MultimediaShotsCollectionDTO();
        }

        return $this->shotGenerator->generateLandscapeShots($multimedia);
    }
}
