<?php
/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Chain;

use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\Apps\RecommendedEditorialsDataTransformer;
use App\Application\DataTransformer\Apps\StandfirstDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
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
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Editorial\Domain\Model\RecommendedEditorials;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Editorial\Domain\Model\SignatureId;
use Ec\Editorial\Domain\Model\Signatures;
use Ec\Editorial\Domain\Model\SourceEditorial;
use Ec\Editorial\Domain\Model\Standfirst;
use Ec\Editorial\Domain\Model\Tag;
use Ec\Editorial\Domain\Model\Tags;
use Ec\Journalist\Domain\Model\AliasId;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Ec\Section\Domain\Model\SectionId;
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

    /**
     * @var RecommendedEditorialsDataTransformer|MockObject
     */
    private RecommendedEditorialsDataTransformer $recommendedEditorialsDataTransformer;

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
        $this->recommendedEditorialsDataTransformer = $this->createMock(RecommendedEditorialsDataTransformer::class);
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
            $this->recommendedEditorialsDataTransformer,
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
            $this->standfirstDataTransformer,
            $this->recommendedEditorialsDataTransformer,
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
     * @dataProvider \App\Tests\Orchestrator\Chain\DataProvider\EditorialOrchestratorDataProvider::getData
     */
    public function executeShouldReturnCorrectData(
        array $editorial,
        array $allJournalistExpected,
        array $allJournalistEditorialExpected,
        array $membershipLinkCombine,
        array $expectedJournalistAliasIds,
        $expectedPhotoFromBodyTags,
    ): void {
        $journalistsEditorial = $editorial['signatures'];

        /** @var Request $requestMock */
        $requestMock = $this->getRequestMock($editorial['id']);

        $editorialMock = $this->getEditorialMock($editorial);
        $promisesEditorials[] = $editorialMock;
        $withEditorials[] = $editorial['id'];

        $sectionMock = $this->getSectionMock($editorial['sectionId']);
        $promisesSections[] = $sectionMock;
        $withSections[] = $editorial['sectionId'];

        $callArgumentsCreateUri = [];
        [
            $membershipCardsPromise,
            $expectedArgumentsCreateUri,
        ] = $this->getBodyTagsMembershipCardsByEditorial($editorial, $membershipLinkCombine, $callArgumentsCreateUri);

        [
            $bodyTagsInsertedNews,
            $expectedInsertedNews,
            $promisesEditorialsInserted,
            $withEditorialsInserted,
            $promisesSectionsInserted,
            $withSectionsInserted,
            $withAliasIdsInserted,
        ] = $this->getBodyTagsInsertedNewsByEditorial($editorial, $allJournalistExpected);

        $withEditorials = array_merge($withEditorials, $withEditorialsInserted);
        $promisesEditorials = array_merge($promisesEditorials, $promisesEditorialsInserted);
        $promisesSections = array_merge($promisesSections, $promisesSectionsInserted);
        $withSections = array_merge($withSections, $withSectionsInserted);

        $withBodyTags = [];
        $withBodyTags[] = BodyTagMembershipCard::class;
        $withBodyTags[] = BodyTagInsertedNews::class;
        $withBodyTags[] = BodyTagPicture::class;
        $withBodyTags[] = BodyTagMembershipCard::class;
        $promiseBodyTagPictures = [];

        $arrayMocks = [
            [BodyTagMembershipCard::class => $membershipCardsPromise],
            [BodyTagInsertedNews::class => $bodyTagsInsertedNews],
            [BodyTagPicture::class => $promiseBodyTagPictures],
            [BodyTagMembershipCard::class => $membershipCardsPromise],
        ];
        $expectedArgumentsBodyTags = $withBodyTags;
        $callArgumentsBodyElements = [];
        $bodyMock = $this->createMock(Body::class);
        $bodyMock->expects(static::exactly(\count($expectedArgumentsBodyTags)))
            ->method('bodyElementsOf')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsBodyElements, $arrayMocks) {
                $callArgumentsBodyElements[] = $strClass;

                return $arrayMocks[\count($callArgumentsBodyElements) - 1][$strClass];
            });

        $editorialMock->expects(self::exactly(4))
            ->method('body')
            ->willReturn($bodyMock);

        [
            $expectedRecommendedNews,
            $promisesEditorialsRecommended,
            $withEditorialsRecommended,
            $promisesSectionsRecommended,
            $withSectionsRecommended,
            $withAliasIdsRecommended,
            $editorialMock,
        ] = $this->getRecommendedNewsByEditorial($editorial, $editorialMock, $allJournalistExpected);

        $withEditorials = array_merge($withEditorials, $withEditorialsRecommended);
        $promisesEditorials = array_merge($promisesEditorials, $promisesEditorialsRecommended);
        $promisesSections = array_merge($promisesSections, $promisesSectionsRecommended);
        $withSections = array_merge($withSections, $withSectionsRecommended);

        $tags = [$this->generateTagMock($editorialMock)];

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

        $this->queryLegacyClient
            ->expects($this->once())
            ->method('findCommentsByEditorialId')
            ->with($editorial['id'])
            ->willReturn(['options' => ['totalrecords' => 0]]);

        $editorialMock = $this->getSignaturesMockByEditorial($editorial, $editorialMock);

        $withAliasIds = array_merge($withAliasIdsInserted, $withAliasIdsRecommended);

        $withAliasIds = array_merge($withAliasIds, $editorial['signatures']);

        [
            $promisesJournalist,
            $promisesAliasIds,
        ] = $this->getJournalistPromisesMock($withAliasIds);

        $callArgumentsAlias = [];
        $expectedArgumentsAlias = $this->resolveSignatures(
            $withAliasIds,
            $promisesJournalist,
            $promisesAliasIds,
            $allJournalistExpected,
            $callArgumentsAlias,
            $expectedJournalistAliasIds
        );

        $journalistEditorialExpected = [];
        foreach ($journalistsEditorial as $journalistEditorialId) {
            $journalistEditorialExpected[] = $allJournalistExpected[$journalistEditorialId];
        }

        /** @var array<string> $withSections */
        $arrayMocks = array_combine($withSections, $promisesSections);
        $expectedArgumentsSections = $withSections;
        $callArgumentsSections = [];
        $this->querySectionClient->expects(static::exactly(\count($expectedArgumentsSections)))
            ->method('findSectionById')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsSections, $arrayMocks) {
                $callArgumentsSections[] = $strClass;

                return $arrayMocks[$strClass];
            });

        /** @var array<string> $withEditorials */
        $arrayMocks = array_combine($withEditorials, $promisesEditorials);
        $expectedArgumentsEditorials = $withEditorials;
        $callArgumentsEditorials = [];
        $this->queryEditorialClient->expects(static::exactly(\count($expectedArgumentsEditorials)))
            ->method('findEditorialById')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsEditorials, $arrayMocks) {
                $callArgumentsEditorials[] = $strClass;

                return $arrayMocks[$strClass];
            });

        $resolveData['photoFromBodyTags'] = $expectedPhotoFromBodyTags;
        $resolveData['membershipLinkCombine'] = $membershipLinkCombine;
        $resolveData['insertedNews'] = $expectedInsertedNews;
        $resolveData['multimedia'] = [];
        $resolveData['recommendedEditorials'] = $expectedRecommendedNews;

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

        $this->recommendedEditorialsDataTransformer
            ->expects(static::once())
            ->method('write')
            ->willReturnSelf();
        $this->recommendedEditorialsDataTransformer
            ->expects(static::once())
            ->method('read')
            ->willReturn($editorial['recommenderExpected']);

        $expectedResult['standfirst'] = $editorial['standfirstExpected'];
        $expectedResult['recommendedEditorials'] = $editorial['recommenderExpected'];

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

    private function resolveSignatures(
        array $withAliasIds,
        array $promisesJournalist,
        array $promisesAliasIds,
        array $allJournalistsExpected,
        array &$callArgumentsAlias,
        array $expectedJournalistAliasIds,
    ) {
        $expectedArgumentsAlias = $withAliasIds;
        $arrayMocks = array_combine($withAliasIds, $promisesAliasIds);
        $arrayJournalistsMocks = array_combine($withAliasIds, $promisesJournalist);

        $this->setupJournalistFactoryMock($expectedArgumentsAlias, $callArgumentsAlias, $arrayMocks);
        $this->setupQueryJournalistClientMock($promisesAliasIds, $promisesJournalist);
        $this->setupJournalistsDataTransformerMock(
            $withAliasIds,
            $promisesJournalist,
            $allJournalistsExpected,
            $expectedJournalistAliasIds
        );

        return $expectedArgumentsAlias;
    }

    private function setupJournalistFactoryMock(
        array $expectedArgumentsAlias,
        array &$callArgumentsAlias,
        array $arrayMocks,
    ): void {
        $this->journalistFactory->expects(static::exactly(\count($expectedArgumentsAlias)))
            ->method('buildAliasId')
            ->willReturnCallback(function ($strClass) use (&$callArgumentsAlias, $arrayMocks) {
                $callArgumentsAlias[] = $strClass;

                return $arrayMocks[$strClass];
            });
    }

    private function setupQueryJournalistClientMock(
        array $promisesAliasIds,
        array $promisesJournalist,
    ): void {
        $withConsecutiveArgs = array_map(function ($aliasId) {
            return [$aliasId];
        }, $promisesAliasIds);

        $mockBuilder = $this->queryJournalistClient->expects(static::exactly(\count($promisesJournalist)))
            ->method('findJournalistByAliasId');

        \call_user_func_array([$mockBuilder, 'withConsecutive'], $withConsecutiveArgs);
        \call_user_func_array([$mockBuilder, 'willReturnOnConsecutiveCalls'], $promisesJournalist);
    }

    private function setupJournalistsDataTransformerMock(
        array $withAliasIds,
        array $promisesJournalist,
        array $allJournalistExpected,
        array $expectedJournalistAliasIds,
    ): void {
        $index = \count($promisesJournalist);
        $this->journalistsDataTransformer->expects(static::exactly($index))
            ->method('write')
            ->willReturnSelf();

        $this->journalistsDataTransformer->expects(static::exactly($index))
            ->method('read')
            ->willReturnOnConsecutiveCalls(
                ...$expectedJournalistAliasIds
            );
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
        $editorialMock = $this->createMock(NewsBase::class);
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

    private function getSectionMock(string $id): MockObject
    {
        $sectionMock = $this->createMock(Section::class);
        $sectionIdMock = $this->createMock(SectionId::class);
        $sectionIdMock
            ->method('id')
            ->willReturn($id);
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
    private function getBodyTagsInsertedNewsByEditorial(array $editorial, array $allJournalistsExpected): array
    {
        $expectedInsertedNews = [];
        $bodyTagsInsertedNews = [];
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
                $signaturesInsertedEditorialArray[] = $allJournalistsExpected[$signatureInsertedId];
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
            $bodyTagsInsertedNews[] = $bodyElementMock;
        }

        return [
            $bodyTagsInsertedNews,
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
     *        insertedNews: array<int, array{
     *            id: string,
     *            sectionId: string,
     *            signatures: array<int, string>,
     *            multimediaId: string
     *        }>,
     *        recommender: array<int, array{
     *            id: string,
     *            sectionId: string,
     *            signatures: array<int, string>,
     *            multimediaId: string
     *        }>,
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
    private function getRecommendedNewsByEditorial(array $editorial, MockObject $editorialMock, array $allJournalistsExpected): array
    {
        $expectedRecommendedNews = [];
        $promisesEditorials = [];
        $withEditorials = [];
        $promisesSections = [];
        $withSections = [];
        $withJournalistId = [];
        $recommenderIds = [];

        foreach ($editorial['recommender'] as $editorialRecommended) {
            $editorialId = $editorialRecommended['id'];
            $editorialIdRecommendedMock = $this->createMock(EditorialId::class);
            $editorialIdRecommendedMock->expects(static::once())
                ->method('id')
                ->willReturn($editorialId);
            $recommenderIds[] = $editorialIdRecommendedMock;
            $editorialRecommendedMock = $this->createMock(Editorial::class);
            $editorialRecommendedMock->expects(static::once())
                ->method('isVisible')
                ->willReturn(true);
            $promisesEditorials[] = $editorialRecommendedMock;
            $withEditorials[] = $editorialId;
            $sectionRecommendedMock = $this->createMock(Section::class);
            $editorialRecommendedMock->expects(static::once())
                ->method('sectionId')
                ->willReturn($editorialRecommended['sectionId']);
            $promisesSections[] = $sectionRecommendedMock;
            $withSections[] = $editorialRecommended['sectionId'];
            $signaturesRecommendedEditorialArray = [];
            $signaturesRecommendedEditorialMocksArray = [];
            foreach ($editorialRecommended['signatures'] as $signatureRecommended) {
                $signatureRecommendedMock = $this->createMock(Signature::class);
                $signatureRecommendedIdMock = $this->createMock(SignatureId::class);
                $signatureRecommendedIdMock->expects(static::once())
                    ->method('id')
                    ->willReturn($signatureRecommended);
                $withJournalistId[] = $signatureRecommended;
                $signatureRecommendedMock->expects(static::once())
                    ->method('id')
                    ->willReturn($signatureRecommendedIdMock);

                $signaturesRecommendedEditorialMocksArray[] = $signatureRecommendedMock;
                $signaturesRecommendedEditorialArray[] = $allJournalistsExpected[$signatureRecommended];
            }
            $signaturesRecommendedEditorialsMock = $this->createMock(Signatures::class);
            $editorialRecommendedMock->expects(static::once())
                ->method('signatures')
                ->willReturn($signaturesRecommendedEditorialsMock);
            $signaturesRecommendedEditorialsMock->expects(static::once())
                ->method('getArrayCopy')
                ->willReturn($signaturesRecommendedEditorialMocksArray);
            $expectedRecommendedNews[$editorialId] = [
                'editorial' => $editorialRecommendedMock,
                'section' => $sectionRecommendedMock,
                'multimediaId' => '',
                'signatures' => $signaturesRecommendedEditorialArray,
            ];
        }

        $recommenderMock = $this->createMock(RecommendedEditorials::class);
        $recommenderMock->expects(static::once())
            ->method('editorialIds')
            ->willReturn($recommenderIds);

        $editorialMock->expects(static::once())
            ->method('recommendedEditorials')
            ->willReturn($recommenderMock);

        return [
            $expectedRecommendedNews,
            $promisesEditorials,
            $withEditorials,
            $promisesSections,
            $withSections,
            $withJournalistId,
            $editorialMock,
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
        $uriMock = [];
        $urisMock = [];

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

            $urisMock = [$uriMock, $uriMock];
        }
        $promiseMock = $this->createMock(Promise::class);

        $this->queryMembershipClient->expects(static::once())
            ->method('getMembershipUrl')
            ->with(
                $editorial['id'],
                $urisMock,
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
