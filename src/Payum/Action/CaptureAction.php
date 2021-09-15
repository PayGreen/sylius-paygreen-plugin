<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Payum;
use Payum\Core\Security\TokenInterface;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\CoreBundle\Application\Kernel as SyliusKernel;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Reply\HttpPostRedirect;


final class CaptureAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /** @var Client */
    private $client;

    /** @var PaygreenBridge */
    private $api;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Payum
     */
    private $payum;


    public function __construct(Client $client, LoggerInterface $logger, Payum $payum)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->payum = $payum;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var TokenInterface $token */
        $token = $request->getToken();

        $notifyToken = $this->createNotifyToken($token->getGatewayName(), $token->getDetails());

        // Set the returned url for the customer
        $afterUrl = str_replace('http://', 'https://', $token->getAfterUrl());
        // Set the notified url to handle the IPN
        $targetUrl = str_replace('http://', 'https://', $notifyToken->getTargetUrl());

        $response = null;

        try {
            $payment_type = $this->api->getPaymentRequest()->getPaymentType();

            if($payment->getAmount() === null) {
                throw new RequestException("Payment amount is not set.", $request);
            }
            $requestData = $this->api->createPaymentForm($order,$payment->getAmount(),$payment_type,$afterUrl,$targetUrl);

            // Create the payment link via PayGreen api
            $response = $this->client->request(
                'POST',
                $this->api->getBaseUrl().'/payins/transaction/cash',
                $requestData
            );
        } catch (RequestException $exception) {
            $this->logger->alert("Exception capture action request.");
            $response = $exception->getResponse();
            if($response !== null) {
                $this->logger->alert("Response: " . $response->getBody()->getContents());
            }
        } finally {
            // Get URL from the response and redirect the customer
            if($response !== null) {
                $content = $response->getBody()->getContents();
                $contentArray = json_decode($content, true);
                $url = $contentArray["data"]["url"];
                $pid = $contentArray["data"]["id"];
                // Set the PID in order to retrieve easily the transaction in the StatusAction
                $payment->setDetails(['pid' => $pid]);
                // Redirect the customer to the PayGreen payment page
                throw new HttpPostRedirect($url);
            }
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof SyliusPaymentInterface
            ;
    }

    public function setApi($api): void
    {
        if (!$api instanceof PaygreenBridge) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . PaygreenBridge::class);
        }

        $this->api = $api;
    }

    /**
     * @param string $gatewayName
     * @param object $model
     *
     * @return TokenInterface
     */
    private function createNotifyToken($gatewayName, $model)
    {
        return $this->payum->getTokenFactory()->createNotifyToken(
            $gatewayName,
            $model
        );
    }
}