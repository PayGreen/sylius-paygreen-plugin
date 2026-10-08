<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Controller;

use PayGreen\SyliusPayumPlugin\Controller\ReturnController;
use Payum\Core\GatewayInterface;
use Payum\Core\Payum;
use Payum\Core\Request\Capture;
use Payum\Core\Security\HttpRequestVerifierInterface;
use Payum\Core\Security\TokenInterface;
use Payum\Core\Storage\IdentityInterface;
use Payum\Core\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReturnControllerTest extends TestCase
{
    public function testItCapturesResolvedPaymentAndInvalidatesToken(): void
    {
        $request = new Request();

        $identity = $this->createMock(IdentityInterface::class);
        $identity->method('getClass')->willReturn(PaymentInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getGatewayName')->willReturn('paygreen');
        $token->method('getAfterUrl')->willReturn('/after-payment');
        $token->method('getDetails')->willReturn($identity);

        $payment = $this->createMock(PaymentInterface::class);

        $storage = $this->createMock(StorageInterface::class);
        $storage->expects(self::once())->method('find')->with($identity)->willReturn($payment);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects(self::once())->method('execute')->with(self::callback(
            static fn (Capture $capture): bool => $capture->getModel() === $payment,
        ));

        $httpRequestVerifier = $this->createMock(HttpRequestVerifierInterface::class);
        $httpRequestVerifier->expects(self::once())->method('verify')->with($request)->willReturn($token);
        $httpRequestVerifier->expects(self::once())->method('invalidate')->with($token);

        $payum = $this->createMock(Payum::class);
        $payum->method('getHttpRequestVerifier')->willReturn($httpRequestVerifier);
        $payum->expects(self::once())->method('getGateway')->with('paygreen')->willReturn($gateway);
        $payum->expects(self::once())->method('getStorage')->with(PaymentInterface::class)->willReturn($storage);

        $response = (new ReturnController($payum))($request);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/after-payment', $response->headers->get('Location'));
    }

    public function testItRedirectsHomeWhenTokenVerificationFails(): void
    {
        $request = new Request();

        $httpRequestVerifier = $this->createMock(HttpRequestVerifierInterface::class);
        $httpRequestVerifier->expects(self::once())->method('verify')->with($request)
            ->willThrowException(new RuntimeException('Invalid or expired token.'));
        $httpRequestVerifier->expects(self::never())->method('invalidate');

        $payum = $this->createMock(Payum::class);
        $payum->method('getHttpRequestVerifier')->willReturn($httpRequestVerifier);
        $payum->expects(self::never())->method('getGateway');

        $response = (new ReturnController($payum))($request);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
    }

    public function testItRedirectsToAfterUrlWhenCaptureFails(): void
    {
        $request = new Request();

        $identity = $this->createMock(IdentityInterface::class);
        $identity->method('getClass')->willReturn(PaymentInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getGatewayName')->willReturn('paygreen');
        $token->method('getAfterUrl')->willReturn('/after-payment');
        $token->method('getDetails')->willReturn($identity);

        $payment = $this->createMock(PaymentInterface::class);

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('find')->with($identity)->willReturn($payment);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects(self::once())->method('execute')
            ->willThrowException(new RuntimeException('PayGreen API unreachable.'));

        $httpRequestVerifier = $this->createMock(HttpRequestVerifierInterface::class);
        $httpRequestVerifier->expects(self::once())->method('verify')->with($request)->willReturn($token);
        // The token is not invalidated when capture fails, so the buyer can retry.
        $httpRequestVerifier->expects(self::never())->method('invalidate');

        $payum = $this->createMock(Payum::class);
        $payum->method('getHttpRequestVerifier')->willReturn($httpRequestVerifier);
        $payum->method('getGateway')->with('paygreen')->willReturn($gateway);
        $payum->method('getStorage')->with(PaymentInterface::class)->willReturn($storage);

        $response = (new ReturnController($payum))($request);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/after-payment', $response->headers->get('Location'));
    }
}
