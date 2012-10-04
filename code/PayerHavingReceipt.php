<?php

class PayerHavingReceipt extends DataExtension{
	public static $db = array(
		'Street' =>		'Varchar',
		'Suburb' =>		'Varchar',
		'CityTown' =>	'Varchar',
		'Country' =>	'Varchar',
	);
	
	public function ReceiptMessage(){
		return $this->owner->renderWith('Payer_receipt');
	}
}
