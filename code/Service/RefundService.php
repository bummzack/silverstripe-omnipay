<?php

namespace SilverStripe\Omnipay\Service;

use Omnipay\Common\Message\NotificationInterface;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\MissingParameterException;

class RefundService extends PaymentService
{

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
                $msg = $this->payment->getLatestMessageOfType(array('CapturedResponse', 'PurchasedResponse'))
                    ->where('"Reference" IS NOT NULL');

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
                'transactionReference' => $reference
            )
        );

        $this->extend('onBeforeRefund', $gatewayData);
        $request = $this->oGateway()->refund($gatewayData);
        $this->extend('onAfterRefund', $request);

        $message = $this->createMessage('RefundRequest', $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage('RefundError', $e);
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        $this->extend('onAfterSendRefund', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = 'PendingRefund';
            $this->payment->write();
        } else {
            if ($serviceResponse->isError()) {
                $this->createMessage('RefundError', $response);
            } else {
                $this->createMessage('RefundedResponse', $response);
                $this->payment->Status = 'Refunded';
                $this->payment->write();
                $this->payment->extend('onRefunded', $serviceResponse);
            }
        }

        return $serviceResponse;
    }

    /**
     * Complete a pending refund.
     * This is only needed for notification, so this method will always assume $isNotification is true!
     *
     * @param array $data
     * @param bool $isNotification
     * @return ServiceResponse
     * @throws InvalidConfigurationException
     * @throws InvalidStateException
     */
    public function complete($data = array(), $isNotification = true)
    {
        // The payment is already refunded
        if ($this->payment->Status === 'Refunded') {
            return $this->generateServiceResponse(ServiceResponse::SERVICE_NOTIFICATION);
        }

        if ($this->payment->Status !== 'PendingRefund') {
            throw new InvalidStateException('Cannot complete this payment. Status is not "PendingRefund"');
        }

        $serviceResponse = $this->handleNotification();

        // check if we're done
        if (!$serviceResponse->isError() && !$serviceResponse->isAwaitingNotification()) {
            $this->createMessage('RefundedResponse', 'Refund confirmed via notification');
            $this->payment->Status = 'Refunded';
            $this->payment->write();
            $this->payment->extend('onRefunded', $serviceResponse);
        }

        return $serviceResponse;
    }


}
