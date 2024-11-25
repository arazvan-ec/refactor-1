<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\BodyTagInsertedNewsDataTransformer;
use App\Infrastructure\Service\Thumbor;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\EditorialTitles;
use Ec\Multimedia\Domain\Model\Clipping;
use Ec\Multimedia\Domain\Model\Clippings;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\TestCase;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class BodyTagInsertedNewsDataTransformerTest extends TestCase
{
    private BodyTagInsertedNewsDataTransformer $transformer;

    protected function setUp(): void
    {
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->transformer = new BodyTagInsertedNewsDataTransformer(
            $this->thumbor,
            'dev'
        );
    }

    /**
     * @test
     *
     * @dataProvider \App\Tests\Application\DataTransformer\Apps\Body\DataProvider\BodyTagInsertedNewsDataProvider::getData()
     */
    public function transformBodyTagInsertedNewsWithSignatures(array $data, array $allSignatures, array $expected): void
    {
        $resolveData = [];
        $id = 'editorial_id';
        $title = 'title body tag inserted news';
        $multimediaId = '1';

        $editorialMock = $this->createMock(Editorial::class);
        $sectionMock = $this->createMock(Section::class);

        $editorialIdBodyTagMock = $this->createMock(EditorialId::class);
        $editorialIdBodyTagMock->expects(static::once())
            ->method('id')
            ->willReturn($id);

        $editorialIdMock = $this->createMock(EditorialId::class);
        $editorialIdMock->expects(static::exactly(2))
            ->method('id')
            ->willReturn($id);

        $bodyElementMock = $this->createMock(BodyTagInsertedNews::class);
        $bodyElementMock->expects(static::once())
            ->method('editorialId')
            ->willReturn($editorialIdBodyTagMock);

        $bodyElementMock->expects(static::once())
            ->method('type')
            ->willReturn('bodytaginsertednews');

        $editorialMock->expects(static::exactly(2))
            ->method('id')
            ->willReturn($editorialIdMock);

        $editorialTitlesMock = $this->createMock(EditorialTitles::class);
        $editorialTitlesMock->expects(static::once())
            ->method('title')
            ->willReturn($title);

        $editorialMock->expects(static::exactly(2))
            ->method('editorialTitles')
            ->willReturn($editorialTitlesMock);

        $clippingsMock = $this->createMock(Clippings::class);
        $multimediaMock = $this->createMock(\Ec\Multimedia\Domain\Model\Multimedia::class);
        $multimediaMock->expects(static::once())
            ->method('clippings')
            ->willReturn($clippingsMock);

        $clippingMock = $this->createMock(Clipping::class);

        $clippingsMock->expects(static::once())
            ->method('clippingByType')
            ->with(ClippingTypes::SIZE_ARTICLE_4_3)
            ->willReturn($clippingMock);

        $fileMock = $multimediaMock->method('file')
            ->willReturn($data['file']);

        $thumborPhoto = $data['photo'];

        $this->thumbor->method('retriveCropBodyTagPicture')
            ->willReturnCallback(function (string $fileImage, string $width, string $height, int $topY, int $bottomX, int $bottomY) use (
                &$callArguments,
                $thumborPhoto
            ) {
                $callArguments[] = [
                    $fileImage,
                    $width,
                    $height,
                    $topY,
                    $bottomX,
                    $bottomY,
                ];

                return $thumborPhoto;
            });


        $resolveData['insertedNews'] = [
            $id => [
                'editorial' => $editorialMock,
                'signatures' => $data['signaturesIndexes'],
                'section' => $sectionMock,
                'multimediaId' => $multimediaId,
            ],
        ];

        $resolveData['shots'] = $data['shots'];
        $resolveData['signatures'] = $allSignatures['signaturesWithIndexId'];

        $resolveData['multimedia'] = [];
        $resolveData['multimedia'][$multimediaId] = $multimediaMock;

        $result = $this->transformer->write($bodyElementMock, $resolveData)->read();

        $this->assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function canTransformShouldReturnBodyTagInsertedNewsString(): void
    {
        static::assertSame(BodyTagInsertedNews::class, $this->transformer->canTransform());
    }
}
