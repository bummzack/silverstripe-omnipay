<?php

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\GatewayInterface;
use Omnipay\PaymentExpress\Message\PxPayAuthorizeResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use SilverStripe\Omnipay\Service\PurchaseService;

class PurchaseServiceTest extends PaymentTest
{

    public function testDummyOnSitePurchase()
    {
        $payment = $this->payment;

        $service = PurchaseService::create($payment);
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

    public function testFailedDummyOnSitePurchase()
    {
        $payment = $this->payment;
        $service = PurchaseService::create($payment);
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

    public function testOnSitePurchase()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPost');
        $service = PurchaseService::create($payment);
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

    public function testInvalidOnsitePurchase()
    {
        $payment = $this->payment->setGateway("PaymentExpress_PxPost");
        $service = PurchaseService::create($payment);
        //pass no card details nothing
        $response = $service->initiate(array());

        //check messaging
        $this->assertFalse($response->isRedirect());
        $this->assertTrue($response->isError());
        $this->assertDOSContains(array(
            array('ClassName' => 'PurchaseError')
        ), $payment->Messages());
    }

    public function testFailedOnSitePurchase()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPost');
        $service = PurchaseService::create($payment);
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

    public function testOffSitePurchase()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $service = PurchaseService::create($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayPurchaseSuccess.txt');//add success mock response from file
        $response = $service->initiate();
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful()); //payment has not been captured
        $this->assertTrue($response->isRedirect());
        $this->assertFalse($response->isError()); // this should not be considered to be an error

        $this->assertSame(
            'https://sec.paymentexpress.com/pxpay/pxpay.aspx?userid=Developer&request=v5H7JrBTzH-4Whs__1iQnz4RGSb9qxRKNR4kIuDP8kIkQzIDiIob9GTIjw_9q_AdRiR47ViWGVx40uRMu52yz2mijT39YtGeO7cZWrL5rfnx0Mc4DltIHRnIUxy1EO1srkNpxaU8fT8_1xMMRmLa-8Fd9bT8Oq0BaWMxMquYa1hDNwvoGs1SJQOAJvyyKACvvwsbMCC2qJVyN0rlvwUoMtx6gGhvmk7ucEsPc_Cyr5kNl3qURnrLKxINnS0trdpU4kXPKOlmT6VacjzT1zuj_DnrsWAPFSFq-hGsow6GpKKciQ0V0aFbAqECN8rl_c-aZWFFy0gkfjnUM4qp6foS0KMopJlPzGAgMjV6qZ0WfleOT64c3E-FRLMP5V_-mILs8a',
            $response->getTargetUrl());
        // Status should be set to pending
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

    public function testFailedOffSitePurchase()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $service = PurchaseService::create($payment);
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
    }

    public function testFailedOffSiteCompletePurchase()
    {
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
            Director::baseURL() . "shop/incomplete",
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

    /**
     * @expectedException \Omnipay\Common\Exception\RuntimeException
     */
    public function testNonExistantGateway()
    {
        //exception when trying to run functions that require a gateway
        $payment = $this->payment;
        $service = PurchaseService::create(
            $payment->init("FantasyGateway", 100, "NZD")
        )->setReturnUrl("complete");

        // Will throw an exception since the gateway doesn't exist
        $result = $service->initiate();
    }


    public function testTokenGateway()
    {
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
            );

        $payment = $this->payment->setGateway('PaymentExpress_PxPost');

        /** @var PurchaseService $service */
        $service = PurchaseService::create($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $service->initiate(array('token' => 'ABC123'));
    }

    public function testTokenGatewayWithAlternateKey()
    {
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
            );

        $payment = $this->payment->setGateway('PaymentExpress_PxPost');

        /** @var PurchaseService $service */
        $service = PurchaseService::create($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $service->initiate(array('my_token' => 'ABC123'));
    }

    public function testAsyncPurchaseConfirmation()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_async_notification' => true
        ));

