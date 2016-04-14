<?php

use SilverStripe\Omnipay\Service\ServiceFactory;
use Omnipay\Common\Message\NotificationInterface;

class PaymentServiceTest extends PaymentTest
{
    //TODO: Test modifications done to ServiceResponse via Extensions

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

    // Test a successful notification
    public function testHandleNotificationSuccess()
    {
        $service = $this->buildNotificationService(NotificationInterface::STATUS_COMPLETED);

        $serviceResponse = $service->handleNotification();

        // notification should be handled fine
        $this->assertFalse($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            '\Omnipay\Common\Message\NotificationInterface',
            $serviceResponse->getOmnipayResponse()
        );
    }

    // Test an error notification
    public function testHandleNotificationError()
    {
        $service = $this->buildNotificationService(NotificationInterface::STATUS_FAILED);

        $serviceResponse = $service->handleNotification();

        // notification should error
        $this->assertTrue($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            '\Omnipay\Common\Message\NotificationInterface',
            $serviceResponse->getOmnipayResponse()
        );
    }

    // Test a pending notification
    public function testHandleNotificationPending()
    {
        $service = $this->buildNotificationService(NotificationInterface::STATUS_PENDING);

        $serviceResponse = $service->handleNotification();

        // notification should not error
        $this->assertFalse($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should be flagged as pending
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            '\Omnipay\Common\Message\NotificationInterface',
            $serviceResponse->getOmnipayResponse()
        );
    }

    // Test a gateway that doesn't return an instance of NotificationInterface
    public function testHandleNotificationInvalid()
    {
        // build a notification that returns an AbstractResponse instead of the expected NotificationInterface
        $service = $this->buildNotificationService(
            NotificationInterface::STATUS_PENDING,
            'Omnipay\Common\Message\AbstractResponse'
        );

        $serviceResponse = $service->handleNotification();

        // notification should error
        $this->assertTrue($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should NOT have an instance of the response attached (since it's invalid)
        $this->assertNull($serviceResponse->getOmnipayResponse());
    }

    /**
     * Test with a gateway that doesn't implement `acceptNotification`.
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidConfigurationException
     */
    public function testHandleNotificationWithIncompatibleGateway()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $service = $this->factory->getService($payment, ServiceFactory::INTENT_PURCHASE);

        // build a gateway that doesn't have the `acceptNotification` method
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('getName'))
            ->getMock();

        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        // this should throw an exception
        $service->handleNotification();
    }

    protected function buildNotificationService(
        $returnState,
        $contract = 'Omnipay\Common\Message\NotificationInterface'
    ) {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $service = $this->factory->getService($payment, ServiceFactory::INTENT_PURCHASE);

        //--------------------------------------------------------------------------------------------------------------
        // Notification response

        $notificationResponse = $this->getMockBuilder($contract)
            ->disableOriginalConstructor()->getMock();

        $notificationResponse->expects($this->any())
            ->method('getTransactionStatus')->will($this->returnValue($returnState));

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('acceptNotification', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('acceptNotification')
            ->will($this->returnValue($notificationResponse));

        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        return $service;
    }
}
