<?php
/**
 * @copyright
 */

namespace App\Tests;

use App\DependencyInjection\Compiler\BodyDataTransformerCompiler;
use App\DependencyInjection\Compiler\EditorialOrchestratorCompiler;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\Kernel
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

        $containerBuilder->expects(self::exactly(2))
            ->method('addCompilerPass')
            ->withConsecutive(
                [$this->callback(function ($compilerPass) {
                    return $compilerPass instanceof EditorialOrchestratorCompiler;
                })],
                [$this->callback(function ($compilerPass) {
                    return $compilerPass instanceof BodyDataTransformerCompiler;
                })],
            );

        $kernel = $this->getMockBuilder(Kernel::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $reflection = new \ReflectionClass($kernel);
        $method = $reflection->getMethod('build');

        $method->invoke($kernel, $containerBuilder);
    }
}
