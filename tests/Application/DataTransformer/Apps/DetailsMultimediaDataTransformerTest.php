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

        $clipping->expects($this->once())
            ->method('width')
            ->willReturn(1920);

        $clipping->expects($this->once())
            ->method('height')
            ->willReturn(1080);

        $clipping->expects($this->exactly(8))
            ->method('topLeftX')
            ->willReturn(0);
        $clipping->expects($this->exactly(8))
            ->method('topLeftY')
            ->willReturn(0);
        $clipping->expects($this->exactly(8))
            ->method('bottomRightX')
            ->willReturn(1920);
        $clipping->expects($this->exactly(8))
            ->method('bottomRightY')
            ->willReturn(1080);

        $this->thumbor->expects($this->exactly(8))
            ->method('retriveCropBodyTagPicture')
            ->willReturn('https://example.com/image.jpg');

        $expectedCaption = 'Test caption';
        $multimedia->expects($this->once())
            ->method('caption')
            ->willReturn($expectedCaption);

        $this->transformer->write($multimedia);
        $result = $this->transformer->read();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('shots', $result);
        $this->assertArrayHasKey('photo', $result);
        $this->assertArrayHasKey('caption', $result);
        $this->assertEquals($expectedCaption, $result['caption']);
    }

    /**
     * @test
     */
    public function retrieveAspectRatioShouldReturnCorrectValue(): void
    {
        $reflection = new \ReflectionClass(DetailsMultimediaDataTransformer::class);
        $method = $reflection->getMethod('retrieveAspectRatio');

        $result = $method->invoke($this->transformer, 1920, 1080);
        $this->assertEquals('16:9', $result);

        $result = $method->invoke($this->transformer, 1000, 1000);
        $this->assertEquals('1:1', $result);

        $result = $method->invoke($this->transformer, 800, 1200);
        $this->assertEquals('3:4', $result);

        $result = $method->invoke($this->transformer, 1200, 1000);
        $this->assertEquals('4:3', $result);

        $result = $method->invoke($this->transformer, 1920, 800);
        $this->assertEquals('16:9', $result);
    }
}
