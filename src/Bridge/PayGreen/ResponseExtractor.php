<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Bridge\PayGreen;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ResponseExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extractPaymentOrderDetails(mixed $response): array
    {
        $payload = $this->normalizeResponse($response);

        return [
            'paygreen_payment_order_id' => $this->findFirst($payload, ['id', 'payment_order_id']),
            'paygreen_hosted_payment_url' => $this->findFirst($payload, ['hosted_payment_url', 'hostedPaymentUrl']),
            'paygreen_status' => $this->findFirst($payload, ['status']),
            'paygreen_response' => $payload,
        ];
    }

    public function extractHostedPaymentUrl(mixed $response): string
    {
        $details = $this->extractPaymentOrderDetails($response);
        $url = $details['paygreen_hosted_payment_url'];

        if (!is_string($url) || '' === $url) {
            throw new RuntimeException('The PayGreen SDK response did not contain a hosted_payment_url.');
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeResponse(mixed $response): array
    {
        if ($response instanceof ResponseInterface) {
            $body = $response->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }

            $contents = $body->getContents();
            $decoded = json_decode($contents, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            $data = $response->toArray();

            return is_array($data) ? $data : [];
        }

        if (is_object($response)) {
            return json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private function findFirst(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $this->findByKey($payload, $key);

            if (null !== $value && '' !== $value) {
                return $value;
            }
        }

        return null;
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
