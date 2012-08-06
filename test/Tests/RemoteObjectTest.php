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
 * Unit tests for ObjectStorage RemoteObject.
 */
namespace HPCloud\Tests\Storage\ObjectStorage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\ObjectStorage\RemoteObject;
use \HPCloud\Storage\ObjectStorage\Object;
use \HPCloud\Storage\ObjectStorage\Container;

class RemoteObjectTest extends \HPCloud\Tests\TestCase {

  const FNAME = 'RemoteObjectTest';
  //const FTYPE = 'text/plain; charset=UTF-8';
  const FTYPE = 'application/octet-stream; charset=UTF-8';
  const FCONTENT = 'Rah rah ah ah ah. Roma roma ma. Gaga oh la la.';
  const FMETA_NAME = 'Foo';
  const FMETA_VALUE = 'Bar';
  const FDISPOSITION = 'attachment; roma.gaga';
  const FENCODING = 'gzip';
  const FCORS_NAME = 'Access-Control-Max-Age';
  const FCORS_VALUE = '2000';

  protected function createAnObject() {
    $container = $this->containerFixture();

    $object = new Object(self::FNAME, self::FCONTENT, self::FTYPE);
    $object->setMetadata(array(self::FMETA_NAME => self::FMETA_VALUE));
    $object->setDisposition(self::FDISPOSITION);
    $object->setEncoding(self::FENCODING);
    $object->setAdditionalHeaders(array(
      'Access-Control-Allow-Origin' => 'http://example.com',
      'Access-control-allow-origin' => 'http://example.com',
    ));

    // Need some headers that Swift actually stores and returns. This
    // one does not seem to be returned ever.
    //$object->setAdditionalHeaders(array(self::FCORS_NAME => self::FCORS_VALUE));

    $container->save($object);
  }

  public function testNewFromHeaders() {
    // This is tested via the container.

    $this->destroyContainerFixture();
    $container = $this->containerFixture();
    $this->createAnObject();

    $obj = $container->remoteObject(self::FNAME);

    $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage\RemoteObject', $obj);

    return $obj;
  }
  /**
   * @depends testNewFromHeaders
   */
  public function testContentLength($obj) {
    $len = strlen(self::FCONTENT);

    $this->assertEquals($len, $obj->contentLength());

    return $obj;
  }

  /**
   * @depends testContentLength
   */
  public function testContentType($obj) {
    $this->assertEquals(self::FTYPE, $obj->contentType());

    return $obj;
  }

  /**
   * @depends testContentType
   */
  public function testEtag($obj) {
    $hash = md5(self::FCONTENT);

    $this->assertEquals($hash, $obj->eTag());

    return $obj;
  }

  /**
   * @depends testContentType
   */
  public function testLastModified($obj) {
    $date = $obj->lastModified();

    $this->assertTrue(is_int($date));
    $this->assertTrue($date > 0);
  }

  /**
   * @depends testNewFromHeaders
   */
  public function testMetadata($obj) {
    $md = $obj->metadata();

    $this->assertArrayHasKey(self::FMETA_NAME, $md);
    $this->assertEquals(self::FMETA_VALUE, $md[self::FMETA_NAME]);
  }

  /**
   * @depends testNewFromHeaders
   */
  public function testDisposition($obj) {
    $this->assertEquals(self::FDISPOSITION, $obj->disposition());
  }

  /**
   * @depends testNewFromHeaders
   */
  public function testEncoding($obj) {
    $this->assertEquals(self::FENCODING, $obj->encoding());
  }

  /**
   * @depends testNewFromHeaders
   */
  public function testHeaders($obj) {
    $headers = $obj->headers();
    $this->assertTrue(count($headers) > 1);

    //fwrite(STDOUT, print_r($headers, TRUE));

    $this->assertNotEmpty($headers['Date']);

    $obj->removeHeaders(array('Date'));

    $headers = $obj->headers();
    $this->assertFalse(isset($headers['Date']));

    // Swift doesn't return CORS headers even though it is supposed to.
    //$this->assertEquals(self::FCORS_VALUE, $headers[self::FCORS_NAME]);
  }

