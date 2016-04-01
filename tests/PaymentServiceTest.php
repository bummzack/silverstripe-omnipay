<?php

use SilverStripe\Omnipay\Service\ServiceFactory;

class PaymentServiceTest extends PaymentTest
{

	public function testRedirectUrl()
    {
		$service = $this->factory->getService(new Payment(), ServiceFactory::INTENT_PURCHASE)
					->setReturnUrl("abc/123")
					->setCancelUrl("xyz/blah/2345235?andstuff=124124#hash");
		$this->assertEquals("abc/123",$service->getReturnUrl());
		$this->assertEquals("xyz/blah/2345235?andstuff=124124#hash",$service->getCancelUrl());
	}

}
