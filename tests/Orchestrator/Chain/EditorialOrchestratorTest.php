<?php
/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Orchestrator\Chain\EditorialOrchestrator;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Editorial\Domain\Model\SignatureId;
use Ec\Editorial\Domain\Model\Signatures;
use Ec\Editorial\Domain\Model\SourceEditorial;
use Ec\Editorial\Domain\Model\SourceEditorialId;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\Tag;
use Ec\Editorial\Domain\Model\Tags;
use Ec\Journalist\Domain\Model\AliasId;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\JournalistId;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialOrchestratorTest extends TestCase
{
    /** @var QueryEditorialClient|MockObject */
    private QueryEditorialClient $queryEditorialClient;

    /** @var QueryLegacyClient|MockObject */
    private QueryLegacyClient $queryLegacyClient;

    /** @var QueryJournalistClient|MockObject */
    private QueryJournalistClient $queryJournalistClient;

    private EditorialOrchestrator $editorialOrchestrator;

    /** @var QuerySectionClient|MockObject */
    private QuerySectionClient $querySectionClient;

    /** @var JournalistFactory|MockObject */
    private JournalistFactory $journalistFactory;

    /** @var AppsDataTransformer|MockObject */
    private AppsDataTransformer $appsDataTransformer;

    /** @var QueryTagClient|MockObject */
    private QueryTagClient $queryTagClient;

    protected function setUp(): void
    {
        $this->queryEditorialClient = $this->createMock(QueryEditorialClient::class);
        $this->queryLegacyClient = $this->createMock(QueryLegacyClient::class);
        $this->queryJournalistClient = $this->createMock(QueryJournalistClient::class);
        $this->querySectionClient = $this->createMock(QuerySectionClient::class);
        $this->journalistFactory = $this->createMock(JournalistFactory::class);
        $this->appsDataTransformer = $this->createMock(AppsDataTransformer::class);
        $this->queryTagClient = $this->createMock(QueryTagClient::class);

        $this->editorialOrchestrator = new EditorialOrchestrator(
            $this->queryLegacyClient,
            $this->queryEditorialClient,
            $this->queryJournalistClient,
            $this->querySectionClient,
            $this->journalistFactory,
            $this->appsDataTransformer,
            $this->queryTagClient
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->queryEditorialClient,
            $this->queryLegacyClient,
            $this->editorialOrchestrator,
            $this->querySectionClient,
            $this->journalistFactory,
            $this->appsDataTransformer
        );
    }

    /**
     * @test
     */
    public function executeShouldReturnCorrectData(): void
    {
        $id = '12345';
        $editorial = $this->createMock(Editorial::class);
        $sourceEditorial = $this->createMock(SourceEditorial::class);
        $sourceEditorialId = $this->createMock(SourceEditorialId::class);
        $editorialId = $this->createMock(EditorialId::class);
        $signature = $this->createMock(Signature::class);
        $signatureId = $this->createMock(SignatureId::class);
        $aliasId = $this->createMock(AliasId::class);
        $journalist = $this->createMock(Journalist::class);
        $journalistId = $this->createMock(JournalistId::class);
        $section = $this->createMock(Section::class);
        $editorialTag = $this->createMock(Tag::class);
        $tag = $this->createMock(\Ec\Tag\Domain\Model\Tag::class);

        $editorialId
            ->method('id')
            ->willReturn($id);

        $sourceEditorialId
            ->method('id')
            ->willReturn($id);

        $sourceEditorial
            ->method('id')
            ->willReturn($sourceEditorialId);

        $editorial
            ->method('sourceEditorial')
            ->willReturn($sourceEditorial);

        $editorial
            ->method('id')
            ->willReturn($editorialId);

        $signatureId
            ->method('id')
            ->willReturn('signature-id');

        $signature
            ->method('id')
            ->willReturn($signatureId);

        $signatures = new Signatures();
        $signatures->addItem($signature);

        $tags = new Tags();
        $tags->addItem($editorialTag);

        $editorial
            ->expects(self::once())
            ->method('tags')
            ->willReturn($tags);

        $editorial
            ->expects(self::once())
            ->method('signatures')
            ->willReturn($signatures);

        $journalistId
            ->method('id')
            ->willReturn('alias-id');

        $journalist
            ->method('id')
            ->willReturn($journalistId);

        $aliasId
            ->method('id')
            ->willReturn('7298');

        $this->journalistFactory
            ->expects($this->once())
            ->method('buildAliasId')
            ->with('signature-id')
            ->willReturn($aliasId);

        $journalist
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $journalist
            ->expects($this->once())
            ->method('isVisible')
            ->willReturn(true);

        $this->queryJournalistClient
            ->expects($this->once())
            ->method('findJournalistByAliasId')
            ->with($aliasId)
            ->willReturn($journalist);

        $this->querySectionClient
            ->expects($this->once())
            ->method('findSectionById')
            ->with($editorial->sectionId())
            ->willReturn($section);

        $this->queryTagClient
            ->expects($this->once())
            ->method('findTagById')
            ->with($editorialTag->id()->id())
            ->willReturn($tag);

        $expectedJournalists = [
            '7298' => $journalist,
        ];

        $this->appsDataTransformer
            ->expects(self::any())
            ->method('write')
            ->with($editorial, $expectedJournalists, $section, [$tag])
            ->willReturnSelf();

        $transformedData = [
            'id' => '4416',
            'signatures' => [
                [
                    'journalistId' => '2338',
                    'aliasId' => '7298',
                    'name' => 'Javier Bocanegra 1',
                    'url' => 'https://www.elconfidencial.dev/autores/Javier+Bocanegra-2338/',
                    'photo' => 'https://images.ecestaticos.dev/K0FFtVTsHaYc4Yd0feIi_Oiu6O4=/dev.f.elconfidencial.com/journalist/1b2/c5e/4ff/1b2c5e4fff467ca4e86b6aa3d3ded248.jpg',
                    'departments' => [
                        [
                            'id' => '11',
                            'name' => 'Fin de semana',
                        ],
                    ],
                ],
            ],
            'section' => [
                'id' => '90',
                'name' => 'Mercados',
                'url' => 'https://www.elconfidencial.dev/mercados',
            ],
            'countComments' => 0,
            'tags' => [
                [
                    'id' => '15919',
                    'name' => 'Bolsas',
                    'url' => 'https://www.elconfidencial.dev/tags/temas/bolsas-15919',
                ],
            ],
        ];

        $this->appsDataTransformer
            ->expects($this->once())
            ->method('read')
            ->willReturn($transformedData);

        $this->queryEditorialClient
            ->expects($this->once())
            ->method('findEditorialById')
            ->with($id)
            ->willReturn($editorial);

        $this->queryLegacyClient
            ->expects($this->once())
            ->method('findCommentsByEditorialId')
            ->with($id)
            ->willReturn(['options' => ['totalrecords' => 0]]);


        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn($id);

        $result = $this->editorialOrchestrator->execute($requestMock);

        $this->assertSame($transformedData, $result);
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
    public function executeShouldContinueWhenTagClientThrowsException(): void
    {
        $id = '12345';
        $editorial = $this->createMock(Editorial::class);
        $sourceEditorial = $this->createMock(SourceEditorial::class);
        $sourceEditorialId = $this->createMock(SourceEditorialId::class);
        $editorialId = $this->createMock(EditorialId::class);
        $signature = $this->createMock(Signature::class);
        $signatureId = $this->createMock(SignatureId::class);
        $aliasId = $this->createMock(AliasId::class);
        $journalist = $this->createMock(Journalist::class);
        $journalistId = $this->createMock(JournalistId::class);
        $section = $this->createMock(Section::class);
        $editorialTag = $this->createMock(Tag::class);

        $editorialId
            ->method('id')
            ->willReturn($id);

        $sourceEditorialId
            ->method('id')
            ->willReturn($id);

        $sourceEditorial
            ->method('id')
            ->willReturn($sourceEditorialId);

        $editorial
            ->method('sourceEditorial')
            ->willReturn($sourceEditorial);

        $editorial
            ->method('id')
            ->willReturn($editorialId);

        $signatureId
            ->method('id')
            ->willReturn('signature-id');

        $signature
            ->method('id')
            ->willReturn($signatureId);

        $signatures = new Signatures();
        $signatures->addItem($signature);

        $tags = new Tags();
        $tags->addItem($editorialTag);

        $editorial
            ->expects(self::once())
            ->method('tags')
            ->willReturn($tags);

        $editorial
            ->expects(self::once())
            ->method('signatures')
            ->willReturn($signatures);

        $journalistId
            ->method('id')
            ->willReturn('alias-id');

        $journalist
            ->method('id')
            ->willReturn($journalistId);

        $aliasId
            ->method('id')
            ->willReturn('7298');

        $this->journalistFactory
            ->expects($this->once())
            ->method('buildAliasId')
            ->with('signature-id')
            ->willReturn($aliasId);

        $journalist
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $journalist
            ->expects($this->once())
            ->method('isVisible')
            ->willReturn(true);

        $this->queryJournalistClient
            ->expects($this->once())
            ->method('findJournalistByAliasId')
            ->with($aliasId)
            ->willReturn($journalist);

        $this->querySectionClient
            ->expects($this->once())
            ->method('findSectionById')
            ->with($editorial->sectionId())
            ->willReturn($section);

        $this->queryTagClient
            ->expects($this->once())
            ->method('findTagById')
            ->with($editorialTag->id()->id())
            ->willThrowException(new \Exception('Tag not found'));

        $expectedJournalists = [
            '7298' => $journalist,
        ];

        $this->appsDataTransformer
            ->expects(self::any())
            ->method('write')
            ->with($editorial, $expectedJournalists, $section, [])
            ->willReturnSelf();

        $transformedData = [
            'id' => '4416',
            'signatures' => [
                [
                    'journalistId' => '2338',
                    'aliasId' => '7298',
                    'name' => 'Javier Bocanegra 1',
                    'url' => 'https://www.elconfidencial.dev/autores/Javier+Bocanegra-2338/',
                    'photo' => 'https://images.ecestaticos.dev/K0FFtVTsHaYc4Yd0feIi_Oiu6O4=/dev.f.elconfidencial.com/journalist/1b2/c5e/4ff/1b2c5e4fff467ca4e86b6aa3d3ded248.jpg',
                    'departments' => [
                        [
                            'id' => '11',
                            'name' => 'Fin de semana',
                        ],
                    ],
                ],
            ],
            'section' => [
                'id' => '90',
                'name' => 'Mercados',
                'url' => 'https://www.elconfidencial.dev/mercados',
            ],
            'countComments' => 0,
            'tags' => [],
        ];

        $this->appsDataTransformer
            ->expects($this->once())
            ->method('read')
            ->willReturn($transformedData);

        $this->queryEditorialClient
            ->expects($this->once())
            ->method('findEditorialById')
            ->with($id)
            ->willReturn($editorial);

        $this->queryLegacyClient
            ->expects($this->once())
            ->method('findCommentsByEditorialId')
            ->with($id)
            ->willReturn(['options' => ['totalrecords' => 0]]);


        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn($id);

        $result = $this->editorialOrchestrator->execute($requestMock);

        $this->assertArrayHasKey('countComments', $result);
        $this->assertSame(0, $result['countComments']);
    }

    /**
     * @test
     */
    public function canOrchestrateShouldReturnExpectedValue(): void
    {
        static::assertSame('editorial', $this->editorialOrchestrator->canOrchestrate());
    }
}
