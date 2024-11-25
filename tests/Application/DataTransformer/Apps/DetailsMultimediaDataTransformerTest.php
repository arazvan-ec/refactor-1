<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps;

use App\Application\DataTransformer\Apps\DetailsMultimediaDataTransformer;
use App\Infrastructure\Service\Thumbor;
use Ec\Multimedia\Domain\Model\Clipping;
use Ec\Multimedia\Domain\Model\Clippings;
use Ec\Multimedia\Domain\Model\ClippingTypes;
use Ec\Multimedia\Domain\Model\Multimedia;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class DetailsMultimediaDataTransformerTest extends TestCase
{
    private DetailsMultimediaDataTransformer $transformer;

    /** @var Thumbor|MockObject */
    private Thumbor $thumbor;

    protected function setUp(): void
    {
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->transformer = new DetailsMultimediaDataTransformer($this->thumbor);
    }

    /**
     * @test
     */
    public function writeAndReadShouldReturnCorrectArray(): void
    {
        $multimedia = $this->createMock(Multimedia::class);
        $clippings = $this->getMockBuilder(Clippings::class)
            ->onlyMethods(['clippingByType'])
            ->getMock();
        $clipping = $this->createMock(Clipping::class);

        $multimedia->expects($this->once())
            ->method('clippings')
            ->willReturn($clippings);

        $clippings->expects($this->once())
            ->method('clippingByType')
            ->with(ClippingTypes::SIZE_MULTIMEDIA_BIG)
            ->willReturn($clipping);

        $clipping->expects($this->exactly(27))
            ->method('topLeftX')
            ->willReturn(0);
        $clipping->expects($this->exactly(27))
            ->method('topLeftY')
            ->willReturn(0);
        $clipping->expects($this->exactly(27))
            ->method('bottomRightX')
            ->willReturn(1920);
        $clipping->expects($this->exactly(27))
            ->method('bottomRightY')
            ->willReturn(1080);

        $this->thumbor->expects($this->exactly(27))
            ->method('retriveCropBodyTagPicture')
            ->willReturn('https://example.com/image.jpg');

        $expectedCaption = 'Test caption';
        $expectedId = '123';

        $multimedia->expects($this->once())
            ->method('caption')
            ->willReturn($expectedCaption);

        $multimedia->expects($this->once())
            ->method('id')
            ->willReturn($expectedId);

        $this->transformer->write($multimedia);
        $result = $this->transformer->read();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($expectedId, $result['id']);

        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('photo', $result['type']);

        $this->assertArrayHasKey('caption', $result);
        $this->assertEquals($expectedCaption, $result['caption']);

        $this->assertArrayHasKey('shots', $result);
        $this->assertIsArray($result['shots']);

        $this->assertArrayHasKey('photo', $result);
        $this->assertEquals('https://example.com/image.jpg', $result['photo']);
    }
}
