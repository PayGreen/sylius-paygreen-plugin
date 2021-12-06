<?php

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action\Api;

use ArrayAccess;
use Paygreen\SyliusPaygreenPlugin\Payum\PaymentResponse;
use Paygreen\SyliusPaygreenPlugin\Payum\Request\PaymentRequest;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetHttpRequest;

class PaymentResponseAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @inheritdoc
     */
    public function execute($request) : void
    {
        /** @var PaymentRequest $request */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        $paymentResponse = new PaymentResponse();
        $paymentResponse->setResponse($httpRequest->query);

        $model['pid'] = $paymentResponse->getParam("pid");

        // Allows you to retrieve parameters directly from the url response from Paygreen
        if ($paymentResponse->isSuccessful()) {
            //$this->log("Payment response success");
            $model['status'] = 1;
        }
        else {
            //$this->log("Payment response refused");
            $model['status'] = 0;
        }

        $request->setModel($model);
    }

    /**
     * @inheritdec
     */
    public function supports($request)
    {
        return $request instanceof PaymentRequest
            && $request->getModel() instanceof ArrayAccess;
    }
}