        // build a stub gateway with the given endpoint
        $isNotification = false;
        $stubGateway = $this->buildPurchaseGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification){
            return $isNotification;
        });
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');

        /** @var PurchaseService $service */
        $service = PurchaseService::create($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $serviceResponse = $service
            ->setCancelUrl('my/cancel/url')
            ->setReturnUrl('my/return/url')
            ->initiate();

        // we should get a redirect
        $this->assertTrue($serviceResponse->isRedirect());
        // that redirect should point to the endpoint returned by omnipay
        $this->assertEquals($serviceResponse->getTargetUrl(), 'https://gateway.tld/endpoint');
        // purchase should be pending
        $this->assertEquals($payment->Status, 'PendingPurchase');

        $serviceResponse = $service->complete(array(), $isNotification);

        // since the confirmation will come in asynchronously, the gateway doesn't report success when coming back
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Gateway will not return success');
        // Our application considers that fact and doesn't mark the service call as an error!
        $this->assertFalse($serviceResponse->isError());
        // We should get redirected to the success page now
        $this->assertEquals($serviceResponse->getTargetUrl(), 'my/return/url');
        // Payment status should still be pending
        $this->assertEquals($payment->Status, 'PendingPurchase');


        // simulate an incoming notification
        $isNotification = true;

        $serviceResponse = $service->complete(array(), $isNotification);

        // the response from the gateway should now be successful
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Response should be successful');
        // Should not be an error
        $this->assertFalse($serviceResponse->isError());
        // We should get an HTTP response with "OK"
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals($httpResponse->getBody(), 'OK');
        $this->assertEquals($httpResponse->getStatusCode(), 200);
        // Payment status should be captured
        $this->assertEquals($payment->Status, 'Captured');
    }

    // Test an async response that comes in before the user returns from the offsite form
    public function testAsyncPurchaseConfirmationIncomingFirst()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_async_notification' => true
        ));

        // build a stub gateway with the given endpoint
        $isNotification = true;
        $stubGateway = $this->buildPurchaseGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification){
            return $isNotification;
        });
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');

        /** @var PurchaseService $service */
        $service = PurchaseService::create($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $serviceResponse = $service
            ->setCancelUrl('my/cancel/url')
            ->setReturnUrl('my/return/url')
            ->initiate();

        // we should get a redirect
        $this->assertTrue($serviceResponse->isRedirect());
        // purchase should be pending
        $this->assertEquals($payment->Status, 'PendingPurchase');

        // Notification comes in first!
        $isNotification = true;
        $serviceResponse = $service->complete(array(), $isNotification);

        // since we're getting the async notification now, payment should be successful
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Response should be successful');
        // Should not be an error
        $this->assertFalse($serviceResponse->isError());
        // We should get an HTTP response with "OK"
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals($httpResponse->getBody(), 'OK');
        $this->assertEquals($httpResponse->getStatusCode(), 200);
        // Payment status should be captured
        $this->assertEquals($payment->Status, 'Captured');

        // Now the user comes back from the offsite payment form
        $isNotification = false;
        $serviceResponse = $service->complete(array(), $isNotification);

        // We won't get an error, our payment is already complete
        $this->assertFalse($serviceResponse->isError());
        // There's no omnipay response since we no longer need to bother with omnipay at this point
        $this->assertNull($serviceResponse->getOmnipayResponse(), 'No omnipay response, payment already completed');
        // We should get redirected to the success page now
        $this->assertEquals($serviceResponse->getTargetUrl(), 'my/return/url');
        // Payment status should still be Captured
        $this->assertEquals($payment->Status, 'Captured');
    }

    protected function buildPurchaseGatewayStub($endpoint, callable $successFunc)
    {
        //--------------------------------------------------------------------------------------------------------------
        // Purchase request and response

        $mockPurchaseResponse = $this->getMockBuilder('Omnipay\PaymentExpress\Message\Response')
            ->disableOriginalConstructor()->getMock();

        $mockPurchaseResponse->expects($this->any())
            ->method('isRedirect')->will($this->returnValue(true));

        $mockPurchaseResponse->expects($this->any())
            ->method('getRedirectResponse')
            ->will($this->returnValue(RedirectResponse::create($endpoint)));

        $mockPurchaseRequest = $this->getMockBuilder('Omnipay\PaymentExpress\Message\PxPayPurchaseRequest')
            ->disableOriginalConstructor()->getMock();

        $mockPurchaseRequest->expects($this->any())->method('send')->will($this->returnValue($mockPurchaseResponse));

        //--------------------------------------------------------------------------------------------------------------
        // Complete purchase request and response

        $mockCompletePurchaseResponse = $this->getMockBuilder('Omnipay\PaymentExpress\Message\Response')
            ->disableOriginalConstructor()->getMock();

        // not successful, since we're waiting for async callback from the gateway
        $mockCompletePurchaseResponse->expects($this->any())
            ->method('isSuccessful')->will($this->returnCallback($successFunc));

        $mockCompletePurchaseRequest = $this->getMockBuilder('Omnipay\PaymentExpress\Message\PxPayCompleteAuthorizeRequest')
            ->disableOriginalConstructor()->getMock();

        $mockCompletePurchaseRequest->expects($this->any())
            ->method('send')->will($this->returnValue($mockCompletePurchaseResponse));

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('purchase', 'completePurchase', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('purchase')
            ->will($this->returnValue($mockPurchaseRequest));

        $stubGateway->expects($this->any())
            ->method('completePurchase')
            ->will($this->returnValue($mockCompletePurchaseRequest));

        return $stubGateway;
    }

    /**
     * @param GatewayInterface|PHPUnit_Framework_MockObject_MockObject $stubGateway
     * @return PHPUnit_Framework_MockObject_MockObject|GatewayFactory
     */
    protected function stubGatewayFactory($stubGateway)
    {
        $factory = $this->getMockBuilder('Omnipay\Common\GatewayFactory')->getMock();
        $factory->expects($this->any())->method('create')->will($this->returnValue($stubGateway));
        return $factory;
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject|Omnipay\Common\Message\AbstractRequest
     */
    protected function stubRequest()
    {
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
