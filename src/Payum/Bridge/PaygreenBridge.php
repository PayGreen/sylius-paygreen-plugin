<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Bridge;

final class PaygreenBridge
{
    /** @var string */
    private $publicKey;

    /** @var string */
    private $privateKey;

    /** @var string */
    private $payment_type;

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
}
