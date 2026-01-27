<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orchestrator\Enricher;

use App\Application\DTO\EmbeddedContentDTO;
use App\Orchestrator\DTO\EditorialContext;
use App\Orchestrator\Enricher\ContentEnricherChainHandler;
use App\Orchestrator\Enricher\ContentEnricherInterface;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\Tags;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ContentEnricherChainHandler::class)]
class ContentEnricherChainHandlerTest extends TestCase
{
    private ContentEnricherChainHandler $handler;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new ContentEnricherChainHandler($this->logger);
    }

    #[Test]
    public function it_starts_with_zero_enrichers(): void
    {
        self::assertSame(0, $this->handler->count());
    }

    #[Test]
    public function it_can_add_enrichers(): void
    {
        $enricher1 = $this->createMock(ContentEnricherInterface::class);
        $enricher2 = $this->createMock(ContentEnricherInterface::class);

        $this->handler->addEnricher($enricher1);
        $this->handler->addEnricher($enricher2);

        self::assertSame(2, $this->handler->count());
    }

    #[Test]
    public function it_executes_all_supporting_enrichers(): void
    {
        $context = $this->createContext();

        $enricher1 = $this->createMock(ContentEnricherInterface::class);
        $enricher1->expects(self::once())->method('supports')->willReturn(true);
        $enricher1->expects(self::once())->method('enrich');

        $enricher2 = $this->createMock(ContentEnricherInterface::class);
        $enricher2->expects(self::once())->method('supports')->willReturn(true);
        $enricher2->expects(self::once())->method('enrich');

        $this->handler->addEnricher($enricher1);
        $this->handler->addEnricher($enricher2);

        $this->handler->enrichAll($context);
    }

    #[Test]
    public function it_skips_non_supporting_enrichers(): void
    {
        $context = $this->createContext();

        $supportingEnricher = $this->createMock(ContentEnricherInterface::class);
        $supportingEnricher->expects(self::once())->method('supports')->willReturn(true);
        $supportingEnricher->expects(self::once())->method('enrich');

        $nonSupportingEnricher = $this->createMock(ContentEnricherInterface::class);
        $nonSupportingEnricher->expects(self::once())->method('supports')->willReturn(false);
        $nonSupportingEnricher->expects(self::never())->method('enrich');

        $this->handler->addEnricher($supportingEnricher);
        $this->handler->addEnricher($nonSupportingEnricher);

        $this->handler->enrichAll($context);
    }

    #[Test]
    public function it_continues_execution_when_enricher_throws_exception(): void
    {
        $context = $this->createContext();

        $failingEnricher = $this->createMock(ContentEnricherInterface::class);
        $failingEnricher->method('supports')->willReturn(true);
        $failingEnricher->method('enrich')
            ->willThrowException(new \RuntimeException('Enricher failed'));

        $successfulEnricher = $this->createMock(ContentEnricherInterface::class);
        $successfulEnricher->expects(self::once())->method('supports')->willReturn(true);
        $successfulEnricher->expects(self::once())->method('enrich');

        $this->handler->addEnricher($failingEnricher);
        $this->handler->addEnricher($successfulEnricher);

        // Logger should receive error
        $this->logger
            ->expects(self::once())
            ->method('error');

        // Should not throw, execution continues
        $this->handler->enrichAll($context);
    }

    #[Test]
    public function it_logs_enricher_failures_with_context(): void
    {
        $context = $this->createContext();

        $failingEnricher = $this->createMock(ContentEnricherInterface::class);
        $failingEnricher->method('supports')->willReturn(true);
        $failingEnricher->method('enrich')
            ->willThrowException(new \RuntimeException('Test error'));

        $this->handler->addEnricher($failingEnricher);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Content enricher'),
                self::callback(function (array $context): bool {
                    return isset($context['enricher'])
                        && isset($context['editorial_id'])
                        && isset($context['exception']);
                })
            );

        $this->handler->enrichAll($context);
    }

    private function createContext(): EditorialContext
    {
        $body = $this->createMock(Body::class);

        $tags = $this->createMock(Tags::class);
        $tags->method('isEmpty')->willReturn(true);

        $editorialId = $this->createMock(EditorialId::class);
        $editorialId->method('id')->willReturn('test-id');

        $editorial = $this->createMock(Editorial::class);
        $editorial->method('body')->willReturn($body);
        $editorial->method('tags')->willReturn($tags);
        $editorial->method('id')->willReturn($editorialId);

        $section = $this->createMock(Section::class);
        $embeddedContent = new EmbeddedContentDTO();

        return new EditorialContext($editorial, $section, $embeddedContent);
    }
}
