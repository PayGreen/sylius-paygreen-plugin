<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use Exception;
use Http\Client\Exception\HttpException;
use Paygreen\Sdk\Payment\V2\Enum\PaymentTypeEnum;
use Paygreen\Sdk\Payment\V2\Model\Address;
use Paygreen\Sdk\Payment\V2\Model\Customer;
use Paygreen\Sdk\Payment\V2\Model\Order;
use Paygreen\Sdk\Payment\V2\Model\PaymentOrder;
use Paygreen\SyliusPaygreenPlugin\Entity\MealVoucherableInterface;
use Paygreen\SyliusPaygreenPlugin\Payum\Action\Api\AbstractApiAction;
use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
use Paygreen\SyliusPaygreenPlugin\Payum\Enum\MealVoucherTypeEnum;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\Payum;
use Payum\Core\Request\RenderTemplate;
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

    /**
     * @throws Exception
     */
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
            $paymentOrder = $this->createPaymentOrder($payment, $order, $paymentType, $targetUrl, $afterUrl);

            // Create the payment link via PayGreen api
            $response = $this->paymentClient->createCashPayment($paymentOrder);
        } catch (HttpException $exception) {
            $response = $exception->getResponse();
            $this->logger->alert("Exception capture action request: " . $response->getBody()->getContents());
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
        } finally {
            // Get URL from the response and redirect the customer

            $content = json_decode($response->getBody()->getContents(), true);

            if ($content['success'] === false) {
                throw new Exception($content['message']);
            }

            $executeUrl = $content['data']['url'];
            $pid = $content['data']['id'];

            // Set the PID in order to retrieve easily the transaction in the StatusAction
            $payment->setDetails(['pid' => $pid]);

            // Redirect the customer to the PayGreen payment page
            if ($this->api->getDisplayMode() === PaygreenBridge::DISPLAY_MODE_INSITE) {
                $renderTemplate = new RenderTemplate('@PaygreenSyliusPaygreenPlugin/Checkout/inSite.html.twig', [
                    'executeUrl' => $executeUrl,
                ]);

                $this->gateway->execute($renderTemplate);

                throw new HttpResponse($renderTemplate->getResult());
            }
            else {
                throw new HttpPostRedirect($executeUrl);
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
    private function createPaymentOrder(
        SyliusPaymentInterface $payment,
        OrderInterface $order,
        string $paymentType,
        string $notifiedUrl,
        ?string $returnedUrl = null
    ): PaymentOrder
    {
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

        if (in_array($paymentType, MealVoucherTypeEnum::getMealVoucherTypes()) && $order instanceof MealVoucherableInterface) {
            /** @var $order MealVoucherableInterface */
            if ($order->getMealVoucherCompatibleAmount() > 0) {
                $paymentOrder->setEligibleAmount([$paymentType => $order->getMealVoucherCompatibleAmount()]);
            }
            else {
                $paymentOrder->setPaymentType(PaymentTypeEnum::CB);
            }

        }

        return $paymentOrder;
    }
}