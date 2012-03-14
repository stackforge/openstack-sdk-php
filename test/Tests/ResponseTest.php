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
 * Unit tests for Response.
 */
namespace HPCloud\Tests\Transport;

require_once 'test/TestCase.php';

class ResponseTest extends \HPCloud\Tests\TestCase {

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

    return new \HPCloud\Transport\Response($file, $metadata);

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
