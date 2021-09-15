<?php

namespace Paygreen\SyliusPaygreenPlugin\Payum\Action\Api;

use ArrayAccess;
use Paygreen\SyliusPaygreenPlugin\Payum\Request\PaymentResponse;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetHttpRequest;

class PaymentResponseAction extends AbstractApiAction
{
    /**
     * @inheritdoc
     */
    public function execute($request) : void
    {
        /** @var PaymentResponse $request */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        $paymentResponse = $this->api->getPaymentRequest();
        $paymentResponse->setResponse($httpRequest->request);

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
        return $request instanceof PaymentResponse
            && $request->getModel() instanceof ArrayAccess;
    }
}
