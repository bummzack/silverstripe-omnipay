<?php

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\GatewayInterface;
use Omnipay\PaymentExpress\Message\PxPayAuthorizeResponse;
use SilverStripe\Omnipay\Service\PurchaseService;

class PurchaseServiceTest extends PaymentTest {

	public function testDummyOnSitePurchase() {
		$payment = $this->payment;

		$service = new PurchaseService($payment);
		$response = $service->initiate(array(
			'firstName' => 'joe',
			'lastName' => 'bloggs',
			'number' => '4242424242424242', //this creditcard will succeed
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));

		$this->assertEquals("Captured", $payment->Status, "is the status updated");
		$this->assertEquals(1222, $payment->Amount);
		$this->assertEquals("GBP", $payment->Currency);
		$this->assertEquals("Dummy", $payment->Gateway);
		$this->assertTrue($response->getOmnipayResponse()->isSuccessful());
		$this->assertFalse($response->isRedirect());
		$this->assertFalse($response->isError());
		$this->assertFalse($response->isCancelled());
		$this->assertFalse($response->isAwaitingNotification());
		$this->assertFalse($response->isNotification());

		//values cannot be changed after successful purchase
		$payment->Amount = 2;
		$payment->Currency = "NZD";
		$payment->Gateway = "XYZ";
		$payment->write();

		$this->assertEquals(1222, $payment->Amount);
		$this->assertEquals("GBP", $payment->Currency);
		$this->assertEquals("Dummy", $payment->Gateway);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}

	public function testFailedDummyOnSitePurchase() {
		$payment = $this->payment;
		$service = new PurchaseService($payment);
		$response = $service->initiate(array(
			'firstName' => 'joe',
			'lastName' => 'bloggs',
			'number' => '4111111111111111',  //this creditcard will decline
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		$this->assertEquals("Created", $payment->Status, "is the status has not been updated");
		$this->assertEquals(1222, $payment->Amount);
		$this->assertEquals("GBP", $payment->Currency);
		$this->assertFalse($response->getOmnipayResponse()->isSuccessful());
        $this->assertTrue($response->isError());
		$this->assertFalse($response->isRedirect());

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError')
		), $payment->Messages());
	}

	public function testOnSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPost');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('paymentexpress/tests/Mock/PxPostPurchaseSuccess.txt');//add success mock response from file
		$response = $service->initiate(array(
			'firstName' => 'joe',
			'lastName' => 'bloggs',
			'number' => '4242424242424242', //this creditcard will succeed
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		$this->assertTrue($response->getOmnipayResponse()->isSuccessful());
		$this->assertFalse($response->isRedirect());
		$this->assertFalse($response->isError());
		$this->assertSame("Captured", $payment->Status, "has the payment been captured");

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}

	public function testInvalidOnsitePurchase() {
		$payment = $this->payment->setGateway("PaymentExpress_PxPost");
		$service = new PurchaseService($payment);
		//pass no card details nothing
		$response = $service->initiate(array());

		//check messaging
		$this->assertFalse($response->isRedirect());
		$this->assertTrue($response->isError());
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseError')
		), $payment->Messages());

