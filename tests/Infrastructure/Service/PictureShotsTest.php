<?php

namespace App\Tests\Infrastructure\Service;

use App\Infrastructure\Service\PictureShots;
use App\Infrastructure\Service\Thumbor;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureId;
use Ec\Multimedia\Domain\Model\Photo\Photo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PictureShotsTest extends TestCase
{
    /**
     * @var Thumbor|MockObject
     */
    private Thumbor $thumbor;
    private PictureShots $pictureShot;

    /**
     * @var BodyTagPicture|MockObject
     */
    private BodyTagPicture $bodyTagPicture;

    protected function setUp(): void
    {
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->pictureShot = new PictureShots($this->thumbor);
    }

    /**
     * @test
     *
     * @dataProvider \App\Tests\Infrastructure\Service\DataProvider\PictureShotsDataProvider::getDataShots()
     */
    public function retrieveShotsByPhotoIdShouldReturnValidArray(
        $id,
        $resolveData,
        $shots,
        $sizes,
        $photoFile,
        $topX,
        $topY,
        $bottomX,
        $bottomY,
        $caption,
        $alternate,
        $orientation,
    ) {
        $bodytagPictureId = $this->createMock(BodyTagPictureId::class);
        $bodytagPictureId->method('id')->willReturn($id);

        $bodyElement = $this->createMock(BodyTagPicture::class);
        $bodyElement->method('id')->willReturn($bodytagPictureId);
        $bodyElement->method('topX')->willReturn($topX);
        $bodyElement->method('topY')->willReturn($topY);
        $bodyElement->method('bottomX')->willReturn($bottomX);
        $bodyElement->method('bottomY')->willReturn($bottomY);
        $bodyElement->method('caption')->willReturn($caption);
        $bodyElement->method('alternate')->willReturn($alternate);
        $bodyElement->method('orientation')->willReturn($orientation);

        $resolveDataMock = [];


        $photo = $this->createMock(Photo::class);
        $photo->method('file')->willReturn($photoFile);
        $resolveDataMock['photoFromBodyTags'] = [$id => $photo];


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

        $result = $this->pictureShot->retrieveShotsByPhotoId($resolveDataMock, $bodyElement);

        foreach ($shots as $ratio => $url) {
            $this->assertEquals($url, $result[$ratio]);
        }

    }

    /**
     * @test
     *
     * @dataProvider \App\Tests\Infrastructure\Service\DataProvider\PictureShotsDataProvider::getDataEmpty()
     */
    public function retrieveShotsByPhotoIdShouldReturnEmptyArray(
        $id,
        $resolveData,
        $expected,
    ) {
        $resolveDataMock = [];
        $bodyElement = $this->createMock(BodyTagPicture::class);
        if (isset($resolveData['photoFromBodyTags'])) {
            $bodytagPictureId = $this->createMock(BodyTagPictureId::class);
            $bodytagPictureId->method('id')->willReturn($resolveData['photoFromBodyTags']['id']['id']);
            $bodyElement->method('id')->willReturn($bodytagPictureId);
            $photo = $this->createMock(Photo::class);
            $resolveDataMock['photoFromBodyTags'] = [$id => $photo];
        }

        $result = $this->pictureShot->retrieveShotsByPhotoId($resolveDataMock, $bodyElement);

        $this->assertEquals($expected, $result);
    }
}
