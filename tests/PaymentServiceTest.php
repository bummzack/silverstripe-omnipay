<?php

use SilverStripe\Omnipay\Service\ServiceFactory;

class PaymentServiceTest extends PaymentTest
{

    /** @var \SilverStripe\Omnipay\Service\PurchaseService */
    protected $service;

    public function setUp()
    {
        parent::setUp();

        $this->service = $this->factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE);
    }

    public function testRedirectUrl()
    {
		$this->service
            ->setReturnUrl("abc/123")
            ->setCancelUrl("xyz/blah/2345235?andstuff=124124#hash");

		$this->assertEquals("abc/123", $this->service->getReturnUrl());
		$this->assertEquals("xyz/blah/2345235?andstuff=124124#hash", $this->service->getCancelUrl());
	}

    public function testCancel()
    {
        $response = $this->service->cancel();

        $this->assertEquals('Void', $this->payment->Status);
        $this->assertTrue($response->isCancelled());
    }

    public function testGateway()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            // set some invalid params
            'parameters' => array(
                'DummyParameter' => 'DummyValue'
            )
        ));

        $gateway = $this->service->oGateway();
        $this->assertEquals($gateway->getShortName(), 'Dummy');

        // change the payment gateway
        $this->payment->Gateway = 'PaymentExpress_PxPay';

        $gateway = $this->service->oGateway();
        $this->assertEquals($gateway->getShortName(), 'PaymentExpress_PxPay');
        $this->assertEquals($gateway->getParameters(), array(
            'username' => 'EXAMPLEUSER',
            'password' => '235llgwxle4tol23l'
        ));
    }
}
