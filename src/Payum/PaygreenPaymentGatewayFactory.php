<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum;

use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

final class PaygreenPaymentGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'paygreen',
            'payum.factory_title' => 'Paygreen Payment',
            'payum.action.api.payment_response' => new Action\Api\PaymentResponseAction(),
        ]);

        $config['payum.api'] = function (ArrayObject $config) : PaygreenBridge {
            return new PaygreenBridge($config['public_key'], $config['private_key'], $config['payment_type']);
        };
    }
}