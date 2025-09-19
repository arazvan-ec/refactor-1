<?php

/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Media;

use App\Application\DataTransformer\Apps\Media\DataTransformers\DetailsMultimediaEmbedVideoDataTransformer;
use Ec\Editorial\Domain\Model\Opening;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaEmbedVideo;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class DetailsMultimediaEmbedVideoDataTransformerTest extends TestCase
{
    private DetailsMultimediaEmbedVideoDataTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new DetailsMultimediaEmbedVideoDataTransformer();
    }

    #[Test]
    public function shouldReadReturnsDefaultForEmptyMultimedia(): void
    {
        $opening = $this->createMock(Opening::class);
        $opening->method('multimediaId')->willReturn('');

        $result = $this->transformer->write([], $opening)->read();

        $this->assertEquals(
            ['id' => '', 'type' => 'multimediaNull'],
            $result
        );
    }

    #[Test]
    public function shouldReadReturnsEmbedVideoDefaultDataForValidMultimedia(): void
    {
        $opening = $this->createMock(Opening::class);
        $opening
            ->expects($this->once())
            ->method('multimediaId')
            ->willReturn('id');

        $multimedia = $this->createMock(MultimediaEmbedVideo::class);
        $multimedia
            ->expects($this->once())
            ->method('caption')
            ->willReturn('Test Caption');
        $multimedia
            ->expects($this->once())
            ->method('text')
            ->willReturn('<iframe src="https://www.testmotion.com/embed/video/x7u5j5"></iframe>');

        /** @var array<string, array{opening: MultimediaPhoto}> $arrayMultimedia */
        $arrayMultimedia = [
            'opening' => $multimedia,
        ];

        $result = $this->transformer->write($arrayMultimedia, $opening)->read();

        $this->assertArrayHasKey('id', $result);
        $this->assertSame('id1', $result['id']);
        $this->assertSame('photo', $result['type']);
        $this->assertSame('Test Caption', $result['caption']);
        $this->assertSame('thumbnail-url', $result['photo']);

        $this->assertArrayHasKey('shots', $result);
        $this->assertInstanceOf(\stdClass::class, $result['shots']);

        $shots = (array) $result['shots'];

        $this->assertArrayHasKey('4:3', $shots);
        $this->assertArrayHasKey('16:9', $shots);
        $this->assertArrayHasKey('3:4', $shots);
        $this->assertArrayHasKey('3:2', $shots);
        $this->assertArrayHasKey('2:3', $shots);

        $this->assertCount(10, $shots['4:3']);
        $this->assertCount(8, $shots['16:9']);
        $this->assertCount(9, $shots['3:4']);
        $this->assertCount(11, $shots['3:2']);
        $this->assertCount(11, $shots['2:3']);

        foreach ($shots as $aspectRatio => $sizesArray) {
            foreach ($sizesArray as $sizeKey => $url) {
                $this->assertSame('thumbnail-url', $url,
                    "Shot for aspect ratio {$aspectRatio} and size {$sizeKey} should be 'thumbnail-url'");
            }
        }
    }
}
