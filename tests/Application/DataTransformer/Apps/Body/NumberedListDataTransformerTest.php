<?php

declare(strict_types=1);

/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\NumberedListDataTransformer;
use Ec\Editorial\Domain\Model\Body\Link;
use Ec\Editorial\Domain\Model\Body\ListItem;
use Ec\Editorial\Domain\Model\Body\NumberedList;
use PHPUnit\Framework\TestCase;

/**
 * @author Antonio Jose Cerezo Aranda <acerezo@elconfidencial.com>
 */
class NumberedListDataTransformerTest extends TestCase
{
    private NumberedListDataTransformer $numberedListTransformer;

    protected function setUp(): void
    {
        $this->numberedListTransformer = new NumberedListDataTransformer();
    }

    protected function tearDown(): void
    {
        unset($this->numberedListTransformer);
    }

    /**
     * @test
     */
    public function canTransformShouldReturnNumberedListString(): void
    {
        static::assertSame(NumberedList::class, $this->numberedListTransformer->canTransform());
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
        $bodyElementMock = $this->createConfiguredMock(NumberedList::class, $expectedArray);

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

        $result = $this->numberedListTransformer->write($bodyElementMock)->read();

        $expectedListItem['links'] = [$expectedLink];
        $expectedArray['items'] = [$expectedListItem];

        static::assertSame($expectedArray, $result);
    }
}
