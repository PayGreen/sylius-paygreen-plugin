<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Webhook;

use Paygreen\Sdk\Payment\V3\Client;
use Paygreen\Sdk\Payment\V3\Model\Listener;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class WebhookListenerProvisioner
{
    private const LISTENER_TYPE = 'webhook';

    /**
     * @var list<string>
     */
    private const EVENTS = [
        'payment_order.authorized',
        'payment_order.successed',
        'payment_order.canceled',
        'payment_order.expired',
        'payment_order.refused',
        'payment_order.error',
    ];

    public function __construct(private readonly ResponseExtractor $responseExtractor)
    {
    }

    public function provision(Client $client, string $shopId, string $webhookUrl): string
    {
        $listeners = $this->extractListeners($this->responseExtractor->normalizeResponse($client->listListener($shopId)));

        foreach ($listeners as $listener) {
            if (!$this->isWebhookListener($listener)) {
                continue;
            }

            if ($this->listenerMatches($listener, $webhookUrl)) {
                $hmacKey = $this->findString($listener, ['hmac_key', 'hmacKey']);
                if (null !== $hmacKey) {
                    return $hmacKey;
                }
            }

            if (!$this->shouldReplaceListener($listener, $webhookUrl)) {
                continue;
            }

            $listenerId = $this->findString($listener, ['id', 'listener_id', 'listenerId']);
            if (null !== $listenerId) {
                $client->deleteListener($listenerId);
            }
        }

        return $this->createListener($client, $shopId, $webhookUrl);
    }

    private function createListener(Client $client, string $shopId, string $webhookUrl): string
    {
        $listener = (new Listener())
            ->setType(self::LISTENER_TYPE)
            ->setUrl($webhookUrl)
            ->setEvents(self::EVENTS)
        ;

        $createResponse = $client->createListener($listener, $shopId);
        $response = $this->responseExtractor->normalizeResponse($createResponse);
        $hmacKey = $this->findString($response, ['hmac_key', 'hmacKey']);

        if (null === $hmacKey) {
            throw new RuntimeException(sprintf(
                'PayGreen listener creation did not return an hmac_key%s.',
                $this->describeFailure($createResponse, $response),
            ));
        }

        return $hmacKey;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function extractListeners(array $payload): array
    {
        if ($this->isListOfArrays($payload)) {
            /** @var list<array<string, mixed>> $listeners */
            $listeners = [];
            foreach ($payload as $item) {
                if (is_array($item)) {
                    $listeners[] = $item;
                }
            }

            return $listeners;
        }

        foreach (['data', 'items', 'results', 'listeners', 'hydra:member'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value)) {
                return $this->extractListeners($value);
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $listener
     */
    private function isWebhookListener(array $listener): bool
    {
        return self::LISTENER_TYPE === $this->findString($listener, ['type']);
    }

    /**
     * @param array<string, mixed> $listener
     */
    private function listenerMatches(array $listener, string $webhookUrl): bool
    {
        $url = $this->findString($listener, ['url']);

        return $url === $webhookUrl && $this->eventsMatch($this->findArray($listener, ['events']));
    }

    /**
     * @param array<string, mixed> $listener
     */
    private function shouldReplaceListener(array $listener, string $webhookUrl): bool
    {
        $listenerUrl = $this->findString($listener, ['url']);
        if (null === $listenerUrl || !str_contains(strtolower($listenerUrl), 'paygreen')) {
            return false;
        }

        return parse_url($listenerUrl, PHP_URL_HOST) === parse_url($webhookUrl, PHP_URL_HOST);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function describeFailure(mixed $response, array $payload): string
    {
        $parts = [];
        if ($response instanceof ResponseInterface) {
            $parts[] = sprintf('status %d', $response->getStatusCode());
        }

        $message = $this->findString($payload, ['message', 'error', 'title', 'detail']);
        if (null !== $message) {
            $parts[] = $message;
        }

        $errors = $this->findByKey($payload, 'errors');
        if (is_array($errors) && [] !== $errors) {
            $parts[] = json_encode($errors, JSON_THROW_ON_ERROR);
        }

        return [] === $parts ? '' : sprintf(' (%s)', implode(': ', $parts));
    }

    /**
     * @param array<array-key, mixed> $events
     */
    private function eventsMatch(array $events): bool
    {
        $actual = array_values(array_filter($events, 'is_string'));
        $expected = self::EVENTS;
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function isListOfArrays(array $value): bool
    {
        if ([] === $value || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_array($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private function findString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->findByKey($payload, $key);

            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     *
     * @return array<array-key, mixed>
     */
    private function findArray(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $this->findByKey($payload, $key);

            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findByKey(array $payload, string $key): mixed
    {
        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $nested = $this->findByKey($value, $key);

                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        return null;
    }
}
