<?php
/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Chain;

use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Orchestrator\Chain\EditorialOrchestrator;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\SourceEditorial;
use Ec\Editorial\Domain\Model\SourceEditorialId;
use Ec\Editorial\Infrastructure\Client\Http\QueryEditorialClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestratorTest extends TestCase
{
    /** @var QueryEditorialClient|MockObject $queryEditorialClient */
    private QueryEditorialClient $queryEditorialClient;

    /** @var QueryLegacyClient|MockObject $queryLegacyClient */
    private QueryLegacyClient $queryLegacyClient;

    private EditorialOrchestrator $editorialOrchestrator;

    protected function setUp(): void
    {
        $this->queryEditorialClient = $this->createMock(QueryEditorialClient::class);
        $this->queryLegacyClient = $this->createMock(QueryLegacyClient::class);
        $this->editorialOrchestrator = new EditorialOrchestrator($this->queryLegacyClient, $this->queryEditorialClient);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->queryEditorialClient, $this->queryLegacyClient, $this->editorialOrchestrator);
    }

    /**
     * @test
     */
    public function executeShouldReturnEditorialFromEditorialClient(): void
    {
        $id = '12345';
        $editorial = $this->createMock(Editorial::class);
        $sourceEditorial = $this->createMock(SourceEditorial::class);
        $sourceEditorialId = $this->createMock(SourceEditorialId::class);
        $editorialId = $this->createMock(EditorialId::class);

        $editorialId
            ->expects($this->once())
            ->method('id')
            ->willReturn($id);

        $sourceEditorialId
            ->method('id')
            ->willReturn($id);

        $sourceEditorial
            ->method('id')
            ->willReturn($sourceEditorialId);

        $editorial
            ->expects($this->once())
            ->method('sourceEditorial')
            ->willReturn($sourceEditorial);

        $editorial
            ->expects($this->once())
            ->method('id')
            ->willReturn($editorialId);

        $this->queryEditorialClient
            ->expects($this->once())
            ->method('findEditorialById')
            ->with($id)
            ->willReturn($editorial);

        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn($id);

        $result = $this->editorialOrchestrator->execute($requestMock);

        $this->assertSame(['editorial' => ['id' => $id]], $result);
    }

    /**
     * @test
     */
    public function executeShouldReturnEditorialFromLegacyClientWhenSourceIsNull(): void
    {
        $id = '12345';
        $editorial = $this->createMock(Editorial::class);

        $this->queryEditorialClient
            ->expects($this->once())
            ->method('findEditorialById')
            ->with($id)
            ->willReturn($editorial);

        $editorial
            ->expects($this->once())
            ->method('sourceEditorial')
            ->willReturn(null);

        $legacyResponse = ['editorial' => ['id' => $id]];

        $this->queryLegacyClient
            ->expects($this->once())
            ->method('findEditorialById')
            ->with($id)
            ->willReturn($legacyResponse);

        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn($id);

        $result = $this->editorialOrchestrator->execute($requestMock);

        $this->assertSame($legacyResponse, $result);
    }

    /**
     * @test
     */
    public function canOrchestrateShouldReturnExpectedValue(): void
    {
        static::assertSame('editorial', $this->editorialOrchestrator->canOrchestrate());
    }
}
