<?php
/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Exception\EditorialNotPublishedYetException;
use App\Orchestrator\Chain\EditorialOrchestrator;
use Ec\Editorial\Domain\Model\Body\BodyNormal;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Body\MembershipCardButtons;
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
use Ec\Journalist\Domain\Model\JournalistId;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use Ec\Tag\Domain\Model\Tag as TagAlias;
use Http\Promise\Promise;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\Orchestrator\Chain\EditorialOrchestrator
 */
class EditorialOrchestratorTest extends TestCase
{
    /** @var QueryEditorialClient|MockObject */
    private QueryEditorialClient $queryEditorialClient;

    /** @var QueryLegacyClient|MockObject */
    private QueryLegacyClient $queryLegacyClient;

    private EditorialOrchestrator $editorialOrchestrator;

    /** @var QuerySectionClient|MockObject */
    private QuerySectionClient $querySectionClient;

    /** @var QueryMultimediaClient|MockObject */
    private QueryMultimediaClient $queryMultimediaClient;

    /** @var JournalistsDataTransformer|MockObject */
    private JournalistsDataTransformer $journalistsDataTransformer;

    /** @var AppsDataTransformer|MockObject */
    private AppsDataTransformer $appsDataTransformer;

    /** @var BodyDataTransformer|MockObject */
    private BodyDataTransformer $bodyDataTransformer;

    /** @var QueryTagClient|MockObject */
    private QueryTagClient $queryTagClient;

    /** @var UriFactoryInterface|MockObject */
    private UriFactoryInterface $uriFactory;

    /** @var MockObject|LoggerInterface */
    private LoggerInterface $logger;

    /** @var QueryMembershipClient|MockObject */
    private QueryMembershipClient $queryMembershipClient;