		//TODO:
			//invalid/incorrect card number/date..lhun check (InvalidCreditCardException)
			//InvalidRequestException thrown when gateway needs specific parameters
		$this->markTestIncomplete();
	}

	public function testFailedOnSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPost');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('paymentexpress/tests/Mock/PxPostPurchaseFailure.txt');//add success mock response from file
		$response = $service->initiate(array(
			'number' => '4111111111111111', //this creditcard will decline
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		$this->assertFalse($response->getOmnipayResponse()->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect());
		$this->assertTrue($response->isError());
		$this->assertSame("Created", $payment->Status);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError')
		), $payment->Messages());
	}

	public function testOffSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPay');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayPurchaseSuccess.txt');//add success mock response from file
		$response = $service->initiate();
		$this->assertFalse($response->getOmnipayResponse()->isSuccessful()); //payment has not been captured
		$this->assertTrue($response->isRedirect());
		$this->assertFalse($response->isError()); // this should not be considered to be an error

		$this->assertSame(
			'https://sec.paymentexpress.com/pxpay/pxpay.aspx?userid=Developer&request=v5H7JrBTzH-4Whs__1iQnz4RGSb9qxRKNR4kIuDP8kIkQzIDiIob9GTIjw_9q_AdRiR47ViWGVx40uRMu52yz2mijT39YtGeO7cZWrL5rfnx0Mc4DltIHRnIUxy1EO1srkNpxaU8fT8_1xMMRmLa-8Fd9bT8Oq0BaWMxMquYa1hDNwvoGs1SJQOAJvyyKACvvwsbMCC2qJVyN0rlvwUoMtx6gGhvmk7ucEsPc_Cyr5kNl3qURnrLKxINnS0trdpU4kXPKOlmT6VacjzT1zuj_DnrsWAPFSFq-hGsow6GpKKciQ0V0aFbAqECN8rl_c-aZWFFy0gkfjnUM4qp6foS0KMopJlPzGAgMjV6qZ0WfleOT64c3E-FRLMP5V_-mILs8a',
            $response->getTargetUrl());
		$this->assertSame("PendingPurchase", $payment->Status);
		//... user would normally be redirected to external gateway at this point ...
		//mock complete purchase response
		$this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt');
		//mock the 'result' get variable into the current request
		$this->getHttpRequest()->query->replace(array('result' => 'abc123'));
		$response = $service->complete();
		$this->assertTrue($response->getOmnipayResponse()->isSuccessful());
		$this->assertSame("Captured", $payment->Status);
        $this->assertFalse($response->isError());

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseRedirectResponse'),
			array('ClassName' => 'CompletePurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}

	public function testFailedOffSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPay');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayPurchaseFailure.txt');//add success mock response from file
		$response = $service->initiate();
		$this->assertFalse($response->getOmnipayResponse()->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect()); //redirect won't occur, because of failure
		$this->assertTrue($response->isError());
		$this->assertSame("Created", $payment->Status);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError'),
		), $payment->Messages());

		//TODO: fail in various ways
		$this->markTestIncomplete();
	}

	public function testFailedOffSiteCompletePurchase() {
		$this->setMockHttpResponse(
			'paymentexpress/tests/Mock/PxPayCompletePurchaseFailure.txt'
		);
		//mock the 'result' get variable into the current request
		$this->getHttpRequest()->query->replace(array('result' => 'abc123'));
		//mimic a redirect or request from offsite gateway
		$response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/complete");
		//redirect works
		$headers = $response->getHeaders();
		$this->assertEquals(
			Director::baseURL()."shop/incomplete",
			$headers['Location'],
			"redirected to shop/incomplete"
		);
		$payment = Payment::get()
					->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
					->first();
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseRedirectResponse'),
			array('ClassName' => 'CompletePurchaseRequest'),
			array('ClassName' => 'CompletePurchaseError')
		), $payment->Messages());
	}


	public function testNonExistantGateway() {
		//exception when trying to run functions that require a gateway
		$payment = $this->payment;
		$service = PurchaseService::create(
				$payment->init("PxPayGateway", 100, "NZD")
			)->setReturnUrl("complete");

		$this->setExpectedException("RuntimeException");
		try{
		$result = $service->initiate();
		}catch(RuntimeException $e){
			$this->markTestIncomplete();
		$totalNZD = Payment::get()->filter('MoneyCurrency', "NZD")->sum();
		$this->assertEquals(27.23, $totalNZD);
		$service->purchase();
		$service->completePurchase();
			//just to assert that exception is thrown
			throw $e;
		}
	}


	public function testTokenGateway() {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPost', array(
            'token_key' => 'token'
        ));
		$stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('purchase', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('purchase')
            ->with(
                $this->logicalAnd(
                    $this->arrayHasKey('token'),
                    $this->contains('ABC123'),
                    $this->logicalNot($this->arrayHasKey('card'))
                )
            )
            ->will(
                $this->returnValue($this->stubRequest())
            )
        ;

		$payment = $this->payment->setGateway('PaymentExpress_PxPost');

		/** @var PurchaseService $service */
		$service = PurchaseService::create($payment);
		$service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

		$service->initiate(array('token' => 'ABC123'));
	}

    public function testTokenGatewayWithAlternateKey() {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPost', array(
            'token_key' => 'my_token'
        ));
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('purchase', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('purchase')
            ->with(
                $this->logicalAnd(
                    $this->arrayHasKey('token'), // my_token should get normalized to this
                    $this->contains('ABC123'),
                    $this->logicalNot($this->arrayHasKey('card'))
                )
            )
            ->will(
                $this->returnValue($this->stubRequest())
            )
        ;

        $payment = $this->payment->setGateway('PaymentExpress_PxPost');

        /** @var PurchaseService $service */
        $service = PurchaseService::create($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $service->initiate(array('my_token' => 'ABC123'));
    }

	/**
	 * @param GatewayInterface|PHPUnit_Framework_MockObject_MockObject $stubGateway
	 * @return PHPUnit_Framework_MockObject_MockObject|GatewayFactory
	 */
	protected function stubGatewayFactory($stubGateway) {
		$factory = $this->getMockBuilder('Omnipay\Common\GatewayFactory')->getMock();
		$factory->expects($this->any())->method('create')->will($this->returnValue($stubGateway));
		return $factory;
	}

    /**
     * @return PHPUnit_Framework_MockObject_MockObject|Omnipay\Common\Message\AbstractRequest
     */
    protected function stubRequest() {
        $request = $this->getMockBuilder('Omnipay\Common\Message\AbstractRequest')
            ->disableOriginalConstructor()
            ->getMock();
        $response = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects($this->any())->method('isSuccessful')->will($this->returnValue(true));
        $request->expects($this->any())->method('send')->will($this->returnValue($response));
        return $request;
    }
}
