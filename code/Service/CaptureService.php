<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use Omnipay\Common\Exception\OmnipayException;

/**
 * Service used in tandem with AuthorizeService.
 * This service captures a previously authorized amount
 */
class CaptureService extends NotificationCompleteService
{
    //TODO: Ensure that this can also capture partial payments. This would probably have to generate additional Payments

    protected $endState = 'Captured';
    protected $pendingState = 'PendingCapture';
    protected $requestMessageType = 'CaptureRequest';
    protected $errorMessageType = 'CaptureError';

    /**
     * Capture a previously authorized payment
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
            throw new InvalidStateException('Cannot capture a payment that isn\'t "Authorized".');
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
                $msg = $this->payment->getLatestMessageOfType('AuthorizedResponse');
                $reference = $msg ? $msg->Reference : null;
            }
        }

        if (empty($reference)) {
            throw new MissingParameterException('transactionReference not found and is not set as parameter');
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsCapture()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support capture', $this->payment->Gateway)
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

        $this->extend('onBeforeCapture', $gatewayData);
        $request = $this->oGateway()->capture($gatewayData);
        $this->extend('onAfterCapture', $request);

        $message = $this->createMessage($this->requestMessageType, $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (OmnipayException $e) {
            $this->createMessage($this->errorMessageType, $e);
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        $this->extend('onAfterSendCapture', $request, $response);

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
       $this->createMessage('CapturedResponse', $gatewayMessage);
       $this->payment->Status = $this->endState;
       $this->payment->write();
       $this->payment->extend('onCaptured', $serviceResponse);
   }
}
