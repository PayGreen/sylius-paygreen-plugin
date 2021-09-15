<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Paygreen\SyliusPaygreenPlugin\Payum\Action\Api\AbstractApiAction;
use Paygreen\SyliusPaygreenPlugin\Types\TransactionStatus;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

final class StatusAction extends AbstractApiAction implements ActionInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var Client */
    private $client;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

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
                $response = $this->client->request(
                    'GET',
                    $this->api->getBaseUrl() . '/payins/transaction/' . $pid,
                    array(
                        "headers" => $this->api->createHeader()
                    )
                );

            } catch (RequestException $exception) {
                $this->logger->alert("Exception request");
                $response = $exception->getResponse();
                $request->markUnknown();
                if ($response !== null) {
                    $this->logger->alert("Response: " . $response->getBody()->getContents());
                }
            } finally {
                if ($response !== null) {
                    $content = $response->getBody()->getContents();
                    $contentArray = json_decode($content, true);
                    // Get the transaction status from Paygreen api
                    $status = $contentArray["data"]["result"]["status"];

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