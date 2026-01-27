<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\Service;

use App\Application\DataTransformer\DTO\InsertedEditorialDTO;
use App\Application\DataTransformer\DTO\MultimediaOpeningDTO;
use App\Application\DataTransformer\DTO\MultimediaShotDTO;
use App\Application\DataTransformer\DTO\MultimediaShotsCollectionDTO;
use App\Application\DataTransformer\DTO\ResolveDataDTO;
use App\Application\DataTransformer\Service\MultimediaShotGenerator;
use App\Application\DataTransformer\Service\MultimediaShotResolver;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Multimedia\Domain\Model\Multimedia as MultimediaModel;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Domain\Model\Photo\Photo;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultimediaShotResolver::class)]
final class MultimediaShotResolverTest extends TestCase
{
    private MultimediaShotResolver $resolver;
    private MultimediaShotGenerator&MockObject $generator;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(MultimediaShotGenerator::class);
        $this->resolver = new MultimediaShotResolver($this->generator);
    }

    #[Test]
    public function resolveForInsertedEditorialReturnsEmptyWhenEditorialNotFound(): void
    {
        $resolveData = new ResolveDataDTO();

        $result = $this->resolver->resolveForInsertedEditorial('nonexistent-id', $resolveData);

        static::assertTrue($result->isEmpty());
    }

    #[Test]
    public function resolveForInsertedEditorialReturnsEmptyWhenNoMultimediaId(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);

        $insertedNews = new InsertedEditorialDTO(
            editorial: $editorial,
            section: $section,
            multimediaId: null, // No multimedia
        );

        $resolveData = new ResolveDataDTO(
            insertedNews: ['editorial-123' => $insertedNews],
        );

        $result = $this->resolver->resolveForInsertedEditorial('editorial-123', $resolveData);

        static::assertTrue($result->isEmpty());
    }

    #[Test]
    public function resolveForInsertedEditorialPrefersOpeningMultimedia(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);

        $insertedNews = new InsertedEditorialDTO(
            editorial: $editorial,
            section: $section,
            multimediaId: 'media-123',
        );

        $openingDTO = new MultimediaOpeningDTO($opening, $resource);

        $resolveData = new ResolveDataDTO(
            insertedNews: ['editorial-123' => $insertedNews],
            multimediaOpening: ['media-123' => $openingDTO],
        );

        $expectedShots = new MultimediaShotsCollectionDTO([
            new MultimediaShotDTO('202w', 'https://example.com/opening.webp', 202, 152),
        ]);

        $this->generator
            ->expects(static::once())
            ->method('generateLandscapeShotsFromOpening')
            ->with($openingDTO)
            ->willReturn($expectedShots);

        $result = $this->resolver->resolveForInsertedEditorial('editorial-123', $resolveData);

        static::assertFalse($result->isEmpty());
        static::assertSame(1, $result->count());
    }

    #[Test]
    public function resolveForInsertedEditorialFallsBackToBodyMultimedia(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $multimedia = $this->createMock(MultimediaModel::class);

        $insertedNews = new InsertedEditorialDTO(
            editorial: $editorial,
            section: $section,
            multimediaId: 'media-123',
        );

        $resolveData = new ResolveDataDTO(
            insertedNews: ['editorial-123' => $insertedNews],
            multimedia: ['media-123' => $multimedia],
            // No opening multimedia
        );

        $expectedShots = new MultimediaShotsCollectionDTO([
            new MultimediaShotDTO('202w', 'https://example.com/body.webp', 202, 152),
        ]);

        $this->generator
            ->expects(static::once())
            ->method('generateLandscapeShots')
            ->with($multimedia)
            ->willReturn($expectedShots);

        $result = $this->resolver->resolveForInsertedEditorial('editorial-123', $resolveData);

        static::assertFalse($result->isEmpty());
    }

    #[Test]
    public function resolveByMultimediaIdReturnsEmptyWhenNeitherExists(): void
    {
        $resolveData = new ResolveDataDTO();

        $result = $this->resolver->resolveByMultimediaId('nonexistent-media', $resolveData);

        static::assertTrue($result->isEmpty());
    }

    #[Test]
    public function resolveByMultimediaIdPrefersOpeningOverBody(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);
        $multimedia = $this->createMock(MultimediaModel::class);

        $openingDTO = new MultimediaOpeningDTO($opening, $resource);

        $resolveData = new ResolveDataDTO(
            multimedia: ['media-123' => $multimedia],
            multimediaOpening: ['media-123' => $openingDTO],
        );

        $openingShots = new MultimediaShotsCollectionDTO([
            new MultimediaShotDTO('202w', 'https://example.com/opening.webp', 202, 152),
        ]);

        $this->generator
            ->expects(static::once())
            ->method('generateLandscapeShotsFromOpening')
            ->willReturn($openingShots);

        // Should NOT call generateLandscapeShots because opening exists
        $this->generator
            ->expects(static::never())
            ->method('generateLandscapeShots');

        $result = $this->resolver->resolveByMultimediaId('media-123', $resolveData);

        static::assertFalse($result->isEmpty());
        static::assertSame('https://example.com/opening.webp', $result->getBySize('202w')?->url);
    }

    #[Test]
    public function resolveByMultimediaIdFallsBackWhenOpeningEmpty(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);
        $multimedia = $this->createMock(MultimediaModel::class);

        $openingDTO = new MultimediaOpeningDTO($opening, $resource);

        $resolveData = new ResolveDataDTO(
            multimedia: ['media-123' => $multimedia],
            multimediaOpening: ['media-123' => $openingDTO],
        );

        // Opening returns empty
        $this->generator
            ->method('generateLandscapeShotsFromOpening')
            ->willReturn(new MultimediaShotsCollectionDTO());

        // Should fall back to body multimedia
        $bodyShots = new MultimediaShotsCollectionDTO([
            new MultimediaShotDTO('202w', 'https://example.com/body.webp', 202, 152),
        ]);

        $this->generator
            ->method('generateLandscapeShots')
            ->willReturn($bodyShots);

        $result = $this->resolver->resolveByMultimediaId('media-123', $resolveData);

        static::assertFalse($result->isEmpty());
        static::assertSame('https://example.com/body.webp', $result->getBySize('202w')?->url);
    }
}
