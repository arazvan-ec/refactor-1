<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps;

use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Infrastructure\Service\Thumbor;
use Ec\Journalist\Domain\Model\Aliases;
use Ec\Journalist\Domain\Model\AliasId;
use Ec\Journalist\Domain\Model\Departments;
use Ec\Journalist\Domain\Model\JournalistId;
use Ec\Journalist\Domain\Model\Journalists;
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

    /**
     * @test
     */
    public function shouldWriteAndRead(): void
    {
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);

        $this->transformer->write([$journalistMock], $sectionMock);

        $result = $this->transformer->read();
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function shouldTransformAJournalist(): void
    {
        $aliasId = '20116';
        $journalistId = '5164';
        $journalistName = 'Juan Carlos';
        $journalistUrl = 'https://www.elconfidencial.dev/autores/juan-carlos-5164/';
        $photoUrl = 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png';
        $departments = [
            [
                'id' => '1',
                'name' => 'Técnico',
            ],
        ];
        $expectedJournalist = [
            $aliasId => [
                'journalistId' => $journalistId,
                'aliasId' => $aliasId,
                'name' => $journalistName,
                'url' => $journalistUrl,
                'photo' => $photoUrl,
                'departments' => $departments,
            ],
        ];
        $journalistMock = $this->createMock(Journalist::class);
        $journalistsMock = $this->createMock(Journalists::class);
        $sectionMock = $this->createMock(Section::class);
        $aliasesMock = $this->createMock(Aliases::class);
        $journalistIdMock = $this->createMock(JournalistId::class);
        $departmentsMock = $this->createMock(Departments::class);
        $aliasMock = $this->createMock(Alias::class);
        $aliasIdMock = $this->createMock(AliasId::class);

        $aliasesMock->method('addAlias')
            ->with($aliasMock)
            ->willReturnSelf();

        $aliasMock->expects(static::once())
            ->method('id')
            ->willReturn($aliasIdMock);

        $aliasIdMock->expects(static::once())
            ->method('id')
            ->willReturn($aliasId);

        $journalistMock->expects(static::once())
            ->method('aliases')
            ->willReturn($aliasesMock);

        $journalistMock->expects(static::once())
            ->method('id')
            ->willReturn($journalistIdMock);

        $journalistIdMock->expects(static::once())
            ->method('id')
            ->willReturn($aliasId);

        $aliasMock->expects(static::once())
            ->method('name')
            ->willReturn($journalistName);

        $journalistMock->expects(static::once())
            ->method('departments')
            ->willReturn($departmentsMock);

        $journalistMock->expects(static::once())
            ->method('photo')
            ->willReturn($photoUrl);

        $result = $this->transformer->write([$journalistMock], $sectionMock)->read();

        $this->assertEquals($expectedJournalist, $result);
    }

    /**
     * @test
     */
    public function shouldReturnJournalistUrlForPrivateAlias(): void
    {
        $siteId = 'elconfidencial';

        $aliasMock = $this->createMock(Alias::class);
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);

        $aliasMock->method('private')
            ->willReturn(true);
        $sectionMock->method('siteId')
            ->willReturn($siteId);
        $sectionMock->method('getPath')
            ->willReturn('path');
        $sectionMock->method('isBlog')
            ->willReturn(false);

        $this->transformer->write([], $sectionMock);

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('journalistUrl');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->transformer, [$aliasMock, $journalistMock]);
        $this->assertStringContainsString('https://www.elconfidencial.dev/path', $result);
    }

    /**
     * @test
     */
    public function shouldReturnJournalistUrlForNonPrivateAlias(): void
    {
        $siteId = 'elconfidencial';

        $aliasMock = $this->createMock(Alias::class);
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);

        $aliasMock->method('private')
            ->willReturn(false);
        $sectionMock->method('siteId')
            ->willReturn($siteId);
        $sectionMock->method('getPath')
            ->willReturn('path');

        $this->transformer->write([], $sectionMock);

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('journalistUrl');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->transformer, [$aliasMock, $journalistMock]);
        $this->assertStringContainsString('https://www.elconfidencial.dev/autores/-/', $result);
    }

    /**
     * @test
     */
    public function shouldReturnPhotoUrlWithPhoto(): void
    {
        $journalistMock = $this->createMock(Journalist::class);

        $journalistMock->expects(static::exactly(2))
            ->method('photo')
            ->willReturn('blog-photo.jpg');
        $this->thumbor->expects(static::once())
            ->method('createJournalistImage')
            ->willReturn('https://thumbor.example.com/blog-photo.jpg');

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('photoUrl');

        $result = $method->invokeArgs($this->transformer, [$journalistMock]);
        $this->assertEquals('https://thumbor.example.com/blog-photo.jpg', $result);
    }

    /**
     * @test
     */
    public function shouldReturnPhotoUrlWithBlogPhoto(): void
    {
        $blogPhoto = 'blog-photo.jpg';
        $journalistMock = $this->createMock(Journalist::class);

        $journalistMock->expects(static::exactly(2))
            ->method('blogPhoto')
            ->willReturn($blogPhoto);

        $this->thumbor->expects(static::once())
            ->method('createJournalistImage')
            ->willReturn('https://thumbor.example.com/blog-photo.jpg');

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('photoUrl');

        $result = $method->invokeArgs($this->transformer, [$journalistMock]);
        $this->assertEquals('https://thumbor.example.com/blog-photo.jpg', $result);
    }

    /**
     * @test
     */
    public function shouldReturnEmptyPhotoUrl(): void
    {
        $journalistMock = $this->createMock(Journalist::class);

        $journalistMock->expects(static::exactly(1))
            ->method('blogPhoto')
            ->willReturn('');
        $journalistMock->expects(static::exactly(1))
            ->method('photo')
            ->willReturn('');

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('photoUrl');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->transformer, [$journalistMock]);
        $this->assertEquals('', $result);
    }
}
