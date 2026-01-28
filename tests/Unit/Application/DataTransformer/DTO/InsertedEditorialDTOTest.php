<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\DTO;

use App\Application\DataTransformer\DTO\InsertedEditorialDTO;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsertedEditorialDTO::class)]
final class InsertedEditorialDTOTest extends TestCase
{
    #[Test]
    public function constructsWithRequiredProperties(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);

        $dto = new InsertedEditorialDTO($editorial, $section);

        static::assertSame($editorial, $dto->editorial);
        static::assertSame($section, $dto->section);
        static::assertSame([], $dto->signatures);
        static::assertNull($dto->multimediaId);
    }

    #[Test]
    public function constructsWithAllProperties(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $signatures = [['name' => 'John Doe', 'twitter' => '@johndoe']];

        $dto = new InsertedEditorialDTO(
            editorial: $editorial,
            section: $section,
            signatures: $signatures,
            multimediaId: 'media-123',
        );

        static::assertSame($editorial, $dto->editorial);
        static::assertSame($section, $dto->section);
        static::assertSame($signatures, $dto->signatures);
        static::assertSame('media-123', $dto->multimediaId);
    }

    #[Test]
    public function fromArrayCreatesCorrectDTO(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);

        $data = [
            'editorial' => $editorial,
            'section' => $section,
            'signatures' => [['name' => 'Author']],
            'multimediaId' => 'media-456',
        ];

        $dto = InsertedEditorialDTO::fromArray($data);

        static::assertSame($editorial, $dto->editorial);
        static::assertSame($section, $dto->section);
        static::assertSame([['name' => 'Author']], $dto->signatures);
        static::assertSame('media-456', $dto->multimediaId);
    }

    #[Test]
    public function fromArrayHandlesMissingOptionalFields(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);

        $data = [
            'editorial' => $editorial,
            'section' => $section,
        ];

        $dto = InsertedEditorialDTO::fromArray($data);

        static::assertSame([], $dto->signatures);
        static::assertNull($dto->multimediaId);
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $section = $this->createMock(Section::class);
        $signatures = [['name' => 'Author']];

        $dto = new InsertedEditorialDTO(
            editorial: $editorial,
            section: $section,
            signatures: $signatures,
            multimediaId: 'media-789',
        );

        $result = $dto->toArray();

        static::assertSame($editorial, $result['editorial']);
        static::assertSame($section, $result['section']);
        static::assertSame($signatures, $result['signatures']);
        static::assertSame('media-789', $result['multimediaId']);
    }
}
