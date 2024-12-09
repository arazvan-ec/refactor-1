<?php
/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\Apps\StandfirstDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Exception\EditorialNotPublishedYetException;
use App\Orchestrator\Chain\EditorialOrchestrator;
use Ec\Editorial\Domain\Model\Body\Body;
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
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\Standfirst;
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
use Ec\Section\Domain\Model\SectionId;
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

    /** @var JournalistFactory|MockObject */
    private JournalistFactory $journalistFactory;

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

    /**
     * @var StandfirstDataTransformer|MockObject
     */
    private StandfirstDataTransformer $standfirstDataTransformer;

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
        $this->standfirstDataTransformer = $this->createMock(StandfirstDataTransformer::class);

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
            $this->standfirstDataTransformer,
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
            $this->journalistsDataTransformer,
            $this->multimediaDataTransformer,
            $this->standfirstDataTransformer
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
     *
     * @param array{
     *      id: string,
     *      sectionId: string,
     *      signatures: array<int, string>,
     *      insertedNews: array<int, array{
     *          id: string,
     *          sectionId: string,
     *          signatures: array<int, string>,
     *          multimediaId: string
     *      }>,
     *      membershipCards: array<int, array{
     *          btns: array<int, array{
     *              urlMembership: string,
     *              url: string
     *          }>
     *      }>,
     *      bodyExpected: array<array<string, mixed>>,
     *      standfirstExpected: array<array<string, mixed>>
     *  } $editorial
     * @param array<int, array<string, string>> $allJournalistExpected
     * @param array<int, array<string, string>> $allJournalistEditorialExpected
     * @param array<string, string>             $membershipLinkCombine
     *
     * @dataProvider \App\Tests\Orchestrator\Chain\DataProvider\EditorialOrchestratorDataProvider::getBodyExpected
     */
    public function executeShouldContinueWhenTagClientThrowsException(
        array $editorial,
        array $allJournalistExpected,
        array $allJournalistEditorialExpected,
        array $membershipLinkCombine,
    ): void {
        $journalistsEditorial = $editorial['signatures'];

        /** @var Request $requestMock */
        $requestMock = $this->getRequestMock($editorial['id']);

        $editorialMock = $this->getEditorialMock($editorial);
        $promisesEditorials[] = $editorialMock;
        $withEditorials[] = $editorial['id'];

        $sectionMock = $this->getSectionMockByEditorial($editorial);
        $promisesSections[] = $sectionMock;
        $withSections[] = $editorial['sectionId'];

        $editorialMock = $this->getSignaturesMockByEditorial($editorial, $editorialMock);

        $withBodyTags = [];

        $withBodyTags[] = BodyTagMembershipCard::class;

        $callArgumentsCreateUri = [];
        [
            $membershipCardsPromise,
            $expectedArgumentsCreateUri,
        ] = $this->getBodyTagsMembershipCardsByEditorial($editorial, $membershipLinkCombine, $callArgumentsCreateUri);

        [
            $insertedNewsPromise,
            $expectedInsertedNews,
            $promisesEditorialsInserted,
            $withEditorialsInserted,
            $promisesSectionsInserted,
            $withSectionsInserted,
            $withAliasIds,
        ] = $this->getBodyTagsInsertedNewsByEditorial($editorial);

        /** @var array<string> $withAliasIds */
        $withAliasIds = array_merge($withAliasIds, $editorial['signatures']);

        $promisesEditorials = array_merge($promisesEditorials, $promisesEditorialsInserted);
        $withEditorials = array_merge($withEditorials, $withEditorialsInserted);
        $withBodyTags[] = BodyTagInsertedNews::class;
        $promisesSections = array_merge($promisesSections, $promisesSectionsInserted);
        $withSections = array_merge($withSections, $withSectionsInserted);

        $withBodyTags[] = BodyTagPicture::class;
        $promiseBodyTagPictures = [];
        $withBodyTags[] = BodyTagMembershipCard::class;

        $arrayMocks = [
            [BodyTagMembershipCard::class => $membershipCardsPromise],
            [BodyTagInsertedNews::class => $insertedNewsPromise],
            [BodyTagPicture::class => $promiseBodyTagPictures],
            [BodyTagMembershipCard::class => $membershipCardsPromise],
        ];
        $expectedArgumentsBodyTags = $withBodyTags;
        $callArgumentsBodyElements = [];
        $bodyMock = $this->createMock(Body::class);
        $bodyMock->expects(static::exactly(count($expectedArgumentsBodyTags)))
            ->method('bodyElementsOf')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsBodyElements, $arrayMocks) {
                $callArgumentsBodyElements[] = $strClass;

                return $arrayMocks[count($callArgumentsBodyElements) - 1][$strClass];
            });

        $editorialMock->expects(self::exactly(4))
            ->method('body')
            ->willReturn($bodyMock);

        [$promisesJournalist, $withAlias] = $this->getJournalistPromisesMock($withAliasIds);

        $tagMock = $this->createMock(Tag::class);
        $tagMock->expects(static::once())
            ->method('id');
        $tagsMock = $this->createMock(Tags::class);
        $tagsMock->expects(static::once())
            ->method('getArrayCopy')->willReturn([$tagMock]);
        $editorialMock
            ->expects(self::once())
            ->method('tags')
            ->willReturn($tagsMock);


        $this->queryTagClient
            ->expects($this->once())
            ->method('findTagById')
            ->willThrowException(new \Exception());


        $journalistEditorialExpected = [];
        foreach ($journalistsEditorial as $journalistEditorialId) {
            $journalistEditorialExpected[] = $allJournalistExpected[$journalistEditorialId];
        }
        $tags = [];
        $expectedResult = [
            'id' => $editorial['id'],
            'section' => [
                'id' => $editorial['sectionId'],
                'name' => 'Mercados',
                'url' => 'https://www.elconfidencial.dev/mercados',
            ],
            'countComments' => 0,
            'tags' => $tags,
            'multimedia' => [],
        ];

        $this->appsDataTransformer
            ->expects(static::once())
            ->method('write')
            ->with($editorialMock, $sectionMock, $tags)
            ->willReturnSelf();

        $this->appsDataTransformer
            ->expects(static::once())
            ->method('read')
            ->willReturn($expectedResult);

        /** @var array<string> $withSections */
        $arrayMocks = array_combine($withSections, $promisesSections);
        $expectedArgumentsSections = $withSections;
        $callArgumentsSections = [];
        $this->querySectionClient->expects(static::exactly(count($expectedArgumentsSections)))
            ->method('findSectionById')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsSections, $arrayMocks) {
                $callArgumentsSections[] = $strClass;

                return $arrayMocks[$strClass];
            });

        /** @var array<string> $withEditorials */
        $arrayMocks = array_combine($withEditorials, $promisesEditorials);
        $expectedArgumentsEditorials = $withEditorials;
        $callArgumentsEditorials = [];
        $this->queryEditorialClient->expects(static::exactly(count($expectedArgumentsEditorials)))
            ->method('findEditorialById')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsEditorials, $arrayMocks) {
                $callArgumentsEditorials[] = $strClass;

                return $arrayMocks[$strClass];
            });


        /** @var array<string> $withAliasIds */
        $arrayMocks = array_combine($withAliasIds, $withAlias);
        $expectedArgumentsAlias = $withAliasIds;
        $callArgumentsAlias = [];
        $this->journalistFactory->expects(static::exactly(count($expectedArgumentsAlias)))
            ->method('buildAliasId')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsAlias, $arrayMocks) {
                $callArgumentsAlias[] = $strClass;

                return $arrayMocks[$strClass];
            });

        $this->queryJournalistClient->expects(static::exactly(count($promisesJournalist)))
            ->method('findJournalistByAliasId')
            ->withConsecutive(
                [$withAlias[0]],
                [$withAlias[1]],
                [$withAlias[2]],
                [$withAlias[3]],
                [$withAlias[4]]
            )
            ->willReturnOnConsecutiveCalls(
                $promisesJournalist[0],
                $promisesJournalist[1],
                $promisesJournalist[2],
                $promisesJournalist[3],
                $promisesJournalist[4],
            );

        $this->journalistsDataTransformer->expects(static::exactly(count($promisesJournalist)))
            ->method('write')
            // ->withOnConsecutiveCalls($promisesJournalist, $sectionMock)
            ->willReturnSelf();

        $this->journalistsDataTransformer->expects(static::exactly(count($promisesJournalist)))
            ->method('read')
            ->willReturnOnConsecutiveCalls(
                [],
                [],
                [],
                $allJournalistEditorialExpected[0],
                $allJournalistEditorialExpected[1],
            );

        $this->queryLegacyClient
            ->expects($this->once())
            ->method('findCommentsByEditorialId')
            ->with($editorial['id'])
            ->willReturn(['options' => ['totalrecords' => 0]]);

        $resolveData['photoFromBodyTags'] = ['' => null];
        $resolveData['membershipLinkCombine'] = $membershipLinkCombine;
        $resolveData['insertedNews'] = $expectedInsertedNews;
        $resolveData['multimedia'] = [];

        $this->bodyDataTransformer->expects(static::once())
            ->method('execute')
            ->with($bodyMock, $resolveData)
            ->willReturn($editorial['bodyExpected']);

        $expectedResult['signatures'] = $journalistEditorialExpected;
        $expectedResult['body'] = $editorial['bodyExpected'];

        $standfirst = $this->createMock(Standfirst::class);

        $editorialMock
            ->expects(static::once())
            ->method('standfirst')
            ->willReturn($standfirst);

        $this->standfirstDataTransformer
           ->expects(static::once())
           ->method('write')
           ->willReturnSelf();
        $this->standfirstDataTransformer
            ->expects(static::once())
            ->method('read')
            ->willReturn($editorial['standfirstExpected']);


        $expectedResult['standfirst'] = $editorial['standfirstExpected'];

        $result = $this->editorialOrchestrator->execute($requestMock);

        $this->assertSame($expectedArgumentsBodyTags, $callArgumentsBodyElements);
        $this->assertSame($expectedArgumentsCreateUri, $callArgumentsCreateUri);
        $this->assertSame($expectedArgumentsSections, $callArgumentsSections);
        $this->assertSame($expectedArgumentsAlias, $callArgumentsAlias);
        $this->assertSame($expectedArgumentsEditorials, $callArgumentsEditorials);
        $this->assertSame($expectedResult, $result);
    }

    /**
     * @test
     *
     * @param array{
     *      id: string,
     *      sectionId: string,
     *      signatures: array<int, string>,
     *      insertedNews: array<int, array{
     *          id: string,
     *          sectionId: string,
     *          signatures: array<int, string>,
     *          multimediaId: string
     *      }>,
     *      membershipCards: array<int, array{
     *          btns: array<int, array{
     *              urlMembership: string,
     *              url: string
     *          }>
     *      }>,
     *      bodyExpected: array<array<string, mixed>>,
     *      standfirstExpected: array<array<string, mixed>>
     *  } $editorial
     * @param array<int, array<string, string>> $allJournalistExpected
     * @param array<int, array<string, string>> $allJournalistEditorialExpected
     * @param array<string, string>             $membershipLinkCombine
     *
     * @dataProvider \App\Tests\Orchestrator\Chain\DataProvider\EditorialOrchestratorDataProvider::getBodyExpected
     */
    public function executeShouldReturnCorrectData(
        array $editorial,
        array $allJournalistExpected,
        array $allJournalistEditorialExpected,
        array $membershipLinkCombine,
    ): void {
        $journalistsEditorial = $editorial['signatures'];

        /** @var Request $requestMock */
        $requestMock = $this->getRequestMock($editorial['id']);

        $editorialMock = $this->getEditorialMock($editorial);
        $promisesEditorials[] = $editorialMock;
        $withEditorials[] = $editorial['id'];

        $sectionMock = $this->getSectionMockByEditorial($editorial);
        $promisesSections[] = $sectionMock;
        $withSections[] = $editorial['sectionId'];

        $editorialMock = $this->getSignaturesMockByEditorial($editorial, $editorialMock);

        $withBodyTags = [];

        $withBodyTags[] = BodyTagMembershipCard::class;

        $callArgumentsCreateUri = [];
        [
            $membershipCardsPromise,
            $expectedArgumentsCreateUri,
        ] = $this->getBodyTagsMembershipCardsByEditorial($editorial, $membershipLinkCombine, $callArgumentsCreateUri);

        [
            $insertedNewsPromise,
            $expectedInsertedNews,
            $promisesEditorialsInserted,
            $withEditorialsInserted,
            $promisesSectionsInserted,
            $withSectionsInserted,
            $withAliasIds,
        ] = $this->getBodyTagsInsertedNewsByEditorial($editorial);


        $withAliasIds = array_merge($withAliasIds, $editorial['signatures']);

        $promisesEditorials = array_merge($promisesEditorials, $promisesEditorialsInserted);
        $withEditorials = array_merge($withEditorials, $withEditorialsInserted);
        $withBodyTags[] = BodyTagInsertedNews::class;
        $promisesSections = array_merge($promisesSections, $promisesSectionsInserted);
        $withSections = array_merge($withSections, $withSectionsInserted);

        $withBodyTags[] = BodyTagPicture::class;
        $promiseBodyTagPictures = [];
        $withBodyTags[] = BodyTagMembershipCard::class;

        $arrayMocks = [
            [BodyTagMembershipCard::class => $membershipCardsPromise],
            [BodyTagInsertedNews::class => $insertedNewsPromise],
            [BodyTagPicture::class => $promiseBodyTagPictures],
            [BodyTagMembershipCard::class => $membershipCardsPromise],
        ];
        $expectedArgumentsBodyTags = $withBodyTags;
        $callArgumentsBodyElements = [];
        $bodyMock = $this->createMock(Body::class);
        $bodyMock->expects(static::exactly(count($expectedArgumentsBodyTags)))
            ->method('bodyElementsOf')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsBodyElements, $arrayMocks) {
                $callArgumentsBodyElements[] = $strClass;

                return $arrayMocks[count($callArgumentsBodyElements) - 1][$strClass];
            });

        $editorialMock->expects(self::exactly(4))
            ->method('body')
            ->willReturn($bodyMock);

        [$promisesJournalist, $withAlias] = $this->getJournalistPromisesMock($withAliasIds);

        $tags = [$this->generateTagMock($editorialMock)];
        $journalistEditorialExpected = [];
        foreach ($journalistsEditorial as $journalistEditorialId) {
            $journalistEditorialExpected[] = $allJournalistExpected[$journalistEditorialId];
        }

        $this->appsDataTransformer
            ->expects(static::once())
            ->method('write')
            ->with($editorialMock, $sectionMock, $tags)
            ->willReturnSelf();

        $tags = [
            [
                'id' => '15919',
                'name' => 'Bolsas',
                'url' => 'https://www.elconfidencial.dev/tags/temas/bolsas-15919',
            ],
        ];
        $expectedResult = [
            'id' => $editorial['id'],
            'section' => [
                'id' => $editorial['sectionId'],
                'name' => 'Mercados',
                'url' => 'https://www.elconfidencial.dev/mercados',
            ],
            'countComments' => 0,
            'tags' => $tags,
            'multimedia' => [],
        ];

        $this->appsDataTransformer
            ->expects(static::once())
            ->method('read')
            ->willReturn($expectedResult);

        /** @var array<string> $withSections */
        $arrayMocks = array_combine($withSections, $promisesSections);
        $expectedArgumentsSections = $withSections;
        $callArgumentsSections = [];
        $this->querySectionClient->expects(static::exactly(count($expectedArgumentsSections)))
            ->method('findSectionById')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsSections, $arrayMocks) {
                $callArgumentsSections[] = $strClass;

                return $arrayMocks[$strClass];
            });

        /** @var array<string> $withEditorials */
        $arrayMocks = array_combine($withEditorials, $promisesEditorials);
        $expectedArgumentsEditorials = $withEditorials;
        $callArgumentsEditorials = [];
        $this->queryEditorialClient->expects(static::exactly(count($expectedArgumentsEditorials)))
            ->method('findEditorialById')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsEditorials, $arrayMocks) {
                $callArgumentsEditorials[] = $strClass;

                return $arrayMocks[$strClass];
            });

        /** @var array<string> $withAliasIds */
        $arrayMocks = array_combine($withAliasIds, $withAlias);
        $expectedArgumentsAlias = $withAliasIds;
        $callArgumentsAlias = [];
        $this->journalistFactory->expects(static::exactly(count($expectedArgumentsAlias)))
            ->method('buildAliasId')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsAlias, $arrayMocks) {
                $callArgumentsAlias[] = $strClass;

                return $arrayMocks[$strClass];
            });

        $this->queryJournalistClient->expects(static::exactly(count($promisesJournalist)))
            ->method('findJournalistByAliasId')
            ->withConsecutive(
                [$withAlias[0]],
                [$withAlias[1]],
                [$withAlias[2]],
                [$withAlias[3]],
                [$withAlias[4]]
            )
            ->willReturnOnConsecutiveCalls(
                $promisesJournalist[0],
                $promisesJournalist[1],
                $promisesJournalist[2],
                $promisesJournalist[3],
                $promisesJournalist[4],
            );

        $this->journalistsDataTransformer->expects(static::exactly(count($promisesJournalist)))
            ->method('write')
            // ->withOnConsecutiveCalls($promisesJournalist, $sectionMock)
            ->willReturnSelf();



        $this->journalistsDataTransformer->expects(static::exactly(count($promisesJournalist)))
            ->method('read')
            ->willReturnOnConsecutiveCalls(
                [],
                [],
                [],
                $allJournalistEditorialExpected[0],
                $allJournalistEditorialExpected[1],
            );

        $this->queryLegacyClient
            ->expects($this->once())
            ->method('findCommentsByEditorialId')
            ->with($editorial['id'])
            ->willReturn(['options' => ['totalrecords' => 0]]);

        $resolveData['photoFromBodyTags'] = ['' => null];
        $resolveData['membershipLinkCombine'] = $membershipLinkCombine;
        $resolveData['insertedNews'] = $expectedInsertedNews;
        $resolveData['multimedia'] = [];

        $this->bodyDataTransformer->expects(static::once())
            ->method('execute')
            ->with($bodyMock, $resolveData)
            ->willReturn($editorial['bodyExpected']);

        $expectedResult['signatures'] = $journalistEditorialExpected;
        $expectedResult['body'] = $editorial['bodyExpected'];

        $standfirst = $this->createMock(Standfirst::class);

        $editorialMock
            ->expects(static::once())
            ->method('standfirst')
            ->willReturn($standfirst);

        $this->standfirstDataTransformer
            ->expects(static::once())
            ->method('write')
            ->willReturnSelf();
        $this->standfirstDataTransformer
            ->expects(static::once())
            ->method('read')
            ->willReturn($editorial['standfirstExpected']);


        $expectedResult['standfirst'] = $editorial['standfirstExpected'];

        $result = $this->editorialOrchestrator->execute($requestMock);

        $this->assertSame($expectedArgumentsBodyTags, $callArgumentsBodyElements);
        $this->assertSame($expectedArgumentsCreateUri, $callArgumentsCreateUri);
        $this->assertSame($expectedArgumentsSections, $callArgumentsSections);
        $this->assertSame($expectedArgumentsAlias, $callArgumentsAlias);
        $this->assertSame($expectedArgumentsEditorials, $callArgumentsEditorials);
        $this->assertSame($expectedResult, $result);
    }

    /**
     * @test
     */
    public function canOrchestrateShouldReturnExpectedValue(): void
    {
        static::assertSame('editorial', $this->editorialOrchestrator->canOrchestrate());
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

    private function getRequestMock(string $editorialId): MockObject|Request
    {
        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->expects($this->once())
            ->method('get')
            ->with('id')
            ->willReturn($editorialId);

        return $requestMock;
    }

    /**
     * @param array{
     *       id: string,
     *       sectionId: string,
     *       signatures: array<int, string>,
     *       insertedNews: array<int, array{
     *           id: string,
     *           sectionId: string,
     *           signatures: array<int, string>,
     *           multimediaId: string
     *       }>,
     *       membershipCards: array<int, array{
     *           btns: array<int, array{
     *               urlMembership: string,
     *               url: string
     *           }>
     *       }>,
     *       bodyExpected: array<array<string, mixed>>
     *   } $editorial
     */
    private function getEditorialMock(array $editorial): MockObject
    {
        $editorialMock = $this->createMock(Editorial::class);
        $editorialIdMock = $this->createMock(EditorialId::class);
        $editorialIdMock->expects(static::exactly(1))
            ->method('id')
            ->willReturn($editorial['id']);
        $sourceEditorialMock = $this->createMock(SourceEditorial::class);
        $editorialMock->expects(static::exactly(1))
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
            ->willReturn($editorial['sectionId']);

        return $editorialMock;
    }

    /**
     * @param array{
     *       id: string,
     *       sectionId: string,
     *       signatures: array<int, string>,
     *       insertedNews: array<int, array{
     *           id: string,
     *           sectionId: string,
     *           signatures: array<int, string>,
     *           multimediaId: string
     *       }>,
     *       membershipCards: array<int, array{
     *           btns: array<int, array{
     *               urlMembership: string,
     *               url: string
     *           }>
     *       }>,
     *       bodyExpected: array<array<string, mixed>>
     *   } $editorial
     */
    private function getSectionMockByEditorial(array $editorial): MockObject
    {
        $sectionMock = $this->createMock(Section::class);
        $sectionIdMock = $this->createMock(SectionId::class);
        $sectionIdMock
            ->method('id')
            ->willReturn($editorial['sectionId']);
        $sectionMock
            ->method('id')
            ->willReturn($sectionIdMock);

        $sectionMock->expects(static::once())
            ->method('siteId')
            ->willReturn('siteId');

        return $sectionMock;
    }

    /**
     * @param array{
     *       id: string,
     *       sectionId: string,
     *       signatures: array<int, string>,
     *       insertedNews: array<int, array{
     *           id: string,
     *           sectionId: string,
     *           signatures: array<int, string>,
     *           multimediaId: string
     *       }>,
     *       membershipCards: array<int, array{
     *           btns: array<int, array{
     *               urlMembership: string,
     *               url: string
     *           }>
     *       }>,
     *       bodyExpected: array<array<string, mixed>>
     *   } $editorial
     */
    private function getSignaturesMockByEditorial(array $editorial, MockObject $editorialMock): MockObject
    {
        $signaturesEditorialMocksArray = [];
        foreach ($editorial['signatures'] as $journalist) {
            $signatureMock = $this->createMock(Signature::class);
            $signatureIdMock = $this->createMock(SignatureId::class);
            $signatureIdMock->expects(static::once())
                ->method('id')
                ->willReturn($journalist);
            $signatureMock->expects(static::once())
                ->method('id')
                ->willReturn($signatureIdMock);
            $signaturesEditorialMocksArray[] = $signatureMock;
        }

        $signaturesEditorialsMock = $this->createMock(Signatures::class);
        $signaturesEditorialsMock->expects(static::once())
            ->method('getArrayCopy')
            ->willReturn($signaturesEditorialMocksArray);

        $editorialMock->expects(static::once())
            ->method('signatures')
            ->willReturn($signaturesEditorialsMock);

        return $editorialMock;
    }

    /**
     * @param array{
     *       id: string,
     *       sectionId: string,
     *       signatures: array<int, string>,
     *       insertedNews: array<int, array{
     *           id: string,
     *           sectionId: string,
     *           signatures: array<int, string>,
     *           multimediaId: string
     *       }>,
     *       membershipCards: array<int, array{
     *           btns: array<int, array{
     *               urlMembership: string,
     *               url: string
     *           }>
     *       }>,
     *       bodyExpected: array<array<string, mixed>>
     *   } $editorial
     *
     * @return array{
     *      0: array<int, BodyTagInsertedNews|MockObject>,
     *      1: array<string, array{
     *          editorial: Editorial|MockObject,
     *          section: Section|MockObject,
     *          multimediaId: string,
     *          signatures: array<int, array<string, mixed>>
     *      }>,
     *      2: array<int, Editorial|MockObject>,
     *      3: array<int, string>,
     *      4: array<int, Section|MockObject>,
     *      5: array<int, string>,
     *      6: array<int, string>
     *  }
     *   */
    private function getBodyTagsInsertedNewsByEditorial(array $editorial): array
    {
        $expectedInsertedNews = [];
        $insertedNews = [];
        $promisesEditorials = [];
        $withEditorials = [];
        $promisesSections = [];
        $withSections = [];
        $withJournalistId = [];
        foreach ($editorial['insertedNews'] as $bodyTag) {
            $bodyElementMock = $this->createMock(BodyTagInsertedNews::class);

            $bodyElementEditorialIdInsertedMock = $this->createMock(EditorialId::class);
            $bodyElementEditorialIdInsertedMock->expects(static::once())
                ->method('id')
                ->willReturn($bodyTag['id']);
            $bodyElementMock->expects(static::once())
                ->method('editorialId')
                ->willReturn($bodyElementEditorialIdInsertedMock);

            $editorialInsertedMock = $this->createMock(Editorial::class);
            $editorialInsertedMock->expects(static::once())
                ->method('isVisible')
                ->willReturn(true);
            $promisesEditorials[] = $editorialInsertedMock;
            $withEditorials[] = $bodyTag['id'];
            $sectionInsertedMock = $this->createMock(Section::class);
            $editorialInsertedMock->expects(static::once())
                ->method('sectionId')
                ->willReturn($bodyTag['sectionId']);
            $promisesSections[] = $sectionInsertedMock;
            $withSections[] = $bodyTag['sectionId'];
            $signaturesInsertedEditorialArray = [];
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
                $signatureInsertedArray = [];
                $signaturesInsertedEditorialArray[] = $signatureInsertedArray;
            }
            $signaturesInsertedEditorialsMock = $this->createMock(Signatures::class);
            $editorialInsertedMock->expects(static::once())
                ->method('signatures')
                ->willReturn($signaturesInsertedEditorialsMock);
            $signaturesInsertedEditorialsMock->expects(static::once())
                ->method('getArrayCopy')
                ->willReturn($signaturesInsertedEditorialMocksArray);
            $expectedInsertedNews[$bodyTag['id']] = [
                'editorial' => $editorialInsertedMock,
                'section' => $sectionInsertedMock,
                'multimediaId' => '',
                'signatures' => $signaturesInsertedEditorialArray,
            ];
            $insertedNews[] = $bodyElementMock;
        }

        return [
            $insertedNews,
            $expectedInsertedNews,
            $promisesEditorials,
            $withEditorials,
            $promisesSections,
            $withSections,
            $withJournalistId,
        ];
    }

    /**
     * @param array{
     *       id: string,
     *       sectionId: string,
     *       signatures: array<int, string>,
     *       insertedNews: array<int, array{
     *           id: string,
     *           sectionId: string,
     *           signatures: array<int, string>,
     *           multimediaId: string
     *       }>,
     *       membershipCards: array<int, array{
     *           btns: array<int, array{
     *               urlMembership: string,
     *               url: string
     *           }>
     *       }>,
     *       bodyExpected: array<array<string, mixed>>
     *   } $editorial
     * @param array<string, string> $membershipLinkCombine
     * @param array<string>         $callArgumentsCreateUri
     *
     * @return array{
     *       0: array<int, BodyTagMembershipCard|MockObject>,
     *       1: array<string>
     *   }
     */
    private function getBodyTagsMembershipCardsByEditorial(array $editorial, array $membershipLinkCombine, &$callArgumentsCreateUri): array
    {
        $membershipCardsPromise = [];
        $uriMock = null;

        $expectedArgumentsCreateUri = [];
        foreach ($editorial['membershipCards'] as $bodytagsMembershipCard) {
            $bodyTagMembershipCardMock = $this->createMock(BodyTagMembershipCard::class);
            $btnsMock = $this->createMock(MembershipCardButtons::class);
            $btnsArray = [];
            foreach ($bodytagsMembershipCard['btns'] as $btn) {
                $url1 = $btn['url'];
                $url2 = $btn['urlMembership'];
                $btnMock = $this->createMock(MembershipCardButton::class);
                $btnMock->expects(static::once())
                    ->method('url')
                    ->willReturn($url1);
                $btnMock->expects(static::once())
                    ->method('urlMembership')
                    ->willReturn($url2);
                $btnsArray[] = $btnMock;
                $expectedArgumentsCreateUri[] = $url2;
                $expectedArgumentsCreateUri[] = $url1;
            }
            $btnsMock->expects(static::once())
                ->method('buttons')
                ->willReturn($btnsArray);
            $bodyTagMembershipCardMock->expects(static::once())
                ->method('buttons')
                ->willReturn($btnsMock);
            $membershipCardsPromise[] = $bodyTagMembershipCardMock;

            $uriMock = $this->createMock(UriInterface::class);
            $callArgumentsCreateUri = [];
            $this->uriFactory->expects(static::exactly(2))
                ->method('createUri')
                ->willReturnCallback(function ($strUrl) use (&$callArgumentsCreateUri, $uriMock) {
                    $callArgumentsCreateUri[] = $strUrl;

                    return $uriMock;
                });
        }
        $promiseMock = $this->createMock(Promise::class);

        $this->queryMembershipClient->expects(static::once())
            ->method('getMembershipUrl')
            ->with(
                $editorial['id'],
                [$uriMock, $uriMock],
                'el-confidencial',
                true
            )
            ->willReturn($promiseMock);


        $promiseMock->expects(static::once())
            ->method('wait')
            ->willReturn($membershipLinkCombine);

        return [$membershipCardsPromise, $expectedArgumentsCreateUri];
    }

    /**
     * @param array<string> $aliasIds
     *
     * @return array{
     *        0: array<int, Journalist|MockObject>,
     *        1: array<string>
     *    }
     */
    private function getJournalistPromisesMock(array $aliasIds): array
    {

        $withAlias = [];
        $promisesJournalist = [];
        foreach ($aliasIds as $aliasId) {
            $journalistMockArray = $this->createMock(Journalist::class);
            $aliasIdMock = $this->createMock(AliasId::class);
            $aliasIdMock
                ->method('id')
                ->willReturn($aliasId);
            $withAlias[] = $aliasIdMock;
            $journalistMockArray->expects(static::once())
                ->method('isVisible')
                ->willReturn(true);
            $journalistMockArray->expects(static::once())
                ->method('isActive')
                ->willReturn(true);

            $promisesJournalist[] = $journalistMockArray;
        }

        return [$promisesJournalist, $withAlias];
    }
}
