<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class PayGreenSyliusPayumPluginExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['paygreen'],
            ]);
        }

        // Sylius 1.x admin: render the meal voucher checkbox through UI template events.
        // On Sylius 2.x the sylius_ui extension still exists but no longer accepts the
        // "events" option — sylius_twig_hooks is the reliable 2.x discriminant.
        if ($container->hasExtension('sylius_ui') && !$container->hasExtension('sylius_twig_hooks')) {
            $container->prependExtensionConfig('sylius_ui', [
                'events' => [
                    'sylius.admin.product_variant.tab_details' => [
                        'blocks' => [
                            'paygreen_meal_voucher_compatible' => [
                                'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/meal_voucher_compatible.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                    'sylius.admin.product.tab_details' => [
                        'blocks' => [
                            'paygreen_meal_voucher_compatible' => [
                                'template' => '@PayGreenSyliusPayumPlugin/admin/product/meal_voucher_compatible.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if (!$container->hasExtension('sylius_twig_hooks')) {
            return;
        }

        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                // Sylius 2.x admin: render the meal voucher checkbox in the product variant form.
                'sylius_admin.product_variant.create.content.form' => [
                    'paygreen_meal_voucher_compatible' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/meal_voucher_compatible.bootstrap.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.product_variant.update.content.form' => [
                    'paygreen_meal_voucher_compatible' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/meal_voucher_compatible.bootstrap.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.product.create.content.form' => [
                    'paygreen_meal_voucher_compatible' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/meal_voucher_compatible.bootstrap.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.product.update.content.form' => [
                    'paygreen_meal_voucher_compatible' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/meal_voucher_compatible.bootstrap.html.twig',
                        'priority' => 0,
                    ],
                ],
                'gateway_configuration' => [
                    'use_payum' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/use_payum.html.twig',
                        'priority' => 0,
                    ],
                ],
                'gateway_configuration.paygreen' => [
                    'config' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/config.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.create.content.form.sections.gateway_configuration' => [
                    'use_payum' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/use_payum.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.create.content.form.sections.gateway_configuration.paygreen' => [
                    'config' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/config.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.update.content.form.sections.gateway_configuration' => [
                    'use_payum' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/use_payum.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.update.content.form.sections.gateway_configuration.paygreen' => [
                    'config' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/config.html.twig',
                        'priority' => 0,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