    protected function setUp(): void
    {
        $this->queryEditorialClient = $this->createMock(QueryEditorialClient::class);
        $this->queryLegacyClient = $this->createMock(QueryLegacyClient::class);
        $this->querySectionClient = $this->createMock(QuerySectionClient::class);
        $this->queryMultimediaClient = $this->createMock(QueryMultimediaClient::class);
        $this->journalistDataTransformer = $this->createMock(JournalistsDataTransformer::class);
        $this->appsDataTransformer = $this->createMock(AppsDataTransformer::class);
        $this->bodyDataTransformer = $this->createMock(BodyDataTransformer::class);
        $this->queryTagClient = $this->createMock(QueryTagClient::class);
        $this->uriFactory = $this->createMock(UriFactoryInterface::class);
        $this->queryMembershipClient = $this->createMock(QueryMembershipClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->editorialOrchestrator = new EditorialOrchestrator(
            $this->queryLegacyClient,
            $this->queryEditorialClient,
            $this->querySectionClient,
            $this->queryMultimediaClient,
            $this->appsDataTransformer,
            $this->queryTagClient,
            $this->bodyDataTransformer,
            $this->uriFactory,
            $this->queryMembershipClient,
            $this->logger,
            $this->journalistDataTransformer
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->editorialOrchestrator,
            $this->queryLegacyClient,
            $this->queryEditorialClient,
            $this->queryJournalistClient,
            $this->querySectionClient,
            $this->queryMultimediaClient,
            $this->journalistFactory,
            $this->appsDataTransformer,
            $this->queryTagClient,
            $this->bodyDataTransformer,
            $this->uriFactory,
            $this->queryMembershipClient,
            $this->logger
        );
    }

    /**
     * @test
     */
    public function executeShouldThrowEditorialNotPublishedWhenIsNotVisible(): void
    {
        $id = '12345';
        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn($id);

        $editorialMock = $this->createMock(Editorial::class);
        $editorialMock->expects($this->once())
            ->method('isVisible')
            ->willReturn(false);

        $sourceEditorialMock = $this->createMock(SourceEditorial::class);
        $editorialMock->expects($this->once())
            ->method('sourceEditorial')
            ->willReturn($sourceEditorialMock);

        $this->queryEditorialClient->expects($this->once())
            ->method('findEditorialById')
            ->with($id)
            ->willReturn($editorialMock);

        $this->expectException(EditorialNotPublishedYetException::class);

        $this->editorialOrchestrator->execute($requestMock);
    }

    /**
     * @test
     */
    public function executeShouldReturnCorrectData(): void
    {
        $id = '12345';
        $editorial = $this->getEditorialMock($id);
        $section = $this->generateSectionMock($editorial);
        $journalist = $this->generateJournalistMock($editorial);
        $tags = [$this->generateTagMock($editorial)];

        $expectedJournalists = [
            '7298' => $journalist,
        ];

        $this->appsDataTransformer
            ->expects(self::any())
            ->method('write')
            ->with($editorial, $expectedJournalists, $section, $tags)
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
            'body' => [],
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
        $editorial = $this->getEditorialMock($id);
        $section = $this->generateSectionMock($editorial);
        $journalist = $this->generateJournalistMock($editorial);
        $editorialTag = $this->createMock(Tag::class);

        $tags = new Tags();
        $tags->addItem($editorialTag);

        $editorial
            ->expects(self::once())
            ->method('tags')
            ->willReturn($tags);

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

    /**
     * @test
     *
     * @dataProvider \App\Tests\Orchestrator\Chain\DataProvider\EditorialOrchestratorProvider::getBodyExpected()
     *
     * @param array<mixed> $bodyExpected
     */
    public function executeShouldReturnCorrectDataWithBodyWithBody(array $bodyExpected): void
    {
        $id = '12345';
        $editorial = $this->getEditorialMock($id);
        $section = $this->generateSectionMock($editorial);
        $journalist = $this->generateJournalistMock($editorial);
        $tags = [$this->generateTagMock($editorial)];

        $expectedJournalists = [
            '7298' => $journalist,
        ];

        $this->appsDataTransformer
            ->expects(self::any())
            ->method('write')
            ->with($editorial, $expectedJournalists, $section, $tags)
            ->willReturnSelf();
        $transformedData = $this->getGenericTransformerData();

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

        $body = $this->generateBody($editorial);

        $resolveData['photoFromBodyTags'] = ['' => null];

        $section->expects(static::once())
            ->method('siteId')
            ->willReturn('siteId');

        $url1 = 'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios1/dp/B0BJQPQVHP';
        $url2 = 'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios2/dp/B0BJQPQVHP';

        $bodyTagMembershipCardMock = $this->createMock(BodyTagMembershipCard::class);
        $btnsMock = $this->createMock(MembershipCardButtons::class);
        $btnMock = $this->createMock(MembershipCardButton::class);
        $btnMock->expects(static::once())
            ->method('url')
            ->willReturn($url1);
        $btnMock->expects(static::once())
            ->method('urlMembership')
            ->willReturn($url2);
        $btnsMock->expects(static::once())
            ->method('buttons')
            ->willReturn([$btnMock]);
        $bodyTagMembershipCardMock->expects(static::once())
            ->method('buttons')
            ->willReturn($btnsMock);
        $uriMock = $this->createMock(UriInterface::class);
        $callArgumentsCreateUri = [];
        $this->uriFactory->expects(static::exactly(2))
            ->method('createUri')
            ->willReturnCallback(function ($strUrl) use (&$callArgumentsCreateUri, $uriMock) {
                $callArgumentsCreateUri[] = $strUrl;

                return $uriMock;
            });
        $expectedArgumentsCreateUri = [$url1, $url2];

        $bodyTagPictureMock = $this->createMock(BodyTagPicture::class);
        $arrayMocks = [
            BodyTagPicture::class => $bodyTagPictureMock,
            BodyTagMembershipCard::class => $bodyTagMembershipCardMock,
        ];
        $callArgumentsBodyElements = [];
        $body->expects(static::exactly(3))
            ->method('bodyElementsOf')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsBodyElements, $arrayMocks) {
                $callArgumentsBodyElements[] = $strClass;

                return [$arrayMocks[$strClass]];
            });
        $expectedArgumentsBodyTags = [BodyTagPicture::class, BodyTagMembershipCard::class, BodyTagMembershipCard::class];

        $promiseMock = $this->createMock(Promise::class);
        $this->queryMembershipClient->expects(static::once())
            ->method('getMembershipUrl')
            ->with(
                $id,
                [$uriMock, $uriMock],
                'el-confidencial',
                true
            )->willReturn($promiseMock);

        $resolveData['membershipLinkCombine'] = [
            'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios1/dp/B0BJQPQVHP' => 'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios1/dp/B0BJQPQVHP?tag=cacatuaMan',
            'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios2/dp/B0BJQPQVHP' => 'https://www.amazon.es/Cecotec-Multifunci%C3%B3n-Funciones-Antiadherente-Accesorios2/dp/B0BJQPQVHP?tag=cacatuaMan',
        ];

        $promiseMock->expects(static::once())
            ->method('wait')
            ->willReturn($resolveData['membershipLinkCombine']);

        $this->bodyDataTransformer->expects(static::once())
            ->method('execute')
            ->with($body, $resolveData)
            ->willReturn($bodyExpected);


        $transformedData['body'] = $bodyExpected;
        $result = $this->editorialOrchestrator->execute($requestMock);

        self::assertSame($expectedArgumentsCreateUri, $callArgumentsCreateUri);
        self::assertSame($expectedArgumentsBodyTags, $callArgumentsBodyElements);

        $this->assertSame($transformedData, $result);
    }

