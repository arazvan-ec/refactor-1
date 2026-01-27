<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\Service;

use App\Application\DataTransformer\DTO\MultimediaOpeningDTO;
use App\Application\DataTransformer\Service\MultimediaShotGenerator;
use App\Infrastructure\Service\Thumbor;
use Ec\Multimedia\Domain\Model\Clipping;
use Ec\Multimedia\Domain\Model\Clippings;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Multimedia\Domain\Model\Multimedia as MultimediaModel;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Domain\Model\Photo\Photo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultimediaShotGenerator::class)]
final class MultimediaShotGeneratorTest extends TestCase
{
    private MultimediaShotGenerator $generator;
    private Thumbor&MockObject $thumbor;

    protected function setUp(): void
    {
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->generator = new MultimediaShotGenerator($this->thumbor);
    }

    #[Test]
    public function generateLandscapeShotsCreatesThreeSizes(): void
    {
        $multimedia = $this->createMultimediaMock();

        $this->thumbor
            ->expects(static::exactly(3))
            ->method('retriveCropBodyTagPicture')
            ->willReturnOnConsecutiveCalls(
                'https://thumbor.example.com/202w.webp',
                'https://thumbor.example.com/144w.webp',
                'https://thumbor.example.com/128w.webp',
            );

        $result = $this->generator->generateLandscapeShots($multimedia);

        static::assertSame(3, $result->count());
        static::assertTrue($result->hasSize('202w'));
        static::assertTrue($result->hasSize('144w'));
        static::assertTrue($result->hasSize('128w'));

        $shot202 = $result->getBySize('202w');
        static::assertNotNull($shot202);
        static::assertSame('https://thumbor.example.com/202w.webp', $shot202->url);
        static::assertSame(202, $shot202->width);
        static::assertSame(152, $shot202->height);
    }

    #[Test]
    public function generateLandscapeShotsFromOpeningUsesPhotoResource(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);
        $clippings = $this->createClippingsMock();

        $opening->method('clippings')->willReturn($clippings);
        $resource->method('file')->willReturn('photo-file.jpg');

        $openingDTO = new MultimediaOpeningDTO($opening, $resource);

        $this->thumbor
            ->expects(static::exactly(3))
            ->method('retriveCropBodyTagPicture')
            ->with(
                'photo-file.jpg',
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
            )
            ->willReturn('https://thumbor.example.com/shot.webp');

        $result = $this->generator->generateLandscapeShotsFromOpening($openingDTO);

        static::assertSame(3, $result->count());
    }

    #[Test]
    public function generateShotsWithSizesUsesCustomSizes(): void
    {
        $multimedia = $this->createMultimediaMock();

        $customSizes = [
            'large' => ['width' => '1000', 'height' => '750'],
            'medium' => ['width' => '500', 'height' => '375'],
        ];

        $this->thumbor
            ->expects(static::exactly(2))
            ->method('retriveCropBodyTagPicture')
            ->willReturnOnConsecutiveCalls(
                'https://thumbor.example.com/large.webp',
                'https://thumbor.example.com/medium.webp',
            );

        $result = $this->generator->generateShotsWithSizes($multimedia, $customSizes);

        static::assertSame(2, $result->count());
        static::assertTrue($result->hasSize('large'));
        static::assertTrue($result->hasSize('medium'));

        $large = $result->getBySize('large');
        static::assertNotNull($large);
        static::assertSame(1000, $large->width);
        static::assertSame(750, $large->height);
    }

    #[Test]
    public function getLandscapeSizesReturnsDefaultSizes(): void
    {
        $sizes = $this->generator->getLandscapeSizes();

        static::assertArrayHasKey('202w', $sizes);
        static::assertArrayHasKey('144w', $sizes);
        static::assertArrayHasKey('128w', $sizes);

        static::assertSame(['width' => '202', 'height' => '152'], $sizes['202w']);
    }

    private function createMultimediaMock(): MultimediaModel&MockObject
    {
        $multimedia = $this->createMock(MultimediaModel::class);
        $clippings = $this->createClippingsMock();

        $multimedia->method('clippings')->willReturn($clippings);
        $multimedia->method('file')->willReturn('test-file.jpg');

        return $multimedia;
    }

    private function createClippingsMock(): Clippings&MockObject
    {
        $clipping = $this->createMock(Clipping::class);
        $clipping->method('topLeftX')->willReturn(0);
        $clipping->method('topLeftY')->willReturn(0);
        $clipping->method('bottomRightX')->willReturn(1000);
        $clipping->method('bottomRightY')->willReturn(750);

        $clippings = $this->createMock(Clippings::class);
        $clippings
            ->method('clippingByType')
            ->with(ClippingTypes::SIZE_ARTICLE_4_3)
            ->willReturn($clipping);

        return $clippings;
    }
}
