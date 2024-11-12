<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\UnorderedListDataTransformer;
use Assert\InvalidArgumentException;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\Link;
use Ec\Editorial\Domain\Model\Body\ListItem;
use Ec\Editorial\Domain\Model\Body\UnorderedList;
use PHPUnit\Framework\TestCase;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\Application\DataTransformer\Apps\Body\UnorderedListDataTransformer
 */
class UnorderedListDataTransformerTest extends TestCase
{
    private UnorderedListDataTransformer $unorderedListDataTransformer;

    protected function setUp(): void
    {
        $this->unorderedListDataTransformer = new UnorderedListDataTransformer();
    }

    /**
     * @test
     */
    public function canTransformShouldReturnGenericListString(): void
    {
        static::assertSame(UnorderedList::class, $this->unorderedListDataTransformer->canTransform());
    }

    /**
     * @test
     */
    public function readShouldReturnExpectedArray(): void
    {
        $expectedLink = [
            'type' => 'link',
            'content' => 'content',
            'url' => 'url',
            'target' => 'target',
        ];
        $linkMock = $this->createConfiguredMock(Link::class, $expectedLink);

        $expectedListItem = [
            'type' => 'listitem',
            'content' => 'content',
            'links' => [$linkMock],
        ];
        $listItemMock = $this->createConfiguredMock(ListItem::class, $expectedListItem);

        $bodyIterator = new \ArrayIterator([$listItemMock]);

        $expectedArray = [
            'type' => 'unorderedlist',
        ];
        $bodyElementMock = $this->createConfiguredMock(UnorderedList::class, $expectedArray);

        $bodyElementMock
            ->method('rewind')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->rewind();
            });

        $bodyElementMock
            ->method('current')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->current();
            });

        $bodyElementMock
            ->method('key')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->key();
            });

        $bodyElementMock
            ->method('next')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->next();
            });

        $bodyElementMock
            ->method('valid')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->valid();
            });

        $result = $this->unorderedListDataTransformer->write($bodyElementMock)->read();

        $expectedListItem['links'] = [$expectedLink];
        $expectedArray['items'] = [$expectedListItem];

        static::assertSame($expectedArray, $result);
    }

    /**
     * @test
     */
    public function writeShouldReturnExceptionWhenBodyElementIsNotUnorderedList(): void
    {
        $bodyElementMock = $this->createMock(BodyElement::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Class "%s" was expected to be instanceof of "%s" but is not.',
                get_class($bodyElementMock),
                UnorderedList::class
            )
        );

        $this->unorderedListDataTransformer->write($bodyElementMock)->read();
    }
}
