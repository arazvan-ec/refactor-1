<?php
/**
 * @copyright
 */

namespace App\Tests\Infrastructure\Enum;

use App\Infrastructure\Enum\SitesEnum;
use PHPUnit\Framework\TestCase;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\Infrastructure\Enum\SitesEnum
 */
class SitesEnumTest extends TestCase
{
    private SitesEnum $elConfidencial;
    private SitesEnum $vanitatis;
    private SitesEnum $alimente;

    protected function setUp(): void
    {
        $this->elConfidencial = SitesEnum::ELCONFIDENCIAL;
        $this->vanitatis = SitesEnum::VANITATIS;
        $this->alimente = SitesEnum::ALIMENTE;
    }

    protected function tearDown(): void
    {
        unset($this->elConfidencial, $this->vanitatis, $this->alimente);
    }

    /**
     * @test
     */
    public function getHostnameByIdMustReturnCorrectValue(): void
    {
        $this->assertSame('elconfidencial', SitesEnum::getHostnameById($this->elConfidencial->value));
        $this->assertSame('vanitatis.elconfidencial', SitesEnum::getHostnameById($this->vanitatis->value));
        $this->assertSame('alimente.elconfidencial', SitesEnum::getHostnameById($this->alimente->value));
        $this->assertSame('elconfidencial', SitesEnum::getHostnameById('999'));
    }

    /**
     * @test
     */
    public function getEncodenameByIdMustReturnCorrectValue(): void
    {
        $this->assertSame('el-confidencial', SitesEnum::getEncodenameById('69'));
        $this->assertSame('el-confidencial', SitesEnum::getEncodenameById($this->elConfidencial->value));
        $this->assertSame('vanitatis', SitesEnum::getEncodenameById($this->vanitatis->value));
        $this->assertSame('alimente', SitesEnum::getEncodenameById($this->alimente->value));
    }
}
