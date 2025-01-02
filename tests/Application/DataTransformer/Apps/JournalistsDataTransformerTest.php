<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps;

use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Infrastructure\Service\Thumbor;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Aliases;
use Ec\Journalist\Domain\Model\AliasId;
use Ec\Journalist\Domain\Model\Department;
use Ec\Journalist\Domain\Model\DepartmentId;
use Ec\Journalist\Domain\Model\Departments;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistId;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Ken Serikawa <kserikawa@ext.elconfidencial.com>
 */
class JournalistsDataTransformerTest extends TestCase
{
    private JournalistsDataTransformer $transformer;
    private MockObject $thumbor;

    private string $aliasId;

    protected function setUp(): void
    {
        $this->thumbor = $this->createMock(Thumbor::class);
        $this->transformer = new JournalistsDataTransformer('dev', $this->thumbor);
        $this->aliasId = '20116';
    }

    /**
     * @test
     */
    public function shouldInitialize(): void
    {
        $this->assertInstanceOf(JournalistsDataTransformer::class, $this->transformer);
    }

    /**
     * @test
     */
    public function shouldWriteAndRead(): void
    {
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);

        $this->transformer->write($this->aliasId, $journalistMock, $sectionMock);

        $result = $this->transformer->read();
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testWriteMethodSetsProperties(): void
    {
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);
        $aliasId = 'test-alias-id';

        $this->transformer->write($aliasId, $journalistMock, $sectionMock);

