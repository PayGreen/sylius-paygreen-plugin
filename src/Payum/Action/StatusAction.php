<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Exception;
use Paygreen\Sdk\Payment\V2\Enum\PaymentTypeEnum;
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
        /** @var GetStatusInterface $request */
        RequestNotSupportedException::assertSupports($this, $request);

        $this->gateway->execute(new PaymentRequest($request->getModel()));

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();
        $paymentDetails = $payment->getDetails();

        // Retrieve the PID of the transaction
        $pid = $paymentDetails['pid'];

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        // Check if the order is already paid
        if($order->getPaymentState() === "paid") {
            return;
        }

        try {
            $response = $this->paymentClient->getTransaction($pid);

            if ($response->getStatusCode() === 200) {
                $content = json_decode($response->getBody()->getContents());

                $paymentType = $content->data->paymentType;

                switch ($content->data->result->status) {
                    case TransactionStatus::STATUS_REFUSED:
                    case TransactionStatus::STATUS_CANCELLED:
                        $request->markCanceled();
                        break;

                    case TransactionStatus::STATUS_SUCCEEDED:
                        $request->markCaptured();
                        break;

                    case TransactionStatus::STATUS_WAITING:
                        if ($paymentType === PaymentTypeEnum::TRD) {
                            $request->markCaptured();
                        }
                        else {
                            $request->markPending();
                        }
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
            else throw new Exception("Invalid API transaction data");

        } catch (Exception $exception) {
            $this->logger->error("PayGreen getTransaction error: {$exception->getMessage()} ({$exception->getCode()})");

            $response = $exception->getResponse();
            $request->markUnknown();

            if ($response !== null) {
                $this->logger->error("Response: " . $response->getBody()->getContents());
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