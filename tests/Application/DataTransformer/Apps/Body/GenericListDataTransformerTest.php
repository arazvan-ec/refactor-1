<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\GenericListDataTransformer;
use Ec\Editorial\Domain\Model\Body\ListItem;
use Ec\Editorial\Domain\Model\Body\UnorderedList;
use PHPUnit\Framework\TestCase;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\Application\DataTransformer\Apps\Body\GenericListDataTransformer
 */
class GenericListDataTransformerTest extends TestCase
{
    private GenericListDataTransformer $dataTransformer;

    protected function setUp(): void
    {
        $this->dataTransformer = $this->getMockForAbstractClass(GenericListDataTransformer::class);
    }

    /**
     * @test
     */
    public function readShouldReturnExpectedArray(): void
    {
        $type = 'bodyTag';
        $content = 'content';
        $links = [];

        $genericListMock = $this->createMock(UnorderedList::class);
        $listItemMock = $this->createMock(ListItem::class);

        $genericListMock->method('type')
            ->willReturn($type);

        $bodyIterator = new \ArrayIterator([$listItemMock]);

        $genericListMock
            ->method('rewind')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->rewind();
            });

        $genericListMock
            ->method('current')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->current();
            });

        $genericListMock
            ->method('key')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->key();
            });

        $genericListMock
            ->method('next')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->next();
            });

        $genericListMock
            ->method('valid')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->valid();
            });

        $listItemMock->method('type')->willReturn($type);
        $listItemMock->method('content')->willReturn($content);

        $expected = [
            'type' => $type,
            'items' => [
                0 => [
                    'type' => $type,
                    'content' => $content,
                    'links' => $links,
                ],
            ],
        ];

        $result = $this->dataTransformer->write($genericListMock)->read();

        static::assertSame($expected, $result);
    }
}
