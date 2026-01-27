<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\DTO;

use App\Application\DataTransformer\DTO\MultimediaShotDTO;
use App\Application\DataTransformer\DTO\MultimediaShotsCollectionDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultimediaShotsCollectionDTO::class)]
final class MultimediaShotsCollectionDTOTest extends TestCase
{
    #[Test]
    public function constructsWithEmptyArrayByDefault(): void
    {
        $collection = new MultimediaShotsCollectionDTO();

        static::assertTrue($collection->isEmpty());
        static::assertSame(0, $collection->count());
    }

    #[Test]
    public function constructsWithShots(): void
    {
        $shot1 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot2 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2]);

        static::assertFalse($collection->isEmpty());
        static::assertSame(2, $collection->count());
    }

    #[Test]
    public function allReturnsAllShots(): void
    {
        $shot1 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot2 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2]);

        static::assertSame([$shot1, $shot2], $collection->all());
    }

    #[Test]
    public function getBySizeReturnsCorrectShot(): void
    {
        $shot1 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot2 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2]);

        static::assertSame($shot1, $collection->getBySize('1440w'));
        static::assertSame($shot2, $collection->getBySize('996w'));
        static::assertNull($collection->getBySize('nonexistent'));
    }

    #[Test]
    public function hasSizeReturnsTrueWhenExists(): void
    {
        $shot = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);

        $collection = new MultimediaShotsCollectionDTO([$shot]);

        static::assertTrue($collection->hasSize('1440w'));
        static::assertFalse($collection->hasSize('996w'));
    }

    #[Test]
    public function toArrayReturnsArrayOfArrays(): void
    {
        $shot1 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot2 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2]);

        $result = $collection->toArray();

        static::assertSame([
            ['size' => '1440w', 'url' => 'https://example.com/1.webp', 'width' => 1440, 'height' => 1080],
            ['size' => '996w', 'url' => 'https://example.com/2.webp', 'width' => 996, 'height' => 747],
        ], $result);
    }

    #[Test]
    public function toLegacyFormatReturnsSizeUrlMap(): void
    {
        $shot1 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot2 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2]);

        $result = $collection->toLegacyFormat();

        static::assertSame([
            '1440w' => 'https://example.com/1.webp',
            '996w' => 'https://example.com/2.webp',
        ], $result);
    }

    #[Test]
    public function fromLegacyFormatCreatesCollectionWithDimensions(): void
    {
        $legacyShots = [
            '1440w' => 'https://example.com/1.webp',
            '996w' => 'https://example.com/2.webp',
        ];

        $sizeDimensions = [
            '1440w' => ['width' => '1440', 'height' => '1080'],
            '996w' => ['width' => '996', 'height' => '747'],
        ];

        $collection = MultimediaShotsCollectionDTO::fromLegacyFormat($legacyShots, $sizeDimensions);

        static::assertSame(2, $collection->count());

        $shot1 = $collection->getBySize('1440w');
        static::assertNotNull($shot1);
        static::assertSame(1440, $shot1->width);
        static::assertSame(1080, $shot1->height);

        $shot2 = $collection->getBySize('996w');
        static::assertNotNull($shot2);
        static::assertSame(996, $shot2->width);
        static::assertSame(747, $shot2->height);
    }

    #[Test]
    public function filterByMinWidthReturnsFilteredCollection(): void
    {
        $shot1 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot2 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);
        $shot3 = new MultimediaShotDTO('375w', 'https://example.com/3.webp', 375, 281);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2, $shot3]);

        $filtered = $collection->filterByMinWidth(500);

        static::assertSame(2, $filtered->count());
        static::assertTrue($filtered->hasSize('1440w'));
        static::assertTrue($filtered->hasSize('996w'));
        static::assertFalse($filtered->hasSize('375w'));
    }

    #[Test]
    public function getLargestReturnsWidestShot(): void
    {
        $shot1 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);
        $shot2 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot3 = new MultimediaShotDTO('375w', 'https://example.com/3.webp', 375, 281);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2, $shot3]);

        $largest = $collection->getLargest();

        static::assertNotNull($largest);
        static::assertSame('1440w', $largest->size);
        static::assertSame(1440, $largest->width);
    }

    #[Test]
    public function getLargestReturnsNullForEmptyCollection(): void
    {
        $collection = new MultimediaShotsCollectionDTO();

        static::assertNull($collection->getLargest());
    }

    #[Test]
    public function getSmallestReturnsNarrowestShot(): void
    {
        $shot1 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);
        $shot2 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot3 = new MultimediaShotDTO('375w', 'https://example.com/3.webp', 375, 281);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2, $shot3]);

        $smallest = $collection->getSmallest();

        static::assertNotNull($smallest);
        static::assertSame('375w', $smallest->size);
        static::assertSame(375, $smallest->width);
    }

    #[Test]
    public function getSmallestReturnsNullForEmptyCollection(): void
    {
        $collection = new MultimediaShotsCollectionDTO();

        static::assertNull($collection->getSmallest());
    }

    #[Test]
    public function isIterableWithForeach(): void
    {
        $shot1 = new MultimediaShotDTO('1440w', 'https://example.com/1.webp', 1440, 1080);
        $shot2 = new MultimediaShotDTO('996w', 'https://example.com/2.webp', 996, 747);

        $collection = new MultimediaShotsCollectionDTO([$shot1, $shot2]);

        $sizes = [];
        foreach ($collection as $shot) {
            $sizes[] = $shot->size;
        }

        static::assertSame(['1440w', '996w'], $sizes);
    }
}
