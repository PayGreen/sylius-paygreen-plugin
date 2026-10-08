<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Bridge\PayGreen;

use GuzzleHttp\Psr7\Response;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Tests\Double\FakeHttpClient;
use Paygreen\Sdk\Payment\V3\Environment;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function testItAuthenticatesTheSdkClientBeforeUsingIt(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], json_encode(['data' => ['token' => 'jwt_123']], JSON_THROW_ON_ERROR)),
            new Response(200, [], '{}'),
        ]);

        $client = (new ClientFactory($httpClient))->create([
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'environment' => Environment::ENVIRONMENT_SANDBOX,
        ]);

        $client->getPaymentOrder('po_123');

        self::assertSame('/auth/authentication/sh_123/secret-key', $httpClient->requests[0]->getUri()->getPath());
        self::assertSame('sk_123', $httpClient->requests[0]->getHeaderLine('Authorization'));
        self::assertSame('/payment/payment-orders/po_123', $httpClient->requests[1]->getUri()->getPath());
        self::assertSame('Bearer jwt_123', $httpClient->requests[1]->getHeaderLine('Authorization'));
    }

    public function testItReusesBearerTokenForTheSameShopAndEnvironment(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], json_encode(['data' => ['token' => 'jwt_123']], JSON_THROW_ON_ERROR)),
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]);

        $factory = new ClientFactory($httpClient);
        $config = [
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'environment' => Environment::ENVIRONMENT_SANDBOX,
        ];

        $factory->create($config)->getPaymentOrder('po_123');
        $factory->create($config)->getPaymentOrder('po_456');

        self::assertCount(3, $httpClient->requests);
        self::assertSame('/auth/authentication/sh_123/secret-key', $httpClient->requests[0]->getUri()->getPath());
        self::assertSame('/payment/payment-orders/po_123', $httpClient->requests[1]->getUri()->getPath());
        self::assertSame('Bearer jwt_123', $httpClient->requests[1]->getHeaderLine('Authorization'));
        self::assertSame('/payment/payment-orders/po_456', $httpClient->requests[2]->getUri()->getPath());
        self::assertSame('Bearer jwt_123', $httpClient->requests[2]->getHeaderLine('Authorization'));
    }
}
