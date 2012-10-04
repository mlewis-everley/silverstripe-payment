<?php 

/**
 * "Abstract" class for a number of payment models
 * 
 *  @package payment
 */
class Payment extends DataObject {
  /**
   * The payment form fields that should be shown on the checkout order form
   */
  public $formFields;
  public $requiredFormFields;
  
  public function getFormFields() {
    if (!$this->formFields) {
      $this->formFields = new FieldList();
    }
    
    return $this->formFields;
  }
  
  public function getFormRequirements() {
    if (!$this->requiredFormFields) {
      $this->requiredFormFields = array();
    }
    
    return $this->requiredFormFields;
  }
  
  /**
   * Get the payment gateway class name from the associating module name
   * 
   * @param $gatewayName
   * @return gateway class name
   * 
   * TODO: Generalize naming convention
   *       Take care of merchant hosted and gateway hosted sub classes
   */
  public static function gateway_class_name($gatewayname) {
    $className = $gatewayname . "_Payment";
    if (class_exists($className)) {
      return $className;
    } else {
      user_error("Payment gateway class is not defined", E_USER_ERROR);
    }
  }
  
  /**
   * Incomplete (default): Payment created but nothing confirmed as successful
   * Success: Payment successful
   * Failure: Payment failed during process
   * Pending: Payment awaiting receipt/bank transfer etc
   */
  public static $db = array(
    'Status' => "Enum('Incomplete, Success, Failure, Pending')",
    'Amount' => 'Money',
    'Message' => 'Text',
    'PaidForID' => "Int",
    'PaidForClass' => 'Varchar',

    //Used for storing any Exception during this payment Process.
    'ExceptionError' => 'Text'
  );
  
  public static $has_one = array(
    'PaidObject' => 'Object',
    'PaidBy' => 'Member',
  );
  
  /**
   * Make payment table transactional.
   */
  static $create_table_options = array(
    'MySQLDatabase' => 'ENGINE=InnoDB'
  );
  
  /**
   * Update the status of this payment
   *
   * @param $status
   */
  public function updatePaymentStatus($status) {
    $payment->Status = $status;
    $payment->write();
  }
}

class Payment_MerchantHosted extends Payment {

  protected static $cvn_mode = true;
  
  public function __construct() {
    parent::__construct();
    
    $this->formFields = new FieldList();
    foreach ($ccFields = $this->getCreditCardFields() as $ccField) {
      $this->formFields->push($ccField);
    }
    $this->requiredFormFields = array();
  }
  
  public function getCreditCardFields() {
    $fields = array(
        new TextField('CardHolderName', 'Credit Card Holder Name :'),
        new CreditCardField('CardNumber', 'Credit Card Number :'),
        new TextField('DateExpiry', 'Credit Card Expiry : (MMYY)', '', 4)
    );
    
    if (self::$cvn_mode) {
      array_push($fields, new TextField('Cvc2', 'Credit Card CVN : (3 or 4 digits)', '', 4));
    }
    return $fields;
  }
}

class Payment_GatewayHosted extends Payment {
  
}

/**
 *  Interface for a payment gateway controller
 */
interface Payment_Controller_Interface {

  public function processRequest($data);
  public function processResponse($response);
}

/**
 * Abstract class for a number of payment controllers.
 */
class Payment_Controller extends Controller implements Payment_Controller_Interface {

  static $URLSegment;

  /**
   * The payment object to be injected to this controller
   */
  public $payment;
  
  /**
   * The gateway object to be injected to this controller
   */
  public $gateway;

  /**
   * Construct a controller instance with injected payment and gateway objects
   */
  public static function init_instance($payment, $gateway) {
    // construct the correct child class
    $class = get_called_class();
    $instance = new $class();

    $instance->payment = $payment;
    $instance->gateway = $gateway;
    return $instance;
  }

  /**
   * Get the controller's class name from a given gateway module name and config
   * TODO: Generalize naming convention; make use of _config.php
   *
   * @param $gatewayName
   * @return Controller class name
   */
  public static function controller_class_name($gatewayName) {
    $paymentClass = Payment::gateway_class_name($gatewayName);
    $controllerClass = $paymentClass . "_Controller";

    if (class_exists($controllerClass)) {
      return $controllerClass;
    } else {
      user_error("Controller class is not defined", E_USER_ERROR);
    }
  }

  /**
   * Process a payment request. Subclasses must call this parent function when overriding
   *
   * @param $data
   */
  public function processRequest($data) {
    if (! isset($data['Amount'])) {
      user_error("Payment amount not set!", E_USER_ERROR);
    } else {
      $amount = $data['Amount'];
    }

    if (! isset($data['Currency'])) {
      $currency = $data['Currency'];
    } else {
      //$currency = $this->payment->site_currency();
    }

    // Save preliminary data to database
    $this->payment->Amount->Amount = $amount;
    $this->payment->Amount->Currency = $currency;
    $this->payment->Status = 'Pending';
    $this->payment->write();
    
    // Send a request to the gateway 
    $this->gateway->process($data);
  }

  /**
   * Process a payment response, to be implemented by specific gateways.
   * This function should return the ID of the payment
   */
  public function processResponse($response) {
    user_error("Please implement processResponse() on $this->class", E_USER_ERROR);
  }
}

/**
 * Factory class to allow easy initiation of payment objects
 */
class PaymentFactory {
  /**
   * Construct an instance of the desired payment controller
   *
   * @param $gatewayName
   * @return PaymentProcessor
   */
  public static function createController($gatewayName) {
    $paymentClass = Payment::payment_class_name($gatewayName);
    $payment = new $paymentClass();
    
    $gatewayClass = Payment_Gateway::gateway_class_name($gatewayName);
    $gateway = new $gatewayClass();
    
    $paymentControllerClass = Payment_Controller::controller_class_name($gatewayName);
    return $paymentControllerClass::init_instance($payment, $gateway);
  }
}