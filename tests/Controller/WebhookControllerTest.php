<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use PayGreen\SyliusPayumPlugin\Controller\WebhookController;
use PayGreen\SyliusPayumPlugin\Status\PayGreenStatusMapper;
use PayGreen\SyliusPayumPlugin\Tests\Double\FakeHttpClient;
use Payum\Core\GatewayInterface;
use Payum\Core\Payum;
use Payum\Core\Request\Notify;
use Psr\Log\AbstractLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class WebhookControllerTest extends TestCase
{
    public function testItRejectsWebhookWhenSignatureIsInvalid(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7-retry-2',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret');
        $payment->expects(self::never())->method('setDetails');

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::never())->method('getGateway');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $response = $this->createController($payum, $paymentRepository, $entityManager)(
            new Request([], [], [], [], [], [], $content),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testItReturnsGenericAcceptedResponseWhenPaymentCannotBeResolved(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-999999',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('999999')->willReturn(null);

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::never())->method('getGateway');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $response = $this->createController($payum, $paymentRepository, $entityManager)(
            new Request([], [], [], [], [], [], $content),
        );
        $responsePayload = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame(['accepted' => true], $responsePayload);
        self::assertArrayNotHasKey('payment_id', $responsePayload);
        self::assertArrayNotHasKey('mapped_status', $responsePayload);
        self::assertArrayNotHasKey('payment_state', $responsePayload);
    }

    public function testItProcessesWebhookWhenSignatureIsValid(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7-retry-2',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $content, 'webhook-secret', true));

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret');
        $payment->method('getId')->willReturn(7);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getDetails')->willReturn(['existing' => 'detail']);
        $payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => 'po_123' === $details['paygreen_payment_order_id']
                && 'captured' === $details['paygreen_status']
                && $details['paygreen_response']['reference'] === 'ORDER-123-payment-7-retry-2'
                && !array_key_exists('webhook_secret', $details),
        ));

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects(self::once())->method('execute')->with(self::isInstanceOf(Notify::class));

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::once())->method('getGateway')->with('paygreen')->willReturn($gateway);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $lockFactory = new LockFactory(new InMemoryStore());

        $request = new Request([], [], [], [], [], [], $content);
        $request->headers->set('signature', $signature);

        $response = $this->createController($payum, $paymentRepository, $entityManager, $lockFactory)($request);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        // The controller must release the lock once processing succeeds, so the
        // same key can be acquired again afterwards.
        self::assertTrue($lockFactory->createLock('paygreen_webhook_po_123', 10.0, false)->acquire());
    }

    public function testItReturnsAcceptedWhenWebhookIsAlreadyLocked(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $content, 'webhook-secret', true));

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret');
        $payment->expects(self::never())->method('setDetails');

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::never())->method('getGateway');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $lockFactory = new LockFactory(new InMemoryStore());
        // Simulate a concurrent webhook already holding the lock for this order.
        $heldLock = $lockFactory->createLock('paygreen_webhook_po_123', 10.0, false);
        self::assertTrue($heldLock->acquire());

        $request = new Request([], [], [], [], [], [], $content);
        $request->headers->set('signature', $signature);

        $response = $this->createController($payum, $paymentRepository, $entityManager, $lockFactory)($request);

        // The controller could not acquire the lock, so it short-circuits without
        // touching the payment (enforced by the mock expectations above) and
        // returns an accepted response. The original lock is still held.
        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertFalse($lockFactory->createLock('paygreen_webhook_po_123', 10.0, false)->acquire());

        $heldLock->release();
    }

    public function testItUsesApiStatusWhenStatusVerificationIsEnabledAndApiDiffers(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7',
            'status' => 'pending',
        ], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $content, 'webhook-secret', true));

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret', [
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'environment_mode' => 'SANDBOX',
            'webhook_secret' => 'webhook-secret',
        ]);
        $payment->method('getId')->willReturn(7);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getDetails')->willReturn([]);
        $payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => 'captured' === $details['paygreen_status'],
        ));

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects(self::once())->method('execute')->with(self::isInstanceOf(Notify::class));

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::once())->method('getGateway')->with('paygreen')->willReturn($gateway);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $httpClient = new FakeHttpClient([
            new Psr7Response(200, [], json_encode(['data' => ['token' => 'jwt_123']], JSON_THROW_ON_ERROR)),
            new Psr7Response(200, [], json_encode(['data' => ['id' => 'po_123', 'status' => 'captured']], JSON_THROW_ON_ERROR)),
        ]);

        $request = new Request([], [], [], [], [], [], $content);
        $request->headers->set('signature', $signature);

        $response = $this->createController(
            $payum,
            $paymentRepository,
            $entityManager,
            new LockFactory(new InMemoryStore()),
            new ClientFactory($httpClient),
            new ResponseExtractor(),
            new InMemoryLogger(),
            true,
        )($request);
        $responsePayload = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame('captured', $responsePayload['paygreen_status']);
        self::assertSame('captured', $responsePayload['mapped_status']);
    }

    public function testItReturnsServerErrorWhenWebhookProcessingCannotBePersisted(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $content, 'webhook-secret', true));

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret');
        $payment->method('getDetails')->willReturn([]);
        $payment->expects(self::once())->method('setDetails');

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects(self::once())->method('execute')->with(self::isInstanceOf(Notify::class));

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::once())->method('getGateway')->with('paygreen')->willReturn($gateway);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush')->willThrowException(new RuntimeException('Database is unavailable.'));

        $lockFactory = new LockFactory(new InMemoryStore());

        $request = new Request([], [], [], [], [], [], $content);
        $request->headers->set('signature', $signature);

        $response = $this->createController($payum, $paymentRepository, $entityManager, $lockFactory)($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        // Even when processing fails, the lock must be released in the finally
        // block so the key becomes available again.
        self::assertTrue($lockFactory->createLock('paygreen_webhook_po_123', 10.0, false)->acquire());
    }

    private function createPaymentWithWebhookSecret(string $webhookSecret, ?array $gatewayConfigData = null): PaymentInterface
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getGatewayName')->willReturn('paygreen');
        $gatewayConfig->method('getConfig')->willReturn($gatewayConfigData ?? ['webhook_secret' => $webhookSecret]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }

    private function createController(
        Payum $payum,
        PaymentRepositoryInterface $paymentRepository,
        EntityManagerInterface $entityManager,
        ?LockFactory $lockFactory = null,
        ?ClientFactory $clientFactory = null,
        ?ResponseExtractor $responseExtractor = null,
        ?InMemoryLogger $logger = null,
        bool $verifyStatusViaApi = false,
    ): WebhookController {
        return new WebhookController(
            $payum,
            $paymentRepository,
            $entityManager,
            new PayGreenStatusMapper(),
            $clientFactory ?? new ClientFactory(),
            $responseExtractor ?? new ResponseExtractor(),
            $lockFactory ?? new LockFactory(new InMemoryStore()),
            $logger ?? new InMemoryLogger(),
            $verifyStatusViaApi,
        );
    }
}

final class InMemoryLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|\Stringable, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
