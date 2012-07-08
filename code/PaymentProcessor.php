<?php

/**
 * Default class for a number of payment controllers.
 * This acts as a generic controller for all payment methods.
 * Override this class if desired to add custom functionalities.
 *
 * Configuration format for PaymentProcessor:
 * PaymentProcessor:
 *   supported_methods:
 *     {method name}:
 *       {controller name}
 *   gateway_classes:
 *     {environment}:
 *       {gateway class name}
 *
 */
class PaymentProcessor extends Controller {
  /**
   * The method name of this controller
   */
  protected $methodName;

  /**
   * The payment object to be injected to this controller
   */
  public $payment;

  /**
   * The gateway object to be injected to this controller
   */
  public $gateway;

  /**
   * If this is set to some url value, the processor will redirect 
   * to the url after a payment finishes processing.
   */
  public $postProcessRedirect = null;
  
  /**
   * Get the supported methods array set by the yaml configuraion
   */
  public static function get_supported_methods() {
    $supported_methods = Config::inst()->get('PaymentProcessor', 'supported_methods');

    // Check if all methods are defined in factory
    foreach ($supported_methods as $method) {
      if (! PaymentFactory::get_factory_config($method)) {
        user_error("Method $method not defined in factory", E_USER_ERROR);
      }
    }

    return $supported_methods;
  }

  /**
   * Set the method name of this controller.
   * This must be called after initializing a controller instance.
   * If not a generic method name 'Payment' will be used.
   *
   * @param String $method
   */
  public function setMethodName($method) {
    $this->methodName = $method;
  }
  
  /**
   * Check the payment data against the gateway's requirements.
   * 
   * @param array $data
   * @return true if satisfied, false otherwise
   */
  public function verifyPaymentData($data) {
    foreach ($this->gateway->paymentDataRequirements() as $key) {
      if (! array_key_exists($key, $data)) {
        return false;
      }
    }
    
    return true;
  }

  /**
   * Process a payment request.
   *
   * @param $data
   * @return Payment
   */
  public function processRequest($data) {
    if (! $this->verifyPaymentData($data)) {
      // If the payment data is not of the correct form, terminate the purchase
      user_error('The payment data is imcomplete.');
    }
    
    // Save preliminary data to database
    $this->payment->Amount->Amount = $data['Amount'];
    $this->payment->Amount->Currency = $data['Currency'];
    $this->payment->Status = Payment::PENDING;
    $this->payment->Method = $this->methodName;
    $this->payment->write();
  }

  /**
   * Process a payment response.
   */
  public function processresponse($response) {
    // Get the reponse result from gateway
    $result = $this->gateway->getResponse($response);

    // Retrieve the payment object if none is referenced at this point
    if (! $this->payment) {
      $this->payment = $this->getPaymentObject($response);
    }
    
    // Save gateway message
    $this->payment->Message = $result->getMessage();
    
    // Save payment status
    switch ($result->getStatus()) {
      case PaymentGateway_Result::SUCCESS:
        $this->payment->updatePaymentStatus(Payment::SUCCESS);
        break;
      case PaymentGateway_Result::FAILURE;
      $this->payment->updatePaymentStatus(Payment::FAILURE);
      break;
      case PaymentGateway_Result::INCOMPLETE;
      $this->payment->updatePaymentStatus(Payment::INCOMPLETE);
      break;
      default:
        break;
    }
    
    // Do post-processing
    return $this->postProcess();
  }

  /**
   * Helper function to get the payment object from the gateway response
   */
  public function getPaymentObject($response) {
  }

  /**
   * Perform an action after the payment is processed.
   * If $postProcessRedirect is set, redirect to the url. If not,
   * render a default page to show payment result.
   */
  public function postProcess() {
    if ($this->postProcessRedirect) {
      // Put the payment ID in a session
      Session::set('PaymentID', $this->payment->ID);
      Controller::curr()->redirect($this->postProcessRedirect);
    } else {
      if ($this->payment) {
        return $this->customise(array(
          "Content" => 'Payment #' . $this->payment->ID . ' status:' . $this->payment->Status,
          "Form" => '',
        ))->renderWith("Page");
      } else {
        return null;
      }
    }
  }

  /**
   * Get the default form fields to be shown at the checkout page
   *
   * return FieldList
   */
  public function getDefaultFormFields() {
    $fieldList = new FieldList();

    $fieldList->push(new NumericField('Amount', 'Amount', ''));
    $fieldList->push(new TextField('Currency', 'Currency', 'NZD'));

    return $fieldList;
  }

  /**
   * Get the custom form fields. Custom controllers use this function
   * to add the form fields specifically to gateways.
   *
   * return FieldList
   */
  public function getCustomFormFields() {
    return new FieldList();
  }

  /**
   * Return a list of combined form fields from all supported payment methods
   *
   * @return FieldList
   */
  public static function get_combined_form_fields() {
    $fieldList = new FieldList();

    // Add the default form fields
    foreach (singleton('PaymentProcessor')->getDefaultFormFields() as $field) {
      $fieldList->push($field);
    }

    // Custom form fields for each gateway
    foreach (self::get_supported_methods() as $methodName) {
      $controller = PaymentFactory::factory($methodName);
      foreach ($controller->getCustomFormFields() as $field) {
        $fieldList->push($field);
      }
    }

    return $fieldList;
  }
}

class PaymentProcessor_MerchantHosted extends PaymentProcessor {

  public function processRequest($data) {
    parent::processRequest($data);

    // Call processResponse directly since there's no need to set return link
    $response = $this->gateway->process($data);
    return $this->processresponse($response);
  }
  
  public function getPaymentObject($response) {
    return $this->payment;
  }

  public function getCustomFormFields() {
    $fieldList = parent::getCustomFormFields();
    $fieldList->push(new TextField('CardHolderName', 'Credit Card Holder Name :'));
    $fieldList->push(new CreditCardField('CardNumber', 'Credit Card Number :'));
    $fieldList->push(new TextField('DateExpiry', 'Credit Card Expiry : (MMYY)', '', 4));
    $fieldList->push(new TextField('Cvc2', 'Credit Card CVN : (3 or 4 digits)', '', 4));

    return $fieldList;
  }
}

class PaymentProcessor_GatewayHosted extends PaymentProcessor {

  public function processRequest($data) {
    parent::processRequest($data);

    // Set the return link
    // TODO: Allow custom return url
    $returnURL = Director::absoluteURL(Controller::join_links(
        $this->link(),
        'processresponse',
        $this->methodName,
        $this->payment->ID));
    $this->gateway->setReturnURL($returnURL);

    // Send a request to the gateway
    $this->gateway->process($data);
  }

  public function processresponse($response) {
    // Reconstruct the gateway object
    $this->setMethodName($response->param('ID'));
    $this->gateway = PaymentFactory::get_gateway($this->methodName);

    return parent::processresponse($response);
  }

  public function getPaymentObject($response) {
    return DataObject::get_by_id('Payment', $response->param('OtherID'));
  }
}