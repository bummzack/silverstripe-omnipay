<?php

namespace SilverStripe\Omnipay\Service;


use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\GatewayResponse;
use SilverStripe\Omnipay\Service\Response\RedirectResponse;
use SilverStripe\Omnipay\Service\Response\SuccessResponse;

class PurchaseService extends PaymentService
{
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
		if ($this->payment->Status !== 'Created') {
            throw new InvalidStateException('Cannot initiate a purchase with this payment. Status is not "Created"');
		}

		if (!$this->payment->isInDB()) {
			$this->payment->write();
		}

        $gateway = $this->oGateway();
        if(!$gateway->supportsPurchase()){
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support purchase', $this->payment->Gateway)
            );
        }

		//update success/fail urls
		//$this->update($data);

        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforePurchase', $gatewayData);
        $request = $this->oGateway()->purchase($gatewayData);
        $this->extend('onAfterPurchase', $request);

        $message = $this->createMessage('PurchaseRequest', $request);
		$message->SuccessURL = $this->returnUrl;
		$message->FailureURL = $this->cancelUrl;
		$message->write();

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage('PurchaseError', $e);
            return $this->getErrorResponse($this->response, $e->getMessage());
        }

        $this->extend('onAfterSendPurchase', $request, $response);

        // check for a redirect.
        if ($response instanceof RedirectResponseInterface && $response->isRedirect()) {
            $this->createMessage('PurchaseRedirectResponse', $response);
            $this->payment->Status = 'PendingCapture';
            $this->payment->write();

            // redirect to off-site payment gateway
            $redirectResponse = new RedirectResponse($this->payment, $response);
            $redirectResponse->setMessage("Redirecting to gateway");
            return $redirectResponse;
        }

        // check for success. We can complete the payment if the gateway returned success
        if ($response->isSuccessful()) {
            return $this->completePayment($response);
        }

        // Leaves us with the error case
        $this->createMessage('PurchaseError', $response);
        return $this->getErrorResponse($response, "Error (".$response->getCode()."): ".$response->getMessage());
	}

	/**
	 * Finalise this payment, after off-site external processing.
	 * This is usually only called by PaymentGatewayController.
	 * @inheritdoc
	 */
    public function complete($data = array(), $isNotification = false)
    {
        // The payment is already captured
        if($this->payment->Status === 'Captured'){
            if($isNotification){

            }
            return null;
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsCompletePurchase()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support completePurchase', $this->payment->Gateway)
            );
        }

        $gatewayResponse = $this->createGatewayResponse();
        // purchase and completePurchase should use the same data
		$gatewayData = $this->gatherGatewayData($data);

		$this->payment->extend('onBeforeCompletePurchase', $gatewayData);
        $request = $gateway->completePurchase($gatewayData);
        $this->payment->extend('onAfterCompletePurchase', $request);

        $this->createMessage('CompletePurchaseRequest', $request);
		$response = null;
		try {
			$response = $this->response = $request->send();
            $this->extend('onAfterSendCompletePurchase', $request, $response);
            $gatewayResponse->setOmnipayResponse($response);
			if ($response->isSuccessful()) {
				$this->completePayment($gatewayResponse);
			} else {
				$this->createMessage('CompletePurchaseError', $response);
			}
		} catch (\Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage("CompletePurchaseError", $e);
		}

		return $gatewayResponse;
	}

    protected function completePayment(AbstractResponse $gwResponse)
    {
        $this->createMessage('PurchasedResponse', $gwResponse);
        $this->payment->Status = 'Captured';
        $this->payment->write();

        $response = new SuccessResponse($this->payment, $gwResponse);
        $response->setMessage('Payment successful');

        $this->payment->extend('onCaptured', $response);

        return $response;
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

}
