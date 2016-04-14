<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use Omnipay\Common\Exception\OmnipayException;

class VoidService extends NotificationCompleteService
{
    protected $endState = 'Void';
    protected $pendingState = 'PendingVoid';
    protected $requestMessageType = 'VoidRequest';
    protected $errorMessageType = 'VoidError';

    /**
     * Void/cancel a payment
     *
     * If the transaction-reference of the payment to capture is known, pass it via $data as
     * `transactionReference` parameter. Otherwise the service will try to look up the reference
     * from previous payment messages.
     *
     * If there's no transaction-reference to be found, this method will raise an exception.
     *
     * @inheritdoc
     * @throws MissingParameterException if no transaction reference can be found from messages or parameters
     */
    public function initiate($data = array())
    {
        if ($this->payment->Status !== 'Authorized') {
            throw new InvalidStateException('Cannot cancel/void a payment that isn\'t "Authorized".');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        if (!empty($data['transactionReference'])) {
            $reference = $data['transactionReference'];
        } else {
            if (!empty($data['receipt'])) { // legacy code?
                $reference = $data['receipt'];
            } else {
                $msg = $this->payment->getLatestMessageOfType(array('AuthorizedResponse', 'PurchasedResponse'));
                $reference = $msg ? $msg->Reference : null;
            }
        }

        if (empty($reference)) {
            throw new MissingParameterException('transactionReference not found and is not set as parameter');
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsVoid()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support void', $this->payment->Gateway)
            );
        }

        $gatewayData = array_merge(
            $data,
            array(
                'transactionReference' => $reference,
                'notifyUrl' => $this->getEndpointUrl('notify')
            )
        );

        $this->extend('onBeforeVoid', $gatewayData);
        $request = $this->oGateway()->void($gatewayData);
        $this->extend('onAfterVoid', $request);

        $message = $this->createMessage($this->requestMessageType, $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (OmnipayException $e) {
            $this->createMessage($this->errorMessageType, $e);
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        $this->extend('onAfterSendVoid', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = $this->pendingState;
            $this->payment->write();
        } else {
            if ($serviceResponse->isError()) {
                $this->createMessage($this->errorMessageType, $response);
            } else {
                $this->markCompleted($serviceResponse, $response);
            }
        }

        return $serviceResponse;
    }

    protected function markCompleted(ServiceResponse $serviceResponse, $gatewayMessage)
    {
        $this->createMessage('VoidedResponse', $gatewayMessage);
        $this->payment->Status = $this->endState;
        $this->payment->write();
        $this->payment->extend('onVoid', $serviceResponse);
    }
}
