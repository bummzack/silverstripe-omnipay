<?php

namespace SilverStripe\Omnipay\Service;

/**
 * Service used in tandem with AuthorizeService.
 * This service captures a previously authorized amount
 */
class CaptureService extends PaymentService
{
    //TODO: Ensure that this can also capture partial payments.
    /**
     * @inheritdoc
     */
    public function initiate($data = array())
    {
        // TODO: Implement initiate() method.
    }

    /**
     * @inheritdoc
     */
    public function complete($data = array(), $isNotification = false)
    {
        // TODO: Implement complete() method.
    }
}
