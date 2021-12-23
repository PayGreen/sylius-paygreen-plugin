<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Exception;
use Paygreen\Sdk\Core\Exception\ConstraintViolationException;
use Paygreen\Sdk\Payment\V2\Enum\PaymentTypeEnum;
use Paygreen\Sdk\Payment\V2\Model\Address;
use Paygreen\Sdk\Payment\V2\Model\Customer;
use Paygreen\Sdk\Payment\V2\Model\Order;
use Paygreen\Sdk\Payment\V2\Model\PaymentOrder;
use Paygreen\SyliusPaygreenPlugin\Entity\MealVoucherableInterface;
use Paygreen\SyliusPaygreenPlugin\Payum\Action\Api\AbstractApiAction;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\Payum;
use Payum\Core\Security\TokenInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Reply\HttpPostRedirect;
use Symfony\Component\HttpClient\Psr18Client;


final class CaptureAction extends AbstractApiAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    /**
     * @var Payum
     */
    private $payum;

    public function __construct(Psr18Client $client, LoggerInterface $logger, Payum $payum)
    {
        $this->payum = $payum;

        parent::__construct($client, $logger);
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
            $paymentType = $this->api->getPaymentType();
            $paymentOrder = $this->buildPaymentOrder($payment, $order, $paymentType, $targetUrl, $afterUrl);

            // Create the payment link via PayGreen api
            $response = $this->paymentClient->createCashPayment($paymentOrder);
        } catch (ConstraintViolationException $exception) {
            $this->logger->alert("Constraint violation exception.");

            dd($exception->getViolationMessages());
        } catch (Exception $exception) {
            $this->logger->alert("Exception capture action request.");

            $response = $exception->getResponse();

            if ($response !== null) {
                $this->logger->alert("Response: " . $response->getBody()->getContents());
            }
        } finally {
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
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof SyliusPaymentInterface
        ;
    }

    /**
     * @param string $gatewayName
     * @param object $model
     *
     * @return TokenInterface
     */
    private function createNotifyToken(string $gatewayName, object $model) : TokenInterface
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
     * @param null|string $returnedUrl
     * @return PaymentOrder
     */
    private function buildPaymentOrder(
        SyliusPaymentInterface $payment,
        OrderInterface $order,
        string $paymentType,
        string $notifiedUrl,
        ?string $returnedUrl = null
    ) {
        $customer = new Customer();
        $customer->setId($order->getCustomer()->getId());
        $customer->setEmail($order->getCustomer()->getEmail());
        $customer->setFirstname($order->getBillingAddress()->getFirstName());
        $customer->setLastname($order->getBillingAddress()->getLastName());

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

        if ($paymentType === PaymentTypeEnum::TRD && $order instanceof MealVoucherableInterface) {
            /** @var $order MealVoucherableInterface */
            if ($order->getMealVoucherCompatibleAmount() > 0) {
                $paymentOrder->setEligibleAmount([PaymentTypeEnum::TRD => $order->getMealVoucherCompatibleAmount()]);
            }
            else {
                $paymentOrder->setPaymentType(PaymentTypeEnum::CB);
            }

        }

        return $paymentOrder;
    }
}