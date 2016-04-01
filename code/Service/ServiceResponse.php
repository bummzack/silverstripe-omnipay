<?php

namespace SilverStripe\Omnipay\Service;

use Omnipay\Common\Message\AbstractResponse;
use SilverStripe\Omnipay\Exception\ServiceException;

/**
 * Class ServiceResponse.
 *
 * A response generated by a service. This response holds several answer-related properties, such as
 * an HTTP Response, the response from the Omnipay gateway and several flags that give information about the
 * nature of this response
 *
 * This should be used more of container that gives access to several parts that need to be transmitted
 * from the service to the application.
 *
 * Do not implement application logic into service responses.
 *
 * @package SilverStripe\Omnipay\Service\Response
 */
class ServiceResponse
{
    /**
     * Flag to mark this response as an error
     */
    const SERVICE_ERROR = 1;

    /**
     * Flag to mark this response as pending (eg. waiting for an asynchronous response)
     */
    const SERVICE_PENDING = 2;

    /**
     * Flag to mark this response as a notification response (eg. HTTP response will be returned to the payment gateway)
     */
    const SERVICE_NOTIFICATION = 4;

    /**
     * Flag to mark this response as a cancelled payment
     */
    const SERVICE_CANCELLED = 8;

    /**
     * @var \Omnipay\Common\Message\ResponseInterface
     */
    protected $omnipayResponse;

    /**
     * @var int
     */
    protected $flags = 0;

    /**
     * @var bool
     */
    protected $isAwaitingNotification = false;

    /**
     * @var \Payment
     */
    protected $payment;

    /**
     * @var string
     */
    protected $targetUrl;

    /**
     * @var \SS_HTTPResponse
     */
    protected $httpResponse;


    /**
     * ServiceResponse constructor.
     * Additional arguments will be treated as state flags
     * @param \Payment $payment the payment instance
     */
    public function __construct(\Payment $payment)
    {
        $this->payment = $payment;
        for ($i = 1, $len = func_num_args(); $i < $len; $i++) {
            $this->addFlag(func_get_arg($i));
        }
    }

    /**
     * @return \Payment
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * Whether or not this is an *offsite* redirect.
     * This is only the case when there's an Omnipay response present that *is* a redirect.
     * @return bool
     */
    public function isRedirect()
    {
        return $this->omnipayResponse && $this->omnipayResponse->isRedirect();
    }

    /**
     * Whether or not this response is an error-response.
     * Attention: This doesn't necessarily correlate with the Omnipay response being successful or not…
     * A redirect is not successful in terms of completing a payment (response from omnipay isn't successful), yet the
     * service completed successfully and shouldn't report an error here!
     *
     * @return boolean
     */
    public function isError()
    {
        return $this->hasFlag(self::SERVICE_ERROR);
    }

    /**
     * Whether or not the request is pending and waiting for an async notification
     * @return bool
     */
    public function isAwaitingNotification()
    {
        return $this->hasFlag(self::SERVICE_PENDING);
    }

    /**
     * Whether or not this is a response to a notification
     * @return bool
     */
    public function isNotification()
    {
        return $this->hasFlag(self::SERVICE_NOTIFICATION);
    }

    /**
     * Whether or not the payment was cancelled
     * @return bool
     */
    public function isCancelled()
    {
        return $this->hasFlag(self::SERVICE_CANCELLED);
    }

    /**
     * Check if the given flag(s) is set (active)
     * @param int $flag the flag to check. Can be a combination of several flags (joined with binary OR)
     * @return bool true if the given flag/s match
     */
    public function hasFlag($flag)
    {
        if (!is_int($flag)) {
            throw new \InvalidArgumentException('Flag must be of type int');
        }
        return ($this->flags & $flag) === $flag;
    }

    /**
     * Add a flag for this response.
     * Example: `$r->addFlag(ServiceResponse::SERVICE_PENDING)`
     *
     * @param int $flag
     * @throws \InvalidArgumentException if the parameter is not of type int
     * @return $this
     */
    public function addFlag($flag)
    {
        if (!is_int($flag)) {
            throw new \InvalidArgumentException('Flag must be of type int');
        }
        $this->flags |= $flag;
        return $this;
    }

    /**
     * Remove a flag from this response.
     * Example: `$r->removeFlag(ServiceResponse::SERVICE_PENDING)`
     *
     * @param int $flag
     * @throws \InvalidArgumentException if the parameter is not of type int
     * @return $this
     */
    public function removeFlag($flag)
    {
        if (!is_int($flag)) {
            throw new \InvalidArgumentException('Flag must be of type int');
        }
        $this->flags &= ~$flag;
        return $this;
    }

    /**
     * The target url where this response should redirect to (this will be used to redirect internally, if
     * the response wasn't set specifically)
     * @return string
     */
    public function getTargetUrl()
    {
        return $this->targetUrl;
    }

    /**
     * Set the target url.
     * In the case of a redirect, the URL is given by the Omnipay response and should be considered immutable.
     * When trying to set a targetUrl in this scenario, an Exception will be raised.
     * @param string $value the new target url
     * @return $this
     * @throws ServiceException if trying to set a targetUrl for a redirect.
     */
    public function setTargetUrl($value)
    {
        if($this->isRedirect()){
            throw new ServiceException('Unable to override target URL of redirect response');
        }
        $this->targetUrl = $value;
        return $this;
    }

    /**
     * Get the response given by the omnipay gateway
     * @return \Omnipay\Common\Message\AbstractResponse|null
     */
    public function getOmnipayResponse()
    {
        return $this->omnipayResponse;
    }

    /**
     * Set the response from Omnipay
     * @param AbstractResponse $response the response from the omnipay gateway
     * @return $this
     */
    public function setOmnipayResponse(AbstractResponse $response)
    {
        $this->omnipayResponse = $response;
        // also set the target Url if the response is a redirect
        if($this->isRedirect()){
            $redirectResponse = $this->omnipayResponse->getRedirectResponse();
            if ($redirectResponse instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
                $this->targetUrl = $redirectResponse->getTargetUrl();
            }
        }
        return $this;
    }

    /**
     * Create a redirect or a response.
     * This should be called when the application is ready to redirect!
     *
     * If the response is a redirect, the redirect takes precedence.
     * Next, the HTTP response will be returned (if set) and lastly
     * a redirect response to the  @see getTargetUrl.
     *
     * If none of these parameters are given, this method will return null
     *
     * @return null|\SS_HTTPResponse
     */
    public function redirectOrRespond()
    {
        if($this->isRedirect()){
            $redirectResponse = $this->omnipayResponse->getRedirectResponse();
            if ($redirectResponse instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
                $this->targetUrl = $redirectResponse->getTargetUrl();
                return \Controller::curr()->redirect($this->targetUrl);
            } else {
                return new \SS_HTTPResponse((string)$redirectResponse->getContent(), 200);
            }
        }

        if($this->httpResponse){
            return $this->httpResponse;
        }

        if($this->targetUrl){
            return \Controller::curr()->redirect($this->targetUrl);
        }

        return null;
    }

    /**
     * Return the HTTP response given by this gateway.
     * This could be a redirect, but might also be a response with content.
     * @return \SS_HTTPResponse
     */
    public function getHttpResponse()
    {
        return $this->httpResponse;
    }

    /**
     * Set the HTTP response
     * @param \SS_HTTPResponse $response the HTTP response. Can be used to return directly from a payment request
     * @return $this
     */
    public function setHttpResponse(\SS_HTTPResponse $response)
    {
        $this->httpResponse = $response;
        return $this;
    }
}
