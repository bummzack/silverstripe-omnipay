<?php

namespace SilverStripe\Omnipay\Service\Response;


use SilverStripe\Omnipay\Exception\ServiceException;

/**
 * Class SuccessResponse
 *
 * A response to return when a service completed successfully
 *
 * @package SilverStripe\Omnipay\Service\Response
 */
class SuccessResponse extends ServiceResponse
{
    /**
     * @var string
     */
    protected $redirectUrl;

    public function __construct(\Payment $payment, $response)
    {
        parent::__construct($payment, $response);

        if($response !== null && !$response->isSuccessful()){
            throw new ServiceException('Cannot create a SuccessResponse with an unsuccessful Omnipay response');
        }
    }

    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl($value)
    {
        $this->redirectUrl = $value;
        return $this;
    }

    public function getHTTPResponse()
    {
        return new \SS_HTTPResponse();
    }
}
