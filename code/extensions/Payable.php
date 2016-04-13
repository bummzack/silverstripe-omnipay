<?php

use SilverStripe\Omnipay\GatewayInfo;

/**
 * An extension for providing payments on a particular data object.
 *
 * @package payment
 */
class Payable extends DataExtension {

	private static $has_many = array(
		'Payments' => 'Payment'
	);

	public function updateCMSFields(FieldList $fields)
    {
		$fields->addFieldToTab("Root.Payments",
			GridField::create("Payments", "Payments", $this->owner->Payments(),
				GridFieldConfig_RecordEditor::create()
					->removeComponentsByType('GridFieldAddNewButton')
					->removeComponentsByType('GridFieldDeleteAction')
					->removeComponentsByType('GridFieldFilterHeader')
					->removeComponentsByType('GridFieldPageCount')
			)
		);
	}

    /**
     * Get the total captured amount
     * @return float
     */
	public function TotalPaid()
    {
		$paid = 0;
		if ($payments = $this->owner->Payments()) {
			foreach ($payments as $payment) {
				if ($payment->Status == 'Captured') {
					$paid += $payment->Amount;
				}
			}
		}
		return $paid;
	}

    /**
     * Get the total captured or authorized amount, excluding Manual payments.
     * @return float
     */
    public function TotalPaidOrAuthorized()
    {
        $paid = 0;
        if ($payments = $this->owner->Payments()) {
            foreach ($payments as $payment) {
                // Captured and authorized payments (which aren't manual) should count towards the total
                if (
                    $payment->Status == 'Captured' ||
                    ($payment->Status == 'Authorized' && !GatewayInfo::isManual($payment->Gateway))
                ) {
                    $paid += $payment->Amount;
                }
            }
        }
        return $paid;
    }

}
