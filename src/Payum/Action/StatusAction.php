<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Exception;
use Paygreen\SyliusPaygreenPlugin\Payum\Action\Api\AbstractApiAction;
use Paygreen\SyliusPaygreenPlugin\Payum\Request\PaymentRequest;
use Paygreen\SyliusPaygreenPlugin\Types\TransactionStatus;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

final class StatusAction extends AbstractApiAction implements ActionInterface
{
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $this->gateway->execute(new PaymentRequest($request->getModel()));

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();

        $details = $payment->getDetails();

        // Retrieve the PID of the transaction
        $pid = $details['pid'];

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        // Check if the order is already paid or not
        if($order->getPaymentState() !== "paid") {

            try {
                // Ask Paygreen api to get the transaction status
                $response = $this->paymentClient->getTransaction($pid);
            } catch (Exception $exception) {
                $this->logger->alert("Exception request.");

                $response = $exception->getResponse();
                $request->markUnknown();

                if ($response !== null) {
                    $this->logger->alert("Response: " . $response->getBody()->getContents());
                }
            } finally {
                if ($response !== null) {
                    $content = json_decode($response->getBody()->getContents(), true);

                    // Get the transaction status from Paygreen api
                    $status = $content["data"]["result"]["status"];

                    // Set the order status
                    switch ($status) {
                        case TransactionStatus::STATUS_REFUSED:
                        case TransactionStatus::STATUS_CANCELLED:
                            $request->markCanceled();
                            break;

                        case TransactionStatus::STATUS_SUCCEEDED:
                            $request->markCaptured();
                            break;

                        case TransactionStatus::STATUS_PENDING:
                            $request->markNew();
                            break;

                        case TransactionStatus::STATUS_REFUNDED:
                            $request->markRefunded();
                            break;

                        case TransactionStatus::STATUS_EXPIRED:
                            $request->markExpired();
                            break;

                        default:
                            $request->markUnknown();
                            break;
                    }
                }
            }
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof SyliusPaymentInterface
            ;
    }
}