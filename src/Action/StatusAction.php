<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Action;

use PayGreen\SyliusPayumPlugin\Status\PayGreenStatusMapper;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class StatusAction implements ActionInterface
{
    public function __construct(private readonly PayGreenStatusMapper $statusMapper)
    {
    }

    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        $details = $payment->getDetails() ?? [];

        match ($this->statusMapper->map(isset($details['paygreen_status']) ? (string) $details['paygreen_status'] : null)) {
            PayGreenStatusMapper::STATUS_NEW => $request->markNew(),
            PayGreenStatusMapper::STATUS_PENDING => $request->markPending(),
            PayGreenStatusMapper::STATUS_AUTHORIZED => $request->markAuthorized(),
            PayGreenStatusMapper::STATUS_CAPTURED => $request->markCaptured(),
            PayGreenStatusMapper::STATUS_CANCELED => $request->markCanceled(),
            PayGreenStatusMapper::STATUS_FAILED => $request->markFailed(),
            default => $request->markUnknown(),
        };
    }

    public function supports($request)
    {
        return $request instanceof GetStatusInterface && $request->getModel() instanceof PaymentInterface;
    }
}
