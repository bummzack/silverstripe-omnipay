<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use Omnipay\Common\Exception\OmnipayException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Helper;
use SilverStripe\Omnipay\PaymentMath;

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
        if (!$this->payment->canRefund()) {
            throw new InvalidConfigurationException('Refunding of this payment not allowed.');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $reference = null;

        // If the gateway isn't manual, we need a transaction reference to refund a payment
        if (!GatewayInfo::isManual($this->payment->Gateway)) {
            if (!empty($data['transactionReference'])) {
                $reference = $data['transactionReference'];
            } elseif (!empty($data['receipt'])) { // legacy code?
                $reference = $data['receipt'];
            } else {
                $reference = $this->payment->TransactionReference;
            }

            if (empty($reference)) {
                throw new MissingParameterException('transactionReference not found and is not set as parameter');
            }
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsRefund()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support refunds', $this->payment->Gateway)
            );
        }

        $total = $amount = (float)$this->payment->MoneyAmount;
        // If there's a custom amount, ensure it doesn't exceed the payment amount
        if (!empty($data['amount']) && is_numeric($data['amount'])) {
            $amount = min($data['amount'], $total);
        }

        $isPartial = $amount < $total;

        $gatewayData = array_merge(
            $data,
            array(
                'amount' => $amount,
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

        Helper::safeExtend($this, 'onAfterSendRefund', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isAwaitingNotification()) {
            if($isPartial){
                $this->createPartialPayment($amount, $this->pendingState);
            }
            $this->payment->Status = $this->pendingState;
            $this->payment->write();
        } else {
            if ($serviceResponse->isError()) {
                $this->createMessage($this->errorMessageType, $response);
            } else {
                if($isPartial){
                    $this->createPartialPayment($amount, $this->pendingState);
                }
                $this->markCompleted($this->endState, $serviceResponse, $response);
            }
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        $partials = $this->payment->getPartialPayments()->filter('Status', 'PendingRefund');

        if ($partials->count() > 0) {
            $total = $runningTotal = $this->payment->MoneyAmount;
            foreach ($partials as $payment) {
                $runningTotal = PaymentMath::Subtract($runningTotal, $payment->MoneyAmount);
                $payment->Status = 'Refunded';
                $payment->write();
            }

            // Ugly hack to set the money amount
            $this->payment->Status = 'Created';
            $this->payment->setAmount($runningTotal);

            // If not everything was refunded, the payment should still have the "Captured" status
            if((float)$runningTotal > 0){
                $endStatus = 'Captured';
            }
        }

        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        if ($endStatus === 'Captured') {
            $this->createMessage('PartiallyRefundedResponse', $gatewayMessage);
        } else {
            $this->createMessage('RefundedResponse', $gatewayMessage);
        }

        Helper::safeExtend($this->payment, 'onRefunded', $serviceResponse);
    }
}
