<?php
/**
 * @copyright
 */

namespace App\Tests\Controller;

use App\Controller\HealthcheckController;
use PHPUnit\Framework\TestCase;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\Controller\HealthcheckController
 */
class HealthcheckControllerTest extends TestCase
{
    /**
     * @test
     */
    public function healthCheckShouldReturnValidResponse(): void
    {
        $applicationName = 'landing-front';
        $environment = 'dev';

        $controller = new HealthcheckController($applicationName, $environment);

        $response = $controller->healthCheck();
        $cacheControlHeader = $response->headers->get('cache-control');
        static::assertSame('SERVICE OK', $response->getContent());
        static::assertStringContainsString('no-store', $cacheControlHeader);
        static::assertStringContainsString('no-cache', $cacheControlHeader);
        static::assertStringContainsString('must-revalidate', $cacheControlHeader);
        static::assertSame(0, $response->getMaxAge());
    }
}
