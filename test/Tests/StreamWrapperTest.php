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

  /**
   * Add additional params to the config.
   *
   * This allows us to insert credentials into the
   * bootstrap config, which in turn allows us to run
   * high-level context-less functions like
   * file_get_contents(), stat(), and is_file().
   */
  protected function addBootstrapConfig() {
    $opts = array(
      'account' => self::$settings['hpcloud.swift.account'],
      'key'     => self::$settings['hpcloud.swift.key'],
      'endpoint' => self::$settings['hpcloud.swift.url'],
    );
    \HPCloud\Bootstrap::setConfiguration($opts);

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
    $res = fopen($url, 'c+', FALSE, $this->basicSwiftContext());
    $this->assertTrue(is_resource($res));
    //fclose($res);

    return $res;
  }

  /**
   * @depends testOpenCreateMode
   */
  public function testTell($res) {
    // Sould be at the beginning of the buffer.
    $this->assertEquals(0, ftell($res));

    return $res;
  }

  /**
   * @depends testTell
   */
  public function testWrite($res) {
    $str = 'To be is to be the value of a bound variable. -- Quine';
    fwrite($res, $str);
    $this->assertGreaterThan(0, ftell($res));

    return $res;
  }

  /**
   * @depends testWrite
   */
  public function testStat($res) {
    $stat = fstat($res);

    $this->assertGreaterThan(0, $stat['size']);

    return $res;
  }

  /**
   * @depends testStat
   */
  public function testSeek($res) {
    $then = ftell($res);
    rewind($res);

    $now = ftell($res);

    // $now should be 0
    $this->assertLessThan($then, $now);
    $this->assertEquals(0, $now);

    fseek($res, 0, SEEK_END);
    $final = ftell($res);

    $this->assertEquals($then, $final);

    return $res;

  }

  /**
   * @depends testSeek
   */
  public function testEof($res) {
    rewind($res);

    $this->assertEquals(0, ftell($res));

    $this->assertFalse(feof($res));

    fseek($res, 0, SEEK_END);
    $this->assertGreaterThan(0, ftell($res));

    $read = fread($res, 8192);

    $this->assertEmpty($read);

    $this->assertTrue(feof($res));

    return $res;
  }

  /**
   * @depends testEof
   */
  public function testFlush($res) {

    $stat1 = fstat($res);

    fflush($res);

    // Grab a copy of the object.
    $url = $this->newUrl(self::FNAME);
    $newObj = fopen($url, 'r', FALSE, $this->basicSwiftContext());

    $stat2 = fstat($newObj);

    $this->assertEquals($stat1['size'], $stat2['size']);

    return $res;
  }

  /**
   * @depends testFlush
   */
  public function testClose($res) {
    $this->assertTrue(is_resource($res));
    fwrite($res, '~~~~');
    //throw new \Exception(stream_get_contents($res));
    fflush($res);

    // This is occasionally generating seemingly
    // spurious PHP errors about Bootstrap::$config.
    fclose($res);

    $url = $this->newUrl(self::FNAME);
    $res2 = fopen($url, 'r', FALSE, $this->basicSwiftContext());
    $this->assertTrue(is_resource($res2));

    $contents = stream_get_contents($res2);
    fclose($res2);
    $this->assertRegExp('/~{4}$/', $contents);

  }

  /**
   * @depends testClose
   */
  public function testCast() {
    $url = $this->newUrl(self::FNAME);
    $res = fopen($url, 'r', FALSE, $this->basicSwiftContext());

    $read = array($res);
    $write = array();
    $except = array();
    $num_changed = stream_select($read, $write, $except, 0);
    $this->assertGreaterThan(0, $num_changed);
  }

  /**
   * @depends testClose
   */
  public function testUrlStat(){
    // Add context to the bootstrap config.
    $this->addBootstrapConfig();

    $url = $this->newUrl(self::FNAME);

    $ret = stat($url);

    // Check that the array looks right.
    $this->assertEquals(26, count($ret));
    $this->assertEquals(0, $ret[3]);
    $this->assertEquals($ret[2], $ret['mode']);

    $this->assertTrue(file_exists($url));
    $this->assertTrue(is_readable($url));
    $this->assertTrue(is_writeable($url));
    $this->assertFalse(is_link($url));
    $this->assertGreaterThan(0, filemtime($url));
    $this->assertGreaterThan(5, filesize($url));

    $perm = fileperms($url);

    // Assert that this is a file. Objects are
    // *always* marked as files.
    $this->assertEquals(0x8000, $perm & 0x8000);

    // Assert writeable by owner.
    $this->assertEquals(0x0080, $perm & 0x0080);

    // Assert not world writable.
    $this->assertEquals(0, $perm & 0x0002);

    $contents = file_get_contents($url);
    $this->assertGreaterThan(5, strlen($contents));

    $fsCopy = '/tmp/hpcloud-copy-test.txt';
    copy($url, $fsCopy, $this->basicSwiftContext());
    $this->assertTrue(file_exists($fsCopy));
    unlink($fsCopy);
  }

  /**
   * @depends testFlush
   */
  public function testUnlink(){
    $url = $this->newUrl(self::FNAME);
    $cxt = $this->basicSwiftContext();

    $ret = unlink($url, $cxt);
    $this->assertTrue($ret);

    $ret2 = unlink($url, $cxt);
    $this->assertFalse($ret2);
  }

  public function testSetOption() {
    $url = $this->newUrl('fake.foo');
    $fake = fopen($url, 'nope', FALSE, $this->basicSwiftContext());

    $this->assertTrue(stream_set_blocking($fake, 1));

    // Returns 0 on success.
    $this->assertEquals(0, stream_set_write_buffer($fake, 8192));

    // Cant set a timeout on a tmp storage:
    $this->assertFalse(stream_set_timeout($fake, 10));

    fclose($fake);
  }

  public function testOpenFailureWithWrite() {
    // Make sure that a file opened as write only does not allow READ ops.
    $url = $this->newUrl(__FUNCTION__);
    //$res = @fopen($url, 'w', FALSE, $this->basicSwiftContext());

    $this->markTestIncomplete();
  }

  public function testReaddir(){}
  public function testRewindDir(){}
  public function testRMDir(){}
  public function testRename(){}



}
