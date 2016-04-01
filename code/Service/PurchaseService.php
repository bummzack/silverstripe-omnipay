<?php

namespace SilverStripe\Omnipay\Service;


use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;

class PurchaseService extends PaymentService
{
    /** @var string message that will be created upon initiating */
    protected $requestMessage = 'PurchaseRequest';

    /** @var string message that will be created when the service completes */
    protected $completeMessage = 'PurchasedResponse';

    /** @var string goal status for the payment */
    protected $endStatus = 'Captured';

    /** @var string pending status for the payment */
    protected $pendingStatus = 'PendingPurchase';

    /**
     * If the return URL wasn't explicitly set, get it from the last PurchaseRequest message
     * @return string
     */
    public function getReturnUrl()
    {
        $value = parent::getReturnUrl();
        if (!$value) {
            $msg = $this->payment->getLatestMessageOfType($this->requestMessage);
            $value = $msg ? $msg->SuccessURL : \Director::baseURL();
        }
        return $value;
    }

    /**
     * If the cancel URL wasn't explicitly set, get it from the last PurchaseRequest message
     * @return string
     */
    public function getCancelUrl()
    {
        $value = parent::getCancelUrl();
        if (!$value) {
            $msg = $this->payment->getLatestMessageOfType($this->requestMessage);
            $value = $msg ? $msg->FailureURL : \Director::baseURL();
        }
        return $value;
    }

    /**
	 * Attempt to make a payment.
	 *
	 * @inheritdoc
	 * @param  array $data returnUrl/cancelUrl + customer creditcard and billing/shipping details.
	 * 	Some keys (e.g. "amount") are overwritten with data from the associated {@link $payment}.
	 *  If this array is constructed from user data (e.g. a form submission), please take care
	 *  to whitelist accepted fields, in order to ensure sensitive gateway parameters like "freeShipping" can't be set.
	 *  If using {@link Form->getData()}, only fields which exist in the form are returned,
	 *  effectively whitelisting against arbitrary user input.
	 */
	public function initiate($data = array()) {
		return $this->doInitiate($data, 'purchase', 'onCaptured');
	}

	/**
	 * Finalise this payment, after off-site external processing.
	 * This is usually only called by PaymentGatewayController.
	 * @inheritdoc
	 */
    public function complete($data = array(), $isNotification = false)
    {
        return $this->doComplete($data, $isNotification, 'completePurchase', 'onCaptured');
	}

    /**
     * Attempt to make a payment.
     *
     * @inheritdoc
     * @param  array $data returnUrl/cancelUrl + customer creditcard and billing/shipping details.
     * 	Some keys (e.g. "amount") are overwritten with data from the associated {@link $payment}.
     *  If this array is constructed from user data (e.g. a form submission), please take care
     *  to whitelist accepted fields, in order to ensure sensitive gateway parameters like "freeShipping" can't be set.
     *  If using {@link Form->getData()}, only fields which exist in the form are returned,
     *  effectively whitelisting against arbitrary user input.
     * @deprecated 3.0 Use the `initiate` method instead
     */
    public function purchase($data = array())
    {
        \Deprecation::notice('3.0', 'Use the `initiate` method instead.');
        return $this->initiate($data);
    }

    /**
     * Finalise this payment, after off-site external processing.
     * This is ususally only called by PaymentGatewayController.
     * @deprecated 3.0 Use the `complete` method instead
     */
    public function completePurchase($data = array())
    {
        \Deprecation::notice('3.0', 'Use the `complete` method instead.');
        return $this->complete($data);
    }


    /**
     * Implementation of initiate that can be configured to use another method (eg. authorize) @see AuthorizeService
     * @param array $data
     * @param string $method
     * @param string $completeHook
     * @return ServiceResponse
     * @throws InvalidConfigurationException
     * @throws InvalidStateException
     */
    protected function doInitiate($data = array(), $method = 'purchase', $completeHook = 'onCaptured')
    {
        if ($this->payment->Status !== 'Created') {
            throw new InvalidStateException('Cannot initiate a '.$method.' with this payment. Status is not "Created"');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $gateway = $this->oGateway();
        $ucMethod = ucfirst($method);
        if(!$gateway->{"supports$ucMethod"}()){
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support ' . $method, $this->payment->Gateway)
            );
        }

        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBefore'. $ucMethod, $gatewayData);
        $request = $this->oGateway()->{$method}($gatewayData);
        $this->extend('onAfter'. $ucMethod, $request);

        $message = $this->createMessage($ucMethod . 'Request', $request);
        $message->SuccessURL = $this->returnUrl;
        $message->FailureURL = $this->cancelUrl;
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage($ucMethod . 'Error', $e);
            // create an error response by wrapping a non-existant Omnipay response
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        $this->extend('onAfterSend' . $ucMethod, $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = $this->pendingStatus;
            $this->payment->write();

            $this->createMessage(
                $serviceResponse->isRedirect() ? $ucMethod . 'RedirectResponse' : $ucMethod . 'Response',
                $response
            );
        } else if($serviceResponse->isError()){
            $this->createMessage($ucMethod . 'Error', $response);
        } else {
            $this->createMessage($this->completeMessage, $response);
            $this->payment->Status = $this->endStatus;
            $this->payment->write();
            $this->payment->extend($completeHook, $serviceResponse);
        }

        return $serviceResponse;
    }

    /**
     * Complete implementation that allows switching of the method so that it can be used for authorize as well
     * @param array $data
     * @param bool $isNotification
     * @param string $method
     * @param string $completeHook
     * @return ServiceResponse
     * @throws InvalidConfigurationException
     * @throws InvalidStateException
     */
    protected function doComplete(
        $data = array(),
        $isNotification = false,
        $method = 'completePurchase',
        $completeHook = 'onCaptured'
    ) {
        $flags = $isNotification ? ServiceResponse::SERVICE_NOTIFICATION : 0;
        // The payment is already captured
        if($this->payment->Status === $this->endStatus){
            return $this->generateServiceResponse($flags);
        }

        if(!$this->payment->Status === $this->pendingStatus){
            throw new InvalidStateException('Cannot complete this payment. Status is not "'.$this->pendingStatus.'"');
        }

        $ucMethod = ucfirst($method);
        $gateway = $this->oGateway();
        if (!$gateway->{"supports$ucMethod"}()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support ' . $method, $this->payment->Gateway)
            );
        }

        // purchase and completePurchase should use the same data
        $gatewayData = $this->gatherGatewayData($data);

        $this->payment->extend('onBefore' . $ucMethod, $gatewayData);
        $request = $gateway->{$method}($gatewayData);
        $this->payment->extend('onAfter' . $ucMethod, $request);

        $this->createMessage($ucMethod . 'Request', $request);
        $response = null;
        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage($ucMethod . 'Error', $e);
            return $this->generateServiceResponse($flags | ServiceResponse::SERVICE_ERROR);
        }

        $serviceResponse = $this->wrapOmnipayResponse($response, $isNotification);
        if($serviceResponse->isError()){
            $this->createMessage($ucMethod . 'Error', $response);
        } else if(!$serviceResponse->isAwaitingNotification()){
            $this->createMessage($this->completeMessage, $response);
            $this->payment->Status = $this->endStatus;
            $this->payment->write();
            $this->payment->extend($completeHook, $serviceResponse);
        }

        return $serviceResponse;
    }
}
