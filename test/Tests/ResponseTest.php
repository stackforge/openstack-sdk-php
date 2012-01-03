<?php
/**
 * @file
 *
 * Unit tests for Response.
 */
namespace HPCloud\Tests\Transport;

require_once 'test/TestCase.php';

class Response extends \HPCloud\Tests\TestCase {

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
    //$this->assert->boolean(is_resource($file))->isTrue();
    $this->assertTrue(is_resource($file));

    $content = fread($file, 1024);

    //$this->assert->string($content)->isEqualTo($this->fakeBody);
    $this->assertEquals($this->fakeBody, $content);

    fclose($file);
  }

  public function testContent() {
    $response = $this->mockFile();

    $this->assertEquals($this->fakeBody, $response->content());
    //$this->assert->string($response->content())->isEqualTo($this->fakeBody);
  }

  public function testMetadata() {
    $response = $this->mockFile();
    $md = $response->metadata();
    $this->assertTrue(is_array($md));
    $this->assertTrue(!empty($md));
    //$this->assert->phpArray($response->metadata())->isNotEmpty();
  }

  public function testHeaders() {
    $response = $this->mockFile();
    $hdr = $response->headers();
    $this->assertTrue(!empty($hdr));
    //$this->assert->phpArray($response->headers())->isNotEmpty();

    $headers = $response->headers();
    $this->assertEquals('close', $headers['Connection']);
    //$this->assert->string($headers['Connection'])->isEqualTo('close');
  }

  public function testHeader() {
    $response = $this->mockFile();

    $this->assertEquals('close', $response->header('Connection'));
    $this->assertNull($response->header('FAKE'));
    $this->assertEquals('YAY', $response->header('FAKE', 'YAY'));
    // $this->assert->string($response->header('Connection'))->isEqualTo('close');
    // $this->assert->boolean(is_null($response->header('FAKE')));
    // $this->assert->string($response->header('FAKE', 'YAY'))->isEqualTo('YAY');
  }

  public function testStatus() {
    $response = $this->mockFile();

    //$this->assert->integer($response->status())->isEqualTo(200);
    $this->assertEquals(200, $response->status());
  }

  public function testStatusMessage() {
    $response = $this->mockFile();

    $this->assertEquals('OK', $response->statusMessage());
    //$this->assert->string($response->statusMessage())->isEqualTo('OK');
  }

  public function testProtocol() {
    $response = $this->mockFile();

    $this->assertEquals('HTTP/1.1', $response->protocol());
    //$this->assert->string($response->protocol())->isEqualTo('HTTP/1.1');
  }
}
