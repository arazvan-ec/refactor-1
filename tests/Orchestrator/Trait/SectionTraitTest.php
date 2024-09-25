<?php
/**
 * @copyright
 */

namespace App\Tests\Orchestrator\Trait;

use App\Orchestrator\Trait\SectionTrait;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class SectionTraitTest extends TestCase
{
    use SectionTrait;

    /** @var QuerySectionClient|MockObject */
    private QuerySectionClient $querySectionClient;

    protected function setUp(): void
    {
        $this->querySectionClient = $this->createMock(QuerySectionClient::class);
        $this->setSectionClient($this->querySectionClient);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->querySectionClient);
    }

    /**
     * @test
     */
    public function getSectionByIdShouldReturnSectionWhenIdIsFound(): void
    {
        $id = '90';
        $section = $this->createMock(Section::class);

        $this->querySectionClient
            ->method('findSectionById')
            ->with($id)
            ->willReturn($section);

        $result = $this->getSectionById($id);

        $this->assertSame($section, $result);
    }

    /**
     * @test
     */
    public function getSectionByIdShouldReturnNullWhenExceptionIsThrown(): void
    {
        $id = '90';

        $exceptionMock = $this->createMock(\Exception::class);

        $this->querySectionClient
            ->method('findSectionById')
            ->will($this->throwException($exceptionMock));

        $result = $this->getSectionById($id);

        static::assertNull($result);
    }

    /**
     * @test
     */
    public function getSectionByIdShouldReturnNullWhenSectionIsNotFound(): void
    {
        $id = '0';

        $this->querySectionClient
            ->method('findSectionById')
            ->with($id)
            ->will($this->throwException(new \Exception("Section not found")));


        $result = $this->getSectionById($id);

        static::assertNull($result);
    }

    /**
     * @test
     */
    public function setSectionClientShouldReturnClient(): void
    {
        $this->setSectionClient($this->querySectionClient);

        static::assertSame($this->querySectionClient, $this->sectionClient());
    }
}
