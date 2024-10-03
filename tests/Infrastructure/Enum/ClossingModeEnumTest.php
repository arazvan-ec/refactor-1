<?php

namespace App\Tests\Infrastructure\Enum;

use App\Infrastructure\Enum\ClossingModeEnum;
use PHPUnit\Framework\TestCase;

class ClossingModeEnumTest extends TestCase
{
    /**
     * @test
     */
    public function testGetClosingModeById(): void
    {
        $this->assertEquals('registry', ClossingModeEnum::getClosingModeById('1'));
        $this->assertEquals('payment', ClossingModeEnum::getClosingModeById('2'));
        $this->assertEquals('apppayment', ClossingModeEnum::getClosingModeById('3'));
        $this->assertEquals('', ClossingModeEnum::getClosingModeById('4'));
    }
}
