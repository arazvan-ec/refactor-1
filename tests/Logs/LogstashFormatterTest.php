<?php
/**
 * @copyright
 */

namespace App\Tests\Logs;

use App\Logs\LogstashFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 *
 * @covers \App\Logs\LogstashFormatter
 */
class LogstashFormatterTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider \App\Tests\Logs\DataProvider\LogstashFormatterDataProvider::formatData()
     *
     * @param array<string, string> $record
     * @param array<string, string> $expected
     */
    public function formatReturnExpectedValue(
        string $applicationName,
        string $environment,
        array $record,
        array $expected,
        ?string $applicationImage = null,
        ?string $nodeName = null,
        ?string $podIp = null,
        ?string $podEnvironment = null,
    ): void {
        $formatter = $this->getMockBuilder(LogstashFormatter::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs([
                $applicationName,
                $environment,
                $applicationImage,
                $nodeName,
                $podIp,
                $podEnvironment,
            ])
            ->onlyMethods(['getGmdate'])
            ->getMock();

        $formatter->method('getGmdate')
            ->willReturn($expected['@timestamp']);

        static::assertSame($expected, json_decode($formatter->format($record), true, 512, JSON_THROW_ON_ERROR));
    }
}
