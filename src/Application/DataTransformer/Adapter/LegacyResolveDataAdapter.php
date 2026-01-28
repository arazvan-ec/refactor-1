<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Adapter;

use App\Application\DataTransformer\DTO\InsertedEditorialDTO;
use App\Application\DataTransformer\DTO\MultimediaOpeningDTO;
use App\Application\DataTransformer\DTO\ResolveDataDTO;

/**
 * Converts legacy array format to ResolveDataDTO and vice versa.
 *
 * Use during migration period to maintain backward compatibility.
 * This adapter allows gradual migration of transformers to typed DTOs
 * without breaking existing code.
 *
 * Migration strategy:
 * 1. Update ResponseAggregator to create ResolveDataDTO
 * 2. Update BodyDataTransformer to pass ResolveDataDTO
 * 3. Update individual transformers to accept ResolveDataDTO
 * 4. Remove this adapter once migration is complete
 *
 * @deprecated Will be removed once all callers use ResolveDataDTO directly
 */
final class LegacyResolveDataAdapter
{
    /**
     * Convert legacy array format to ResolveDataDTO.
     *
     * Expected legacy array structure:
     * [
     *     'insertedNews' => [editorialId => ['editorial' => ..., 'section' => ..., ...]],
     *     'multimedia' => [multimediaId => Multimedia],
     *     'multimediaOpening' => [multimediaId => ['opening' => ..., 'resource' => ...]],
     *     'photoFromBodyTags' => [photoId => Photo],
     *     'membershipLinkCombine' => [oldUrl => newUrl],
     *     'recommendedNews' => [...],
     * ]
     *
     * @param array<string, mixed> $legacyResolveData
     */
    public static function fromArray(array $legacyResolveData): ResolveDataDTO
    {
        $insertedNews = [];
        foreach ($legacyResolveData['insertedNews'] ?? [] as $id => $data) {
            if (is_array($data)) {
                $insertedNews[$id] = InsertedEditorialDTO::fromArray($data);
            }
        }

        $multimediaOpening = [];
        foreach ($legacyResolveData['multimediaOpening'] ?? [] as $id => $data) {
            if (is_array($data) && isset($data['opening'], $data['resource'])) {
                $multimediaOpening[$id] = MultimediaOpeningDTO::fromArray($data);
            }
        }

        return new ResolveDataDTO(
            insertedNews: $insertedNews,
            multimedia: $legacyResolveData['multimedia'] ?? [],
            multimediaOpening: $multimediaOpening,
            photoBodyTags: $legacyResolveData['photoFromBodyTags'] ?? [],
            membershipLinks: $legacyResolveData['membershipLinkCombine'] ?? [],
            recommendedNews: $legacyResolveData['recommendedNews'] ?? [],
        );
    }

    /**
     * Convert ResolveDataDTO back to legacy array format.
     *
     * Useful when calling legacy code that expects arrays.
     *
     * @return array<string, mixed>
     */
    public static function toArray(ResolveDataDTO $dto): array
    {
        $insertedNews = [];
        foreach ($dto->insertedNews as $id => $insertedDto) {
            $insertedNews[$id] = $insertedDto->toArray();
        }

        $multimediaOpening = [];
        foreach ($dto->multimediaOpening as $id => $openingDto) {
            $multimediaOpening[$id] = $openingDto->toArray();
        }

        return [
            'insertedNews' => $insertedNews,
            'multimedia' => $dto->multimedia,
            'multimediaOpening' => $multimediaOpening,
            'photoFromBodyTags' => $dto->photoBodyTags,
            'membershipLinkCombine' => $dto->membershipLinks,
            'recommendedNews' => $dto->recommendedNews,
        ];
    }

    /**
     * Check if the given data is already a ResolveDataDTO.
     *
     * @param array<string, mixed>|ResolveDataDTO $data
     */
    public static function isDTO(array|ResolveDataDTO $data): bool
    {
        return $data instanceof ResolveDataDTO;
    }

    /**
     * Ensure data is a ResolveDataDTO.
     *
     * If already a DTO, returns as-is. If array, converts.
     *
     * @param array<string, mixed>|ResolveDataDTO $data
     */
    public static function ensureDTO(array|ResolveDataDTO $data): ResolveDataDTO
    {
        if ($data instanceof ResolveDataDTO) {
            return $data;
        }

        return self::fromArray($data);
    }

    /**
     * Ensure data is a legacy array.
     *
     * If already an array, returns as-is. If DTO, converts.
     *
     * @param array<string, mixed>|ResolveDataDTO $data
     *
     * @return array<string, mixed>
     */
    public static function ensureArray(array|ResolveDataDTO $data): array
    {
        if ($data instanceof ResolveDataDTO) {
            return self::toArray($data);
        }

        return $data;
    }
}
