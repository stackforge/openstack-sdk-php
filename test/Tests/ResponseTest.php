<?php
/**
 * @file
 *
 * Unit tests for Response.
 */
namespace HPCloud\Transport\Tests\Units;

require_once  'mageekguy.atoum.phar';
require_once 'test/TestCase.php';

use \mageekguy\atoum;

class Response extends \HPCloud\TestCase {

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

    //$this->assert->stream($file)->isRead();
    $this->assert->boolean(is_resource($file))->isTrue();

    $content = fread($file, 1024);

    $this->assert->string($content)->isEqualTo($this->fakeBody);

    fclose($file);
  }

  public function testContent() {
    $response = $this->mockFile();

    $this->assert->string($response->content())->isEqualTo($this->fakeBody);
  }

  public function testMetadata() {
    $response = $this->mockFile();
    $this->assert->phpArray($response->metadata())->isNotEmpty();
  }

  public function testHeaders() {
    $response = $this->mockFile();
    $this->assert->phpArray($response->headers())->isNotEmpty();

    $headers = $response->headers();
    $this->assert->string($headers['Connection'])->isEqualTo('close');
  }

  public function testHeader() {
    $response = $this->mockFile();

    $this->assert->string($response->header('Connection'))->isEqualTo('close');
    $this->assert->boolean(is_null($response->header('FAKE')));
    $this->assert->string($response->header('FAKE', 'YAY'))->isEqualTo('YAY');
  }

  public function testStatus() {
    $response = $this->mockFile();

    $this->assert->integer($response->status())->isEqualTo(200);
  }

  public function testStatusMessage() {
    $response = $this->mockFile();

    $this->assert->string($response->statusMessage())->isEqualTo('OK');
  }

  public function testProtocol() {
    $response = $this->mockFile();

    $this->assert->string($response->protocol())->isEqualTo('HTTP/1.1');
  }
}
