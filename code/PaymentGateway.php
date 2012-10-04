<?php
/**
 * Abstract class for a number of payment gateways
 *
 * @package payment
 */
abstract class PaymentGateway {
  /**
   * The default environment. This will be overriden by yaml config.
   */
  public static $environment = 'dev';
  
  /**
   * The gateway url
   * 
   * @var String
   */
  public $gatewayURL;

  /**
   * The link to return to after processing payment (for gateway-hosted payments only)
   * 
   * @var String
   */
  protected $returnURL;
  
  /**
   * The link to return to after cancelling payment (for gateway-hosted payments only)
   * 
   * @var String
   */
  protected $cancelURL;
  
  /**
   * A ValidationResult object that holds the validation status of the payment data
   *
   * @var ValidationResult
   */
  protected $validationResult;

  private $validationResult;

  public $gatewayResponse;


  public static function get_environment() {
    return Config::inst()->get('PaymentGateway', 'environment');
  }
  
  public function __construct() {
    $this->validationResult = new ValidationResult();
  }
  
  /**
   * Set the return url, default to the site root
   * 
   * @param String $url
   */
  public function setReturnURL($url = null) {
    if ($url) {
      $this->returnURL = $url;
    } else {
      $this->returnURL = Director::absoluteBaseURL();
    }
  }
  
  /**
   * Set the cancel url, default to the site root
   * 
   * @param String $url
   */
  public function setCancelURL($url) {
    if ($url) {
      $this->cancelURL = $url;
    } else {
      $this->cancelURL = Director::absoluteBaseURL();
    }
  }
  
  public function getValidationResult() {
    if (!$this->validationResult) {
      $this->validationResult = new ValidationResult();
    }
    return $this->validationResult;
  }
  
  /**
   * Get the list of credit card types supported by this gateway
   * 
   * @return array('id' => 'Credit Card Type')
   */
  public function getSupportedCreditCardType() {
    return null;
  }
  
  /**
   * Get the list of currencies supported by this gateway
   * 
   * @return array
   */
  public function getSupportedCurrencies() {
    return array('USD');  
  }

  /**
   * Validate the payment data against the gateway-specific requirements
   * TODO use $this->data instead of passing $data
   * 
   * @param Array $data
   * @return ValidationResult
   */
  public function validate($data) {

    $validationResult = $this->getValidationResult();
    
    if (! isset($data['Amount'])) {
      $validationResult->error('Payment amount not set');
    } 
    else if (empty($data['Amount'])) {
      $validationResult->error('Payment amount cannot be null');
    } 
    
    if (! isset($data['Currency'])) {
      $validationResult->error('Payment currency not set');
    } 
    else if (empty($data['Currency'])) {
      $validationResult->error('Payment currency cannot be null');
    } 
    else if (! in_array($data['Currency'], $this->getSupportedCurrencies())) {
      $validationResult->error('Currency ' . $data['Currency'] . ' not supported by this gateway');
    }
    
    return $validationResult;
  }
  
  /**
   * Send a request to the gateway to process the payment.
   * To be implemented by individual gateway
   *
   * @param array $data
   */
  abstract public function process($data);
  
  /**
   * Return a set of requirements for the payment data array for this gateway 
   * 
   * @return array
   */
  public function paymentDataRequirements() {
    return array('Amount', 'Currency');
  }

  /**
   * Post a set of payment data to a remote server
   *
   * @param array $data
   * @param String $endpoint. If not set, assume $gatewayURL
   *
   * @return RestfulService_Response
   */
  public function postPaymentData($data, $endpoint = null) {
    if (! $endpoint) {
      $endpoint = $this->gatewayURL;
    }

    $service = new RestfulService($endpoint);
    return $service->request(null, 'POST', $data);
  }
}

/**
 * Class for gateway results
 * 
 * TODO break down into Failure_Result, Success_Result, Incomplete_Result for convenience?
 */
class PaymentGateway_Result extends ValidationResult {

  const SUCCESS = 'Success';
  const FAILURE = 'Failure';
  const INCOMPLETE = 'Incomplete';
  
  /**
   * Status of payment processing
   * 
   * @var String
   */
  protected $status;

  function __construct($status, $message = null) {

    $this->setStatus($status);
    parent::__construct($this->valid, $message);
  }
  
  /**
   * Set the payment result status.
   * validate() returns true if the status is Success, false otherwise
   * 
   * @param String $status
   */
  function setStatus($status) {

    if ($status == self::SUCCESS) {
      $this->valid = true;
      $this->status = $status;
    } 
    else if ($status == self::FAILURE || $status == self::INCOMPLETE) {
      $this->valid = false;
      $this->status = $status;
    } 
    else {
      user_error("Result status is invalid", E_USER_ERROR);
    }
  }

  public function isSuccess() {
    return $this->status == self::SUCCESS;
  }

  public function isFailure() {
    return $this->status == self::FAILURE;
  }

  public function isIncomplete() {
    return $this->status == self::INCOMPLETE;
  }
  
  function error($message, $code = null) {
    $this->status = self::FAILURE;
    return parent::error($message, $code);
  }

  public function getStatus() {
    return $this->status;
  }
}

class PaymentGateway_Success extends PaymentGateway_Result {

  function __construct($message = null) {
    parent::__construct(self::SUCCESS, $message);
  }
}

class PaymentGateway_Failure extends PaymentGateway_Result {

  function __construct($message = null) {
    parent::__construct(self::FAILURE, $message);
  }
}

class PaymentGateway_Incomplete extends PaymentGateway_Result {

  function __construct($message = null) {
    parent::__construct(self::INCOMPLETE, $message);
  }
}
