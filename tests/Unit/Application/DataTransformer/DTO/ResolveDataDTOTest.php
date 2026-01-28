<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\DTO;

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

#[CoversClass(ResolveDataDTO::class)]
final class ResolveDataDTOTest extends TestCase
{
    #[Test]
    public function constructsWithDefaults(): void
    {
        $dto = new ResolveDataDTO();

        static::assertSame([], $dto->insertedNews);
        static::assertSame([], $dto->multimedia);
        static::assertSame([], $dto->multimediaOpening);
        static::assertSame([], $dto->photoBodyTags);
        static::assertSame([], $dto->membershipLinks);
        static::assertSame([], $dto->recommendedNews);
    }

    #[Test]
    public function hasInsertedNewsReturnsTrueWhenExists(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $insertedEditorial = new InsertedEditorialDTO($editorial, $section);

        $dto = new ResolveDataDTO(
            insertedNews: ['editorial-123' => $insertedEditorial],
        );

        static::assertTrue($dto->hasInsertedNews('editorial-123'));
        static::assertFalse($dto->hasInsertedNews('editorial-456'));
    }

    #[Test]
    public function getInsertedNewsReturnsCorrectDTO(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $insertedEditorial = new InsertedEditorialDTO($editorial, $section);

        $dto = new ResolveDataDTO(
            insertedNews: ['editorial-123' => $insertedEditorial],
        );

        static::assertSame($insertedEditorial, $dto->getInsertedNews('editorial-123'));
        static::assertNull($dto->getInsertedNews('editorial-456'));
    }

    #[Test]
    public function hasMultimediaReturnsTrueWhenExists(): void
    {
        $multimedia = $this->createMock(MultimediaModel::class);

        $dto = new ResolveDataDTO(
            multimedia: ['media-123' => $multimedia],
        );

        static::assertTrue($dto->hasMultimedia('media-123'));
        static::assertFalse($dto->hasMultimedia('media-456'));
    }

    #[Test]
    public function getMultimediaReturnsCorrectObject(): void
    {
        $multimedia = $this->createMock(MultimediaModel::class);

        $dto = new ResolveDataDTO(
            multimedia: ['media-123' => $multimedia],
        );

        static::assertSame($multimedia, $dto->getMultimedia('media-123'));
        static::assertNull($dto->getMultimedia('media-456'));
    }

    #[Test]
    public function hasMultimediaOpeningReturnsTrueWhenExists(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);
        $multimediaOpening = new MultimediaOpeningDTO($opening, $resource);

        $dto = new ResolveDataDTO(
            multimediaOpening: ['media-123' => $multimediaOpening],
        );

        static::assertTrue($dto->hasMultimediaOpening('media-123'));
        static::assertFalse($dto->hasMultimediaOpening('media-456'));
    }

    #[Test]
    public function getMultimediaOpeningReturnsCorrectDTO(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);
        $multimediaOpening = new MultimediaOpeningDTO($opening, $resource);

        $dto = new ResolveDataDTO(
            multimediaOpening: ['media-123' => $multimediaOpening],
        );

        static::assertSame($multimediaOpening, $dto->getMultimediaOpening('media-123'));
        static::assertNull($dto->getMultimediaOpening('media-456'));
    }

    #[Test]
    public function hasPhotoForBodyTagReturnsTrueWhenExists(): void
    {
        $photo = $this->createMock(Photo::class);

        $dto = new ResolveDataDTO(
            photoBodyTags: ['photo-123' => $photo],
        );

        static::assertTrue($dto->hasPhotoForBodyTag('photo-123'));
        static::assertFalse($dto->hasPhotoForBodyTag('photo-456'));
    }

    #[Test]
    public function getPhotoForBodyTagReturnsCorrectObject(): void
    {
        $photo = $this->createMock(Photo::class);

        $dto = new ResolveDataDTO(
            photoBodyTags: ['photo-123' => $photo],
        );

        static::assertSame($photo, $dto->getPhotoForBodyTag('photo-123'));
        static::assertNull($dto->getPhotoForBodyTag('photo-456'));
    }

    #[Test]
    public function hasMembershipLinkReturnsTrueWhenExists(): void
    {
        $dto = new ResolveDataDTO(
            membershipLinks: ['https://old.url' => 'https://new.url'],
        );

        static::assertTrue($dto->hasMembershipLink('https://old.url'));
        static::assertFalse($dto->hasMembershipLink('https://other.url'));
    }

    #[Test]
    public function getMembershipLinkReturnsCorrectUrl(): void
    {
        $dto = new ResolveDataDTO(
            membershipLinks: ['https://old.url' => 'https://new.url'],
        );

        static::assertSame('https://new.url', $dto->getMembershipLink('https://old.url'));
        static::assertNull($dto->getMembershipLink('https://other.url'));
    }

    #[Test]
    public function fromLegacyArrayCreatesCorrectDTO(): void
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

        $dto = ResolveDataDTO::fromLegacyArray($legacyData);

        static::assertTrue($dto->hasInsertedNews('editorial-123'));
        static::assertSame($editorial, $dto->getInsertedNews('editorial-123')->editorial);
        static::assertTrue($dto->hasMultimedia('media-123'));
        static::assertTrue($dto->hasMultimediaOpening('media-123'));
        static::assertTrue($dto->hasPhotoForBodyTag('photo-123'));
        static::assertTrue($dto->hasMembershipLink('https://old.url'));
        static::assertSame(['rec1', 'rec2'], $dto->recommendedNews);
    }

    #[Test]
    public function toLegacyArrayConvertsCorrectly(): void
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

        $legacyArray = $dto->toLegacyArray();

        static::assertArrayHasKey('insertedNews', $legacyArray);
        static::assertArrayHasKey('multimedia', $legacyArray);
        static::assertArrayHasKey('multimediaOpening', $legacyArray);
        static::assertArrayHasKey('photoFromBodyTags', $legacyArray);
        static::assertArrayHasKey('membershipLinkCombine', $legacyArray);
        static::assertArrayHasKey('recommendedNews', $legacyArray);

        static::assertSame($editorial, $legacyArray['insertedNews']['editorial-123']['editorial']);
        static::assertSame($multimedia, $legacyArray['multimedia']['media-123']);
    }
}
