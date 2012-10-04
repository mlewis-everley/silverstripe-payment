<?php

/**
 * Helper class for creating payment processors
 */
class PaymentFactory {
  /**
   * Get the factory config for a particular payment method
   *
   * @param String methodName
   */
  public static function get_factory_config($methodName) {
    $factoryConfig = Config::inst()->get('PaymentFactory', $methodName);
    if ($factoryConfig) {
      return $factoryConfig;
    } else {
      return null;
    }
  }
  
  /**
   * Get the gateway object that will be used for the given payment method.
   * The gateway class is automatically retrieved based on configuration
   * 
   * @param String $methodName
   */
  static function get_gateway($methodName) {
    // Get the gateway environment setting
    $environment = PaymentGateway::get_environment();
  
    // Get the custom class configuration if applicable.
    // If not, apply naming convention.
    $methodConfig = self::get_factory_config($methodName);
    $gatewayClassConfig = $methodConfig['gateway_classes'];
    if (isset($gatewayClassConfig[$environment])) {
      $gatewayClass = $gatewayClassConfig[$environment];
    } else {
      switch($environment) {
        case 'live':
          $gatewayClass = $methodName . 'Gateway_Production';
          break;
        case 'dev':
          $gatewayClass = $methodName . 'Gateway_Dev';
          break;
        case 'test':
          $gatewayClass = $methodName . 'Gateway_Mock';
          break;
      }
    }
  
    if (class_exists($gatewayClass)) {
      return new $gatewayClass();
    } else {
      throw new Exception("$gatewayClass class does not exists.");
    }
  }
  
  /**
   * Get the payment object that will be used for the given payment method.
   * 
   * The payment class is automatically retrieved based on naming convention 
   * if not specified in the yaml config. 
   * 
   * @param String methodName
   */
  static function get_payment_model($methodName) {
    // Get the custom payment class configuration.
    // If not applicable, apply naming convention
    $methodConfig = self::get_factory_config($methodName);
    if (isset($methodConfig['model'])) {
      $paymentClass = $methodConfig['model'];
    } else {
      $paymentClass = $methodName;
    }
  
    if (class_exists($paymentClass)) {
      return new $paymentClass();
    } else {
      throw new Exception("$paymentClass class does not exists");
    }
  }
  
  /**
   * Factory function to create payment controller object
   *
   * @param String $methodName
   */
  public static function factory($methodName) {
    $supported_methods = PaymentProcessor::get_supported_methods();
  
    if (! in_array($methodName, $supported_methods)) {
      throw new Exception("The method $methodName is not supported");
    }
  
    $methodConfig = self::get_factory_config($methodName);
    if (isset($methodConfig['controller'])) {
      $controllerClass = $methodConfig['controller'];
      $controller = new $controllerClass();
      $controller->setMethodName($methodName);
  
      // Set the dependencies
      $controller->gateway = self::get_gateway($methodName);
      $controller->payment = self::get_payment_model($methodName);
  
      return $controller;
    } else {
      throw new Exception("No controller is defined for the method $methodName");
    }
  }
}