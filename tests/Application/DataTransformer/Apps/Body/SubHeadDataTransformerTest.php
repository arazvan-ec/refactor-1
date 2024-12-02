<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\SubHeadDataTransformer;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\Link;
use Ec\Editorial\Domain\Model\Body\SubHead;
use PHPUnit\Framework\TestCase;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\Application\DataTransformer\Apps\Body\SubHeadDataTransformer
 * @covers \App\Application\DataTransformer\Apps\Body\ElementContentWithLinksDataTransformer
 * @covers \App\Application\DataTransformer\Apps\Body\ElementContentDataTransformer
 * @covers \App\Application\DataTransformer\Apps\Body\ElementTypeDataTransformer
 */
class SubHeadDataTransformerTest extends TestCase
{
    private SubHeadDataTransformer $subHeadDataTransformer;

    protected function setUp(): void
    {
        $this->subHeadDataTransformer = new SubHeadDataTransformer();
    }

    /**
     * @test
     */
    public function canTransformShouldReturnSubHeadString(): void
    {
        static::assertSame(SubHead::class, $this->subHeadDataTransformer->canTransform());
    }

    /**
     * @test
     */
    public function readShouldReturnExpectedArray(): void
    {
        $expectedLink = [
            'type' => 'link',
            'content' => 'links',
            'url' => 'https://www.elconfidencial.com/',
            'target' => '_self',
        ];

        $linkMock = $this->createConfiguredMock(Link::class, $expectedLink);

        $expectedArray = [
            'type' => 'subhead',
            'content' => 'Contenido #1, con #replace0#',
            'links' => [$linkMock],
        ];

        $bodyElementMock = $this->createConfiguredMock(SubHead::class, $expectedArray);

        $result = $this->subHeadDataTransformer->write($bodyElementMock)->read();

        $expectedArray['links'] = [$expectedLink];

        static::assertSame($expectedArray, $result);
    }

    /**
     * @test
     */
    public function writeShouldReturnExceptionWhenBodyElementIsNotSubHead(): void
    {
        $bodyElementMock = $this->createMock(BodyElement::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BodyElement should be instance of '.SubHead::class);

        $this->subHeadDataTransformer->write($bodyElementMock)->read();
    }
}
