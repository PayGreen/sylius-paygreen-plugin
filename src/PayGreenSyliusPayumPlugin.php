<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin;

use PayGreen\SyliusPayumPlugin\DependencyInjection\Compiler\ConfigureSyliusTwigHooksPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PayGreenSyliusPayumPlugin extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ConfigureSyliusTwigHooksPass());
    }
}
