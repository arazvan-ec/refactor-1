<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

/**
 * Centralized configuration for multimedia image sizes.
 *
 * Consolidates SIZES_RELATIONS constants previously duplicated across:
 * - DetailsMultimediaPhotoDataTransformer
 * - DetailsMultimediaDataTransformer
 * - PictureShots
 */
final class MultimediaImageSizes
{
    public const WIDTH = 'width';
    public const HEIGHT = 'height';

    public const ASPECT_RATIO_16_9 = '16:9';
    public const ASPECT_RATIO_4_3 = '4:3';
    public const ASPECT_RATIO_3_4 = '3:4';
    public const ASPECT_RATIO_3_2 = '3:2';
    public const ASPECT_RATIO_2_3 = '2:3';
    public const ASPECT_RATIO_1_1 = '1:1';

    /**
     * Size relations for opening multimedia (used by media data transformers).
     *
     * @var array<string, array<string, array{width: string, height: string}>>
     */
    public const SIZES_RELATIONS = [
        self::ASPECT_RATIO_4_3 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1080'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '900'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '747'],
            '557w' => [self::WIDTH => '557', self::HEIGHT => '418'],
            '381w' => [self::WIDTH => '381', self::HEIGHT => '286'],
            '600w' => [self::WIDTH => '600', self::HEIGHT => '450'],
            '414w' => [self::WIDTH => '414', self::HEIGHT => '311'],
            '375w' => [self::WIDTH => '375', self::HEIGHT => '281'],
            '360w' => [self::WIDTH => '360', self::HEIGHT => '270'],
            '767w' => [self::WIDTH => '767', self::HEIGHT => '575'],
        ],
        self::ASPECT_RATIO_16_9 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '810'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '675'],
            '972w' => [self::WIDTH => '972', self::HEIGHT => '547'],
            '720w' => [self::WIDTH => '720', self::HEIGHT => '405'],
            '600w' => [self::WIDTH => '600', self::HEIGHT => '338'],
            '414w' => [self::WIDTH => '414', self::HEIGHT => '233'],
            '375w' => [self::WIDTH => '375', self::HEIGHT => '211'],
            '360w' => [self::WIDTH => '360', self::HEIGHT => '203'],
        ],
        self::ASPECT_RATIO_3_4 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1920'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1600'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '1328'],
            '391w' => [self::WIDTH => '391', self::HEIGHT => '521'],
            '300w' => [self::WIDTH => '300', self::HEIGHT => '400'],
            '600w' => [self::WIDTH => '600', self::HEIGHT => '800'],
            '414w' => [self::WIDTH => '414', self::HEIGHT => '552'],
            '375w' => [self::WIDTH => '375', self::HEIGHT => '500'],
            '360w' => [self::WIDTH => '360', self::HEIGHT => '480'],
        ],
        self::ASPECT_RATIO_3_2 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '960'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '800'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '664'],
            '557w' => [self::WIDTH => '557', self::HEIGHT => '371'],
            '381w' => [self::WIDTH => '381', self::HEIGHT => '254'],
            '600w' => [self::WIDTH => '600', self::HEIGHT => '400'],
            '414w' => [self::WIDTH => '414', self::HEIGHT => '276'],
            '375w' => [self::WIDTH => '375', self::HEIGHT => '250'],
            '360w' => [self::WIDTH => '360', self::HEIGHT => '240'],
            '767w' => [self::WIDTH => '767', self::HEIGHT => '511'],
            'lo-res' => [self::WIDTH => '48', self::HEIGHT => '32'],
        ],
        self::ASPECT_RATIO_2_3 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '2160'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1800'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '1494'],
            '557w' => [self::WIDTH => '557', self::HEIGHT => '835'],
            '381w' => [self::WIDTH => '381', self::HEIGHT => '571'],
            '600w' => [self::WIDTH => '600', self::HEIGHT => '900'],
            '414w' => [self::WIDTH => '414', self::HEIGHT => '621'],
            '375w' => [self::WIDTH => '375', self::HEIGHT => '562'],
            '360w' => [self::WIDTH => '360', self::HEIGHT => '540'],
            '767w' => [self::WIDTH => '767', self::HEIGHT => '1150'],
            'lo-res' => [self::WIDTH => '48', self::HEIGHT => '72'],
        ],
    ];

    /**
     * Size relations for body tag pictures (used by PictureShots).
     *
     * @var array<string, array<string, array{width: string, height: string}>>
     */
    public const BODY_TAG_SIZES_RELATIONS = [
        self::ASPECT_RATIO_16_9 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '810'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '675'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '560'],
            '640w' => [self::WIDTH => '640', self::HEIGHT => '360'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '219'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '320'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '215'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '185'],
        ],
        self::ASPECT_RATIO_3_4 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1920'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1600'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '1328'],
            '560w' => [self::WIDTH => '560', self::HEIGHT => '747'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '520'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '757'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '509'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '437'],
        ],
        self::ASPECT_RATIO_1_1 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1440'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1200'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '996'],
            '560w' => [self::WIDTH => '560', self::HEIGHT => '560'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '390'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '568'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '382'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '328'],
        ],
        self::ASPECT_RATIO_4_3 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '1080'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '900'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '747'],
            '560w' => [self::WIDTH => '560', self::HEIGHT => '420'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '292'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '426'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '286'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '246'],
        ],
        self::ASPECT_RATIO_3_2 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '960'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '800'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '664'],
            '640w' => [self::WIDTH => '640', self::HEIGHT => '427'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '260'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '379'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '254'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '219'],
        ],
        self::ASPECT_RATIO_2_3 => [
            '1440w' => [self::WIDTH => '1440', self::HEIGHT => '2160'],
            '1200w' => [self::WIDTH => '1200', self::HEIGHT => '1800'],
            '996w' => [self::WIDTH => '996', self::HEIGHT => '1494'],
            '560w' => [self::WIDTH => '560', self::HEIGHT => '840'],
            '390w' => [self::WIDTH => '390', self::HEIGHT => '585'],
            '568w' => [self::WIDTH => '568', self::HEIGHT => '852'],
            '382w' => [self::WIDTH => '382', self::HEIGHT => '573'],
            '328w' => [self::WIDTH => '328', self::HEIGHT => '492'],
        ],
    ];

    /**
     * Get sizes for a specific aspect ratio (opening multimedia).
     *
     * @return array<string, array{width: string, height: string}>|null
     */
    public static function forAspectRatio(string $aspectRatio): ?array
    {
        return self::SIZES_RELATIONS[$aspectRatio] ?? null;
    }

    /**
     * Get sizes for a specific aspect ratio (body tag pictures).
     *
     * @return array<string, array{width: string, height: string}>|null
     */
    public static function forBodyTagAspectRatio(string $aspectRatio): ?array
    {
        return self::BODY_TAG_SIZES_RELATIONS[$aspectRatio] ?? null;
    }

    /**
     * Get all available aspect ratios.
     *
     * @return list<string>
     */
    public static function aspectRatios(): array
    {
        return array_keys(self::SIZES_RELATIONS);
    }
}
