<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service\Editorial;

use App\Application\DTO\EmbeddedContentDTO;
use App\Application\Service\Editorial\EmbeddedContentFetcher;
use App\Application\Service\Editorial\EmbeddedContentFetcherInterface;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\Opening;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\RecommendedEditorials;
use Ec\Editorial\Domain\Model\Signatures;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Multimedia\Domain\Model\Multimedia\Multimedia;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaId;
use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient as QueryMultimediaOpeningClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(EmbeddedContentFetcher::class)]
class EmbeddedContentFetcherTest extends TestCase
{
    private EmbeddedContentFetcherInterface $fetcher;
    private MockObject&QueryEditorialClient $editorialClient;
    private MockObject&QuerySectionClient $sectionClient;
    private MockObject&QueryMultimediaClient $multimediaClient;
    private MockObject&QueryMultimediaOpeningClient $multimediaOpeningClient;
    private MockObject&QueryJournalistClient $journalistClient;
    private MockObject&JournalistFactory $journalistFactory;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->editorialClient = $this->createMock(QueryEditorialClient::class);
        $this->sectionClient = $this->createMock(QuerySectionClient::class);
        $this->multimediaClient = $this->createMock(QueryMultimediaClient::class);
        $this->multimediaOpeningClient = $this->createMock(QueryMultimediaOpeningClient::class);
        $this->journalistClient = $this->createMock(QueryJournalistClient::class);
        $this->journalistFactory = $this->createMock(JournalistFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->fetcher = new EmbeddedContentFetcher(
            $this->editorialClient,
            $this->sectionClient,
            $this->multimediaClient,
            $this->multimediaOpeningClient,
            $this->journalistClient,
            $this->journalistFactory,
            $this->logger,
        );
    }

    #[Test]
    public function it_implements_interface(): void
    {
        self::assertInstanceOf(EmbeddedContentFetcherInterface::class, $this->fetcher);
    }

    #[Test]
    public function fetch_returns_embedded_content_dto(): void
    {
        $editorial = $this->createEditorialWithNoEmbeddedContent();
        $section = $this->createMock(Section::class);

        $result = $this->fetcher->fetch($editorial, $section);

        self::assertInstanceOf(EmbeddedContentDTO::class, $result);
        self::assertEmpty($result->insertedNews);
        self::assertEmpty($result->recommendedEditorials);
        self::assertEmpty($result->recommendedNews);
    }

    #[Test]
    public function fetch_returns_empty_arrays_when_no_embedded_content(): void
    {
        $editorial = $this->createEditorialWithNoEmbeddedContent();
        $section = $this->createMock(Section::class);

        $result = $this->fetcher->fetch($editorial, $section);

        self::assertIsArray($result->insertedNews);
        self::assertIsArray($result->recommendedEditorials);
        self::assertIsArray($result->multimediaPromises);
    }

    #[Test]
    public function embedded_content_dto_can_convert_to_resolve_data_array(): void
    {
        $dto = new EmbeddedContentDTO();

        $result = $dto->toResolveDataArray();

        self::assertArrayHasKey('insertedNews', $result);
        self::assertArrayHasKey('recommendedEditorials', $result);
        self::assertArrayHasKey('multimedia', $result);
        self::assertArrayHasKey('multimediaOpening', $result);
    }

    #[Test]
    public function embedded_content_dto_can_merge(): void
    {
        $dto1 = new EmbeddedContentDTO(
            insertedNews: [],
            recommendedEditorials: [],
            multimediaPromises: ['promise1'],
        );

        $dto2 = new EmbeddedContentDTO(
            insertedNews: [],
            recommendedEditorials: [],
            multimediaPromises: ['promise2'],
        );

        $merged = $dto1->merge($dto2);

        self::assertCount(2, $merged->multimediaPromises);
    }

    private function createEditorialWithNoEmbeddedContent(): Editorial&MockObject
    {
        $body = $this->createMock(Body::class);
        $body->method('bodyElementsOf')->willReturn([]);

        $multimedia = $this->createMock(\Ec\Editorial\Domain\Model\Multimedia\Multimedia::class);
        $multimediaId = $this->createMock(MultimediaId::class);
        $multimediaId->method('id')->willReturn('');
        $multimedia->method('id')->willReturn($multimediaId);

        $opening = $this->createMock(Opening::class);
        $opening->method('multimediaId')->willReturn('');

        $recommendedEditorials = $this->createMock(RecommendedEditorials::class);
        $recommendedEditorials->method('editorialIds')->willReturn([]);

        $signatures = $this->createMock(Signatures::class);
        $signatures->method('getArrayCopy')->willReturn([]);

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);
        $editorial->method('multimedia')->willReturn($multimedia);
        $editorial->method('opening')->willReturn($opening);
        $editorial->method('recommendedEditorials')->willReturn($recommendedEditorials);
        $editorial->method('signatures')->willReturn($signatures);
        $editorial->method('metaImage')->willReturn('');

        return $editorial;
    }
}