    private function getEditorialMock(string $id): MockObject
    {
        $editorial = $this->createMock(Editorial::class);
        $sourceEditorial = $this->createMock(SourceEditorial::class);
        $sourceEditorialId = $this->createMock(SourceEditorialId::class);
        $editorialId = $this->createMock(EditorialId::class);

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
            ->method('isVisible')
            ->willReturn(true);
        $editorial
            ->method('id')
            ->willReturn($editorialId);

        return $editorial;
    }

    /**
     * @return Section|MockObject
     */
    private function generateSectionMock(MockObject $editorialMock): Section
    {
        $sectionId = 'sectionId';
        $section = $this->createMock(Section::class);

        $editorialMock->expects(static::once())
            ->method('sectionId')
            ->willReturn($sectionId);

        $this->querySectionClient
            ->expects($this->once())
            ->method('findSectionById')
            ->with($sectionId)
            ->willReturn($section);

        return $section;
    }

    private function generateJournalistMock(MockObject $editorialMock): Journalist|MockObject
    {
        $signature = $this->createMock(Signature::class);
        $signatureId = $this->createMock(SignatureId::class);
        $aliasId = $this->createMock(AliasId::class);
        $journalist = $this->createMock(Journalist::class);
        $journalistId = $this->createMock(JournalistId::class);

        $signatureId
            ->method('id')
            ->willReturn('signature-id');

        $signature
            ->method('id')
            ->willReturn($signatureId);

        $signatures = new Signatures();
        $signatures->addItem($signature);

        $editorialMock
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

        return $journalist;
    }

    private function generateTagMock(MockObject $editorialMock): MockObject|TagAlias
    {
        $editorialTag = $this->createMock(Tag::class);
        $tag = $this->createMock(TagAlias::class);

        $tags = new Tags();
        $tags->addItem($editorialTag);

        $editorialMock
            ->expects(self::once())
            ->method('tags')
            ->willReturn($tags);

        $this->queryTagClient
            ->expects($this->once())
            ->method('findTagById')
            ->with($editorialTag->id()->id())
            ->willReturn($tag);

        return $tag;
    }

    /**
     * @return BodyNormal|MockObject
     */
    private function generateBody(MockObject $editorialMock): BodyNormal
    {
        $body = $this->createMock(BodyNormal::class);
        $editorialMock->expects(static::exactly(3))
            ->method('body')
            ->willReturn($body);

        return $body;
    }

    /**
     * @return array<mixed>
     */
    private function getGenericTransformerData(): array
    {
        return [
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
            'body' => [],
        ];
    }
}
