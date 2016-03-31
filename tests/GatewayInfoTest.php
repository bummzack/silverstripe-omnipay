<?php
use Omnipay\Common\AbstractGateway;
use SilverStripe\Omnipay\GatewayInfo;

class GatewayInfoTest extends SapphireTest
{
    public function setUpOnce()
    {
        parent::setUpOnce();
        
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'parameters' => array(
                'username' => 'EXAMPLEUSER',
                'password' => '235llgwxle4tol23l'
            ),
            'use_authorize' => true
        ));
    }


	public function testIsOffsite()
    {
		$this->assertFalse(GatewayInfo::isOffsite('\GatewayInfoTest_OnsiteGateway'));
		$this->assertTrue(GatewayInfo::isOffsite('\GatewayInfoTest_OffsiteGateway'));
        //
		$this->assertTrue(GatewayInfo::isOffsite('PaymentExpress_PxPay'));
	}

    public function testUseAuthorize()
    {
        $this->assertTrue(
            GatewayInfo::shouldUseAuthorize('PaymentExpress_PxPay'),
            'PaymentExpress_PxPay was configured to use authorize!'
        );
    }

}

class GatewayInfoTest_OnsiteGateway extends AbstractGateway implements TestOnly
{
	public function getName() {
		return 'GatewayInfoTest_OnsiteGateway';
	}

	public function getDefaultParameters() {
		return array();
	}

	public function purchase(array $parameters = array()) {}
}

class GatewayInfoTest_OffsiteGateway extends AbstractGateway implements TestOnly {

	public function getName() {
		return 'GatewayInfoTest_OffsiteGateway';
	}

	public function getDefaultParameters() {
		return array();
	}

	public function purchase(array $parameters = array()) {}

    public function completePurchase(array $options = array()) {}

}
