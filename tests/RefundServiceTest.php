<?php

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\GatewayInterface;
use SilverStripe\Omnipay\Service\ServiceFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use SilverStripe\Omnipay\Service\RefundService;
use Omnipay\Common\Message\NotificationInterface;

/**
 * Test the refund service
 */
class RefundServiceTest extends PaymentTest
{
    public function testRefundSuccess()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture("Payment", "payment3");

        $stubGateway = $this->buildPaymentGatewayStub(true, 'paymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var RefundService $service */
        $service = RefundService::create($payment);

        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // with a successful refund, we get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($payment->Status, 'Refunded', 'Payment status should be set to refunded');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'PurchasedResponse',
                'Reference' => 'paymentReceipt'
            ),
            array( // the generated refund request
                'ClassName' => 'RefundRequest',
                'Reference' => 'paymentReceipt'
            ),
            array( // the generated refund response
                'ClassName' => 'RefundedResponse',
                'Reference' => 'paymentReceipt'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testRefundSuccessWithTransactionParameter()
    {
        // load a captured payment from fixture
        $this->payment->Status = 'Captured';

        $stubGateway = $this->buildPaymentGatewayStub(true, 'testThisRecipe123');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var RefundService $service */
        $service = RefundService::create($this->payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate(array('transactionReference' => 'testThisRecipe123'));

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // with a successful refund, we get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($this->payment->Status, 'Refunded', 'Payment status should be set to refunded');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // the generated refund request
                'ClassName' => 'RefundRequest',
                'Reference' => 'testThisRecipe123'
            ),
            array( // the generated refund response
                'ClassName' => 'RefundedResponse',
                'Reference' => 'testThisRecipe123'
            )
        ), $this->payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testRefundSuccessViaNotification()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture("Payment", "payment3");

        // use notification on the gateway
        Config::inst()->update('GatewayInfo', $payment->Gateway, array(
            'use_async_notification' => true
        ));

        $stubGateway = $this->buildPaymentGatewayStub(false, 'paymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var RefundService $service */
        $service = RefundService::create($payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // A refund waiting for a notification won't be successful from Omnipays point of view
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful());
        // response should have the "AwaitingNotification" flag
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        // check payment status
        $this->assertEquals($payment->Status, 'PendingRefund', 'Payment status should be set to "PendingRefund"');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'PurchasedResponse',
                'Reference' => 'paymentReceipt'
            ),
            array( // the generated refund request
                'ClassName' => 'RefundRequest',
                'Reference' => 'paymentReceipt'
            )
        ), $payment->Messages());

        // Now a notification comes in
        $response = $this->get('paymentendpoint/c3b502a48a4740c063e1732de4cc8077/notify');

        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->getBody(), "OK");

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);
        $this->assertEquals($payment->Status, 'Refunded', 'Payment status should be set to "Refunded"');

        // check existance of messages
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'PurchasedResponse'
            ),
            array( // the generated refund request
                'ClassName' => 'RefundRequest'
            ),
            array( // should now have a refunded response
                'ClassName' => 'RefundedResponse'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    public function testRefundFailure()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture("Payment", "payment3");

        $stubGateway = $this->buildPaymentGatewayStub(false, 'paymentReceipt');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        /** @var RefundService $service */
        $service = RefundService::create($payment);

        $serviceResponse = $service->initiate();

        // the service should respond with an error
        $this->assertTrue($serviceResponse->isError());
        // Omnipay response should be unsuccessful
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful());
        // payment status should be unchanged
        $this->assertEquals($payment->Status, 'Captured', 'Payment status should be unchanged');

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array( // response that was loaded from the fixture
                'ClassName' => 'PurchasedResponse',
                'Reference' => 'paymentReceipt'
            ),
            array( // the generated refund request
                'ClassName' => 'RefundRequest',
                'Reference' => 'paymentReceipt'
            ),
            array( // the generated refund response
                'ClassName' => 'RefundError',
                'Reference' => 'paymentReceipt'
            )
        ), $payment->Messages());

        // clear the gateway factory override
        Injector::inst()->unregisterNamedObject('Omnipay\Common\GatewayFactory');
    }

    /**
     * @expectedException  \SilverStripe\Omnipay\Exception\InvalidStateException
     */
    public function testRefundInvalidStatus()
    {
        /** @var RefundService $service */
        $this->payment->Status = 'Created';
        // create a refund service with a payment that isn't captured
        $service = RefundService::create($this->payment);

        // this should throw an exception
        $service->initiate();
    }

    /**
     * @expectedException  \SilverStripe\Omnipay\Exception\MissingParameterException
     */
    public function testRefundMissingTransactionReference()
    {
        /** @var RefundService $service */
        $this->payment->Status = 'Captured';
        // create a refund service with a payment that is captured,
        // but doesn't have any transaction references in messages
        $service = RefundService::create($this->payment);

        // this should throw an exception
        $service->initiate();
    }


    protected function buildPaymentGatewayStub(
        $successValue,
        $transactionReference,
        $returnState = NotificationInterface::STATUS_COMPLETED
    ) {
        //--------------------------------------------------------------------------------------------------------------
        // Refund request and response

        $mockRefundResponse = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()->getMock();

        $mockRefundResponse->expects($this->any())
            ->method('isSuccessful')->will($this->returnValue($successValue));

        $mockRefundResponse->expects($this->any())
            ->method('getTransactionReference')->will($this->returnValue($transactionReference));

        $mockRefundRequest = $this->getMockBuilder('Omnipay\Common\Message\AbstractRequest')
            ->disableOriginalConstructor()->getMock();

        $mockRefundRequest->expects($this->any())
            ->method('send')->will($this->returnValue($mockRefundResponse));

        $mockRefundRequest->expects($this->any())
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
            ->setMethods(array('refund', 'acceptNotification', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('refund')
            ->will($this->returnValue($mockRefundRequest));

        $stubGateway->expects($this->any())
            ->method('acceptNotification')
            ->will($this->returnValue($notificationResponse));

        return $stubGateway;
    }
}
