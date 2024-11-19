<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\BodyTagInsertedNewsDataTransformer;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\EditorialTitles;
use Ec\Editorial\Infrastructure\Client\Http\QueryEditorialClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataTransformerTest extends TestCase
{
    private BodyTagInsertedNewsDataTransformer $transformer;

    /** @var QueryEditorialClient|MockObject */
    private QueryEditorialClient $queryEditorialClient;

    /** @var QuerySectionClient|MockObject */
    private QuerySectionClient $querySectionClient;

    protected function setUp(): void
    {
        $this->queryEditorialClient = $this->createMock(QueryEditorialClient::class);
        $this->querySectionClient = $this->createMock(QuerySectionClient::class);
        $this->transformer = new BodyTagInsertedNewsDataTransformer(
            $this->queryEditorialClient,
            $this->querySectionClient,
            'dev'
        );
    }

    /**
     * @test
     *
     * @dataProvider \App\Tests\Application\DataTransformer\Apps\Body\DataProvider\BodyTagInsertedNewsDataProvider::getData()
     */
    public function transformBodyTagInsertedNewsWithSignatures(array $signatures): void
    {
        $resolveData = [];

        $bodyElement = $this->createMock(BodyTagInsertedNews::class);
        $bodyElement->method('editorialId')
            ->willReturn($this->createMock(EditorialId::class));

        $editorialMock = $this->createMock(Editorial::class);
        $sectionMock = $this->createMock(Section::class);

        $editorialMock->method('id')
            ->willReturn($this->createMock(EditorialId::class));
        $editorialMock->method('editorialTitles')
            ->willReturn($this->createMock(EditorialTitles::class));
        $editorialMock->method('publicationDate')
            ->willReturn(new \DateTime());

        $this->queryEditorialClient
            ->expects($this->once())
            ->method('findEditorialById')
            ->willReturn($editorialMock);

        $this->querySectionClient
            ->expects($this->once())
            ->method('findSectionById')
            ->willReturn($sectionMock);

        $resolveData['signatures'] = $signatures;

        $result = $this->transformer->write($bodyElement, $resolveData)
            ->read();



        $this->assertArrayHasKey('editorialId', $result);
        $this->assertIsString($result['editorialId']);
        $this->assertArrayHasKey('title', $result);
        $this->assertIsString($result['title']);
        $this->assertArrayHasKey('signatures', $result);
        $this->assertIsArray($result['signatures']);
        $this->assertArrayHasKey('editorial', $result);
        $this->assertIsString($result['editorial']);

        // todo: validate photo index contents
        $this->assertArrayHasKey('photo', $result);
        $this->assertIsArray($result['photo']);
    }

    /**
     * @test
     */
    public function canTransformShouldReturnBodyTagInsertedNewsString(): void
    {
        static::assertSame(BodyTagInsertedNews::class, $this->transformer->canTransform());
    }
}
