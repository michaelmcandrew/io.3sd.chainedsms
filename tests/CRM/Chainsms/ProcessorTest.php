<?php

/**
 * Generated by PHPUnit_SkeletonGenerator on 2014-12-19 at 16:19:58.
 */
class CRM_Chainsms_ProcessorTest extends PHPUnit_Framework_TestCase {
  
  /**
   * @var CRM_Chainsms_Processor
   */
  protected $object;

  /**
   * Sets up the fixture, for example, opens a network connection.
   * This method is called before a test is executed.
   */
  protected function setUp() {
    $this->object = new CRM_Chainsms_Processor;
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   */
  protected function tearDown() {
    
  }

  /**
   * @covers CRM_Chainsms_Processor::inbound
   * @todo   Implement testInbound().
   */
  public function testInbound() {
    // Remove the following lines when you implement this test.
    $this->markTestIncomplete(
        'This test has not been implemented yet.'
    );
  }

  /**
   * @covers CRM_Chainsms_Processor::cleanInboundResponse
   * @todo   Implement testCleanInboundResponse().
   */
  public function testCleanInboundResponse() {
    // not an array because this could grow to be a lot of consts
    $cleanInboundResponseArray = array(
      "A :) x x " => "A", // smilies and kisses :) xx
      "b <3" => "b", // we <3 other emoticons
      "(C)" => "C", // (someone put theirs in brackets)
      "D." => "D", // full stop.
      "E" => "E", // identical
      "f!" => "f", // people use exclamation marks!
      "h " => "h", // it has to work with trailing spaces 
    );
    
    foreach($cleanInboundResponseArray as $dirty => $clean){
      $this->assertEquals(CRM_Chainsms_Processor::cleanInboundResponse($dirty), $clean);
    }
  }

  /**
   * @covers CRM_Chainsms_Processor::mostRecentOutboundChainSMS
   * @todo   Implement testMostRecentOutboundChainSMS().
   */
  public function testMostRecentOutboundChainSMS() {
    // Remove the following lines when you implement this test.
    $this->markTestIncomplete(
        'This test has not been implemented yet.'
    );
  }

  /**
   * @covers CRM_Chainsms_Processor::penultimateInboundSMS
   * @todo   Implement testPenultimateInboundSMS().
   */
  public function testPenultimateInboundSMS() {
    // Remove the following lines when you implement this test.
    $this->markTestIncomplete(
        'This test has not been implemented yet.'
    );
  }

  /**
   * @covers CRM_Chainsms_Processor::outbound
   * @todo   Implement testOutbound().
   */
  public function testOutbound() {
    // Remove the following lines when you implement this test.
    $this->markTestIncomplete(
        'This test has not been implemented yet.'
    );
  }

  /**
   * @covers CRM_Chainsms_Processor::addMessage
   * @todo   Implement testAddMessage().
   */
  public function testAddMessage() {
    // Remove the following lines when you implement this test.
    $this->markTestIncomplete(
        'This test has not been implemented yet.'
    );
  }

  /**
   * @covers CRM_Chainsms_Processor::printMessages
   * @todo   Implement testPrintMessages().
   */
  public function testPrintMessages() {
    // Remove the following lines when you implement this test.
    $this->markTestIncomplete(
        'This test has not been implemented yet.'
    );
  }

  public function testDoesWordContainSwear(){
    $this->storedSafeWords = CRM_Core_BAO_Setting::setItem(array('safekitten'), 'org.thirdsectordesign.chainsms', 'safe_list');
    $this->storedBadWords = CRM_Core_BAO_Setting::setItem(array('kitten'), 'org.thirdsectordesign.chainsms', 'swear_list');
    
    $this->assertTrue($this->object->doesResponseContainBadWord('KITTENS'));
    $this->assertTrue($this->object->doesResponseContainBadWord('I like safekitten and KITTENS!'));
    $this->assertFalse($this->object->doesResponseContainBadWord('I like safekitten'));
  }
}