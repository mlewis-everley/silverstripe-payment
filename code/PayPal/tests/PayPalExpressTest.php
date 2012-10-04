<?php

class PayPalExpressTest extends SapphireTest {
  
  public $processor;
  public $data;
  
  function setUp() {
    parent::setUp();
    
    $paymentMethods = array('PayPalExpress');
    Config::inst()->remove('PaymentProcessor', 'supported_methods');
    Config::inst()->update('PaymentProcessor', 'supported_methods', $paymentMethods);
    
    Config::inst()->remove('PaymentGateway', 'environment');
    Config::inst()->update('PaymentGateway', 'environment', 'dev');
    
    $this->processor = PaymentFactory::factory('PayPalExpress');
    $this->data = array(
      'Amount' => '10',
      'Currency' => 'USD'  
    );
  }

  function testClassConfig() {
    $this->assertEquals(get_class($this->processor), 'PaymentProcessor_GatewayHosted');
    $this->assertEquals(get_class($this->processor->gateway), 'PayPalExpressGateway');
    $this->assertEquals(get_class($this->processor->payment), 'PayPal');
  }

  function testSetExpressCheckout() {
    $this->processor->gateway->setReturnURL('www.example.com');
    $this->processor->gateway->setCancelURL('www.example.com');

    $token = $this->processor->gateway->setExpressCheckout();
    $this->assertNotNull($token);
  }
}