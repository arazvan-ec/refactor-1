<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps;

use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Infrastructure\Service\Thumbor;
use PHPUnit\Framework\TestCase;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Section\Domain\Model\Section;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class JournalistsDataTransformerTest extends TestCase
{
    private JournalistsDataTransformer $transformer;

    protected function setUp(): void
    {
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->transformer = new JournalistsDataTransformer('dev', $this->thumbor);
    }

    public function testWriteAndRead(): void
    {
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);

        $this->transformer->write([$journalistMock], $sectionMock);

        $result = $this->transformer->read();
        $this->assertIsArray($result);
    }

    public function testTransformerJournalists(): void
    {
        $journalistMock = $this->createMock(Journalist::class);
        $aliasMock = $this->createMock(Alias::class);

        $aliasMock->method('id')->willReturn((object)['id' => fn() => 'alias-123']);
        $aliasMock->method('name')->willReturn('Alias Name');
        $aliasMock->method('private')->willReturn(false);

        $journalistMock->method('aliases')->willReturn([$aliasMock]);
        $journalistMock->method('id')->willReturn((object)['id' => fn() => 'journalist-123']);
        $journalistMock->method('departments')->willReturn([]);
        $journalistMock->method('photo')->willReturn('');
        $journalistMock->method('blogPhoto')->willReturn('');

        $sectionMock = $this->createMock(Section::class);
        $sectionMock->method('siteId')->willReturn('site-id');
        $sectionMock->method('getPath')->willReturn('path');
        $sectionMock->method('isBlog')->willReturn(false);

        $this->transformer->write([$journalistMock], $sectionMock);
        $result = $this->transformer->read();

        $this->assertArrayHasKey('alias-123', $result);
        $this->assertEquals('Alias Name', $result['alias-123']['name']);
    }

    public function testJournalistUrlForPrivateAlias(): void
    {
        $aliasMock = $this->createMock(Alias::class);
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);

        $aliasMock->method('private')->willReturn(true);
        $sectionMock->method('siteId')->willReturn('site-id');
        $sectionMock->method('getPath')->willReturn('path');
        $sectionMock->method('isBlog')->willReturn(false);

        $this->transformer->write([], $sectionMock);

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('journalistUrl');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->transformer, [$aliasMock, $journalistMock]);
        $this->assertStringContainsString('https://www.site-id.path/', $result);
    }

    public function testPhotoUrlWithBlogPhoto(): void
    {
        $journalistMock = $this->createMock(Journalist::class);

        $journalistMock->method('blogPhoto')->willReturn('blog-photo.jpg');
        $this->thumborMock->method('createJournalistImage')->willReturn('https://thumbor.example.com/blog-photo.jpg');

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('photoUrl');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->transformer, [$journalistMock]);
        $this->assertEquals('https://thumbor.example.com/blog-photo.jpg', $result);
    }

    public function testPhotoUrlWithPhoto(): void
    {
        $journalistMock = $this->createMock(Journalist::class);

        $journalistMock
