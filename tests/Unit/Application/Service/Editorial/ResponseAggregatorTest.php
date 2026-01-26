<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service\Editorial;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\Apps\Media\MediaDataTransformerHandler;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\Apps\RecommendedEditorialsDataTransformer;
use App\Application\DataTransformer\Apps\StandfirstDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Application\DTO\EmbeddedContentDTO;
use App\Application\DTO\FetchedEditorialDTO;
use App\Application\Service\Editorial\ResponseAggregator;
use App\Application\Service\Editorial\ResponseAggregatorInterface;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\Multimedia\Multimedia;
use Ec\Editorial\Domain\Model\Opening;
use Ec\Editorial\Domain\Model\Signatures;
use Ec\Editorial\Domain\Model\StandFirst;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ResponseAggregator::class)]
class ResponseAggregatorTest extends TestCase
{
    private ResponseAggregatorInterface $aggregator;
    private MockObject&AppsDataTransformer $appsTransformer;
    private MockObject&BodyDataTransformer $bodyTransformer;
    private MockObject&JournalistsDataTransformer $journalistsTransformer;
    private MockObject&MultimediaDataTransformer $multimediaTransformer;
    private MockObject&StandfirstDataTransformer $standfirstTransformer;
    private MockObject&RecommendedEditorialsDataTransformer $recommendedTransformer;
    private MockObject&MediaDataTransformerHandler $mediaHandler;
    private MockObject&QueryLegacyClient $legacyClient;
    private MockObject&QueryJournalistClient $journalistClient;
    private MockObject&JournalistFactory $journalistFactory;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->appsTransformer = $this->createMock(AppsDataTransformer::class);
        $this->bodyTransformer = $this->createMock(BodyDataTransformer::class);
        $this->journalistsTransformer = $this->createMock(JournalistsDataTransformer::class);
        $this->multimediaTransformer = $this->createMock(MultimediaDataTransformer::class);
        $this->standfirstTransformer = $this->createMock(StandfirstDataTransformer::class);
        $this->recommendedTransformer = $this->createMock(RecommendedEditorialsDataTransformer::class);
        $this->mediaHandler = $this->createMock(MediaDataTransformerHandler::class);
        $this->legacyClient = $this->createMock(QueryLegacyClient::class);
        $this->journalistClient = $this->createMock(QueryJournalistClient::class);
        $this->journalistFactory = $this->createMock(JournalistFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->aggregator = new ResponseAggregator(
            $this->appsTransformer,
            $this->bodyTransformer,
            $this->journalistsTransformer,
            $this->multimediaTransformer,
            $this->standfirstTransformer,
            $this->recommendedTransformer,
            $this->mediaHandler,
            $this->legacyClient,
            $this->journalistClient,
            $this->journalistFactory,
            $this->logger,
        );
    }

    #[Test]
    public function it_implements_interface(): void
    {
        self::assertInstanceOf(ResponseAggregatorInterface::class, $this->aggregator);
    }

    #[Test]
    public function aggregate_returns_array_with_required_keys(): void
    {
        $fetchedEditorial = $this->createFetchedEditorialDTO();
        $embeddedContent = new EmbeddedContentDTO();

        $this->setupTransformerMocks();
        $this->setupLegacyClientMock();

        $result = $this->aggregator->aggregate(
            $fetchedEditorial,
            $embeddedContent,
            [],
            [],
            [],
            []
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('countComments', $result);
        self::assertArrayHasKey('signatures', $result);
        self::assertArrayHasKey('body', $result);
        self::assertArrayHasKey('standfirst', $result);
        self::assertArrayHasKey('recommendedEditorials', $result);
    }

    #[Test]
    public function aggregate_includes_comments_count(): void
    {
        $fetchedEditorial = $this->createFetchedEditorialDTO();
        $embeddedContent = new EmbeddedContentDTO();

        $this->setupTransformerMocks();
        $this->legacyClient->expects(self::once())
            ->method('findCommentsByEditorialId')
            ->willReturn(['options' => ['totalrecords' => 42]]);

        $result = $this->aggregator->aggregate(
            $fetchedEditorial,
            $embeddedContent,
            [],
            [],
            [],
            []
        );

        self::assertEquals(42, $result['countComments']);
    }

    #[Test]
    public function aggregate_returns_zero_comments_when_not_found(): void
    {
        $fetchedEditorial = $this->createFetchedEditorialDTO();
        $embeddedContent = new EmbeddedContentDTO();

        $this->setupTransformerMocks();
        $this->legacyClient->expects(self::once())
            ->method('findCommentsByEditorialId')
            ->willReturn(['options' => []]);

        $result = $this->aggregator->aggregate(
            $fetchedEditorial,
            $embeddedContent,
            [],
            [],
            [],
            []
        );

        self::assertEquals(0, $result['countComments']);
    }

    private function createFetchedEditorialDTO(): FetchedEditorialDTO
    {
        $editorialId = $this->createMock(EditorialId::class);
        $editorialId->method('id')->willReturn('test-id');

        $body = $this->createMock(Body::class);
        $standFirst = $this->createMock(StandFirst::class);
        $multimedia = $this->createMock(Multimedia::class);
        $opening = $this->createMock(Opening::class);
        $signatures = $this->createMock(Signatures::class);
        $signatures->method('getArrayCopy')->willReturn([]);

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('id')->willReturn($editorialId);
        $editorial->method('body')->willReturn($body);
        $editorial->method('standFirst')->willReturn($standFirst);
        $editorial->method('multimedia')->willReturn($multimedia);
        $editorial->method('opening')->willReturn($opening);
        $editorial->method('signatures')->willReturn($signatures);
        $editorial->method('editorialType')->willReturn('news');

        $section = $this->createMock(Section::class);

        return new FetchedEditorialDTO($editorial, $section);
    }

    private function setupTransformerMocks(): void
    {
        $this->appsTransformer->method('write')->willReturnSelf();
        $this->appsTransformer->method('read')->willReturn(['base' => 'data']);

        $this->bodyTransformer->method('execute')->willReturn([]);

        $this->standfirstTransformer->method('write')->willReturnSelf();
        $this->standfirstTransformer->method('read')->willReturn(['standfirst' => 'data']);

        $this->recommendedTransformer->method('write')->willReturnSelf();
        $this->recommendedTransformer->method('read')->willReturn([]);
    }

    private function setupLegacyClientMock(): void
    {
        $this->legacyClient->method('findCommentsByEditorialId')
            ->willReturn(['options' => ['totalrecords' => 0]]);
    }
}
