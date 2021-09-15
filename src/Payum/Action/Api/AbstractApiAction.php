<?php

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action\Api;

use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractApiAction implements ActionInterface, GatewayAwareInterface, ApiAwareInterface, LoggerAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var PaygreenBridge
     */
    protected $api;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @inheritDoc
     */
    public function setApi($api) : void
    {
        if (!$api instanceof PaygreenBridge) {
            throw new UnsupportedApiException('Not supported.');
        }

        $this->api = $api;
    }

    /**
     * {@inheritDoc}
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Logs the given message.
     *
     * @param string $message
     */
    protected function log($message) : void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->info($message);
    }
}
