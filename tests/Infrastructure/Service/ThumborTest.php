<?php

namespace App\Tests\Infrastructure\Service;

use App\Infrastructure\Service\Thumbor;
use PHPUnit\Framework\TestCase;
use Thumbor\Url\BuilderFactory;

class ThumborTest extends TestCase
{
    private Thumbor $thumbor;
    private string $thumborServerUrl = 'http://thumbor-server';
    private string $thumborSecret = 'secret';
    private string $awsBucket = 'aws-bucket';

    protected function setUp(): void
    {
        $this->thumbor = new Thumbor($this->thumborServerUrl, $this->thumborSecret, $this->awsBucket);
    }

    public function testCreateJournalistImage()
    {
        $fileImage = '123456789.jpg';
        $expectedPath = $this->awsBucket . '/journalist/123/456/789/' . $fileImage;

        $builderFactoryMock = $this->createMock(BuilderFactory::class);
        $builderFactoryMock->method('url')->with($expectedPath)->willReturn('http://thumbor-url');

        $reflection = new \ReflectionClass($this->thumbor);
        $property = $reflection->getProperty('thumborFactory');
        $property->setAccessible(true);
        $property->setValue($this->thumbor, $builderFactoryMock);

        $result = $this->thumbor->createJournalistImage($fileImage);

        $this->assertEquals('http://thumbor-url', $result);
    }
}
