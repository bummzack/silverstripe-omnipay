<?php

use SilverStripe\Omnipay\PaymentMath;
/**
 * Test payment math operations
 */
class PaymentMathTest extends SapphireTest
{
    public function setUp()
    {
        parent::setUp();
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 2);
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'useBcMath', true);
    }

    public function testPrecision()
    {
        if (!function_exists('bcsub')) {
            $this->markTestIncomplete('BCMath extension not available');
            return;
        }

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', -1);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 0);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 1);
        $this->assertEquals('99.9', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.4', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 2);
        $this->assertEquals('99.90', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.49', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 15);
        $this->assertEquals('99.900000000000000', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.499000000000000', PaymentMath::add('0.273', '0.226'));
    }

    public function testPrecisionFloat()
    {
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', -1);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 0);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 1);
        $this->assertEquals('99.9', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.4', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 2);
        $this->assertEquals('99.90', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.49', PaymentMath::add('0.273', '0.226'));

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 15);
        $this->assertEquals('99.900000000000000', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.499000000000000', PaymentMath::add('0.273', '0.226'));
    }

    public function testSubtraction()
    {
        if (!function_exists('bcsub')) {
            $this->markTestIncomplete('BCMath extension not available');
            return;
        }

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.40', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.99', $result);

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.4000', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.9990', $result);
    }

    public function testSubtractionFloat()
    {
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.40', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.99', $result);

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.4000', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.9990', $result);
    }

    public function testAddition()
    {
        if (!function_exists('bcadd')) {
            $this->markTestIncomplete('BCMath extension not available');
            return;
        }

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.00', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.10', $result);

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.0000', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.1010', $result);
    }

    public function testAdditionFloat()
    {
        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.00', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.10', $result);

        Config::inst()->update('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.0000', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.1010', $result);
    }
}
