<?php

namespace SilverStripe\Omnipay\Service\Response;

use SilverStripe\Omnipay\Exception\ServiceException;

/**
 * Class ErrorResponse
 *
 * This will be returned by a service if the gateway responded with an error
 *
 * @package SilverStripe\Omnipay\Service\Response
 */
class ErrorResponse extends ServiceResponse
{
    public function __construct(\Payment $payment, $response)
    {
        parent::__construct($payment, $response);

        if($response !== null && $response->isSuccessful()){
            throw new ServiceException('Cannot create an ErrorResponse with a successful Omnipay response');
        }
    }

    
    public function isSuccessful()
    {
        return false;
    }

    public function getHTTPResponse()
    {
        return new \SS_HTTPResponse($this->getMessage(), 500);
    }
}
