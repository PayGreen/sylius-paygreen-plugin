<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Paygreen\Sdk\Core\Environment;
use Paygreen\Sdk\Payment\V2\PaymentClient;
use Paygreen\SyliusPaygreenPlugin\Payum\Action\Api\AbstractApiAction;
use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
use Paygreen\SyliusPaygreenPlugin\Types\TransactionStatus;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\GetStatusInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Symfony\Component\HttpClient\Psr18Client;

final class StatusAction extends AbstractApiAction implements ActionInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var Psr18Client */
    private $client;

    /** @var PaymentClient */
    protected $api;

    public function __construct(Psr18Client $client, LoggerInterface $logger)
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
                $response = $this->api->getTransaction($pid);

            } catch (RequestException $exception) {
                $this->logger->alert("Exception request");
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

    public function setApi($api): void
    {
        if (!$api instanceof PaygreenBridge) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . PaygreenBridge::class);
        }

        $environment = new Environment(
            $api->getPaymentRequest()->getPublicKey(),
            $api->getPaymentRequest()->getPrivateKey(),
            'SANDBOX',
            2
        );

        $this->api = new PaymentClient($this->client, $environment, $this->logger);
    }
}