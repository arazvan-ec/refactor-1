<?php

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\BodyTagPictureDataTransformer;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use App\Infrastructure\Service\Thumbor;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureId;
use Ec\Multimedia\Domain\Model\Photo\Photo;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use PHPUnit\Framework\TestCase;

class BodyTagPictureDataTransformerTest extends TestCase
{
    private BodyTagPictureDataTransformer $dataTransformer;
    private QueryMultimediaClient $queryMultimediaClient;
    private Thumbor $thumbor;

    protected function setUp(): void
    {
        $this->queryMultimediaClient = $this->createMock(QueryMultimediaClient::class);
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->dataTransformer = new BodyTagPictureDataTransformer($this->queryMultimediaClient, $this->thumbor);
    }

    /**
     * @test
     *
     * @dataProvider \App\Tests\Application\DataTransformer\Apps\Body\DataProvider\BodyTagPictureDataProvider::getData()
     */
    public function readShouldReturnExpectedArray(
        array $shots,
        array $sizes,
        string $photoFile,
        int $topX,
        int $topY,
        int $bottomX,
        int $bottomY,
        string $caption,
        string $alternate,
        string $orientation,
    ): void {
        $bodytagPictureId = $this->createMock(BodyTagPictureId::class);
        $bodytagPictureId->method('id')->willReturn('123');

        $bodyElement = $this->createMock(BodyTagPicture::class);
        $bodyElement->method('id')->willReturn($bodytagPictureId);
        $bodyElement->method('topX')->willReturn($topX);
        $bodyElement->method('topY')->willReturn($topY);
        $bodyElement->method('bottomX')->willReturn($bottomX);
        $bodyElement->method('bottomY')->willReturn($bottomY);
        $bodyElement->method('caption')->willReturn($caption);
        $bodyElement->method('alternate')->willReturn($alternate);
        $bodyElement->method('orientation')->willReturn($orientation);


        $photo = $this->createMock(Photo::class);
        $photo->method('file')->willReturn($photoFile);

        $this->queryMultimediaClient->method('findPhotoById')->willReturn($photo);


        $withConsecutiveArgs = [];
        $willReturn = [];
        foreach ($shots as $ratio => $url) {
            $withConsecutiveArgs[] = [
                $photoFile,
                $sizes[$ratio]['width'],
                $sizes[$ratio]['height'],
                $topX,
                $topY,
                $bottomX,
                $bottomY,
            ];
            $willReturn[] = $url;
        }

        $this->thumbor
            ->expects(static::exactly(count($shots)))
            ->method('retriveCropBodyTagPicture')
            ->withConsecutive(...$withConsecutiveArgs)
            ->willReturnOnConsecutiveCalls(...$willReturn);

        $result = $this->dataTransformer->write($bodyElement)->read();

        foreach ($shots as $ratio => $url) {
            $this->assertEquals($url, $result['shots'][$ratio]);
        }
        $this->assertEquals($caption, $result['caption']);
        $this->assertEquals($alternate, $result['alternate']);
        $this->assertEquals($orientation, $result['orientation']);
    }

    public function canTransformShouldReturnBodyTagPictureString(): void
    {
        $this->assertEquals(BodyTagPicture::class, $this->dataTransformer->canTransform());
    }
}
