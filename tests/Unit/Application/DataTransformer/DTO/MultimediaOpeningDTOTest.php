<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DataTransformer\DTO;

use App\Application\DataTransformer\DTO\MultimediaOpeningDTO;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Domain\Model\Photo\Photo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultimediaOpeningDTO::class)]
final class MultimediaOpeningDTOTest extends TestCase
{
    #[Test]
    public function constructsWithCorrectProperties(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);

        $dto = new MultimediaOpeningDTO($opening, $resource);

        static::assertSame($opening, $dto->opening);
        static::assertSame($resource, $dto->resource);
    }

    #[Test]
    public function fromArrayCreatesCorrectDTO(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);

        $data = [
            'opening' => $opening,
            'resource' => $resource,
        ];

        $dto = MultimediaOpeningDTO::fromArray($data);

        static::assertSame($opening, $dto->opening);
        static::assertSame($resource, $dto->resource);
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        $opening = $this->createMock(MultimediaPhoto::class);
        $resource = $this->createMock(Photo::class);

        $dto = new MultimediaOpeningDTO($opening, $resource);

        $result = $dto->toArray();

        static::assertSame($opening, $result['opening']);
        static::assertSame($resource, $result['resource']);
    }
}
