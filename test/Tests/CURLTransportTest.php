<?php
/**
 * @file
 *
 * Unit tests for ObjectStorage Object.
 */
namespace HPCloud\Tests\Transport;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Transport;
use \HPCloud\Transport\CURLTransport;

class ObjectTest extends \HPCloud\Tests\TestCase {

  public function testConstructor() {
    $curl = new CURLTransport();

    $trans = '\HPCloud\Transport\CURLTransport';
    \HPCloud\Bootstrap::setConfiguration(array(
      'transport' => $trans,
    ));

    // Need to test getting instance from Bootstrap.
    $this->assertInstanceOf($trans, Transport::instance());
  }

  public function testDoRequest() {
    $url = 'http://technosophos.com/index.php';
    $method = 'GET';
    $headers = array();

    $curl = new CURLTransport();
    $response = $curl->doRequest($url, $method, $headers);

    $this->assertInstanceOf('\HPCloud\Transport\Response', $response);

    $this->assertEquals(200, $response->status());

    $md = $response->metadata();
    $this->assertEquals(200, $md['http_code']);

    $this->assertTrue(strlen($response->header('Date', '')) > 0);

    $file = $response->file();
    $this->assertTrue(is_resource($file));

    $contents = $response->content();
    $this->assertTrue(strlen($contents) > 0);

  }

  /**
   * @depends testDoRequest
   * @expectedException \HPCloud\Transport\FileNotFoundException
   */
  public function testDoRequestException() {
    $url = 'http://technosophos.com/this-does-no-exist';
    $method = 'GET';
    $headers = array();

    $curl = new CURLTransport();
    $curl->doRequest($url, $method, $headers);
  }

  public function testSwiftAuth() {
    // We know that the object works, so now we test whether it can
    // communicate with Swift.
    $storage = $this->swiftAuth();

    $info = $storage->accountInfo();

    $this->assertTrue(count($info) > 1);
  }
}
