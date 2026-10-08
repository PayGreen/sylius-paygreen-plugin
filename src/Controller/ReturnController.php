<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Controller;

use Payum\Core\Payum;
use Payum\Core\Reply\ReplyInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Security\TokenInterface;
use Payum\Core\Storage\IdentityInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class ReturnController
{
    public function __construct(
        private readonly Payum $payum,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $httpRequestVerifier = $this->payum->getHttpRequestVerifier();

        try {
            $token = $httpRequestVerifier->verify($request);
        } catch (Throwable $exception) {
            $this->logger->warning('PayGreen return token verification failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return new RedirectResponse('/');
        }

        $afterUrl = $token->getAfterUrl() ?: '/';

        try {
            $gateway = $this->payum->getGateway($token->getGatewayName());
            $gateway->execute(new Capture($this->resolvePayment($token)));
            $httpRequestVerifier->invalidate($token);
        } catch (ReplyInterface $reply) {
            // Payum drives part of its flow through thrown replies (e.g. an
            // HttpRedirect). These are not errors and must bubble up so Payum
            // can handle them.
            throw $reply;
        } catch (Throwable $exception) {
            $this->logger->error('PayGreen return processing failed.', [
                'gateway' => $token->getGatewayName(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return new RedirectResponse($afterUrl);
    }

    private function resolvePayment(TokenInterface $token): PaymentInterface
    {
        $identity = $token->getDetails();
        if (!$identity instanceof IdentityInterface) {
            throw new RuntimeException('The Payum token does not reference a payment.');
        }

        $payment = $this->payum->getStorage($identity->getClass())->find($identity);
        if (!$payment instanceof PaymentInterface) {
            throw new RuntimeException('The Payum token payment could not be resolved.');
        }

        return $payment;
    }
}
