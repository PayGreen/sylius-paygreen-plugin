<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Exception;
use Paygreen\Sdk\Core\Environment;
use Paygreen\Sdk\Payment\V2\Model\Address;
use Paygreen\Sdk\Payment\V2\Model\Customer;
use Paygreen\Sdk\Payment\V2\Model\Order;
use Paygreen\Sdk\Payment\V2\Model\PaymentOrder;
use Paygreen\Sdk\Payment\V2\PaymentClient;
use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Reply\HttpPostRedirect;
use Symfony\Component\HttpClient\Psr18Client;


final class CaptureAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /** @var Psr18Client */
    private $client;

    /** @var PaygreenBridge */
    private $api;

    /** @var PaymentClient */
    private $paymentClient;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Payum
     */
    private $payum;

    public function __construct(Psr18Client $client, LoggerInterface $logger, Payum $payum)
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
        $afterUrl = $token->getAfterUrl();

        // Set the notified url to handle the IPN
        $targetUrl = $notifyToken->getTargetUrl();

        try {
            $payment_type = $this->api->getPaymentRequest()->getPaymentType();
            $paymentOrder = $this->buildPaymentOrder($payment, $order, $payment_type, $targetUrl, $afterUrl);

            // Create the payment link via PayGreen api
            $response = $this->paymentClient->createCashPayment($paymentOrder);
        } catch (Exception $exception) {
            $this->logger->alert("Exception capture action request.");

            $response = $exception->getResponse();

            if ($response !== null) {
                $this->logger->alert("Response: " . $response->getBody()->getContents());
            }
        }

        // Get URL from the response and redirect the customer
        if ($response !== null) {
            $content = json_decode($response->getBody()->getContents(), true);
            $url = $content['data']['url'];
            $pid = $content['data']['id'];

            // Set the PID in order to retrieve easily the transaction in the StatusAction
            $payment->setDetails(['pid' => $pid]);

            // Redirect the customer to the PayGreen payment page
            throw new HttpPostRedirect($url);
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

        $environment = new Environment(
            $api->getPaymentRequest()->getPublicKey(),
            $api->getPaymentRequest()->getPrivateKey(),
            'SANDBOX',
            2
        );

        $this->paymentClient = new PaymentClient($this->client, $environment, $this->logger);

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

    /**
     * @param SyliusPaymentInterface $payment
     * @param OrderInterface $order
     * @param string $paymentType
     * @param string $notifiedUrl
     * @param null|null $returnedUrl
     * @return PaymentOrder
     */
    private function buildPaymentOrder(
        SyliusPaymentInterface $payment,
        OrderInterface $order,
        $paymentType,
        $notifiedUrl,
        $returnedUrl = null
    ) {
        $customer = new Customer();
        $customer->setId($order->getCustomer()->getId());
        $customer->setEmail($order->getCustomer()->getEmail());
        $customer->setFirstname($order->getCustomer()->getFirstName());
        $customer->setLastname($order->getCustomer()->getLastName());

        $shippingAddress = new Address();
        $shippingAddress->setStreetLineOne($order->getShippingAddress()->getStreet());
        $shippingAddress->setCity($order->getShippingAddress()->getCity());
        $shippingAddress->setCountryCode($order->getShippingAddress()->getCountryCode());
        $shippingAddress->setPostcode($order->getShippingAddress()->getPostcode());

        $billingAddress = new Address();
        $billingAddress->setStreetLineOne($order->getBillingAddress()->getStreet());
        $billingAddress->setCity($order->getBillingAddress()->getCity());
        $billingAddress->setCountryCode($order->getBillingAddress()->getCountryCode());
        $billingAddress->setPostcode($order->getBillingAddress()->getPostcode());

        $orderSdk = new Order();
        $orderSdk->setCustomer($customer);
        $orderSdk->setBillingAddress($billingAddress);
        $orderSdk->setShippingAddress($shippingAddress);
        $orderSdk->setReference("{$order->getNumber()}-{$order->getPayments()->count()}");
        $orderSdk->setAmount($payment->getAmount());
        $orderSdk->setCurrency('EUR');

        $paymentOrder = new PaymentOrder();
        $paymentOrder->setType('CASH');
        $paymentOrder->setPaymentType($paymentType);
        $paymentOrder->setOrder($orderSdk);
        $paymentOrder->setNotifiedUrl($notifiedUrl);

        if (!empty($returnedUrl)) {
            $paymentOrder->setReturnedUrl($returnedUrl);
        }

        return $paymentOrder;
    }
}