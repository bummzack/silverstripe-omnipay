<?php

use SilverStripe\Omnipay\Service\CaptureService;
use Omnipay\Common\Message\NotificationInterface;


//TODO: This shares a lot of duplicate code with RefundServiceTest.
//TODO: Maybe merge or simplify this, similar to the authorize and purchase tests.
/**
 * Test the capture service
 */
class CaptureServiceTest extends PaymentTest
{
    public function testCaptureSuccess()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture("Payment", "payment6");

        $this->assertEquals($payment->Status, 'Authorized', 'Payment status should be set to Authorized');

        $stubGateway = $this->buildPaymentGatewayStub(true, 'authorizedPaymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var CaptureService $service */
        $service = CaptureService::create($payment);

        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // with a successful capture, we get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($payment->Status, 'Captured', 'Payment status should be set to captured');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'AuthorizedResponse',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated refund request
                'ClassName' => 'CaptureRequest',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated refund response
                'ClassName' => 'CapturedResponse',
                'Reference' => 'authorizedPaymentReceipt'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testCaptureSuccessWithTransactionParameter()
    {
        // load an authorized payment from fixture
        $this->payment->Status = 'Authorized';

        $stubGateway = $this->buildPaymentGatewayStub(true, 'testThisRecipe123');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var RefundService $service */
        $service = CaptureService::create($this->payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate(array('transactionReference' => 'testThisRecipe123'));

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // with a successful refund, we get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($this->payment->Status, 'Captured', 'Payment status should be set to captured');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // the generated refund request
                'ClassName' => 'CaptureRequest',
                'Reference' => 'testThisRecipe123'
            ),
            array( // the generated refund response
                'ClassName' => 'CapturedResponse',
                'Reference' => 'testThisRecipe123'
            )
        ), $this->payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testCaptureSuccessViaNotification()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture("Payment", "payment6");

        // use notification on the gateway
        Config::inst()->update('GatewayInfo', $payment->Gateway, array(
            'use_async_notification' => true
        ));

        $stubGateway = $this->buildPaymentGatewayStub(false, 'authorizedPaymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var CaptureService $service */
        $service = CaptureService::create($payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // A capture waiting for a notification won't be successful from Omnipays point of view
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful());
        // response should have the "AwaitingNotification" flag
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        // check payment status
        $this->assertEquals($payment->Status, 'PendingCapture', 'Payment status should be set to "PendingCapture"');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'AuthorizedResponse',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated capture request
                'ClassName' => 'CaptureRequest',
                'Reference' => 'authorizedPaymentReceipt'
            )
        ), $payment->Messages());

        // Now a notification comes in
        $response = $this->get('paymentendpoint/51efcc0e94718dd80d97b1281762a9bc/notify');

        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->getBody(), "OK");

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);
        $this->assertEquals($payment->Status, 'Captured', 'Payment status should be set to "Captured"');

        // check existance of messages
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'AuthorizedResponse'
            ),
            array( // the generated capture request
                'ClassName' => 'CaptureRequest'
            ),
            array( // the generated capture response
                'ClassName' => 'CapturedResponse'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testCaptureFailure()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture("Payment", "payment6");

        $stubGateway = $this->buildPaymentGatewayStub(false, 'authorizedPaymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var CaptureService $service */
        $service = CaptureService::create($payment);

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
            array( // the generated capture request
                'ClassName' => 'CaptureRequest',
                'Reference' => 'authorizedPaymentReceipt'
            ),
            array( // the generated capture response
                'ClassName' => 'CaptureError',
                'Reference' => 'authorizedPaymentReceipt'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    /**
     * @expectedException  \SilverStripe\Omnipay\Exception\InvalidStateException
     */
    public function testCaptureInvalidStatus()
    {
        /** @var CaptureService $service */
        $this->payment->Status = 'Created';
        // create a capture service with a payment that isn't captured
        $service = CaptureService::create($this->payment);

        // this should throw an exception
        $service->initiate();
    }

    /**
     * @expectedException  \SilverStripe\Omnipay\Exception\MissingParameterException
     */
    public function testCaptureMissingTransactionReference()
    {
        /** @var CaptureService $service */
        $this->payment->Status = 'Authorized';
        // create a capture service with a payment that is captured,
        // but doesn't have any transaction references in messages
        $service = CaptureService::create($this->payment);

        // this should throw an exception
        $service->initiate();
    }


    protected function buildPaymentGatewayStub(
        $successValue,
        $transactionReference,
        $returnState = NotificationInterface::STATUS_COMPLETED
    ) {
        //--------------------------------------------------------------------------------------------------------------
        // capture request and response

        $mockCaptureResponse = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()->getMock();

        $mockCaptureResponse->expects($this->any())
            ->method('isSuccessful')->will($this->returnValue($successValue));

        $mockCaptureResponse->expects($this->any())
            ->method('getTransactionReference')->will($this->returnValue($transactionReference));

        $mockCaptureRequest = $this->getMockBuilder('Omnipay\Common\Message\AbstractRequest')
            ->disableOriginalConstructor()->getMock();

        $mockCaptureRequest->expects($this->any())
            ->method('send')->will($this->returnValue($mockCaptureResponse));

        $mockCaptureRequest->expects($this->any())
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
            ->setMethods(array('capture', 'acceptNotification', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('capture')
            ->will($this->returnValue($mockCaptureRequest));

        $stubGateway->expects($this->any())
            ->method('acceptNotification')
            ->will($this->returnValue($notificationResponse));

        return $stubGateway;
    }
}
