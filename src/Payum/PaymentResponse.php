<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum;

use InvalidArgumentException;

final class PaymentResponse
{
    /** @var array */
    private $parameters = array();

    /**
     * Retrieves a response parameter
     *
     * @param string $key
     * @throws InvalidArgumentException
     */
    public function getParam($key): string
    {
        $parameters = $this->parameters;
        if (!array_key_exists($key, $parameters)) {
            throw new InvalidArgumentException('Parameter ' . $key . ' does not exist.');
        }

        return $parameters[$key];
    }

    public function isSuccessful(): bool
    {
        try {
            return $this->getParam('result') == "SUCCESSED";
        }
        catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public function setResponse(array $httpRequest) : void
    {
        $parameters = $this->parameters;

        foreach($httpRequest as $key => $value) {
            $parameters[$key] = $value;
        }

        $this->parameters = $parameters;
    }
}