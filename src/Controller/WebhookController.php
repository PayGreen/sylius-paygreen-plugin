<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use PayGreen\SyliusPayumPlugin\Status\PayGreenStatusMapper;
use Payum\Core\Payum;
use Payum\Core\Request\Notify;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Throwable;

final class WebhookController
{
    public function __construct(
        private readonly Payum $payum,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PayGreenStatusMapper $statusMapper,
        private readonly ClientFactory $clientFactory,
        private readonly ResponseExtractor $responseExtractor,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $verifyStatusViaApi = false,
        private readonly string $webhookSecretConfigKey = 'webhook_secret',
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $payload = json_decode($content, true);

        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $status = $this->findByKey($payload, 'status');
        $payment = $this->findPayment($payload);

        if (!$payment instanceof PaymentInterface) {
            $this->logger->warning('PayGreen webhook payment could not be resolved from reference.', [
                'paygreen_status' => is_string($status) ? $status : null,
            ]);

            return new JsonResponse(['accepted' => true], Response::HTTP_ACCEPTED);
        }

        $paymentId = $payment->getId();
        $paymentOrderId = $this->findByKey($payload, 'id');
        $payGreenStatus = is_string($status) ? $status : null;

        $this->logger->info('PayGreen webhook received.', [
            'payment_id' => $paymentId,
            'paygreen_payment_order_id' => is_string($paymentOrderId) ? $paymentOrderId : null,
            'paygreen_status' => $payGreenStatus,
        ]);

        if (!$this->isSignatureValid($request, $content, $payment)) {
            $this->logger->warning('PayGreen webhook signature invalid.', [
                'payment_id' => $paymentId,
                'paygreen_payment_order_id' => is_string($paymentOrderId) ? $paymentOrderId : null,
            ]);

            return new JsonResponse(['error' => 'Invalid PayGreen webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!is_string($paymentOrderId) || '' === $paymentOrderId) {
            $this->logger->warning('PayGreen webhook missing payment order id.', [
                'payment_id' => $paymentId,
                'paygreen_status' => $payGreenStatus,
            ]);

            return new JsonResponse(['accepted' => true], Response::HTTP_ACCEPTED);
        }

        $lock = $this->lockFactory->createLock(sprintf('paygreen_webhook_%s', $paymentOrderId), 10.0, false);
        if (!$lock->acquire()) {
            $this->logger->info('PayGreen webhook already being processed.', [
                'payment_id' => $paymentId,
                'paygreen_payment_order_id' => $paymentOrderId,
            ]);

            return new JsonResponse(['accepted' => true], Response::HTTP_ACCEPTED);
        }

        $gatewayName = $payment->getMethod()?->getGatewayConfig()?->getGatewayName();
        try {
            $payGreenStatus = $this->resolveAuthoritativeStatus($payment, $paymentOrderId, $payGreenStatus);
            $details = $payment->getDetails() ?? [];
            $payment->setDetails(array_filter(array_replace($details, [
                'paygreen_payment_order_id' => $paymentOrderId,
                'paygreen_status' => $payGreenStatus,
                'paygreen_response' => $payload,
            ]), static fn (mixed $value): bool => null !== $value));

            if (is_string($gatewayName) && '' !== $gatewayName) {
                $this->payum->getGateway($gatewayName)->execute(new Notify($payment));
            }

            $this->entityManager->flush();

            $mappedStatus = $this->statusMapper->map($payGreenStatus);
            $this->logger->info('PayGreen webhook successfully processed.', [
                'payment_id' => $paymentId,
                'paygreen_payment_order_id' => $paymentOrderId,
                'paygreen_status' => $payGreenStatus,
                'mapped_status' => $mappedStatus,
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('PayGreen webhook processing failed.', [
                'payment_id' => $paymentId,
                'paygreen_payment_order_id' => $paymentOrderId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return new JsonResponse(['error' => 'PayGreen webhook processing failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            $lock->release();
        }

        return new JsonResponse([
            'accepted' => true,
            'payment_id' => $paymentId,
            'paygreen_status' => $payGreenStatus,
            'mapped_status' => $this->statusMapper->map($payGreenStatus),
            'payment_state' => $payment->getState(),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findPayment(array $payload): ?PaymentInterface
    {
        $reference = $this->findByKey($payload, 'reference');
        if (!is_string($reference) || 1 !== preg_match('/-payment-(\d+)(?:-retry-\d+)?$/', $reference, $matches)) {
            return null;
        }

        $payment = $this->paymentRepository->find($matches[1]);

        return $payment instanceof PaymentInterface ? $payment : null;
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

    private function isSignatureValid(Request $request, string $content, PaymentInterface $payment): bool
    {
        $signature = (string) $request->headers->get('signature', '');
        if ('' === $signature) {
            return false;
        }

        $webhookSecret = $this->resolveWebhookSecret($payment);
        if (null === $webhookSecret || '' === $webhookSecret) {
            return false;
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $content, $webhookSecret, true));

        return hash_equals($expectedSignature, $signature);
    }

    private function resolveWebhookSecret(PaymentInterface $payment): ?string
    {
        $config = $payment->getMethod()?->getGatewayConfig()?->getConfig();
        if (!is_array($config)) {
            return null;
        }

        $webhookSecret = $config[$this->webhookSecretConfigKey] ?? null;

        return is_string($webhookSecret) ? $webhookSecret : null;
    }

    private function resolveAuthoritativeStatus(PaymentInterface $payment, string $paymentOrderId, ?string $webhookStatus): ?string
    {
        if (!$this->verifyStatusViaApi) {
            return $webhookStatus;
        }

        try {
            $gatewayConfig = $payment->getMethod()?->getGatewayConfig()?->getConfig();
            if (!is_array($gatewayConfig)) {
                return $webhookStatus;
            }

            $response = $this->clientFactory
                ->create($this->normalizePayGreenApiConfig($gatewayConfig))
                ->getPaymentOrder($paymentOrderId)
            ;
            $details = $this->responseExtractor->extractPaymentOrderDetails($response);
            $apiStatus = $details['paygreen_status'] ?? null;
            if (!is_string($apiStatus) || '' === $apiStatus) {
                return $webhookStatus;
            }

            if ($apiStatus !== $webhookStatus) {
                $this->logger->warning('PayGreen webhook status differs from API status.', [
                    'payment_id' => $payment->getId(),
                    'paygreen_payment_order_id' => $paymentOrderId,
                    'webhook_status' => $webhookStatus,
                    'api_status' => $apiStatus,
                ]);
            }

            return $apiStatus;
        } catch (Throwable $exception) {
            $this->logger->error('PayGreen webhook API status verification failed.', [
                'payment_id' => $payment->getId(),
                'paygreen_payment_order_id' => $paymentOrderId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $webhookStatus;
        }
    }

    /**
     * @param array<string, mixed> $gatewayConfig
     *
     * @return array<string, mixed>
     */
    private function normalizePayGreenApiConfig(array $gatewayConfig): array
    {
        return array_replace($gatewayConfig, [
            'environment' => $gatewayConfig['environment'] ?? $gatewayConfig['environment_mode'] ?? null,
        ]);
    }
}
