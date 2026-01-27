<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\Adapter;

use App\Application\DataTransformer\Adapter\LegacyResolveDataAdapter;
use App\Application\DataTransformer\DTO\InsertedEditorialDTO;
use App\Application\DataTransformer\DTO\MultimediaOpeningDTO;
use App\Application\DataTransformer\DTO\ResolveDataDTO;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Multimedia\Domain\Model\Multimedia as MultimediaModel;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Domain\Model\Photo\Photo;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyResolveDataAdapter::class)]
final class LegacyResolveDataAdapterTest extends TestCase
{
    #[Test]
    public function fromArrayConvertsLegacyFormat(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $multimedia = $this->createMock(MultimediaModel::class);
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);
        $photo = $this->createMock(Photo::class);

        $legacyData = [
            'insertedNews' => [
                'editorial-123' => [
                    'editorial' => $editorial,
                    'section' => $section,
                    'signatures' => [['name' => 'Author']],
                    'multimediaId' => 'media-123',
                ],
            ],
            'multimedia' => [
                'media-123' => $multimedia,
            ],
            'multimediaOpening' => [
                'media-123' => [
                    'opening' => $opening,
                    'resource' => $resource,
                ],
            ],
            'photoFromBodyTags' => [
                'photo-123' => $photo,
            ],
            'membershipLinkCombine' => [
                'https://old.url' => 'https://new.url',
            ],
            'recommendedNews' => ['rec1', 'rec2'],
        ];

        $dto = LegacyResolveDataAdapter::fromArray($legacyData);

        static::assertInstanceOf(ResolveDataDTO::class, $dto);
        static::assertTrue($dto->hasInsertedNews('editorial-123'));
        static::assertSame($editorial, $dto->getInsertedNews('editorial-123')->editorial);
        static::assertTrue($dto->hasMultimedia('media-123'));
        static::assertTrue($dto->hasMultimediaOpening('media-123'));
        static::assertTrue($dto->hasPhotoForBodyTag('photo-123'));
        static::assertTrue($dto->hasMembershipLink('https://old.url'));
        static::assertSame(['rec1', 'rec2'], $dto->recommendedNews);
    }

    #[Test]
    public function fromArrayHandlesEmptyData(): void
    {
        $dto = LegacyResolveDataAdapter::fromArray([]);

        static::assertInstanceOf(ResolveDataDTO::class, $dto);
        static::assertSame([], $dto->insertedNews);
        static::assertSame([], $dto->multimedia);
        static::assertSame([], $dto->multimediaOpening);
        static::assertSame([], $dto->photoBodyTags);
        static::assertSame([], $dto->membershipLinks);
        static::assertSame([], $dto->recommendedNews);
    }

    #[Test]
    public function toArrayConvertsDTO(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $multimedia = $this->createMock(MultimediaModel::class);
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);
        $photo = $this->createMock(Photo::class);

        $insertedEditorial = new InsertedEditorialDTO(
            editorial: $editorial,
            section: $section,
            signatures: [['name' => 'Author']],
            multimediaId: 'media-123',
        );

        $multimediaOpening = new MultimediaOpeningDTO($opening, $resource);

        $dto = new ResolveDataDTO(
            insertedNews: ['editorial-123' => $insertedEditorial],
            multimedia: ['media-123' => $multimedia],
            multimediaOpening: ['media-123' => $multimediaOpening],
            photoBodyTags: ['photo-123' => $photo],
            membershipLinks: ['https://old.url' => 'https://new.url'],
            recommendedNews: ['rec1'],
        );

        $array = LegacyResolveDataAdapter::toArray($dto);

        static::assertArrayHasKey('insertedNews', $array);
        static::assertArrayHasKey('multimedia', $array);
        static::assertArrayHasKey('multimediaOpening', $array);
        static::assertArrayHasKey('photoFromBodyTags', $array);
        static::assertArrayHasKey('membershipLinkCombine', $array);
        static::assertArrayHasKey('recommendedNews', $array);

        static::assertSame($editorial, $array['insertedNews']['editorial-123']['editorial']);
        static::assertSame($multimedia, $array['multimedia']['media-123']);
        static::assertSame($opening, $array['multimediaOpening']['media-123']['opening']);
    }

    #[Test]
    public function isDTOReturnsTrueForDTO(): void
    {
        $dto = new ResolveDataDTO();

        static::assertTrue(LegacyResolveDataAdapter::isDTO($dto));
    }

    #[Test]
    public function isDTOReturnsFalseForArray(): void
    {
        $array = ['insertedNews' => []];

        static::assertFalse(LegacyResolveDataAdapter::isDTO($array));
    }

    #[Test]
    public function ensureDTOReturnsDTOWhenAlreadyDTO(): void
    {
        $dto = new ResolveDataDTO();

        $result = LegacyResolveDataAdapter::ensureDTO($dto);

        static::assertSame($dto, $result);
    }

    #[Test]
    public function ensureDTOConvertArrayToDTO(): void
    {
        $array = ['recommendedNews' => ['rec1']];

        $result = LegacyResolveDataAdapter::ensureDTO($array);

        static::assertInstanceOf(ResolveDataDTO::class, $result);
        static::assertSame(['rec1'], $result->recommendedNews);
    }

    #[Test]
    public function ensureArrayReturnsArrayWhenAlreadyArray(): void
    {
        $array = ['insertedNews' => []];

        $result = LegacyResolveDataAdapter::ensureArray($array);

        static::assertSame($array, $result);
    }

    #[Test]
    public function ensureArrayConvertsDTOToArray(): void
    {
        $dto = new ResolveDataDTO(
            recommendedNews: ['rec1'],
        );

        $result = LegacyResolveDataAdapter::ensureArray($dto);

        static::assertIsArray($result);
        static::assertArrayHasKey('recommendedNews', $result);
        static::assertSame(['rec1'], $result['recommendedNews']);
    }

    #[Test]
    public function roundTripPreservesData(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);

        $originalArray = [
            'insertedNews' => [
                'editorial-123' => [
                    'editorial' => $editorial,
                    'section' => $section,
                    'signatures' => [],
                    'multimediaId' => null,
                ],
            ],
            'multimedia' => [],
            'multimediaOpening' => [],
            'photoFromBodyTags' => [],
            'membershipLinkCombine' => [],
            'recommendedNews' => [],
        ];

        $dto = LegacyResolveDataAdapter::fromArray($originalArray);
        $resultArray = LegacyResolveDataAdapter::toArray($dto);

        static::assertSame(
            $originalArray['insertedNews']['editorial-123']['editorial'],
            $resultArray['insertedNews']['editorial-123']['editorial'],
        );
    }
}
