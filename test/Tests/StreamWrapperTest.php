<?php
/**
 * @file
 *
 * Unit tests for the stream wrapper.
 */
namespace HPCloud\Tests\Storage\ObjectStorage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\ObjectStorage\StreamWrapper;
use \HPCloud\Storage\ObjectStorage\Container;
use \HPCloud\Storage\ObjectStorage\Object;
use \HPCloud\Storage\ObjectStorage\ACL;

class StreamWrapperTest extends \HPCloud\Tests\TestCase {

  const FNAME = 'streamTest.txt';
  const FTYPE = 'text/plain';

  protected function newUrl($objectName) {
    $scheme = StreamWrapper::DEFAULT_SCHEME;
    $cname   = self::$settings['hpcloud.swift.container'];
    $cname = urlencode($cname);

    $objectParts = explode('/', $objectName);
    for ($i = 0; $i < count($objectParts); ++$i) {
      $objectParts[$i] = urlencode($objectParts[$i]);
    }
    $objectName = implode('/', $objectParts);

    $url = $scheme . '://' . $cname . '/' . $objectName;

    return $url;
  }

  /**
   * This assumes auth has already been done.
   */
  protected function basicSwiftContext($add = array(), $scheme = NULL) {
    $cname   = self::$settings['hpcloud.swift.container'];

    if (empty($scheme)) {
      $scheme = StreamWrapper::DEFAULT_SCHEME;
    }

    $params = $add + array(
        'token' => self::$ostore->token(),
        'swift_endpoint' => self::$ostore->url(),
      );
    $cxt = array($scheme => $params);

    return stream_context_create($cxt);
  }

  /**
   * This performs authentication via context.
   */
  protected function authSwiftContext($add = array(), $scheme = NULL) {
    $cname   = self::$settings['hpcloud.swift.container'];
    $account = self::$settings['hpcloud.swift.account'];
    $key     = self::$settings['hpcloud.swift.key'];
    $baseURL = self::$settings['hpcloud.swift.url'];

    if (empty($scheme)) {
      $scheme = StreamWrapper::DEFAULT_SCHEME;
    }

    $params = $add + array(
        'account' => $account,
        'key' => $key,
        'endpoint' => $baseURL,
      );
    $cxt = array($scheme => $params);

    return stream_context_create($cxt);

  }

  // Canary. There are UTF-8 encoding issues in stream wrappers.
  public function testStreamContext() {
    $cxt = $this->authSwiftContext();
    $array = stream_context_get_options($cxt);

    $opts = $array['swift'];
    $endpoint = self::$settings['hpcloud.swift.url'];

    $this->assertEquals($endpoint, $opts['endpoint'], 'A UTF-8 encoding issue.');
  }

  /**
   * @depends testStreamContext
   */
  public function testRegister() {
    // Canary
    $this->assertNotEmpty(StreamWrapper::DEFAULT_SCHEME);

    $klass = '\HPCloud\Storage\ObjectStorage\StreamWrapper';
    stream_wrapper_register(StreamWrapper::DEFAULT_SCHEME, $klass);

    $wrappers = stream_get_wrappers();

    $this->assertContains(StreamWrapper::DEFAULT_SCHEME, $wrappers);
  }

  /**
   * @depends testRegister
   */
  public function testOpenFailureWithoutContext() {
    $url = $this->newUrl('foo→/bar.txt');
    $ret = @fopen($url, 'r');

    $this->assertFalse($ret);
  }

  /**
   * @depends testRegister
   */
  public function testOpen() {
    $cname   = self::$settings['hpcloud.swift.container'];

    // Create a fresh container.
    $this->eradicateContainer($cname);
    $this->containerFixture();

    // Simple write test.
    $oUrl = $this->newUrl('foo→/test.csv');

    $res = fopen($oUrl, 'nope', FALSE, $this->authSwiftContext());

    $this->assertTrue(is_resource($res));

    fclose($res);

    // Now we test the same, but re-using the auth token:
    $cxt = $this->basicSwiftContext();
    $res = fopen($oUrl, 'nope', FALSE, $cxt);

    $this->assertTrue(is_resource($res));

  }

  /**
   * @depends testOpen
   */
  public function testOpenFailureWithRead() {
    $url = $this->newUrl(__FUNCTION__);
    $res = @fopen($url, 'r', FALSE, $this->basicSwiftContext());

    $this->assertFalse($res);

  }

  /**
   * @depends testOpen
   */
  public function testOpenCreateMode() {
    $url = $this->newUrl(self::FNAME);
    $res = fopen($url, 'c', FALSE, $this->basicSwiftContext());
    $this->assertTrue(is_resource($res));

    return $res;
  }

  /**
   * @depends testOpenCreateMode
   */
  public function testWrite($res) {

  }

  /**
   * @depends testOpenCreateMode
   */
  public function testClose($res) {
    fclose($res);

    $url = $this->newUrl(self::FNAME);
    $res2 = fopen($url, 'r', FALSE, $this->basicSwiftContext());
    $this->assertTrue(is_resource($res2));
  }

  public function testOpenFailureWithWrite() {
    // Make sure that a file opened as write only does not allow READ ops.
    $url = $this->newUrl(__FUNCTION__);
    //$res = @fopen($url, 'w', FALSE, $this->basicSwiftContext());

    $this->markTestIncomplete();
  }


}
