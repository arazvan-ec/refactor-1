<?php

namespace App;

use App\DependencyInjection\Compiler\BodyDataTransformerCompiler;
use App\DependencyInjection\Compiler\EditorialOrchestratorCompiler;
use App\DependencyInjection\Compiler\MediaDataTransformerCompiler;
use App\DependencyInjection\Compiler\MultimediaFactoryCompiler;
use App\DependencyInjection\Compiler\MultimediaTypeOrchestratorCompiler;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new EditorialOrchestratorCompiler());
        $container->addCompilerPass(new MultimediaTypeOrchestratorCompiler());
        $container->addCompilerPass(new BodyDataTransformerCompiler());
        $container->addCompilerPass(new MultimediaFactoryCompiler());
        $container->addCompilerPass(new MediaDataTransformerCompiler());
    }
}
