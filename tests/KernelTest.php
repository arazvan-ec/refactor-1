<?php

/**
 * @copyright
 */

namespace App\Tests;

use App\DependencyInjection\Compiler\BodyDataTransformerCompiler;
use App\DependencyInjection\Compiler\EditorialOrchestratorCompiler;
use App\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
#[CoversClass(Kernel::class)]
class KernelTest extends TestCase
{
    #[Test]
    public function buildAddLandingOrchestratorCompilerPassToContainerBuilder(): void
    {
        $containerBuilder = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $invokedCount = self::exactly(2);
        $containerBuilder->expects($invokedCount)
            ->method('addCompilerPass')
            ->willReturnCallback(function ($method) use ($containerBuilder, $invokedCount) {
                if (1 == $invokedCount->numberOfInvocations()) {
                    self::assertInstanceOf(EditorialOrchestratorCompiler::class, $method);
                } elseif (2 == $invokedCount->numberOfInvocations()) {
                    self::assertInstanceOf(BodyDataTransformerCompiler::class, $method);
                }

                return $containerBuilder;
            });

        $kernel = $this->getMockBuilder(Kernel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($kernel);
        $method = $reflection->getMethod('build');

        $method->invoke($kernel, $containerBuilder);
    }
}
