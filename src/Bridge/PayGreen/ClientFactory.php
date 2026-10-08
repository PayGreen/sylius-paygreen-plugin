<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Bridge\PayGreen;

use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Paygreen\Sdk\Payment\V3\Client;
use Paygreen\Sdk\Payment\V3\Environment;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ClientFactory
{
    /**
     * @var array<string, string>
     */
    private array $bearerTokens = [];

    public function __construct(private readonly ?HttpClient $httpClient = null)
    {
    }

    /**
     * @param array{shop_id?: string, public_key?: string, secret_key?: string, webhook_secret?: string, environment?: string} $config
     */
    public function create(array $config): Client
    {
        $shopId = (string) ($config['shop_id'] ?? '');
        $environment = (string) ($config['environment'] ?? Environment::ENVIRONMENT_PRODUCTION);

        $client = new Client(
            $this->httpClient ?? HttpClientDiscovery::find(),
            new Environment(
                $shopId,
                (string) ($config['secret_key'] ?? ''),
                $environment,
            ),
        );

        $cacheKey = $this->buildBearerTokenCacheKey($shopId, $environment);
        if (isset($this->bearerTokens[$cacheKey])) {
            $client->setBearer($this->bearerTokens[$cacheKey]);

            return $client;
        }

        $this->authenticate($client, $cacheKey);

        return $client;
    }

    private function authenticate(Client $client, string $cacheKey): void
    {
        $payload = $this->normalizeResponse($client->authenticate());
        $token = $payload['data']['token'] ?? null;

        if (!is_string($token) || '' === $token) {
            unset($this->bearerTokens[$cacheKey]);

            $message = $payload['message'] ?? null;

            throw new RuntimeException(sprintf(
                'PayGreen authentication failed%s.',
                is_string($message) && '' !== $message ? sprintf(': %s', $message) : ''
            ));
        }

        $this->bearerTokens[$cacheKey] = $token;
        $client->setBearer($token);
    }

    private function buildBearerTokenCacheKey(string $shopId, string $environment): string
    {
        return sprintf('%s|%s', $shopId, $environment);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(ResponseInterface $response): array
    {
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $decoded = json_decode($body->getContents(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
