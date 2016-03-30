<?php

namespace SilverStripe\Omnipay\Service;


class VoidService extends PaymentService
{

	/**
	 * Cancel this payment, and prevent any future changes.
	 * @inheritdoc
	 */
	public function initiate($data = array())
    {
        //TODO: call gateway function, if available
		$this->payment->Status = "Void";
		$this->payment->write();
    }

    public function complete($data = array(), $isNotification = false)
    {
        // TODO: Implement complete() method.
    }
}
