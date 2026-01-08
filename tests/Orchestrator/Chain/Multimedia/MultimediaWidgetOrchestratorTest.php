<?php

/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Chain\Multimedia;

use App\Orchestrator\Chain\Multimedia\MultimediaWidgetOrchestrator;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaId;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaWidget;
use Ec\Multimedia\Domain\Model\Multimedia\ResourceId;
use Ec\Widget\Domain\Model\EveryWidget;
use Ec\Widget\Domain\Model\QueryWidgetClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
#[CoversClass(MultimediaWidgetOrchestrator::class)]
class MultimediaWidgetOrchestratorTest extends TestCase
{
    /** @var QueryWidgetClient|MockObject */
    private QueryWidgetClient $queryWidgetClient;
    private MultimediaWidgetOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->queryWidgetClient = $this->createMock(QueryWidgetClient::class);
        $this->orchestrator = new MultimediaWidgetOrchestrator($this->queryWidgetClient);
    }

    #[Test]
    public function canOrchestrateReturnsWidget(): void
    {
        $result = $this->orchestrator->canOrchestrate();

        static::assertSame('widget', $result);
    }

    #[Test]
    public function executeReturnsArrayWithMultimediaAndWidget(): void
    {
        $multimediaId = 'widget-123';
        $resourceId = 'resource-456';

        $multimediaIdMock = $this->createMock(MultimediaId::class);
        $multimediaIdMock->method('id')->willReturn($multimediaId);

        $resourceIdMock = $this->createMock(ResourceId::class);
        $resourceIdMock->method('id')->willReturn($resourceId);

        $multimedia = $this->createMock(MultimediaWidget::class);
        $multimedia->method('id')->willReturn($multimediaIdMock);
        $multimedia->method('resourceId')->willReturn($resourceIdMock);

        $widget = $this->createMock(EveryWidget::class);

        $this->queryWidgetClient
            ->expects(static::once())
            ->method('findWidgetById')
            ->with($resourceId)
            ->willReturn($widget);

        $result = $this->orchestrator->execute($multimedia);

        static::assertArrayHasKey($multimediaId, $result);
        static::assertArrayHasKey('opening', $result[$multimediaId]);
        static::assertArrayHasKey('resource', $result[$multimediaId]);
        static::assertSame($multimedia, $result[$multimediaId]['opening']);
        static::assertSame($widget, $result[$multimediaId]['resource']);
    }
}
