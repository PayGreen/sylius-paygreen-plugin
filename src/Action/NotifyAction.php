<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Action;

use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Notify;
use Sylius\Component\Core\Model\PaymentInterface;

final class NotifyAction implements ActionInterface, ApiAwareInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $api = [];

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly ResponseExtractor $responseExtractor,
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

        if (!isset($details['paygreen_payment_order_id'])) {
            return;
        }

        $response = $this->clientFactory
            ->create($this->api)
            ->getPaymentOrder((string) $details['paygreen_payment_order_id'])
        ;

        $payment->setDetails(array_filter(array_replace(
            $details,
            $this->responseExtractor->extractPaymentOrderDetails($response),
        ), static fn (mixed $value): bool => null !== $value));
    }

    public function supports($request)
    {
        return $request instanceof Notify && $request->getModel() instanceof PaymentInterface;
    }
}
