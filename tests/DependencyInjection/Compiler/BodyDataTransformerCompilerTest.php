<?php
/**
 * @copyright
 */

namespace App\Tests\DependencyInjection\Compiler;

use App\Application\DataTransformer\BodyElementDataTransformerHandler;
use App\DependencyInjection\Compiler\BodyDataTransformerCompiler;
use App\Orchestrator\OrchestratorChainHandler;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 *
 * @covers \App\DependencyInjection\Compiler\BodyDataTransformerCompiler
 */
class BodyDataTransformerCompilerTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function process(): void
    {
        $bodyElementDataTransformerHandlerDefinition = new Definition();
        $this->setDefinition(BodyElementDataTransformerHandler::class, $bodyElementDataTransformerHandlerDefinition);

        $dataTransformerDefinition = new Definition();
        $dataTransformerDefinition->addTag('app.data_transformer');
        $this->setDefinition('data_transformer_service', $dataTransformerDefinition);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            BodyElementDataTransformerHandler::class,
            'addDataTransformer',
            [
                $dataTransformerDefinition,
            ]
        );
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $orchestratorChainDefinition = new Definition(BodyElementDataTransformerHandler::class);
        $container->setDefinition(BodyElementDataTransformerHandler::class, $orchestratorChainDefinition);

        $container->addCompilerPass(new BodyDataTransformerCompiler());
    }
}
