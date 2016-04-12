<?php

use SilverStripe\Omnipay\Service\VoidService;
use Omnipay\Common\Message\NotificationInterface;


//TODO: This shares a lot of duplicate code with RefundServiceTest.
//TODO: Maybe merge or simplify this, similar to the authorize and purchase tests.
/**
 * Test the void service
 */
class VoidServiceTest extends PaymentTest
{
    public function testVoidSuccess()
    {
        // load an authorized payment from fixture
        $payment = $this->objFromFixture("Payment", "payment6");

        $stubGateway = $this->buildPaymentGatewayStub(true, 'authorizedPaymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var VoidService $service */
        $service = VoidService::create($payment);

        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // with a successful "voiding", we get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($payment->Status, 'Void', 'Payment status should be set to Void');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'AuthorizedResponse',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated void request
                'ClassName' => 'VoidRequest',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated void response
                'ClassName' => 'VoidedResponse',
                'Reference' => 'authorizedPaymentReceipt'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testVoidSuccessWithTransactionParameter()
    {
        // load an authorized payment from fixture
        $this->payment->Status = 'Authorized';

        $stubGateway = $this->buildPaymentGatewayStub(true, 'testThisRecipe123');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var VoidService $service */
        $service = VoidService::create($this->payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate(array('transactionReference' => 'testThisRecipe123'));

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // when "voiding" successfully, we get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($this->payment->Status, 'Void', 'Payment status should be set to Void');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // the generated void request
                'ClassName' => 'VoidRequest',
                'Reference' => 'testThisRecipe123'
            ),
            array( // the generated void response
                'ClassName' => 'VoidedResponse',
                'Reference' => 'testThisRecipe123'
            )
        ), $this->payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testVoidSuccessViaNotification()
    {
        // load an authorized payment from fixture
        $payment = $this->objFromFixture("Payment", "payment6");

        // use notification on the gateway
        Config::inst()->update('GatewayInfo', $payment->Gateway, array(
            'use_async_notification' => true
        ));

        $stubGateway = $this->buildPaymentGatewayStub(false, 'authorizedPaymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var VoidService $service */
        $service = VoidService::create($payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // When waiting for a notification, request won't be successful from Omnipays point of view
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful());
        // response should have the "AwaitingNotification" flag
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        // check payment status
        $this->assertEquals($payment->Status, 'PendingVoid', 'Payment status should be set to "PendingVoid"');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'AuthorizedResponse',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated void request
                'ClassName' => 'VoidRequest',
                'Reference' => 'authorizedPaymentReceipt'
            )
        ), $payment->Messages());

        // Now a notification comes in
        $response = $this->get('paymentendpoint/51efcc0e94718dd80d97b1281762a9bc/notify');

        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->getBody(), "OK");

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);
        $this->assertEquals($payment->Status, 'Void', 'Payment status should be set to "Void"');

        // check existance of messages
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'AuthorizedResponse'
            ),
            array( // the generated void request
                'ClassName' => 'VoidRequest'
            ),
            array( // the generated void response
                'ClassName' => 'VoidedResponse'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testVoidFailure()
    {
        // load an authorized payment from fixture
        $payment = $this->objFromFixture("Payment", "payment6");

        $stubGateway = $this->buildPaymentGatewayStub(false, 'authorizedPaymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var VoidService $service */
        $service = VoidService::create($payment);

        $serviceResponse = $service->initiate();

        // the service should respond with an error
        $this->assertTrue($serviceResponse->isError());
        // Omnipay response should be unsuccessful
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful());
        // payment status should be unchanged
        $this->assertEquals($payment->Status, 'Authorized', 'Payment status should be unchanged');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'AuthorizedResponse',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated void request
                'ClassName' => 'VoidRequest',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated void error
                'ClassName' => 'VoidError',
                'Reference' => 'authorizedPaymentReceipt'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    /**
     * @expectedException  \SilverStripe\Omnipay\Exception\InvalidStateException
     */
    public function testVoidInvalidStatus()
    {
        $this->payment->Status = 'Created';

        // create a void service with a payment that is created
        /** @var VoidService $service */
        $service = VoidService::create($this->payment);

        // this should throw an exception
        $service->initiate();
    }

    /**
     * @expectedException  \SilverStripe\Omnipay\Exception\MissingParameterException
     */
    public function testVoidMissingTransactionReference()
    {
        $this->payment->Status = 'Authorized';

        // create a void service with a payment that could be cancelled,
        // but doesn't have any transaction references in messages
        /** @var VoidService $service */
        $service = VoidService::create($this->payment);

        // this should throw an exception
        $service->initiate();
    }


    protected function buildPaymentGatewayStub(
        $successValue,
        $transactionReference,
        $returnState = NotificationInterface::STATUS_COMPLETED
    ) {
        //--------------------------------------------------------------------------------------------------------------
        // void request and response

        $mockVoidResponse = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()->getMock();

        $mockVoidResponse->expects($this->any())
            ->method('isSuccessful')->will($this->returnValue($successValue));

        $mockVoidResponse->expects($this->any())
            ->method('getTransactionReference')->will($this->returnValue($transactionReference));

        $mockVoidRequest = $this->getMockBuilder('Omnipay\Common\Message\AbstractRequest')
            ->disableOriginalConstructor()->getMock();

        $mockVoidRequest->expects($this->any())
            ->method('send')->will($this->returnValue($mockVoidResponse));

        $mockVoidRequest->expects($this->any())
            ->method('getTransactionReference')->will($this->returnValue($transactionReference));

        //--------------------------------------------------------------------------------------------------------------
        // Notification

        $notificationResponse = $this->getMockBuilder('Omnipay\Common\Message\NotificationInterface')
            ->disableOriginalConstructor()->getMock();

        $notificationResponse->expects($this->any())
            ->method('getTransactionStatus')->will($this->returnValue($returnState));

        $notificationResponse->expects($this->any())
            ->method('getTransactionReference')->will($this->returnValue($transactionReference));


        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('void', 'acceptNotification', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('void')
            ->will($this->returnValue($mockVoidRequest));

        $stubGateway->expects($this->any())
            ->method('acceptNotification')
            ->will($this->returnValue($notificationResponse));

        return $stubGateway;
    }
}