  /**
   * @depends testNewFromHeaders
   */
  public function testUrl($obj) {
    $url = $obj->url();

    $this->assertTrue(strpos($obj->url(), $obj->name())> 0);
  }
  /**
   * @depends testNewFromHeaders
   */
  public function testStream($obj) {
    $res = $obj->stream();

    $this->assertTrue(is_resource($res));

    $res_md = stream_get_meta_data($res);

    // This will be HTTP if we are using the PHP stream
    // wrapper, but for CURL this will be PHP.

    $klass = \HPCloud\Bootstrap::config('transport', NULL);
    if ($klass == '\HPCloud\Transport\PHPStreamTransport') {
      $expect = 'http';
    }
    else {
      $expect = 'PHP';
    }

    $this->assertEquals($expect, $res_md['wrapper_type']);

    $content = fread($res, $obj->contentLength());

    fclose($res);

    $this->assertEquals(self::FCONTENT, $content);

    // Now repeat the tests, only with a local copy of the data.
    // This allows us to test the local tempfile buffering.

    $obj->setContent($content);

    $res2 = $obj->stream();
    $res_md = stream_get_meta_data($res2);

    $this->assertEquals('PHP', $res_md['wrapper_type']);

    $content = fread($res2, $obj->contentLength());

    fclose($res2);

    $this->assertEquals(self::FCONTENT, $content);

    // Finally, we redo the first part of the test to make sure that 
    // refreshing gets us a new copy:

    $res3 = $obj->stream(TRUE);
    $res_md = stream_get_meta_data($res3);
    $this->assertEquals($expect, $res_md['wrapper_type']);
    fclose($res3);

    return $obj;
  }

  // To avoid test tainting from testStream(), we start over.
  public function testContent() {
    $container = $this->containerFixture();
    $obj = $container->object(self::FNAME);

    $content = $obj->content();
    $this->assertEquals(self::FCONTENT, $content);

    // Make sure remoteObject retrieves the same content.
    $obj = $container->remoteObject(self::FNAME);
    $content = $obj->content();
    $this->assertEquals(self::FCONTENT, $content);

  }

  /**
   * @depends testStream
   */
  public function testCaching() {
    $container = $this->containerFixture();
    $obj = $container->remoteObject(self::FNAME);

    $this->assertFalse($obj->isCaching());

    // This is a roundabout way of testing. We know that if caching is
    // turned on, then we can get a local copy of the file instead of a
    // remote, and the best way to find this out is by grabbing a
    // stream. The local copy will be in a php://temp stream.
    //
    // The CURL, though, backs its up with a temp file wrapped in a PHP 
    // stream. Other backends are likely to do the same. So this test
    // is weakened for CURL backends.
    $transport = \HPCloud\Bootstrap::config('transport');
    if ($transport == '\HPCloud\Transport\PHPStreamTransport') {
      $expect = 'http';
    }
    else {
      $expect = 'PHP';
    }

    $content = $obj->content();

    $res1 = $obj->stream();
    $md = stream_get_meta_data($res1);
    $this->assertEquals($expect, $md['wrapper_type']);

    fclose($res1);

    // Enable caching and retest.
    $obj->setCaching(TRUE);
    $this->assertTrue($obj->isCaching());

    // This will cache the content.
    $content = $obj->content();

    $res2 = $obj->stream();
    $md = stream_get_meta_data($res2);

    // If this is using the PHP version, it built content from the
    // cached version.
    $this->assertEquals('PHP', $md['wrapper_type']);

    fclose($res2);
  }

  /**
   * @depends testNewFromHeaders
   */
  public function testContentVerification($obj) {
    $this->assertTrue($obj->isVerifyingContent());
    $obj->setContentVerification(FALSE);
    $this->assertFALSE($obj->isVerifyingContent());
    $obj->setContentVerification(TRUE);
  }

  /**
   * @depends testCaching
   */
  public function testIsDirty() {
    $container = $this->containerFixture();
    $obj = $container->remoteObject(self::FNAME);

    // THere is no content. Assert false.
    $this->assertFalse($obj->isDirty());

    $obj->setCaching(TRUE);
    $obj->content();

    // THere is content, but it is unchanged.
    $this->assertFalse($obj->isDirty());

    // Change content and retest.
    $obj->setContent('foo');

    $this->assertTrue($obj->isDirty());
  }

  /**
   * @depends testIsDirty
   */
  public function testRefresh() {
    $container = $this->containerFixture();
    $obj = $container->remoteObject(self::FNAME);

    $obj->setContent('foo');
    $this->assertTrue($obj->isDirty());

    $obj->refresh(FALSE);
    $this->assertFalse($obj->isDirty());
    $this->assertEquals(self::FCONTENT, $obj->content());

    $obj->setContent('foo');
    $this->assertTrue($obj->isDirty());

    $obj->refresh(TRUE);
    $this->assertFalse($obj->isDirty());
    $this->assertEquals(self::FCONTENT, $obj->content());

    $this->destroyContainerFixture();

  }

}
