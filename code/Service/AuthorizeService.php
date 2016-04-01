<?php

namespace SilverStripe\Omnipay\Service;


use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;

class AuthorizeService extends PurchaseService
{
    protected $requestMessage = 'AuthorizeRequest';
    protected $completeMessage = 'AuthorizedResponse';
    protected $endStatus = 'Authorized';
    protected $pendingStatus = 'PendingAuthorization';

    /**
     * Start an authorization request
     *
     * @inheritdoc
     */
    public function initiate($data = array()) {
        return $this->doInitiate($data, 'authorize', 'onAuthorized');
    }

    /**
     * Finalise this authorization, after off-site external processing.
     * This is usually only called by PaymentGatewayController.
     * @inheritdoc
     */
    public function complete($data = array(), $isNotification = false)
    {
        return $this->doComplete($data, $isNotification, 'completeAuthorize', 'onAuthorized');
    }
}
