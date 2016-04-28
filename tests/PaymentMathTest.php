<?php

use SilverStripe\Omnipay\PaymentMath;
/**
 *
 */
class PaymentMathTest extends SapphireTest
{
    public function setUp()
    {
        parent::setUp();
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 2);
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'useBcMath', true);
    }

    public function testSubtraction()
    {
        $amount = '100.00';
        $result = PaymentMath::subtract($amount, '3.6');
        $this->assertEquals('96.40', $result);

        $result = PaymentMath::subtract($amount, '54.001');
        $this->assertEquals('45.99', $result);

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::subtract($amount, '3.6');
        $this->assertEquals('96.4000', $result);

        $result = PaymentMath::subtract($amount, '54.001');
        $this->assertEquals('45.9990', $result);
    }

    public function testSubtractionFloat()
    {
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        $amount = '100.00';
        $result = PaymentMath::subtract($amount, '3.6');
        $this->assertEquals('96.40', $result);

        $result = PaymentMath::subtract($amount, '54.001');
        $this->assertEquals('45.99', $result);

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 4);
        
        $result = PaymentMath::subtract($amount, '54.001');
        $this->assertEquals('45.9990', $result);

        $result = PaymentMath::subtract($amount, '54.001');
        $this->assertEquals('45.9990', $result);
    }
}
