<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\MultimediaImageSizes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Config\MultimediaImageSizes
 */
final class MultimediaImageSizesTest extends TestCase
{
    public function test_for_aspect_ratio_returns_sizes(): void
    {
        $sizes = MultimediaImageSizes::forAspectRatio(MultimediaImageSizes::ASPECT_RATIO_16_9);

        self::assertIsArray($sizes);
        self::assertArrayHasKey('1440w', $sizes);
        self::assertSame('1440', $sizes['1440w']['width']);
        self::assertSame('810', $sizes['1440w']['height']);
    }

    public function test_for_aspect_ratio_returns_null_for_unknown(): void
    {
        $sizes = MultimediaImageSizes::forAspectRatio('unknown');

        self::assertNull($sizes);
    }

    public function test_aspect_ratios_returns_all_keys(): void
    {
        $ratios = MultimediaImageSizes::aspectRatios();

        self::assertContains(MultimediaImageSizes::ASPECT_RATIO_16_9, $ratios);
        self::assertContains(MultimediaImageSizes::ASPECT_RATIO_4_3, $ratios);
        self::assertContains(MultimediaImageSizes::ASPECT_RATIO_3_4, $ratios);
        self::assertContains(MultimediaImageSizes::ASPECT_RATIO_3_2, $ratios);
        self::assertContains(MultimediaImageSizes::ASPECT_RATIO_2_3, $ratios);
    }

    public function test_for_body_tag_aspect_ratio_returns_sizes(): void
    {
        $sizes = MultimediaImageSizes::forBodyTagAspectRatio(MultimediaImageSizes::ASPECT_RATIO_16_9);

        self::assertIsArray($sizes);
        self::assertArrayHasKey('1440w', $sizes);
        self::assertSame('1440', $sizes['1440w']['width']);
        self::assertSame('810', $sizes['1440w']['height']);
    }

    public function test_for_body_tag_aspect_ratio_includes_1_1(): void
    {
        $sizes = MultimediaImageSizes::forBodyTagAspectRatio(MultimediaImageSizes::ASPECT_RATIO_1_1);

        self::assertIsArray($sizes);
        self::assertArrayHasKey('1440w', $sizes);
        self::assertSame('1440', $sizes['1440w']['width']);
        self::assertSame('1440', $sizes['1440w']['height']);
    }

    public function test_for_body_tag_aspect_ratio_returns_null_for_unknown(): void
    {
        $sizes = MultimediaImageSizes::forBodyTagAspectRatio('unknown');

        self::assertNull($sizes);
    }

    public function test_sizes_relations_has_all_aspect_ratios(): void
    {
        self::assertArrayHasKey(MultimediaImageSizes::ASPECT_RATIO_16_9, MultimediaImageSizes::SIZES_RELATIONS);
        self::assertArrayHasKey(MultimediaImageSizes::ASPECT_RATIO_4_3, MultimediaImageSizes::SIZES_RELATIONS);
        self::assertArrayHasKey(MultimediaImageSizes::ASPECT_RATIO_3_4, MultimediaImageSizes::SIZES_RELATIONS);
        self::assertArrayHasKey(MultimediaImageSizes::ASPECT_RATIO_3_2, MultimediaImageSizes::SIZES_RELATIONS);
        self::assertArrayHasKey(MultimediaImageSizes::ASPECT_RATIO_2_3, MultimediaImageSizes::SIZES_RELATIONS);
    }

    public function test_body_tag_sizes_relations_includes_1_1(): void
    {
        self::assertArrayHasKey(MultimediaImageSizes::ASPECT_RATIO_1_1, MultimediaImageSizes::BODY_TAG_SIZES_RELATIONS);
    }
}
