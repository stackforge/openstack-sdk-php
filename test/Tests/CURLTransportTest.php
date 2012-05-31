<?php
/* ============================================================================
(c) Copyright 2012 Hewlett-Packard Development Company, L.P.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights to
use, copy, modify, merge,publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE  LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
============================================================================ */
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

class CURLTransportTest extends \HPCloud\Tests\TestCase {

  public static function tearDownAfterClass() {
    $transport = NULL;
    if (isset(self::$settings['transport'])) {
      $transport = self::$settings['transport'];
    }
    \HPCloud\Bootstrap::setConfiguration(array(
      'transport' => $transport,
    ));
  }

  public function testConstructor() {
    $curl = new CURLTransport();

    // This is prone to failure because instance() caches
    // the class.
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
    $storage = $this->objectStore();

    $info = $storage->accountInfo();

    $this->assertTrue(count($info) > 1);
  }
}
