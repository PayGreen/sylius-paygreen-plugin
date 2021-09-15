<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum;
use InvalidArgumentException;

final class PaygreenSdk
{
    /** @var string */
    private $publicKey;

    /** @var string */
    private $privateKey;

    /** @var string */
    private $payment_type;

    /** @var array */
    private $parameters = array();

    public function __construct(string $publicKey, string $privateKey, string $payment_type)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->payment_type = $payment_type;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getPaymentType(): string
    {
        return $this->payment_type;
    }

    /**
     * Retrieves a response parameter
     *
     * @param string $key
     * @throws InvalidArgumentException
     */
    public function getParam($key): string
    {
        $parameters = $this->parameters;
        if(!array_key_exists($key, $parameters)) {
            throw new InvalidArgumentException('Parameter ' . $key . ' does not exist.');
        }

        return $parameters[$key];
    }

    public function isSuccessful(): bool
    {
        return $this->getParam('result') == "SUCCESSED";
    }

    public function setResponse(array $httpRequest) : void
    {
        $parameters = $this->parameters;

        foreach($httpRequest as $key=>$value) {
            $parameters[$key]=$value;
        }

        $this->parameters = $parameters;
    }
}