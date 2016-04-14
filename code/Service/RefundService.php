<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use Omnipay\Common\Exception\OmnipayException;

class RefundService extends NotificationCompleteService
{
    protected $endState = 'Refunded';
    protected $pendingState = 'PendingRefund';
    protected $requestMessageType = 'RefundRequest';
    protected $errorMessageType = 'RefundError';

    /**
     * Return money to the previously charged credit card.
     *
     * If the transaction-reference of the payment to refund is known, pass it via $data as
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
        if ($this->payment->Status !== 'Captured') {
            throw new InvalidStateException('Cannot refund a payment that isn\'t "Captured".');
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
                $msg = $this->payment->getLatestMessageOfType(array('CapturedResponse', 'PurchasedResponse'));
                $reference = $msg ? $msg->Reference : null;
            }
        }

        if (empty($reference)) {
            throw new MissingParameterException('transactionReference not found and is not set as parameter');
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsRefund()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support refunds', $this->payment->Gateway)
            );
        }

        $gatewayData = array_merge(
            $data,
            array(
                'amount' => (float)$this->payment->MoneyAmount,
                'currency' => $this->payment->MoneyCurrency,
                'transactionReference' => $reference,
                'notifyUrl' => $this->getEndpointUrl('notify')
            )
        );

        $this->extend('onBeforeRefund', $gatewayData);
        $request = $this->oGateway()->refund($gatewayData);
        $this->extend('onAfterRefund', $request);

        $message = $this->createMessage($this->requestMessageType, $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (OmnipayException $e) {
            $this->createMessage($this->errorMessageType, $e);
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        $this->extend('onAfterSendRefund', $request, $response);

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
        $this->createMessage('RefundedResponse', $gatewayMessage);
        $this->payment->Status = $this->endState;
        $this->payment->write();
        $this->payment->extend('onRefunded', $serviceResponse);
    }


}
