<?php

class AuthorizeService extends PaymentService
{
    /**
     * Initiate the authorisation process for on-site and off-site gateways.
     * @inheritdoc
     */
    public function initiate($data = array())
    {
        // TODO: Implement initiate() method.
    }

    /**
     * Complete authorisation, after off-site external processing.
     * @inheritdoc
     */
    public function complete($data = array(), $isNotification = false)
    {
        // TODO: Implement complete() method.
    }
}
