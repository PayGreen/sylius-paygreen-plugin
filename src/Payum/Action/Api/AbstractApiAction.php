<?php

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action\Api;

use Paygreen\Sdk\Payment\V2\Client;
use Paygreen\Sdk\Payment\V2\Environment;
use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Psr18Client;

abstract class AbstractApiAction implements ActionInterface, GatewayAwareInterface, ApiAwareInterface, LoggerAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var Psr18Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PaygreenBridge
     */
    protected $api;

    /**
     * @var Client
     */
    protected $paymentClient;

    public function __construct(Psr18Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function setApi($api): void
    {
        if (!$api instanceof PaygreenBridge) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . PaygreenBridge::class);
        }

        $this->api = $api;

        $this->buildPaymentClient();
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

    private function buildPaymentClient()
    {
        $environment = new Environment(
            $this->api->getPublicKey(),
            $this->api->getPrivateKey(),
            (string) getenv('PAYGREEN_API_SERVER'),
            2
        );

        $this->paymentClient = new Client($this->client, $environment, $this->logger);
    }
}
