<?php

namespace App\Tests\Application\DataTransformer\Apps;

use App\Application\DataTransformer\Apps\DetailsAppsDataTransformer;
use App\Infrastructure\Service\Thumbor;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\TestCase;

class DetailsAppsDataTransformerTest extends TestCase
{
    private DetailsAppsDataTransformer $transformer;
    private Thumbor $thumbor;

    protected function setUp(): void
    {
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->transformer = new DetailsAppsDataTransformer('jpg', $this->thumbor);
    }

    public function testWriteAndRead()
    {
        $editorial = $this->createMock(Editorial::class);
        $journalist = $this->createMock(Journalist::class);
        $section = $this->createMock(Section::class);

        $journalists = ['aliasId' => $journalist];

        $this->transformer->write($editorial, $journalists, $section);
        $result = $this->transformer->read();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('signatures', $result);
        $this->assertArrayHasKey('section', $result);
    }

    public function testTransformerEditorial()
    {
        $editorial = $this->createMock(Editorial::class);
        $editorialId = $this->createMock(EditorialId::class);

        $editorial->method('id')->willReturn($editorialId);
        $editorialId->method('id')->willReturn('12345');

        $this->transformer->write($editorial, [], $this->createMock(Section::class));
        $result = $this->transformer->read();

        $this->assertEquals('12345', $result['id']);
    }

    public function testTransformerJournalists()
    {
        $journalist = $this->createMock(Journalist::class);
        $alias = $this->createMock(Alias::class);
        $aliasId = 'aliasId';

        $journalist->method('aliases')->willReturn([$alias]);
        $alias->method('id')->willReturn($aliasId);
        $alias->method('name')->willReturn('John Doe');

        $this->thumbor->method('createJournalistImage')->willReturn('http://image.url');

        $this->transformer->write($this->createMock(Editorial::class), [$aliasId => $journalist], $this->createMock(Section::class));
        $result = $this->transformer->read();

        $this->assertNotEmpty($result['signatures']);
        $this->assertEquals('John Doe', $result['signatures'][0]['name']);
        $this->assertEquals('http://image.url', $result['signatures'][0]['photo']);
    }

    public function testTransformerSection()
    {
        $section = $this->createMock(Section::class);
        $section->method('id')->willReturn('sectionId');
        $section->method('name')->willReturn('Section Name');
        $section->method('getPath')->willReturn('section-path');
        $section->method('siteId')->willReturn('siteId');
        $section->method('isBlog')->willReturn(false);

        $this->transformer->write($this->createMock(Editorial::class), [], $section);
        $result = $this->transformer->read();

        $this->assertEquals('sectionId', $result['section']['id']);
        $this->assertEquals('Section Name', $result['section']['name']);
        $this->assertEquals('https://www.siteId.section-path', $result['section']['url']);
    }
}
