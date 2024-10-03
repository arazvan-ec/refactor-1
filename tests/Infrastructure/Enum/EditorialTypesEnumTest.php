<?php

namespace App\Tests\Infrastructure\Enum;

use App\Infrastructure\Enum\EditorialTypesEnum;
use PHPUnit\Framework\TestCase;

class EditorialTypesEnumTest extends TestCase
{
    /*
     * @test
     */
    public function testGetNameById()
    {
        $this->assertEquals(['id' => '1', 'name' => 'noticia'], EditorialTypesEnum::getNameById('news'));
        $this->assertEquals(['id' => '3', 'name' => 'blog'], EditorialTypesEnum::getNameById('blog'));
        $this->assertEquals(['id' => '12', 'name' => 'directo deportivo'], EditorialTypesEnum::getNameById('livesport'));
        $this->assertEquals(['id' => '13', 'name' => 'directo informativo'], EditorialTypesEnum::getNameById('live'));
        $this->assertEquals(['id' => '14', 'name' => 'chronicle'], EditorialTypesEnum::getNameById('chronicle'));
        $this->assertEquals(['id' => '15', 'name' => 'lovers'], EditorialTypesEnum::getNameById('lovers'));
        $this->assertEquals(['id' => '1', 'name' => 'noticia'], EditorialTypesEnum::getNameById('unknown'));
    }
}
