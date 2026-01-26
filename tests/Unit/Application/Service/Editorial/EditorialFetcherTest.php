<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service\Editorial;

use App\Application\DTO\FetchedEditorialDTO;
use App\Application\Service\Editorial\EditorialFetcher;
use App\Application\Service\Editorial\EditorialFetcherInterface;
use App\Infrastructure\Client\Legacy\QueryLegacyClient;
use App\Exception\EditorialNotPublishedYetException;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditorialFetcher::class)]
class EditorialFetcherTest extends TestCase
{
    private EditorialFetcherInterface $fetcher;
    private MockObject&QueryEditorialClient $editorialClient;
    private MockObject&QuerySectionClient $sectionClient;
    private MockObject&QueryLegacyClient $legacyClient;

    protected function setUp(): void
    {
        $this->editorialClient = $this->createMock(QueryEditorialClient::class);
        $this->sectionClient = $this->createMock(QuerySectionClient::class);
        $this->legacyClient = $this->createMock(QueryLegacyClient::class);

        $this->fetcher = new EditorialFetcher(
            $this->editorialClient,
            $this->sectionClient,
            $this->legacyClient,
        );
    }

    #[Test]
    public function it_implements_interface(): void
    {
        self::assertInstanceOf(EditorialFetcherInterface::class, $this->fetcher);
    }

    #[Test]
    public function fetch_returns_dto_with_editorial_and_section(): void
    {
        $editorialId = 'test-editorial-id';
        $sectionId = 'test-section-id';

        $editorial = $this->createEditorialMock($sectionId, isVisible: true, hasSource: true);
        $section = $this->createSectionMock($sectionId);

        $this->editorialClient->expects(self::once())
            ->method('findEditorialById')
            ->with($editorialId)
            ->willReturn($editorial);

        $this->sectionClient->expects(self::once())
            ->method('findSectionById')
            ->with($sectionId)
            ->willReturn($section);

        $result = $this->fetcher->fetch($editorialId);

        self::assertInstanceOf(FetchedEditorialDTO::class, $result);
        self::assertSame($editorial, $result->editorial);
        self::assertSame($section, $result->section);
        self::assertFalse($result->isLegacy);
    }

    #[Test]
    public function fetch_throws_exception_when_editorial_not_visible(): void
    {
        $editorialId = 'invisible-editorial';
        $editorial = $this->createEditorialMock('section-id', isVisible: false, hasSource: true);

        $this->editorialClient->expects(self::once())
            ->method('findEditorialById')
            ->with($editorialId)
            ->willReturn($editorial);

        $this->expectException(EditorialNotPublishedYetException::class);

        $this->fetcher->fetch($editorialId);
    }

    #[Test]
    public function fetch_returns_legacy_data_when_no_source_editorial(): void
    {
        $editorialId = 'legacy-editorial';
        $legacyData = ['id' => $editorialId, 'title' => 'Legacy Title'];

        $editorial = $this->createEditorialMock('section-id', isVisible: true, hasSource: false);

        $this->editorialClient->expects(self::once())
            ->method('findEditorialById')
            ->with($editorialId)
            ->willReturn($editorial);

        $this->legacyClient->expects(self::once())
            ->method('findEditorialById')
            ->with($editorialId)
            ->willReturn($legacyData);

        $result = $this->fetcher->fetchLegacy($editorialId);

        self::assertIsArray($result);
        self::assertEquals($legacyData, $result);
    }

    #[Test]
    public function should_use_legacy_returns_true_when_no_source(): void
    {
        $editorial = $this->createEditorialMock('section-id', isVisible: true, hasSource: false);

        $result = $this->fetcher->shouldUseLegacy($editorial);

        self::assertTrue($result);
    }

    #[Test]
    public function should_use_legacy_returns_false_when_has_source(): void
    {
        $editorial = $this->createEditorialMock('section-id', isVisible: true, hasSource: true);

        $result = $this->fetcher->shouldUseLegacy($editorial);

        self::assertFalse($result);
    }

    private function createEditorialMock(
        string $sectionId,
        bool $isVisible,
        bool $hasSource,
    ): Editorial&MockObject {
        $editorial = $this->createMock(Editorial::class);
        $editorial->method('sectionId')->willReturn($sectionId);
        $editorial->method('isVisible')->willReturn($isVisible);
        $editorial->method('sourceEditorial')->willReturn($hasSource ? 'source-data' : null);

        return $editorial;
    }

    private function createSectionMock(string $sectionId): Section&MockObject
    {
        $section = $this->createMock(Section::class);
        $section->method('id')->willReturn($sectionId);

        return $section;
    }
}
