<?php

namespace SilverStripe\Omnipay\Service\Response;


use SilverStripe\Omnipay\Exception\ServiceException;

/**
 * Class RedirectResponse
 *
 * A special case of response that redirects to an **offsite** Payment form.
 *
 * @package SilverStripe\Omnipay\Service\Response
 */
class RedirectResponse extends ServiceResponse
{
    public function __construct(\Payment $payment, $response)
    {
        parent::__construct($payment, $response);

        if($response !== null && !$response->isRedirect()){
            throw new ServiceException(
                'Cannot create a RedirectResponse with an Omnipay response that isn\'t a redirect'
            );
        }
    }

    public function getHTTPResponse()
    {
        // Offsite gateway, use payment response to determine redirection,
        // either through GET with simple URL, or POST with a self-submitting form.
        $redirectResponse = $this->omnipayResponse->getRedirectResponse();
        if ($redirectResponse instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
            return \Controller::curr()->redirect($redirectResponse->getTargetUrl());
        } else {
            return new \SS_HTTPResponse((string)$redirectResponse->getContent(), 200);
        }
    }
}
