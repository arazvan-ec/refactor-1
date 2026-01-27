<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\DTO;

use App\Application\DataTransformer\DTO\MultimediaShotDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultimediaShotDTO::class)]
final class MultimediaShotDTOTest extends TestCase
{
    #[Test]
    public function constructsWithCorrectProperties(): void
    {
        $dto = new MultimediaShotDTO(
            size: '1440w',
            url: 'https://thumbor.example.com/image.webp',
            width: 1440,
            height: 1080,
        );

        static::assertSame('1440w', $dto->size);
        static::assertSame('https://thumbor.example.com/image.webp', $dto->url);
        static::assertSame(1440, $dto->width);
        static::assertSame(1080, $dto->height);
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        $dto = new MultimediaShotDTO(
            size: '996w',
            url: 'https://thumbor.example.com/image.webp',
            width: 996,
            height: 747,
        );

        $result = $dto->toArray();

        static::assertSame([
            'size' => '996w',
            'url' => 'https://thumbor.example.com/image.webp',
            'width' => 996,
            'height' => 747,
        ], $result);
    }

    #[Test]
    #[DataProvider('aspectRatioProvider')]
    public function getAspectRatioReturnsCorrectRatio(
        int $width,
        int $height,
        string $expectedRatio,
    ): void {
        $dto = new MultimediaShotDTO(
            size: 'test',
            url: 'https://example.com/image.webp',
            width: $width,
            height: $height,
        );

        static::assertSame($expectedRatio, $dto->getAspectRatio());
    }

    /**
     * @return iterable<string, array{width: int, height: int, expectedRatio: string}>
     */
    public static function aspectRatioProvider(): iterable
    {
        yield '4:3 aspect ratio' => [
            'width' => 1440,
            'height' => 1080,
            'expectedRatio' => '4:3',
        ];

        yield '16:9 aspect ratio' => [
            'width' => 1920,
            'height' => 1080,
            'expectedRatio' => '16:9',
        ];

        yield '3:4 aspect ratio (portrait)' => [
            'width' => 1080,
            'height' => 1440,
            'expectedRatio' => '3:4',
        ];

        yield '1:1 aspect ratio (square)' => [
            'width' => 500,
            'height' => 500,
            'expectedRatio' => '1:1',
        ];

        yield '3:2 aspect ratio' => [
            'width' => 1200,
            'height' => 800,
            'expectedRatio' => '3:2',
        ];
    }
}
