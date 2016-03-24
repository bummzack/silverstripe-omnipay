<?php

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
            //TODO: Throw exception?
			return null; //could be handled better? send payment response?
		}

		if (!$this->payment->isInDB()) {
			$this->payment->write();
		}

        $gateway = $this->oGateway();
        if(!$gateway->supportsPurchase()){
            //TODO: Throw exception?
            return null;
        }

		//update success/fail urls
		$this->update($data);

        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforePurchase', $gatewayData);
        $request = $this->oGateway()->purchase($gatewayData);
        $this->extend('onAfterPurchase', $request);

        $message = $this->createMessage('PurchaseRequest', $request);
		$message->SuccessURL = $this->returnurl;
		$message->FailureURL = $this->cancelurl;
		$message->write();

		$gatewayResponse = $this->createGatewayResponse();
		try {
			$response = $this->response = $request->send();
            $this->extend('onAfterSendPurchase', $request, $response);
            $gatewayResponse->setOmnipayResponse($response);
			//update payment model
			if ($response->isSuccessful()) {
                $this->completePayment($gatewayResponse);
			} elseif ($response->isRedirect()) {
				// redirect to off-site payment gateway
				$this->createMessage('PurchaseRedirectResponse', $response);
				$this->payment->Status = 'PendingCapture';
				$this->payment->write();
                $gatewayResponse->setMessage("Redirecting to gateway");
			} else {
				//handle error
				$this->createMessage('PurchaseError', $response);
                $gatewayResponse->setMessage(
					"Error (".$response->getCode()."): ".$response->getMessage()
				);
			}
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage('PurchaseError', $e);
            $gatewayResponse->setMessage($e->getMessage());
		}

        $gatewayResponse->setRedirectURL($this->getRedirectURL());

		return $gatewayResponse;
	}

	/**
	 * Finalise this payment, after off-site external processing.
	 * This is ususally only called by PaymentGatewayController.
	 * @inheritdoc
	 */
    public function complete($data = array(), $isNotification = false)
    {
        if($this->payment->Status === 'Captured'){
            return null;
        }
        
        $gateway = $this->oGateway();
        if (!$gateway->supportsCompletePurchase()) {
            //TODO: Throw exception?
            return null;
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
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage("CompletePurchaseError", $e);
		}

		return $gatewayResponse;
	}

    protected function completePayment(GatewayResponse $gwResponse)
    {
        $gwResponse->setMessage("Payment successful");
        $this->createMessage('PurchasedResponse', $gwResponse->getOmnipayResponse());
        $this->payment->Status = 'Captured';
        $this->payment->write();
        $this->payment->extend('onCaptured', $gwResponse);
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
        Deprecation::notice('3.0', 'Use the `initiate` method instead.');
        return $this->initiate($data);
    }

    /**
     * Finalise this payment, after off-site external processing.
     * This is ususally only called by PaymentGatewayController.
     * @deprecated 3.0 Use the `complete` method instead
     */
    public function completePurchase($data = array())
    {
        Deprecation::notice('3.0', 'Use the `complete` method instead.');
        return $this->complete($data);
    }

}
