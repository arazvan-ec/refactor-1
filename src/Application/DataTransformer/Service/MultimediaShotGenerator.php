<?php

declare(strict_types=1);

namespace App\Application\DataTransformer\Service;

use App\Application\DataTransformer\DTO\MultimediaShotDTO;
use App\Application\DataTransformer\DTO\MultimediaShotsCollectionDTO;
use App\Application\DataTransformer\DTO\MultimediaOpeningDTO;
use App\Infrastructure\Service\Thumbor;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Multimedia\Domain\Model\Multimedia as MultimediaModel;

/**
 * Generates Thumbor shots for multimedia items.
 *
 * Replaces MultimediaTrait with explicit dependency injection.
 * Centralizes shot generation logic previously duplicated across transformers.
 *
 * @see MultimediaTrait (deprecated, use this service instead)
 */
final readonly class MultimediaShotGenerator
{
    /**
     * Default landscape sizes for inserted news and recommendations.
     *
     * @var array<string, array{width: string, height: string}>
     */
    private const LANDSCAPE_SIZES = [
        '202w' => ['width' => '202', 'height' => '152'],
        '144w' => ['width' => '144', 'height' => '108'],
        '128w' => ['width' => '128', 'height' => '96'],
    ];

    public function __construct(
        private Thumbor $thumbor,
        private string $extension = 'webp',
    ) {}

    /**
     * Generate landscape shots from multimedia model.
     *
     * Uses 4:3 aspect ratio clipping for consistent thumbnails.
     */
    public function generateLandscapeShots(MultimediaModel $multimedia): MultimediaShotsCollectionDTO
    {
        $clippings = $multimedia->clippings();
        $clipping = $clippings->clippingByType(ClippingTypes::SIZE_ARTICLE_4_3);

        $shots = [];
        foreach (self::LANDSCAPE_SIZES as $size => $dimensions) {
            $url = $this->thumbor->retriveCropBodyTagPicture(
                $multimedia->file(),
                $dimensions['width'],
                $dimensions['height'],
                $clipping->topLeftX(),
                $clipping->topLeftY(),
                $clipping->bottomRightX(),
                $clipping->bottomRightY(),
            );

            $shots[] = new MultimediaShotDTO(
                size: $size,
                url: $url,
                width: (int) $dimensions['width'],
                height: (int) $dimensions['height'],
            );
        }

        return new MultimediaShotsCollectionDTO($shots);
    }

    /**
     * Generate landscape shots from opening multimedia data.
     *
     * Uses the photo resource from the opening multimedia DTO.
     */
    public function generateLandscapeShotsFromOpening(MultimediaOpeningDTO $openingData): MultimediaShotsCollectionDTO
    {
        $clippings = $openingData->opening->clippings();
        $clipping = $clippings->clippingByType(ClippingTypes::SIZE_ARTICLE_4_3);

        $shots = [];
        foreach (self::LANDSCAPE_SIZES as $size => $dimensions) {
            $url = $this->thumbor->retriveCropBodyTagPicture(
                $openingData->resource->file(),
                $dimensions['width'],
                $dimensions['height'],
                $clipping->topLeftX(),
                $clipping->topLeftY(),
                $clipping->bottomRightX(),
                $clipping->bottomRightY(),
            );

            $shots[] = new MultimediaShotDTO(
                size: $size,
                url: $url,
                width: (int) $dimensions['width'],
                height: (int) $dimensions['height'],
            );
        }

        return new MultimediaShotsCollectionDTO($shots);
    }

    /**
     * Generate shots with custom sizes.
     *
     * @param array<string, array{width: string, height: string}> $sizes
     */
    public function generateShotsWithSizes(
        MultimediaModel $multimedia,
        array $sizes,
        string $clippingType = ClippingTypes::SIZE_ARTICLE_4_3,
    ): MultimediaShotsCollectionDTO {
        $clippings = $multimedia->clippings();
        $clipping = $clippings->clippingByType($clippingType);

        $shots = [];
        foreach ($sizes as $size => $dimensions) {
            $url = $this->thumbor->retriveCropBodyTagPicture(
                $multimedia->file(),
                $dimensions['width'],
                $dimensions['height'],
                $clipping->topLeftX(),
                $clipping->topLeftY(),
                $clipping->bottomRightX(),
                $clipping->bottomRightY(),
            );

            $shots[] = new MultimediaShotDTO(
                size: $size,
                url: $url,
                width: (int) $dimensions['width'],
                height: (int) $dimensions['height'],
            );
        }

        return new MultimediaShotsCollectionDTO($shots);
    }

    /**
     * Get available landscape sizes.
     *
     * @return array<string, array{width: string, height: string}>
     */
    public function getLandscapeSizes(): array
    {
        return self::LANDSCAPE_SIZES;
    }
}
