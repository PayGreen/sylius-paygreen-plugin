<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Webhook;

use GuzzleHttp\Psr7\Response;
use Paygreen\Sdk\Payment\V3\Client;
use Paygreen\Sdk\Payment\V3\Environment;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use PayGreen\SyliusPayumPlugin\Tests\Double\FakeHttpClient;
use PayGreen\SyliusPayumPlugin\Webhook\WebhookListenerProvisioner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebhookListenerProvisionerTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const LISTENER_EVENTS = [
        'payment_order.authorized',
        'payment_order.successed',
        'payment_order.canceled',
        'payment_order.expired',
        'payment_order.refused',
        'payment_order.error',
    ];

    public function testItReturnsExistingListenerWithMatchingWebhookUrl(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->json([
                'data' => [[
                    'id' => 'listener_123',
                    'type' => 'webhook',
                    'url' => 'https://example.test/paygreen/webhook',
                    'events' => self::LISTENER_EVENTS,
                    'hmac_key' => 'hmac_existing',
                ]],
            ])),
        ]);

        $hmacKey = $this->provisioner()->provision(
            $this->client($httpClient),
            'sh_123',
            'https://example.test/paygreen/webhook',
        );

        self::assertSame('hmac_existing', $hmacKey);
        self::assertCount(1, $httpClient->requests);
        self::assertSame('GET', $httpClient->requests[0]->getMethod());
        self::assertSame('/notifications/listeners', $httpClient->requests[0]->getUri()->getPath());
    }

    public function testItCreatesListenerWhenNoExistingListenerMatches(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->json(['data' => []])),
            new Response(200, [], $this->json(['data' => [
                'id' => 'listener_created',
                'hmac_key' => 'hmac_created',
            ]])),
        ]);

        $hmacKey = $this->provisioner()->provision(
            $this->client($httpClient),
            'sh_123',
            'https://example.test/paygreen/webhook',
        );

        self::assertSame('hmac_created', $hmacKey);
        self::assertCount(2, $httpClient->requests);
        self::assertSame('GET', $httpClient->requests[0]->getMethod());
        self::assertSame('POST', $httpClient->requests[1]->getMethod());
        self::assertSame('/notifications/listeners', $httpClient->requests[1]->getUri()->getPath());
    }

    public function testItDeletesListenerWithWrongUrlBeforeRecreatingIt(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->json([
                'data' => [[
                    'id' => 'listener_wrong',
                    'type' => 'webhook',
                    'url' => 'https://example.test/old-paygreen-webhook',
                    'events' => self::LISTENER_EVENTS,
                    'hmac_key' => 'hmac_old',
                ]],
            ])),
            new Response(200, [], '{}'),
            new Response(200, [], $this->json(['data' => [
                'id' => 'listener_created',
                'hmac_key' => 'hmac_created',
            ]])),
        ]);

        $hmacKey = $this->provisioner()->provision(
            $this->client($httpClient),
            'sh_123',
            'https://example.test/paygreen/webhook',
        );

        self::assertSame('hmac_created', $hmacKey);
        self::assertCount(3, $httpClient->requests);
        self::assertSame('DELETE', $httpClient->requests[1]->getMethod());
        self::assertSame('/notifications/listeners/listener_wrong', $httpClient->requests[1]->getUri()->getPath());
        self::assertSame('POST', $httpClient->requests[2]->getMethod());
    }

    public function testItDoesNotDeleteWebhookListenerFromDifferentHost(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->json([
                'data' => [[
                    'id' => 'listener_other_host',
                    'type' => 'webhook',
                    'url' => 'https://other-shop.test/paygreen/webhook',
                    'events' => self::LISTENER_EVENTS,
                    'hmac_key' => 'hmac_other',
                ]],
            ])),
            new Response(200, [], $this->json(['data' => [
                'id' => 'listener_created',
                'hmac_key' => 'hmac_created',
            ]])),
        ]);

        $hmacKey = $this->provisioner()->provision(
            $this->client($httpClient),
            'sh_123',
            'https://example.test/paygreen/webhook',
        );

        self::assertSame('hmac_created', $hmacKey);
        self::assertCount(2, $httpClient->requests);
        self::assertSame('GET', $httpClient->requests[0]->getMethod());
        self::assertSame('POST', $httpClient->requests[1]->getMethod());
        self::assertSame('/notifications/listeners', $httpClient->requests[1]->getUri()->getPath());
    }

    public function testItIncludesPayGreenResponseDetailsWhenListenerCreationDoesNotReturnHmacKey(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->json(['data' => []])),
            new Response(400, [], $this->json([
                'message' => 'url: Invalid value',
            ])),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('status 400: url: Invalid value');

        $this->provisioner()->provision(
            $this->client($httpClient),
            'sh_123',
            'http://localhost/payment/paygreen/webhook',
        );
    }

    private function provisioner(): WebhookListenerProvisioner
    {
        return new WebhookListenerProvisioner(new ResponseExtractor());
    }

    private function client(FakeHttpClient $httpClient): Client
    {
        $client = new Client($httpClient, new Environment('sh_123', 'sk_123', Environment::ENVIRONMENT_SANDBOX));
        $client->setBearer('jwt_123');

        return $client;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
