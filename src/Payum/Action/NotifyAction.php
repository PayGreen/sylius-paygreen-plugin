<?php

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action;

use ArrayAccess;
use Paygreen\SyliusPaygreenPlugin\Payum\Request\PaymentRequest;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Notify;
use Psr\Log\LoggerInterface;

class NotifyAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var LoggerInterface
     */
    protected $logger;


    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    /**
     * {@inheritDoc}
     *
     * @param mixed $request
     */
    public function execute($request) : void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        // Get the IPN and set the Payment response
        $this->gateway->execute(new PaymentRequest($model));
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return $request instanceof Notify
            && $request->getModel() instanceof ArrayAccess;
    }
}
