<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Bridge;


use Paygreen\SyliusPaygreenPlugin\Entity\MealVoucherableInterface;
use Paygreen\SyliusPaygreenPlugin\Payum\PaygreenSdk;
use Sylius\Bundle\CoreBundle\Application\Kernel as SyliusKernel;
use Sylius\Component\Core\Model\OrderInterface;
use http\Exception\UnexpectedValueException;

final class PaygreenBridge
{
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
     * @return array
     */
    public function createPaymentForm(OrderInterface $order, int $amount, string $type, string $afterUrl, string $targetUrl) : array
    {
        $customer = $order->getCustomer();
        $billingAddress = $order->getBillingAddress();

        if ($customer === null || $billingAddress === null) {
            return [];
        }

        $requestData = [
            "headers" => $this->createHeader(),
            "json" => [
                "orderId" => "{$order->getNumber()}-{$order->getPayments()->count()}",
                "amount" => $amount,
                "currency" => "EUR",
                "paymentType" => $type,
                "mode" => "CASH",
                "returned_url" => $afterUrl,
                "notified_url" => $targetUrl,
                "buyer" => [
                    "id" => $customer->getId(),
                    "lastName" => $customer->getLastName(),
                    "firstName" => $customer->getFirstName(),
                    "email" => $customer->getEmail(),
                    "country" => $billingAddress->getCountryCode(),
                    "companyName" => $billingAddress->getCompany()
                ],
                "billingAddress" => [
                    "lastName" => $billingAddress->getLastName(),
                    "firstName" => $billingAddress->getFirstName(),
                    "address" => $billingAddress->getStreet(),
                    "zipCode" => $billingAddress->getPostcode(),
                    "city" => $billingAddress->getCity(),
                    "country" => $billingAddress->getCountryCode()
                ],
                "shippingAddress" => [
                    "lastName" => $billingAddress->getLastName(),
                    "firstName" => $billingAddress->getFirstName(),
                    "address" => $billingAddress->getStreet(),
                    "zipCode" => $billingAddress->getPostcode(),
                    "city" => $billingAddress->getCity(),
                    "country" => $billingAddress->getCountryCode()
                ]
            ]
        ];

        if ($type === 'TRD' && $order instanceof MealVoucherableInterface) {
            /** @var $order MealVoucherableInterface */
            $requestData['json']['eligibleAmount'] = [
                'TRD' => $order->getMealVoucherCompatibleAmount(),
            ];
        }

        return $requestData;
    }

    /**
     * Creates the request header.
     */
    public function createHeader() : array
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
     */
    public function getBaseUrl() : string
    {
        $base = getenv('PAYGREEN_URL_API');
        if ($base === false) {
            throw new UnexpectedValueException('PAYGREEN_URL_API does not exist.');
        }

        return $base.$this->paymentRequest->getPublicKey();
    }

    /**
     * Get the module version from the composer.json
     */
    public function getModuleVersion() : string
    {
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
