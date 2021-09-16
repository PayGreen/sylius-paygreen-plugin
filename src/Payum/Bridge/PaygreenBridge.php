<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Bridge;

use Paygreen\SyliusPaygreenPlugin\Payum\PaygreenSdk;
use Sylius\Bundle\CoreBundle\Application\Kernel as SyliusKernel;
use Sylius\Component\Core\Model\OrderInterface;

final class PaygreenBridge
{

    const URL_API_SANDBOX = "https://sandbox.paygreen.fr/api/";
    const URL_API_PRODUCTION = "https://paygreen.fr/api/";
    const SYLIUS_PAYGREEN_VERSION = "1.0.0";

    /** @var PaygreenSdk */
    private $paymentRequest;

    public function getPaymentRequest(): PaygreenSdk
    {
        return $this->paymentRequest;
    }

    public function __construct(string $publicKey, string $privateKey, string $payment_type)
    {
        $this->paymentRequest = new PaygreenSdk($publicKey, $privateKey, $payment_type);
    }

    /**
     * Creates the request form.
     *
     * @param OrderInterface $order
     * @param int $amount
     * @param string $type
     * @param string $afterUrl
     * @param string $targetUrl
     *
     * @return array
     */
    public function createPaymentForm($order, $amount, $type, $afterUrl, $targetUrl)
    {
        if($order->getCustomer() === null || $order->getBillingAddress() === null) {
            return array();
        }
        return array(
            "headers" => $this->createHeader(),
            "json" => array(
                "orderId" => "{$order->getNumber()}-{$order->getPayments()->count()}",
                "amount" => $amount,
                "currency" => "EUR",
                "paymentType" => $type,
                "mode" => "CASH",
                "returned_url" => $afterUrl,
                "notified_url" => $targetUrl,
                "buyer" => array(
                    "id" => $order->getCustomer()->getId(),
                    "lastName" => $order->getCustomer()->getLastName(),
                    "firstName" => $order->getCustomer()->getFirstName(),
                    "email" => $order->getCustomer()->getEmail(),
                    "country" => $order->getBillingAddress()->getCountryCode(),
                    "companyName" => $order->getBillingAddress()->getCompany()
                ),
                "billingAddress" => array(
                    "lastName" => $order->getBillingAddress()->getLastName(),
                    "firstName" => $order->getBillingAddress()->getFirstName(),
                    "address" => $order->getBillingAddress()->getStreet(),
                    "zipCode" => $order->getBillingAddress()->getPostcode(),
                    "city" => $order->getBillingAddress()->getCity(),
                    "country" => $order->getBillingAddress()->getCountryCode()
                ),
                "shippingAddress" => array(
                    "lastName" => $order->getBillingAddress()->getLastName(),
                    "firstName" => $order->getBillingAddress()->getFirstName(),
                    "address" => $order->getBillingAddress()->getStreet(),
                    "zipCode" => $order->getBillingAddress()->getPostcode(),
                    "city" => $order->getBillingAddress()->getCity(),
                    "country" => $order->getBillingAddress()->getCountryCode()
                )
            )
        );
    }

    /**
     * Creates the request header.
     *
     * @return array
     */
    public function createHeader()
    {
        $phpVersion = phpversion();
        $syliusVersion = SyliusKernel::VERSION;
        return array(
            "Accept" => "application/json",
            "Content-Type" => "application/json",
            "Cache-Control" => "no-cache",
            "User-Agent"=> "Sylius/".$syliusVersion." php:".$phpVersion.";module:".$this->getModuleVersion(),
            "Authorization" => "Bearer ".$this->paymentRequest->getPrivateKey()
        );
    }

    /**
     * Creates the request base url. You can change the URL_API_SANDBOX to URL_API_PRODUCTION depending of your customer account.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return PaygreenBridge::URL_API_SANDBOX.$this->paymentRequest->getPublicKey();
    }

    /**
     * Get the module version from the composer.json
     *
     * @return string
     */
    public function getModuleVersion() {
        $filename = __DIR__."/../../../composer.json";
        $version = "undefined";
        if (($filecontent = @file_get_contents($filename)) !== false) {
            $composerData = json_decode($filecontent, true);
            if(array_key_exists("version",$composerData)) {
                $version = $composerData["version"];
            }
        }
        return $version;
    }
}
