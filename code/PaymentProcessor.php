<?php

/**
 * Static class for processing payment. 
 * 
 * @package payment
 */
class PaymentProcessor {
  public $paymentController;
  
  /**
   * Inject the corresponding payment controller
   */
  public function __construct($paymentController) {
    $this->paymentController = $paymentController;
  }
  
  /**
   * Process payment from order form using the injected payment controller
   * 
   * @param $data
   * @return PaymentProcessorResult
   */
  public function processPayment($data) {
    return $this->paymentController->processPayment($data, $form);
  }
}