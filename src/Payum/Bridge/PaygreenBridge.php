<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Bridge;

final class PaygreenBridge
{
    public const DISPLAY_MODE_REDIRECT = 'REDIRECT';
    public const DISPLAY_MODE_INSITE = 'INSITE';

    /** @var string */
    private $publicKey;

    /** @var string */
    private $privateKey;

    /** @var string */
    private $paymentType;

    /** @var string */
    private $displayMode;

    public function __construct(string $publicKey, string $privateKey, string $paymentType, string $displayMode)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->paymentType = $paymentType;
        $this->displayMode = $displayMode;
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
        return $this->paymentType;
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }
}
