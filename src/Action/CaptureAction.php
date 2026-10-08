<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Action;

use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\PaymentOrderBuilder;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Sylius\Component\Core\Model\PaymentInterface;
use Throwable;

final class CaptureAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var array<string, mixed>
     */
    private array $api = [];

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly PaymentOrderBuilder $paymentOrderBuilder,
        private readonly ResponseExtractor $responseExtractor,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, mixed> $api
     */
    public function setApi($api)
    {
        if (!is_array($api)) {
            throw new UnsupportedApiException('PayGreen API configuration must be an array.');
        }

        $this->api = $api;
    }

    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        $details = $payment->getDetails() ?? [];
        try {
            $client = $this->clientFactory->create($this->api);

            if (isset($details['paygreen_payment_order_id'])) {
                if ($this->isCancelReturn()) {
                    $response = $client->cancelPaymentOrder((string) $details['paygreen_payment_order_id']);
                    $this->resetPaymentForRetry($payment, $details, $response);

                    return;
                }

                $response = $client->getPaymentOrder((string) $details['paygreen_payment_order_id']);
                $payment->setDetails(array_filter(array_replace(
                    $details,
                    $this->responseExtractor->extractPaymentOrderDetails($response),
                ), static fn (mixed $value): bool => null !== $value));

                return;
            }

            $paymentOrder = $this->paymentOrderBuilder->build(
                $payment,
                $this->api,
                $this->resolveTokenUrl($request, 'getTargetUrl'),
                $this->buildCancelUrl($this->resolveTokenUrl($request, 'getTargetUrl')),
            );

            $response = $client->createPaymentOrder($paymentOrder);
            $extractedDetails = $this->responseExtractor->extractPaymentOrderDetails($response);
            $payment->setDetails(array_filter(array_replace($details, $extractedDetails), static fn (mixed $value): bool => null !== $value));

            $paymentOrderId = $extractedDetails['paygreen_payment_order_id'] ?? null;
            $this->logger->info('PayGreen payment order created.', [
                'payment_id' => $payment->getId(),
                'paygreen_payment_order_id' => is_string($paymentOrderId) ? $paymentOrderId : null,
            ]);

            $hostedPaymentUrl = $extractedDetails['paygreen_hosted_payment_url'] ?? null;
            if (!is_string($hostedPaymentUrl) || '' === $hostedPaymentUrl) {
                $payment->setState(PaymentInterface::STATE_NEW);

                $message = $this->buildMissingHostedPaymentUrlMessage($extractedDetails);
                $this->logger->warning('PayGreen hosted payment URL missing in response.', [
                    'payment_id' => $payment->getId(),
                    'paygreen_payment_order_id' => is_string($paymentOrderId) ? $paymentOrderId : null,
                    'detail' => $this->extractPayGreenResponseDetail($extractedDetails),
                ]);

                throw new RuntimeException($message);
            }

            $payment->setState(PaymentInterface::STATE_PROCESSING);

            throw new HttpRedirect($hostedPaymentUrl);
        } catch (HttpRedirect $reply) {
            throw $reply;
        } catch (Throwable $exception) {
            $this->logger->error('PayGreen capture failed.', [
                'payment_id' => $payment->getId(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function supports($request)
    {
        return $request instanceof Capture && $request->getModel() instanceof PaymentInterface;
    }

    private function resolveTokenUrl(object $request, string $method): ?string
    {
        if (!method_exists($request, 'getToken')) {
            return null;
        }

        $token = $request->getToken();

        if (null === $token || !method_exists($token, $method)) {
            return null;
        }

        $url = $token->{$method}();

        return is_string($url) && '' !== $url ? $url : null;
    }

    private function isCancelReturn(): bool
    {
        $httpRequest = new GetHttpRequest();
        $this->gateway->execute($httpRequest);

        return 'cancel' === ($httpRequest->query['paygreen_result'] ?? null);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function resetPaymentForRetry(PaymentInterface $payment, array $details, mixed $response): void
    {
        unset(
            $details['paygreen_payment_order_id'],
            $details['paygreen_hosted_payment_url'],
            $details['paygreen_status'],
            $details['paygreen_response'],
        );

        $payment->setDetails(array_filter(array_replace($details, [
            'paygreen_cancel_response' => $this->responseExtractor->normalizeResponse($response),
            'paygreen_retry_count' => ((int) ($details['paygreen_retry_count'] ?? 0)) + 1,
        ]), static fn (mixed $value): bool => null !== $value));
        $payment->setState(PaymentInterface::STATE_NEW);
    }

    private function buildCancelUrl(?string $targetUrl): ?string
    {
        if (null === $targetUrl) {
            return null;
        }

        $separator = str_contains($targetUrl, '?') ? '&' : '?';

        return sprintf('%s%spaygreen_result=cancel', $targetUrl, $separator);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function buildMissingHostedPaymentUrlMessage(array $details): string
    {
        $detail = $this->extractPayGreenResponseDetail($details);

        return is_string($detail) && '' !== $detail
            ? sprintf('The PayGreen SDK response did not contain a hosted_payment_url: %s', $detail)
            : 'The PayGreen SDK response did not contain a hosted_payment_url.'
        ;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function extractPayGreenResponseDetail(array $details): ?string
    {
        $response = $details['paygreen_response'] ?? [];
        $detail = is_array($response) ? ($response['detail'] ?? $response['message'] ?? null) : null;

        return is_string($detail) && '' !== $detail ? $detail : null;
    }
}
