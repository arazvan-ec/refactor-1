<?php
/**
 * @copyright
 */

namespace App\Tests\DependencyInjection\Compiler;

use App\DependencyInjection\Compiler\EditorialOrchestratorCompiler;
use App\Orchestrator\OrchestratorChainHandler;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\DependencyInjection\Compiler\EditorialOrchestratorCompiler
 */
class EditorialOrchestratorCompilerTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function process(): void
    {
        $orchestratorDefinition = new Definition();
        $orchestratorDefinition->addTag('app.orchestrators');
        $this->setDefinition('orchestrator_service', $orchestratorDefinition);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            OrchestratorChainHandler::class,
            'addOrchestrator',
            [
                $orchestratorDefinition,
            ]
        );
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $orchestratorChainDefinition = new Definition(OrchestratorChainHandler::class);
        $container->setDefinition(OrchestratorChainHandler::class, $orchestratorChainDefinition);

        $container->addCompilerPass(new EditorialOrchestratorCompiler());
    }
}
