<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Payum\Factory;

use Paygreen\Sdk\Payment\V3\Environment;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class PayGreenGatewayFactory extends GatewayFactory
{
    public const FACTORY_NAME = 'paygreen';

    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => self::FACTORY_NAME,
            'payum.factory_title' => 'PayGreen',
            'payum.api' => static fn (ArrayObject $config): array => [
                'shop_id' => $config['shop_id'],
                'public_key' => $config['public_key'],
                'secret_key' => $config['secret_key'],
                'webhook_secret' => $config['webhook_secret'],
                'environment' => $config['environment_mode'],
            ],
            'environment_mode' => Environment::ENVIRONMENT_PRODUCTION,
        ]);

        $config['payum.default_options'] = [
            'environment_mode' => Environment::ENVIRONMENT_PRODUCTION,
            'shop_id' => '',
            'public_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
        ];

        $config['payum.required_options'] = ['shop_id', 'secret_key'];
    }
}
