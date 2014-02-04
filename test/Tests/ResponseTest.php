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
 * Unit tests for Response.
 */
namespace OpenStack\Tests\Transport;

require_once 'test/TestCase.php';

class ResponseTest extends \OpenStack\Tests\TestCase {

  protected $fakeBody = '{"msg":"This is a fake response"}';
  protected $fakeHeaders = array(
    'HTTP/1.1 200 OK',
    'Date: Thu, 22 Dec 2011 16:35:00 GMT',
    'Server: Apache/2.2.21 (Unix)',
    'Expires: Sun, 19 Nov 1978 05:00:00 GMT',
    'Cache-Control: store, no-cache, must-revalidate, post-check=0, pre-check=0',
    'Set-Cookie: abc; expires=Sat, 14-Jan-2012 20:08:20 GMT; path=/; domain=.technosophos.com',
    'Last-Modified: Thu, 22 Dec 2011 16:35:00 GMT',
    'Vary: Accept-Encoding,User-Agent',
    'Connection: close',
    'Content-Type: text/html; charset=utf-8',
  );

  /**
   * Build a simple mock response with a file.
   */
  protected function mockFile() {
    $file = fopen('php://memory', 'rw');
    fwrite($file, $this->fakeBody);

    // Why does rewind not reset unread_bytes?
    //rewind($file);
    fseek($file, 0, SEEK_SET);

    $metadata = stream_get_meta_data($file);
    $metadata['wrapper_data'] = $this->fakeHeaders;
    // This is a dangerous work-around for the fact that
    // reset and seek don't reset the unread_bytes.
    $metadata['unread_bytes'] = strlen($this->fakeBody);

    return new \OpenStack\Transport\Response($file, $metadata);

  }

  public function testFile() {
    $response = $this->mockFile();

    #print_r($response);

    $file = $response->file();

    $this->assertTrue(is_resource($file));

    $content = fread($file, 1024);

    $this->assertEquals($this->fakeBody, $content);

    fclose($file);
  }

  public function testContent() {
    $response = $this->mockFile();

    $this->assertEquals($this->fakeBody, $response->content());
  }

  public function testMetadata() {
    $response = $this->mockFile();
    $md = $response->metadata();
    $this->assertTrue(is_array($md));
    $this->assertTrue(!empty($md));
  }

  public function testHeaders() {
    $response = $this->mockFile();
    $hdr = $response->headers();
    $this->assertTrue(!empty($hdr));

    $headers = $response->headers();
    $this->assertEquals('close', $headers['Connection']);
  }

  public function testHeader() {
    $response = $this->mockFile();

    $this->assertEquals('close', $response->header('Connection'));
    $this->assertNull($response->header('FAKE'));
    $this->assertEquals('YAY', $response->header('FAKE', 'YAY'));
  }

  public function testStatus() {
    $response = $this->mockFile();

    $this->assertEquals(200, $response->status());
  }

  public function testStatusMessage() {
    $response = $this->mockFile();

    $this->assertEquals('OK', $response->statusMessage());
  }

  public function testProtocol() {
    $response = $this->mockFile();

    $this->assertEquals('HTTP/1.1', $response->protocol());
  }
}
