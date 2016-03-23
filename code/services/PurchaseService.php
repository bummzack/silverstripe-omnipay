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
		if ($this->payment->Status !== "Created") {
			return null; //could be handled better? send payment response?
		}
		if (!$this->payment->isInDB()) {
			$this->payment->write();
		}
		//update success/fail urls
		$this->update($data);

		//set the client IP address, if not already set
		if(!isset($data['clientIp'])){
			$data['clientIp'] = Controller::curr()->getRequest()->getIP();
		}

		$gatewaydata = array_merge($data, array(
			'amount' => (float) $this->payment->MoneyAmount,
			'currency' => $this->payment->MoneyCurrency,
			//set all gateway return/cancel/notify urls to PaymentGatewayController endpoint
			'returnUrl' => $this->getEndpointURL("complete", $this->payment->Identifier),
			'cancelUrl' => $this->getEndpointURL("cancel", $this->payment->Identifier),
			'notifyUrl' => $this->getEndpointURL("notify", $this->payment->Identifier)
		));

        // Often, the shop will want to pass in a transaction ID (order #, etc), but if there's
        // not one we need to set it as Ominpay requires this.
		if(!isset($gatewaydata['transactionId'])){
			$gatewaydata['transactionId'] = $this->payment->Identifier;
		}

        // We only look for a card if we aren't already provided with a token
        // Increasingly we can expect tokens or nonce's to be more common (e.g. Stripe and Braintree)
        $tokenKey = GatewayInfo::getTokenKey($this->payment->Gateway);
        if (empty($gatewaydata[$tokenKey])) {
            $gatewaydata['card'] = $this->getCreditCard($data);
        } elseif ($tokenKey !== 'token') {
            // some gateways (eg. braintree) use a different key but we need
            // to normalize that for omnipay
            $gatewaydata['token'] = $gatewaydata[$tokenKey];
            unset($gatewaydata[$tokenKey]);
        }

        $this->extend('onBeforePurchase', $gatewaydata);
        $request = $this->oGateway()->purchase($gatewaydata);
        $this->extend('onAfterPurchase', $request);

        $message = $this->createMessage('PurchaseRequest', $request);
		$message->SuccessURL = $this->returnurl;
		$message->FailureURL = $this->cancelurl;
		$message->write();

		$gatewayresponse = $this->createGatewayResponse();
		try {
			$response = $this->response = $request->send();
            $this->extend('onAfterSendPurchase', $request, $response);
			$gatewayresponse->setOmnipayResponse($response);
			//update payment model
			if (GatewayInfo::isManual($this->payment->Gateway)) {
				//initiate manual payment
				$this->createMessage('AuthorizedResponse', $response);
				$this->payment->Status = 'Authorized';
				$this->payment->write();
				$gatewayresponse->setMessage("Manual payment authorised");
			} elseif ($response->isSuccessful()) {
				//successful payment
				$this->createMessage('PurchasedResponse', $response);
				$this->payment->Status = 'Captured';
				$gatewayresponse->setMessage("Payment successful");
				$this->payment->write();
				$this->payment->extend('onCaptured', $gatewayresponse);
			} elseif ($response->isRedirect()) {
				// redirect to off-site payment gateway
				$this->createMessage('PurchaseRedirectResponse', $response);
				$this->payment->Status = 'Authorized';
				$this->payment->write();
				$gatewayresponse->setMessage("Redirecting to gateway");
			} else {
				//handle error
				$this->createMessage('PurchaseError', $response);
				$gatewayresponse->setMessage(
					"Error (".$response->getCode()."): ".$response->getMessage()
				);
			}
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage('PurchaseError', $e);
			$gatewayresponse->setMessage($e->getMessage());
		}
		$gatewayresponse->setRedirectURL($this->getRedirectURL());

		return $gatewayresponse;
	}

	/**
	 * Finalise this payment, after off-site external processing.
	 * This is ususally only called by PaymentGatewayController.
	 * @inheritdoc
	 */
    public function complete($data = array(), $isNotification = false)
    {
        $gatewayresponse = $this->createGatewayResponse();

		//set the client IP address, if not already set
		if(!isset($data['clientIp'])){
			$data['clientIp'] = Controller::curr()->getRequest()->getIP();
		}

		$gatewaydata = array_merge($data, array(
			'amount' => (float) $this->payment->MoneyAmount,
			'currency' => $this->payment->MoneyCurrency
		));

		$this->payment->extend('onBeforeCompletePurchase', $gatewaydata);
        $request = $this->oGateway()->completePurchase($gatewaydata);
        $this->payment->extend('onAfterCompletePurchase', $request);

        $this->createMessage('CompletePurchaseRequest', $request);
		$response = null;
		try {
			$response = $this->response = $request->send();
            $this->extend('onAfterSendCompletePurchase', $request, $response);
			$gatewayresponse->setOmnipayResponse($response);
			if ($response->isSuccessful()) {
				$this->createMessage('PurchasedResponse', $response);
				$this->payment->Status = 'Captured';
				$this->payment->write();
				$this->payment->extend('onCaptured', $gatewayresponse);
			} else {
				$this->createMessage('CompletePurchaseError', $response);
			}
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage("CompletePurchaseError", $e);
		}

		return $gatewayresponse;
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
