<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigureSyliusTwigHooksPass implements CompilerPassInterface
{
    private const USE_PAYUM_TEMPLATE = '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/use_payum.html.twig';

    /**
     * @var list<string>
     */
    private const USE_PAYUM_HOOKABLE_SERVICE_IDS = [
        'sylius_twig_hooks.hook.sylius_admin.payment_method.create.content.form.sections.gateway_configuration.hookable.use_payum',
        'sylius_twig_hooks.hook.sylius_admin.payment_method.update.content.form.sections.gateway_configuration.hookable.use_payum',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::USE_PAYUM_HOOKABLE_SERVICE_IDS as $serviceId) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            $container->getDefinition($serviceId)->setArgument(2, self::USE_PAYUM_TEMPLATE);
        }
    }
}
