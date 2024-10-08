<?php

namespace App;

use App\DependencyInjection\Compiler\BodyTranslator;
use App\DependencyInjection\Compiler\EditorialOrchestratorCompiler;
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
        $container->addCompilerPass(new BodyTranslator());
    }
}
