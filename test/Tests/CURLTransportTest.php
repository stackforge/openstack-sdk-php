<?php
/* ============================================================================
(c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
============================================================================ */
/**
 * @file
 *
 * Unit tests for ObjectStorage Object.
 */
namespace OpenStack\Tests\Transport;

require_once 'src/OpenStack/Bootstrap.php';
require_once 'test/TestCase.php';

use \OpenStack\Transport;
use \OpenStack\Transport\CURLTransport;

/**
 * @group curl
 */
class CURLTransportTest extends \OpenStack\Tests\TestCase {

  public static function tearDownAfterClass() {
    $transport = NULL;
    if (isset(self::$settings['transport'])) {
      $transport = self::$settings['transport'];
    }
    \OpenStack\Bootstrap::setConfiguration(array(
      'transport' => $transport,
    ));
  }

  public function testConstructor() {
    $curl = new CURLTransport();

    // This is prone to failure because instance() caches
    // the class.
    $trans = '\OpenStack\Transport\CURLTransport';
    \OpenStack\Bootstrap::setConfiguration(array(
      'transport' => $trans,
    ));

    // Need to test getting instance from Bootstrap.
    $this->assertInstanceOf($trans, Transport::instance());
  }

  public function testDoRequest() {
    $url = 'http://www.openstack.org';
    $method = 'GET';
    $headers = array();

    $curl = new CURLTransport();
    $response = $curl->doRequest($url, $method, $headers);

    $this->assertInstanceOf('\OpenStack\Transport\Response', $response);

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
   * @expectedException \OpenStack\Transport\FileNotFoundException
   */
  public function testDoRequestException() {
    $url = 'http://www.openstack.org/this-does-no-exist';
    $method = 'GET';
    $headers = array();

    $curl = new CURLTransport();
    $curl->doRequest($url, $method, $headers);
  }

  // public function testSwiftAuth() {
  //   // We know that the object works, so now we test whether it can
  //   // communicate with Swift.
  //   $storage = $this->objectStore();

  //   $info = $storage->accountInfo();

  //   $this->assertTrue(count($info) > 1);
  // }
}
