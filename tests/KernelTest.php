<?php
/**
 * @copyright
 */

namespace App\Tests;

use App\DependencyInjection\Compiler\EditorialOrchestratorCompiler;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class KernelTest extends TestCase
{
    /**
     * @test
     */
    public function buildAddLandingOrchestratorCompilerPassToContainerBuilder(): void
    {
        $containerBuilder = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $containerBuilder->expects($this->once())
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(EditorialOrchestratorCompiler::class));

        $kernel = $this->getMockBuilder(Kernel::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $reflection = new \ReflectionClass($kernel);
        $method = $reflection->getMethod('build');

        $method->invoke($kernel, $containerBuilder);
    }
}
