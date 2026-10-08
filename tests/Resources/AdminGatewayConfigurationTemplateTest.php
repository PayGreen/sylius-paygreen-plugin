<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Resources;

use PHPUnit\Framework\TestCase;

final class AdminGatewayConfigurationTemplateTest extends TestCase
{
    public function testItDoesNotRenderWebhookUrlField(): void
    {
        $template = file_get_contents(__DIR__ . '/../../src/Resources/views/admin/payment_method/form/sections/gateway_configuration/config.html.twig');

        self::assertIsString($template);
        self::assertStringNotContainsString('form.gatewayConfig.config.webhook_url', $template);
    }
}
