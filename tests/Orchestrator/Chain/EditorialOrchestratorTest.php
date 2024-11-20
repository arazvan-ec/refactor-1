<?php
/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Exception\EditorialNotPublishedYetException;
use App\Orchestrator\Chain\EditorialOrchestrator;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyNormal;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
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
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use Ec\Tag\Domain\Model\Tag as TagAlias;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Http\Promise\Promise;

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

    /** @var QueryJournalistClient|MockObject */
    private QueryJournalistClient $queryJournalistClient;

    /** @var JournalistsDataTransformer|MockObject */
    private JournalistsDataTransformer $journalistsDataTransformer;

    /** @var AppsDataTransformer|MockObject */
    private AppsDataTransformer $appsDataTransformer;

    /** @var BodyDataTransformer|MockObject */
    private BodyDataTransformer $bodyDataTransformer;

    /** @var MultimediaDataTransformer|MockObject */
    private MultimediaDataTransformer $multimediaDataTransformer;

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
        $this->journalistsDataTransformer = $this->createMock(JournalistsDataTransformer::class);
        $this->appsDataTransformer = $this->createMock(AppsDataTransformer::class);
        $this->bodyDataTransformer = $this->createMock(BodyDataTransformer::class);
        $this->queryTagClient = $this->createMock(QueryTagClient::class);
        $this->uriFactory = $this->createMock(UriFactoryInterface::class);
        $this->queryMembershipClient = $this->createMock(QueryMembershipClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->queryJournalistClient = $this->createMock(QueryJournalistClient::class);
        $this->journalistFactory = $this->createMock(JournalistFactory::class);
        $this->multimediaDataTransformer = $this->createMock(MultimediaDataTransformer::class);

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
            $this->journalistsDataTransformer,
            $this->queryJournalistClient,
            $this->journalistFactory,
            $this->multimediaDataTransformer,
            'dev'
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
            $this->logger,
            $this->journalistsDataTransformer
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
     *
     **/
    public function executeShouldReturnCorrectData(
    ): void {

        $editorialId = 'editorialId';
        $sectionId = 'sectionId';
        $journalistsEditorial = ['1', '2'];
        $bodytagsInsertedNews = [
            [
                'id' => '3',
                'sectionId' => 'sectionId3',
                'signatures' => ['5', '6'],
            ],
            [
                'id' => '4',
                'sectionId' => 'sectionId4',
                'signatures' => ['7'],
            ],
        ];
        $bodytagsMembershipCards = [];

        $editorialMock = $this->createMock(Editorial::class);
        $promisesEditorials[] = $editorialMock;
        $withEditorials[] = $editorialId;
        $withJournalistId = [];
        $withBodyTags = [];
        $promiseBodyTags = [];
        $sourceEditorialMock = $this->createMock(SourceEditorial::class);
        $editorialIdMock = $this->createMock(EditorialId::class);
        $editorialIdMock
            ->method('id')
            ->willReturn($editorialId);
        $editorialMock
            ->method('id')
            ->willReturn($editorialIdMock);
        $editorialMock->expects(static::once())
            ->method('sourceEditorial')
            ->willReturn($sourceEditorialMock);
        $editorialMock->expects(static::once())
            ->method('isVisible')
            ->willReturn(true);
        $editorialMock->expects(static::once())
            ->method('sectionId')
            ->willReturn($sectionId);
        $sectionMock = $this->createMock(Section::class);
        $promisesSections[] = $sectionMock;
        $withSections[] = $sectionId;

        $signaturesEditorialMocksArray = [];
        foreach ($journalistsEditorial as $journalist) {
            $signatureMock = $this->createMock(Signature::class);
            $signatureIdMock = $this->createMock(SignatureId::class);
            $signatureIdMock->expects(static::exactly(2))
                ->method('id')
                ->willReturn($journalist);
            $withJournalistId[] = $journalist;
            $signatureMock->expects(static::exactly(2))
                ->method('id')
                ->willReturn($signatureIdMock);
            $signaturesEditorialMocksArray[] = $signatureMock;
        }

        $signaturesEditorialsMock = $this->createMock(Signatures::class);
        $editorialMock->expects(static::exactly(2))
            ->method('signatures')
            ->willReturn($signaturesEditorialsMock);
        $signaturesEditorialsMock->expects(static::exactly(2))
            ->method('getArrayCopy')
            ->willReturn($signaturesEditorialMocksArray);


        $sectionMock->expects(static::once())
            ->method('siteId')
            ->willReturn('siteId');


        $withBodyTags[] = BodyTagMembershipCard::class;

        $membershipCardsPromise = [];
        foreach ($bodytagsMembershipCards as $bodytagsMembershipCard) {
            $membershipCardsPromise = [];
        }
        $promiseBodyTags[] = $membershipCardsPromise;




        $withBodyTags[] = BodyTagInsertedNews::class;
        $insertedNews = [];
        foreach ($bodytagsInsertedNews as $bodyTag) {
            $bodyElementMock = $this->createMock(BodyTagInsertedNews::class);

            $bodyElementEditorialIdInsertedMock = $this->createMock(EditorialId::class);
            $bodyElementEditorialIdInsertedMock->expects(static::once())
                ->method('id')
                ->willReturn($bodyTag['id']);
            $bodyElementMock->expects(static::once())
                ->method('editorialId')
                ->willReturn($bodyElementEditorialIdInsertedMock);

            $editorialInsertedMock = $this->createMock(Editorial::class);
            /*$editorialInsertedIdMock = $this->createMock(EditorialId::class);
            $editorialInsertedIdMock
                ->method('id')
                ->willReturn($editorialInsertedIdMock);
            $editorialMock
                ->method('id')
                ->willReturn($editorialInsertedIdMock);*/
            $promisesEditorials[] = $editorialInsertedMock;
            $withEditorials[] = $bodyTag['id'];
            $sectionInsertedMock = $this->createMock(Section::class);
            $editorialInsertedMock->expects(static::once())
                ->method('sectionId')
                ->willReturn($bodyTag['sectionId']);
            $promisesSections[] = $sectionInsertedMock;
            $withSections[] = $bodyTag['sectionId'];

            $signaturesInsertedEditorialMocksArray = [];
            foreach ($bodyTag['signatures'] as $signatureInsertedId) {

                $signatureInsertedMock = $this->createMock(Signature::class);
                $signatureInsertedIdMock = $this->createMock(SignatureId::class);
                $signatureInsertedIdMock->expects(static::once())
                    ->method('id')
                    ->willReturn($signatureInsertedId);
                $withJournalistId[] = $signatureInsertedId;
                $signatureInsertedMock->expects(static::once())
                    ->method('id')
                    ->willReturn($signatureInsertedIdMock);
                $signaturesInsertedEditorialMocksArray[] = $signatureInsertedMock;
            }
            $signaturesInsertedEditorialsMock = $this->createMock(Signatures::class);
            $editorialInsertedMock->expects(static::once())
                ->method('signatures')
                ->willReturn($signaturesInsertedEditorialsMock);
            $signaturesInsertedEditorialsMock->expects(static::once())
                ->method('getArrayCopy')
                ->willReturn($signaturesInsertedEditorialMocksArray);

            $insertedNews[] = $bodyElementMock;
        }
        $promiseBodyTags[] = $insertedNews;



        $withBodyTags[] = BodyTagPicture::class;

        $membershipPicturesPromise = [];
        foreach ($membershipPicturesPromise as $membershipPicturePromise) {
            $membershipCardsPromise = [];
        }
        $promiseBodyTags[] = $membershipPicturesPromise;

        $withBodyTags[] = BodyTagMembershipCard::class;
        $promiseBodyTags[] = $membershipCardsPromise;

        $bodyMock = $this->createMock(Body::class);
        $bodyMock
            ->expects(static::exactly(count($withBodyTags)))
            ->method('bodyElementsOf')
            ->withConsecutive(
                [$withBodyTags[0]],
                [$withBodyTags[1]],
                [$withBodyTags[2]],
                [$withBodyTags[3]],
            )
            ->willReturnOnConsecutiveCalls(
                $promiseBodyTags[0],
                $promiseBodyTags[1],
                $promiseBodyTags[2],
                $promiseBodyTags[3],
            );

        $editorialMock->expects(self::exactly(4))
            ->method('body')
            ->willReturn($bodyMock);

        $withAlias = [];
        $promisesJournalist = [];
        foreach ($withJournalistId as $aliasId) {
            $journalistMockArray = $this->createMock(Journalist::class);
            $aliasIdMock = $this->createMock(AliasId::class);
            $aliasIdMock->expects(static::once())
                ->method('id')
                ->willReturn($aliasId);
            $withAlias[] = $aliasIdMock;
            $journalistMockArray->expects(static::once())
                ->method('isVisible')
                ->willReturn(true);
            $journalistMockArray->expects(static::once())
                ->method('isActive')
                ->willReturn(true);

            $promisesJournalist[$aliasId] = $journalistMockArray;
        }

        $tags = [$this->generateTagMock($editorialMock)];

        $this->journalistsDataTransformer->expects(static::once())
            ->method('write')
            ->with($promisesJournalist, $sectionMock)
            ->willReturnSelf();
        $this->journalistsDataTransformer->expects(static::once())
            ->method('read')
            ->willReturn($this->getGenericJournalistData());

        $journalistExpected = [];
        foreach ($journalistsEditorial as $journalistEditorialId) {
            $journalistExpected[] = $this->getGenericJournalistData()[$journalistEditorialId];
        }

        $transformedData = [
            'id' => '4416',
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
            'signatures' => $journalistExpected,
        ];

        $this->appsDataTransformer
            ->expects(static::once())
            ->method('write')
            ->with($editorialMock, $sectionMock, $tags)
            ->willReturnSelf();

        $this->appsDataTransformer
            ->expects(static::once())
            ->method('read')
            ->willReturn($transformedData);

        $this->querySectionClient
            ->expects(static::exactly(count($withSections)))
            ->method('findSectionById')
            ->withConsecutive(
                [$withSections[0]],
                [$withSections[1]],
                [$withSections[2]]
            )
            ->willReturnOnConsecutiveCalls(
                $promisesSections[0],
                $promisesSections[1],
                $promisesSections[2]
            );

        $this->queryEditorialClient
            ->expects(static::exactly(count($withEditorials)))
            ->method('findEditorialById')
            ->withConsecutive(
                [$withEditorials[0]],
                [$withEditorials[1]],
                [$withEditorials[2]]
            )
            ->willReturnOnConsecutiveCalls(
                $promisesEditorials[0],
                $promisesEditorials[1],
                $promisesEditorials[2]
            );

        $this->journalistFactory
            ->expects(static::exactly(count($withJournalistId)))
            ->method('buildAliasId')
            ->withConsecutive(
                [$withJournalistId[0]],
                [$withJournalistId[1]],
                [$withJournalistId[2]],
                [$withJournalistId[3]],
                [$withJournalistId[4]]
            )
            ->willReturnOnConsecutiveCalls(
                $withAlias[0],
                $withAlias[1],
                $withAlias[2],
                $withAlias[3],
                $withAlias[4]
            );

        $this->queryJournalistClient
            ->expects(static::exactly(count($withAlias)))
            ->method('findJournalistByAliasId')
            ->withConsecutive(
                [$withAlias[0]],
                [$withAlias[1]],
                [$withAlias[2]],
                [$withAlias[3]],
                [$withAlias[4]]
            )
            ->willReturnOnConsecutiveCalls(
                $promisesJournalist[$withJournalistId[0]],
                $promisesJournalist[$withJournalistId[1]],
                $promisesJournalist[$withJournalistId[2]],
                $promisesJournalist[$withJournalistId[3]],
                $promisesJournalist[$withJournalistId[4]],
            );

        $this->queryLegacyClient
            ->expects($this->once())
            ->method('findCommentsByEditorialId')
            ->with($editorialId)
            ->willReturn(['options' => ['totalrecords' => 0]]);

        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn($editorialId);

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

    public function executeShouldContinueWhenTagClientThrowsException(): void
    {
        $id = '12345';
        $editorial = $this->getEditorialMock($id);
        $section = $this->generateSectionMock($editorial);
        $journalist = $this->generateJournalistMock($editorial, $section);
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

        $this->appsDataTransformer
            ->expects(self::any())
            ->method('write')
            ->with($editorial, $journalist, $section, [])
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
     * @dataProvider \App\Tests\Orchestrator\Chain\DataProvider\EditorialOrchestratorProvider::getBodyExpected()
     *
     * @param array<mixed> $bodyExpected
     */
    public function executeShouldReturnCorrectDataWithBodyWithBody(array $bodyExpected): void
    {
        $id = '12345';
        $editorial = $this->getEditorialMock($id);
        $section = $this->generateSectionMock($editorial);
        $journalist = $this->generateJournalistMock($editorial, $section);
        $tags = [$this->generateTagMock($editorial)];

        $this->appsDataTransformer
            ->expects(self::any())
            ->method('write')
            ->with($editorial, $section, $tags)
            ->willReturnSelf();
        // $transformedData = $this->getGenericTransformerData();

        $this->appsDataTransformer
            ->expects($this->once())
            ->method('read')
            ->willReturn([]);

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

    /**
     * @param MockObject|Editorial $editorialMock
     */
    private function generateJournalistMock($editorialMock, MockObject $sectionMock): array
    {
        $aliasId = 'aliasId';
        $idInserted = 'idInserted';
        $bodyTagInsertedNews = $this->createMock(BodyTagInsertedNews::class);
        $signatureInsertedMock = $this->createMock(Signature::class);
        $signatureIdInsertedMock = $this->createMock(SignatureId::class);
        $signatureIdInsertedMock->expects(static::once())
            ->method('id')
            ->willReturn($aliasId);
        $signatureInsertedMock->expects(static::once())
            ->method('id')
            ->willReturn($signatureIdInsertedMock);

        $editorialIdInsertedMock = $this->createMock(EditorialId::class);
        $editorialIdInsertedMock->expects(static::once())
            ->method('id')
            ->willReturn($idInserted);
        $bodyTagInsertedNews->expects(static::once())
            ->method('editorialId')
            ->willReturn($editorialIdInsertedMock);
        $editorialInsertedMock = $this->createMock(Editorial::class);
        $editorialInsertedMock->expects(static::once())
            ->method('id')
            ->willReturn($editorialIdInsertedMock);
        $this->queryEditorialClient->expects(static::once())
            ->method('findEditorialById')
            ->with($idInserted)
            ->willReturn($editorialInsertedMock);

        $bodyMock = $this->createMock(Body::class);
        $bodyMock->expects(static::once())
            ->method('bodyElementsOf')
            ->with(BodyTagInsertedNews::class)
            ->willReturn([$bodyTagInsertedNews]);
        $editorialMock->expects(static::once())
            ->method('body')
            ->willReturn($bodyMock);
        $signaturesMock = $this->createMock(Signatures::class);
        $editorialMock->expects(static::once())
            ->method('signatures')
            ->willReturn($signaturesMock);
        $signaturesMock->expects(static::once())
            ->method('addItem')
            ->with($signatureInsertedMock)
            ->willReturnSelf();


        $expected =  [
            'journalistId' => 'journalistId',
            'aliasId' => $aliasId,
            'name' => 'name',
            'url' => 'url',
            'departments' => [
                [
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
            'photo' => 'photo',
        ];
        /*$this->journalistDataTransformer->expects(static::once())
            ->method('write')
            ->with($editorialMock, $sectionMock)
            ->willReturnSelf();
        $this->journalistDataTransformer->expects(static::once())
            ->method('read')
            ->willReturn($expected);*/

        return $expected;
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
    private function getGenericJournalistData(): array
    {
        return [
            '1' => [
                'journalistId' => '1',
                'aliasId' => '1',
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
            '2' => [
                'journalistId' => '2',
                'aliasId' => '2',
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
            '5' => [
                'journalistId' => '5',
                'aliasId' => '5',
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
            '6' => [
                'journalistId' => '6',
                'aliasId' => '6',
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
            '7' => [
                'journalistId' => '7',
                'aliasId' => '7',
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

        ];

    }
}
