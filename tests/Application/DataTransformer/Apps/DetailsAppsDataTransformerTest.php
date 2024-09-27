<?php

namespace App\Tests\Application\DataTransformer\Apps;

use App\Application\DataTransformer\Apps\DetailsAppsDataTransformer;
use App\Infrastructure\Service\Thumbor;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Journalist\Domain\Model\Alias;
use Ec\Journalist\Domain\Model\Aliases;
use Ec\Journalist\Domain\Model\AliasId;
use Ec\Journalist\Domain\Model\Department;
use Ec\Journalist\Domain\Model\DepartmentId;
use Ec\Journalist\Domain\Model\Departments;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistId;
use Ec\Section\Domain\Model\Section;
use Ec\Section\Domain\Model\SectionId;
use PHPUnit\Framework\TestCase;

class DetailsAppsDataTransformerTest extends TestCase
{
    private DetailsAppsDataTransformer $transformer;
    private Thumbor $thumbor;

    protected function setUp(): void
    {
        $thumborServerUrl = 'https://thumbor.server.url';
        $thumborSecret = 'thumbor-secret';
        $awsBucket = 'aws-bucket';

        $this->thumbor = $this->createMock(Thumbor::class);
        $this->transformer = new DetailsAppsDataTransformer('dev', $thumborServerUrl, $thumborSecret, $awsBucket);
    }

    /**
     * @test
     */
    public function writeAndReadShouldReturnCorrectArray(): void
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

    /**
     * @test
     */
    public function transformerEditorialShouldReturnCorrectEditorialArray(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $editorialId = $this->createMock(EditorialId::class);

        $editorial->method('id')->willReturn($editorialId);
        $editorialId->method('id')->willReturn('12345');

        $this->transformer->write($editorial, [], $this->createMock(Section::class));
        $result = $this->transformer->read();

        $this->assertEquals('12345', $result['id']);
    }

    /**
     * @test
     *
     * @dataProvider \App\Tests\Application\DataTransformer\Apps\DataProvider\EditorialForAppsDataProvider::getPhotoOrBlogPhotoJournalist()
     */
    public function transformerJournalistsNoPrivateAlias(string $method, string $value): void
    {
        $aliasId = $this->createMock(AliasId::class);
        $aliasId->method('id')->willReturn('aliasId');

        $alias = $this->createMock(Alias::class);
        $alias->method('id')->willReturn($aliasId);
        $alias->method('name')->willReturn('Alias Name');
        $alias->method('private')->willReturn(false);

        $departmentId = $this->createMock(DepartmentId::class);
        $departmentId->method('id')->willReturn('departmentId');

        $department = $this->createMock(Department::class);
        $department->method('id')->willReturn($departmentId);
        $department->method('name')->willReturn('DepartmentName');

        $aliases = new Aliases();
        $aliases->addAlias($alias);

        $departments = new Departments();
        $departments->addDepartment($department);

        $journalistId = $this->createMock(JournalistId::class);
        $journalistId->method('id')->willReturn('journalistId');

        $journalist = $this->createMock(Journalist::class);
        $journalist->method('id')->willReturn($journalistId);
        $journalist->method('name')->willReturn('JournalistName');
        $journalist->method('aliases')->willReturn($aliases);
        $journalist->method('departments')->willReturn($departments);
        $journalist->method($method)->willReturn($value);

        $journalists = ['aliasId' => $journalist];

        $sectionId = $this->createMock(SectionId::class);
        $sectionId->method('id')->willReturn('sectionId');

        $section = $this->createMock(Section::class);
        $section->method('id')->willReturn($sectionId);
        $section->method('name')->willReturn('SectionName');
        $section->method('siteId')->willReturn('siteId');
        $section->method('isBlog')->willReturn(false);
        $section->method('getPath')->willReturn('section-path');

        $editorial = $this->createMock(Editorial::class);

        $this->transformer->write($editorial, $journalists, $section);
        $result = $this->transformer->read();

        $this->assertArrayHasKey('signatures', $result);
        $this->assertEquals($journalist->id()->id(), $result['signatures'][0]['journalistId']);
        $this->assertEquals($aliasId->id(), $result['signatures'][0]['aliasId']);
        $this->assertEquals($alias->name(), $result['signatures'][0]['name']);
        $this->assertEquals(
            'https://www.elconfidencial.dev/autores/JournalistName-'.$journalist->id()->id().'/',
            $result['signatures'][0]['url']
        );
        if ('blogPhoto' === $method) {
            $this->assertEquals(
                'https://thumbor.server.url/oRqpV6YYMVMlT2WPXboH69LRMQ0=/aws-bucket/journalist/blo/gPh/oto/blogPhoto.jpg',
                $result['signatures'][0]['photo']
            );
        } else {
            $this->assertEquals(
                'https://thumbor.server.url/TX0gpA4ve-eY4X8pGqXXCiGvmto=/aws-bucket/journalist/pho/to./jpg/photo.jpg',
                $result['signatures'][0]['photo']
            );
        }
        $this->assertArrayHasKey('departments', $result['signatures'][0]);
        $this->assertEquals('departmentId', $result['signatures'][0]['departments'][0]['id']);
        $this->assertEquals('DepartmentName', $result['signatures'][0]['departments'][0]['name']);
    }

