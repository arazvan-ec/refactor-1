<?php

namespace App\Tests\Application\DataTransformer\Apps;

use App\Application\DataTransformer\Apps\DetailsAppsDataTransformer;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\EditorialTitles;
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
use Ec\Tag\Domain\Model\Tag;
use Ec\Tag\Domain\Model\TagId;
use Ec\Tag\Domain\Model\TagType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DetailsAppsDataTransformerTest extends TestCase
{
    private DetailsAppsDataTransformer $transformer;

    /** @var QueryLegacyClient|MockObject */
    private QueryLegacyClient $queryLegacyClient;

    protected function setUp(): void
    {
        $thumborServerUrl = 'https://thumbor.server.url';
        $thumborSecret = 'thumbor-secret';
        $awsBucket = 'aws-bucket';

        $this->queryLegacyClient = $this->createMock(QueryLegacyClient::class);
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
        $tag = $this->createMock(Tag::class);

        $journalists = ['aliasId' => $journalist];

        $this->transformer->write($editorial, $journalists, $section, [$tag]);
        $result = $this->transformer->read();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('signatures', $result);
        $this->assertArrayHasKey('section', $result);
        $this->assertArrayHasKey('tags', $result);
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

        $editorial->method('editorialType')->willReturn('news');
        $editorial->method('editorialTitles')->willReturn($this->createMock(EditorialTitles::class));
        $editorial->method('lead')->willReturn('Lead text');
        $editorial->method('publicationDate')->willReturn(new \DateTime('2023-01-01 00:00:00'));
        $editorial->method('endOn')->willReturn(new \DateTime('2023-01-02 00:00:00'));
        $editorial->method('indexed')->willReturn(true);
        $editorial->method('isDeleted')->willReturn(false);
        $editorial->method('isPublished')->willReturn(true);
        $editorial->method('closingModeId')->willReturn('1');
        $editorial->method('canComment')->willReturn(true);
        $editorial->method('isBrand')->willReturn(false);
        $editorial->method('isAmazonOnsite')->willReturn(false);
        $editorial->method('contentType')->willReturn('article');
        $editorial->method('canonicalEditorialId')->willReturn('54321');
        $editorial->method('urlDate')->willReturn(new \DateTime('2023-01-01 00:00:00'));
        $editorial->method('body')->willReturn($this->createMock(Body::class));

        $this->queryLegacyClient->method('findCommentsByEditorialId')->willReturn(['options' => ['totalrecords' => 10]]);

        $this->transformer->write($editorial, [], $this->createMock(Section::class), []);
        $result = $this->transformer->read();

        $this->assertEquals('12345', $result['id']);
        $this->assertEquals('Lead text', $result['lead']);
        $this->assertEquals('2023-01-01 00:00:00', $result['publicationDate']);
        $this->assertEquals('2023-01-02 00:00:00', $result['endOn']);
        $this->assertEquals('registry', $result['closingModeId']);
        $this->assertEquals(true, $result['indexable']);
        $this->assertEquals(false, $result['deleted']);

        $this->assertEquals(true, $result['published']);
        $this->assertEquals(true, $result['commentable']);
        $this->assertEquals(false, $result['isAmazonOnsite']);
        $this->assertEquals('article', $result['contentType']);
        $this->assertEquals('54321', $result['canonicalEditorialId']);
        $this->assertEquals('2023-01-01 00:00:00', $result['urlDate']);

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
        $tag = $this->createMock(Tag::class);

        $this->transformer->write($editorial, $journalists, $section, [$tag]);
        $result = $this->transformer->read();

        $this->assertArrayHasKey('signatures', $result);
        $this->assertEquals($journalist->id()->id(), $result['signatures'][0]['journalistId']);
        $this->assertEquals($aliasId->id(), $result['signatures'][0]['aliasId']);
        $this->assertEquals($alias->name(), $result['signatures'][0]['name']);
        $this->assertEquals(
            'https://www.elconfidencial.dev/autores/journalistname-'.$journalist->id()->id().'/',
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
        $tag = $this->createMock(Tag::class);

        $this->transformer->write($editorial, $journalists, $section, [$tag]);
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
        $tag = $this->createMock(Tag::class);

        $this->transformer->write($editorial, $journalists, $section, [$tag]);
        $result = $this->transformer->read();

        $this->assertEquals($sectionId, $result['section']['id']);
        $this->assertEquals($section->name(), $result['section']['name']);
        $this->assertEquals('https://www.elconfidencial.dev/section-path', $result['section']['url']);
    }

    /**
     * @test
     */
    public function transformerTagsShouldReturnCorrectTags(): void
    {
        $editorial = $this->createMock(Editorial::class);
        $journalist = $this->createMock(Journalist::class);
        $journalists = ['aliasId' => $journalist];
        $section = $this->createMock(Section::class);
        $tag = $this->createMock(Tag::class);
        $tagId = $this->createMock(TagId::class);
        $tagId->method('id')->willReturn('tagId');
        $tag->method('id')->willReturn($tagId);
        $tag->method('name')->willReturn('Tag Name');
        $type = $this->createMock(TagType::class);
        $type->method('name')->willReturn('Type Name');
        $tag->method('type')->willReturn($type);

        $this->transformer->write($editorial, $journalists, $section, [$tag]);
        $result = $this->transformer->read();

        $this->assertEquals($tagId->id(), $result['tags'][0]['id']);
        $this->assertEquals($tag->name(), $result['tags'][0]['name']);
        $this->assertEquals(
            'https://www.elconfidencial.dev/tags/type-name/tag-name-tagId',
            $result['tags'][0]['url']
        );
    }
}
