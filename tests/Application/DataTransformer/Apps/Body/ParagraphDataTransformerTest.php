<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\ParagraphDataTransformer;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\Link;
use Ec\Editorial\Domain\Model\Body\Paragraph;
use PHPUnit\Framework\TestCase;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 *
 * @covers \App\Application\DataTransformer\Apps\Body\ParagraphDataTransformer
 * @covers \App\Application\DataTransformer\Apps\Body\ElementContentWithLinksDataTransformer
 * @covers \App\Application\DataTransformer\Apps\Body\ElementContentDataTransformer
 * @covers \App\Application\DataTransformer\Apps\Body\ElementTypeDataTransformer
 */
class ParagraphDataTransformerTest extends TestCase
{
    private ParagraphDataTransformer $paragraphDataTransformer;

    protected function setUp(): void
    {
        $this->paragraphDataTransformer = new ParagraphDataTransformer();
    }

    /**
     * @test
     */
    public function canTransformShouldReturnParagraphString(): void
    {
        static::assertSame(Paragraph::class, $this->paragraphDataTransformer->canTransform());
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
            'type' => 'paragraph',
            'content' => 'Contenido #1, con #replace0#',
            'links' => [$linkMock],
        ];

        $bodyElementMock = $this->createConfiguredMock(Paragraph::class, $expectedArray);

        $result = $this->paragraphDataTransformer->write($bodyElementMock)->read();

        $expectedArray['links'] = [$expectedLink];

        static::assertSame($expectedArray, $result);
    }

    /**
     * @test
     */
    public function writeShouldReturnExceptionWhenBodyElementIsNotParagraph(): void
    {
        $bodyElementMock = $this->createMock(BodyElement::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BodyElement should be instance of '.Paragraph::class);

        $this->paragraphDataTransformer->write($bodyElementMock)->read();
    }
}