        $this->assertSame($aliasId, $this->getPrivateProperty($this->transformer, 'aliasId'));
        $this->assertSame($journalistMock, $this->getPrivateProperty($this->transformer, 'journalist'));
        $this->assertSame($sectionMock, $this->getPrivateProperty($this->transformer, 'section'));
    }

    /**
     * @test
     */
    public function shouldTransformAJournalist(): void
    {
        $journalistId = '5164';
        $journalistName = 'Juan Carlos';
        $journalistUrl = 'https://www.elconfidencial.dev/autores/juan-carlos-5164/';
        $photoUrl = 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png';
        $expectedThumbor = $photoUrl.'thumbor';
        $departments = [
            [
                'id' => '1',
                'name' => 'Técnico',
            ],
        ];

        $expectedAlias = [
            'id' => new AliasId($this->aliasId),
            'name' => $journalistName,
            'private' => false,
        ];

        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);
        $aliasesMock = $this->createMock(Aliases::class);
        $aliasIdMock = $this->createMock(AliasId::class);

        $journalistIdMock = $this->createMock(JournalistId::class);
        $departmentsMock = $this->createMock(Departments::class);

        $journalistMock->method('id')
            ->willReturn($journalistIdMock);

        $journalistIdMock
            ->method('id')
            ->willReturn($journalistId);

        $journalistMock->method('aliases')
            ->willReturn($aliasesMock);

        $aliasItemMock = $this->createConfiguredMock(Alias::class, $expectedAlias);

        $aliasItemMock->expects(static::once())
            ->method('name')
            ->willReturn($journalistName);

        $aliasItemMock->method('id')
            ->willReturn($aliasIdMock);

        $aliasIdMock->method('id')
            ->willReturn($this->aliasId);

        $journalistMock->expects(static::once())
            ->method('departments')
            ->willReturn($departmentsMock);

        $journalistMock->expects(static::once())
            ->method('name')
            ->willReturn($journalistName);

        $journalistMock->expects(static::once())
            ->method('blogPhoto')
            ->willReturn('');

        $journalistMock->method('photo')
            ->willReturn($photoUrl);

        $this->thumbor->expects(static::once())
            ->method('createJournalistImage')
            ->with($photoUrl)
            ->willReturn($expectedThumbor);

        $bodyIterator = new \ArrayIterator([$aliasItemMock]);
        $aliasesMock
            ->method('rewind')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->rewind();
            });

        $aliasesMock
            ->method('current')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->current();
            });

        $aliasesMock
            ->method('key')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->key();
            });

        $aliasesMock
            ->method('next')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->next();
            });

        $aliasesMock
            ->method('valid')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->valid();
            });

        $result = $this->transformer
            ->write($this->aliasId, $journalistMock, $sectionMock)
            ->read();

        $expectedJournalist = [
            'journalistId' => $journalistId,
            'aliasId' => $this->aliasId,
            'name' => $journalistName,
            'url' => $journalistUrl,
            'photo' => $expectedThumbor,
            'departments' => [],
        ];

        $this->assertEquals($expectedJournalist['journalistId'], $result['journalistId']);
        $this->assertEquals($expectedJournalist['aliasId'], $result['aliasId']);
        $this->assertEquals($expectedJournalist['name'], $result['name']);
        $this->assertEquals($expectedJournalist['url'], $result['url']);
        $this->assertEquals($expectedJournalist['departments'], $result['departments']);
        $this->assertEquals(
            $expectedJournalist['photo'],
            $result['photo']
        );

        $this->assertEquals($expectedJournalist, $result);
    }

    /**
     * @test
     */
    public function shouldTransformAJournalistWhenHasBlogPhoto(): void
    {
        $journalistId = '5164';
        $journalistName = 'Juan Carlos';
        $journalistUrl = 'https://www.elconfidencial.dev/autores/juan-carlos-5164/';
        $photoUrl = 'https://images.ecestaticos.dev/FGsmLp_UG1BtJpvlkXA8tzDqltY=/dev.f.elconfidencial.com/journalist/953/855/f9d/953855f9d072b9cd509c3f6c5f9dc77f.png';
        $expectedThumbor = $photoUrl.'thumbor';
        $departmentId = new DepartmentId('1');
        $departmentName = 'Técnico';
        $expectedDepartment = [
            'id' => $departmentId,
            'name' => $departmentName,
        ];

        $expectedAlias = [
            'id' => new AliasId($this->aliasId),
            'name' => $journalistName,
            'private' => false,
        ];

        $journalistMock = $this->createMock(Journalist::class);
        $journalistIdMock = $this->createMock(JournalistId::class);

        $sectionMock = $this->createMock(Section::class);

        $aliasesMock = $this->createMock(Aliases::class);
        $aliasIdMock = $this->createMock(AliasId::class);

        $departmentsMock = $this->createMock(Departments::class);
        $departmentMock = $this->createMock(Department::class);
        $departmentIdMock = $this->createMock(DepartmentId::class);

        $journalistMock->method('id')
            ->willReturn($journalistIdMock);

        $journalistIdMock
            ->method('id')
            ->willReturn($journalistId);

        $journalistMock->method('aliases')
            ->willReturn($aliasesMock);

        $aliasItemMock = $this->createConfiguredMock(Alias::class, $expectedAlias);

        $aliasItemMock->expects(static::once())
            ->method('name')
            ->willReturn($journalistName);

        $aliasItemMock->method('id')
            ->willReturn($aliasIdMock);

        $aliasIdMock->method('id')
            ->willReturn($this->aliasId);

        $journalistMock->expects(static::once())
            ->method('departments')
            ->willReturn($departmentsMock);

        $journalistMock->expects(static::once())
            ->method('name')
            ->willReturn($journalistName);

        $journalistMock->expects(static::exactly(2))
            ->method('blogPhoto')
            ->willReturn($photoUrl);

        $this->thumbor->expects(static::once())
            ->method('createJournalistImage')
            ->with($photoUrl)
            ->willReturn($expectedThumbor);

        $bodyIterator = new \ArrayIterator([$aliasItemMock]);
        $aliasesMock
            ->method('rewind')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->rewind();
            });

        $aliasesMock
            ->method('current')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->current();
            });

        $aliasesMock
            ->method('key')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->key();
            });

        $aliasesMock
            ->method('next')
            ->willReturnCallback(static function () use ($bodyIterator) {
                $bodyIterator->next();
            });

        $aliasesMock
            ->method('valid')
            ->willReturnCallback(static function () use ($bodyIterator) {
                return $bodyIterator->valid();
            });

        $departmentItemMock = $this->createConfiguredMock(Department::class, $expectedDepartment);
        $departmentItemMock->expects(static::once())
            ->method('id')
            ->willReturn($departmentId);

        $departmentIdMock->method('id')
            ->willReturn('1');

        $bodyIteratorDepartments = new \ArrayIterator([$departmentItemMock]);
        $departmentsMock
            ->method('rewind')
            ->willReturnCallback(static function () use ($bodyIteratorDepartments) {
                $bodyIteratorDepartments->rewind();
            });

        $departmentsMock
            ->method('current')
            ->willReturnCallback(static function () use ($bodyIteratorDepartments) {
                return $bodyIteratorDepartments->current();
            });

        $departmentsMock
            ->method('key')
            ->willReturnCallback(static function () use ($bodyIteratorDepartments) {
                return $bodyIteratorDepartments->key();
            });

        $departmentsMock
            ->method('next')
            ->willReturnCallback(static function () use ($bodyIteratorDepartments) {
                $bodyIteratorDepartments->next();
            });

        $departmentsMock
            ->method('valid')
            ->willReturnCallback(static function () use ($bodyIteratorDepartments) {
                return $bodyIteratorDepartments->valid();
            });

        $result = $this->transformer
            ->write($this->aliasId, $journalistMock, $sectionMock)
            ->read();

        $expectedJournalist = [
            'journalistId' => $journalistId,
            'aliasId' => $this->aliasId,
            'name' => $journalistName,
            'url' => $journalistUrl,
            'photo' => $expectedThumbor,
            'departments' => [
                $expectedDepartment,
            ],
        ];

        $this->assertEquals($expectedJournalist['journalistId'], $result['journalistId']);
        $this->assertEquals($expectedJournalist['aliasId'], $result['aliasId']);
        $this->assertEquals($expectedJournalist['name'], $result['name']);
        $this->assertEquals($expectedJournalist['url'], $result['url']);
        $this->assertEquals($expectedJournalist['departments'], $result['departments']);
        $this->assertEquals(
            $expectedJournalist['photo'],
            $result['photo']
        );

        $this->assertEquals($expectedJournalist, $result);
    }

    /**
     * @test
     */
    public function shouldReadTransformsJournalistData(): void
    {
        $aliasesMock = $this->createMock(Aliases::class);
        $journalistMock = $this->createMock(Journalist::class);
        $sectionMock = $this->createMock(Section::class);

        $journalistMock->method('aliases')
            ->willReturn($aliasesMock);

        $aliasesMock->method('hasAlias')
            ->willReturn(true);

        $journalistMock->expects(static::once())
            ->method('aliases')
            ->willReturn($aliasesMock);

        $this->transformer->write('test-alias-id', $journalistMock, $sectionMock);

        $result = $this->transformer->read();

        $this->assertIsArray($result);
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

        $this->transformer->write($this->aliasId, $journalistMock, $sectionMock);

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

        $this->transformer->write($this->aliasId, $journalistMock, $sectionMock);

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

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