    /**
     * @test
     *
     * @dataProvider \App\Tests\Application\DataTransformer\Apps\DataProvider\EditorialForAppsDataProvider::getPhotoOrBlogPhotoJournalist()
     */
    public function transformerJournalistsWithPrivateAlias(string $method, string $value): void
    {
        $aliasId = $this->createMock(AliasId::class);
        $aliasId->method('id')->willReturn('aliasId');

        $alias = $this->createMock(Alias::class);
        $alias->method('id')->willReturn($aliasId);
        $alias->method('name')->willReturn('Alias Name');
        $alias->method('private')->willReturn(true);

        $departmentId = $this->createMock(DepartmentId::class);
        $departmentId->method('id')->willReturn('departmentId');

        $department = $this->createMock(Department::class);
        $department->method('id')->willReturn($departmentId);
        $department->method('name')->willReturn('DepartmentName');

        $aliases = new Aliases();
        $aliases->addAlias($alias);

        $departments = new Departments();
        $departments->addDepartment($department);

        $journalistId = $this->createMock(JournalistId::class);
        $journalistId->method('id')->willReturn('journalistId');

        $journalist = $this->createMock(Journalist::class);
        $journalist->method('id')->willReturn($journalistId);
        $journalist->method('name')->willReturn('JournalistName');
        $journalist->method('aliases')->willReturn($aliases);
        $journalist->method('departments')->willReturn($departments);
        $journalist->method($method)->willReturn($value);

        $journalists = ['aliasId' => $journalist];

        $sectionId = $this->createMock(SectionId::class);
        $sectionId->method('id')->willReturn('sectionId');

        $section = $this->createMock(Section::class);
        $section->method('id')->willReturn($sectionId);
        $section->method('name')->willReturn('SectionName');
        $section->method('siteId')->willReturn('siteId');
        $section->method('isBlog')->willReturn(false);
        $section->method('getPath')->willReturn('section-path');

        $editorial = $this->createMock(Editorial::class);

        $this->transformer->write($editorial, $journalists, $section);
        $result = $this->transformer->read();

        $this->assertArrayHasKey('signatures', $result);
        $this->assertEquals($journalist->id()->id(), $result['signatures'][0]['journalistId']);
        $this->assertEquals($aliasId->id(), $result['signatures'][0]['aliasId']);
        $this->assertEquals($alias->name(), $result['signatures'][0]['name']);
        $this->assertEquals(
            'https://www.elconfidencial.dev/section-path',
            $result['signatures'][0]['url']
        );
        if ('blogPhoto' === $method) {
            $this->assertEquals(
                'https://thumbor.server.url/oRqpV6YYMVMlT2WPXboH69LRMQ0=/aws-bucket/journalist/blo/gPh/oto/blogPhoto.jpg',
                $result['signatures'][0]['photo']
            );
        } else {
            $this->assertEquals(
                'https://thumbor.server.url/TX0gpA4ve-eY4X8pGqXXCiGvmto=/aws-bucket/journalist/pho/to./jpg/photo.jpg',
                $result['signatures'][0]['photo']
            );
        }
        $this->assertArrayHasKey('departments', $result['signatures'][0]);
        $this->assertEquals('departmentId', $result['signatures'][0]['departments'][0]['id']);
        $this->assertEquals('DepartmentName', $result['signatures'][0]['departments'][0]['name']);
    }

    /**
     * @test
     */
    public function transformerSectionShouldReturnCorrectSection(): void
    {
        $section = $this->createMock(Section::class);
        $sectionId = $this->createMock(SectionId::class);
        $section->method('id')->willReturn($sectionId);
        $section->method('name')->willReturn('Section Name');
        $section->method('getPath')->willReturn('section-path');
        $section->method('siteId')->willReturn('siteId');
        $section->method('isBlog')->willReturn(false);
        $editorial = $this->createMock(Editorial::class);
        $journalist = $this->createMock(Journalist::class);
        $journalists = ['aliasId' => $journalist];

        $this->transformer->write($editorial, $journalists, $section);
        $result = $this->transformer->read();

        $this->assertEquals($sectionId, $result['section']['id']);
        $this->assertEquals($section->name(), $result['section']['name']);
        $this->assertEquals('https://www.elconfidencial.dev/section-path', $result['section']['url']);
    }
}
