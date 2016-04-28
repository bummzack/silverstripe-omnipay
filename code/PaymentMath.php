<?php
/**
 * Created by PhpStorm.
 * User: bummzack
 * Date: 28/04/16
 * Time: 17:28
 */

namespace SilverStripe\Omnipay;

/**
 * Helper class to deal with payment arithmetics
 */
class PaymentMath
{
    /**
     * Desired precision for the output strings.
     *
     * @config Can be configured via `SilverStripe\Omnipay\PaymentMath.precision`
     * @var int
     */
    private static $precision = 2;

    /**
     * Whether or not to use bc-math functions. Should be set to true, if possible.
     * Only set this to false for unit-tests!
     *
     * @config Can be configured via `SilverStripe\Omnipay\PaymentMath.useBcMath`
     * @var bool
     */
    private static $useBcMath = true;

    /**
     * Subtract two numbers that are represented as a string.
     * Numbers will not be rounded but floored instead! So 10.0 - 0.1 with a precision of 0 will result in 9!
     * @param string $amountA first operand
     * @param string $amountB second operand
     * @return string the result as a string
     */
    public static function subtract($amountA, $amountB)
    {
        $precision = (int)\Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'precision');
        if (function_exists('bcsub') && \Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'useBcMath')) {
            return bcsub($amountA, $amountB, $precision);
        }

        return self::formatFloat((float)$amountA - (float)$amountB, $precision);
    }

    /**
     * Add two numbers that are represented as a string
     * Numbers will not be rounded but floored instead! So 0.22 + 0.27 with a precision of 1 will result in 0.4!
     * @param string $amountA first operand
     * @param string $amountB second operand
     * @return string the result as a string
     */
    public static function add($amountA, $amountB)
    {
        $precision = (int)\Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'precision');
        if (function_exists('bcadd') && \Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'useBcMath')) {
            return bcadd($amountA, $amountB, $precision);
        }

        return self::formatFloat((float)$amountA + (float)$amountB, $precision);
    }

    /**
     * Format a float to string
     * @param float $f the number to format as string
     * @param int $precision desired precision
     * @return string
     */
    private static function formatFloat($f, $precision)
    {
        $exponent = pow(10, max(0, $precision));
        // clear off additional digits that will cause number_format to round numbers
        $i = floor($f * $exponent) / $exponent;
        return number_format($i, $precision, '.', '');
    }
}
