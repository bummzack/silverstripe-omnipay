<?php

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\GatewayInterface;
use SilverStripe\Omnipay\Service\ServiceFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use SilverStripe\Omnipay\Service\PurchaseService;

/**
 * Test the refund service
 */
class RefundServiceTest extends PaymentTest
{
    public function testRefund()
    {
        $this->markTestIncomplete();
    }
    
    
    protected function buildPaymentGatewayStub(callable $successFunc)
    {
        //--------------------------------------------------------------------------------------------------------------
        // Refund request and response

        $mockRefundResponse = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()->getMock();

        $mockRefundResponse->expects($this->any())
            ->method('isSuccessful')->will($this->returnCallback($successFunc));

        $mockRefundRequest = $this->getMockBuilder('Omnipay\Common\Message\AbstractRequest')
            ->disableOriginalConstructor()->getMock();

        $mockRefundRequest->expects($this->any())->method('send')->will($this->returnValue($mockRefundResponse));

        //--------------------------------------------------------------------------------------------------------------
        // Notification




        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('refund', 'acceptNotification', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('refund')
            ->will($this->returnValue($mockRefundRequest));

        /*
        $stubGateway->expects($this->any())
            ->method('acceptNotification')
            ->will($this->returnValue($mockCompletePaymentRequest));
        */
        return $stubGateway;
    }
}
