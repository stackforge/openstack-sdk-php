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
 * Unit tests for the stream wrapper file systema.
 */
namespace OpenStack\Tests\Storage\ObjectStorage;

require_once 'src/OpenStack/Bootstrap.php';
require_once 'test/TestCase.php';

use \OpenStack\Storage\ObjectStorage\StreamWrapperFS;
use \OpenStack\Storage\ObjectStorage\Container;
use \OpenStack\Storage\ObjectStorage\Object;
use \OpenStack\Storage\ObjectStorage\ACL;

/**
 * @group streamWrapper
 */
class StreamWrapperFSTest extends \OpenStack\Tests\TestCase {

  const FNAME = 'streamTest.txt';
  const FTYPE = 'application/x-tuna-fish; charset=iso-8859-13';

  /*public static function setUpBeforeClass() {
  }*/

  /**
   * Cleaning up the test container so we can reuse it for other tests.
   */
  public static function tearDownAfterClass() {

    // First we get an identity
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantId = self::conf('openstack.identity.tenantId');
    $url = self::conf('openstack.identity.url');

    $ident = new \OpenStack\Services\IdentityService($url);

    $token = $ident->authenticateAsUser($user, $pass, $tenantId);

    // Then we need to get an instance of storage
    $store = \OpenStack\Storage\ObjectStorage::newFromIdentity($ident);


    // Delete the container and all the contents.
    $cname = self::$settings['openstack.swift.container'];
    
    try {
      $container = $store->container($cname);
    }
    // The container was never created.
    catch (\OpenStack\Transport\FileNotFoundException $e) {
      return;
    }

    foreach ($container as $object) {
      try {
        $container->delete($object->name());
      }
      catch (\Exception $e) {}
    }

    $store->deleteContainer($cname);
  }

  protected function newUrl($objectName) {
    $scheme = StreamWrapperFS::DEFAULT_SCHEME;
    $cname = self::$settings['openstack.swift.container'];
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
    $cname   = self::$settings['openstack.swift.container'];

    if (empty($scheme)) {
      $scheme = StreamWrapperFS::DEFAULT_SCHEME;
    }

    if (empty(self::$ostore)) {
      throw new \Exception('OStore is gone.');
    }

    $params = $add + array(
      'token' => $this->objectStore()->token(),
      'swift_endpoint' => $this->objectStore()->url(),
      'content_type' => self::FTYPE,
    );
    $cxt = array($scheme => $params);

    return stream_context_create($cxt);
  }

  /**
   * This performs authentication via context.
   *
   * UPDATE: This now users IdentityService instead of deprecated
   * swauth.
   */
  protected function authSwiftContext($add = array(), $scheme = NULL) {
    $cname   = self::$settings['openstack.swift.container'];
    $account = self::$settings['openstack.identity.access'];
    $key     = self::$settings['openstack.identity.secret'];
    $tenant  = self::$settings['openstack.identity.tenantId'];
    $baseURL = self::$settings['openstack.identity.url'];

    if (empty($scheme)) {
      $scheme = StreamWrapperFS::DEFAULT_SCHEME;
    }

    $params = $add + array(
      'account' => $account,
      'key' => $key,
      'endpoint' => $baseURL,
      'tenantid' => $tenant,
      'content_type' => self::FTYPE,
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
      'account' => self::$settings['openstack.identity.access'],
      'key'     => self::$settings['openstack.identity.secret'],
      'endpoint' => self::$settings['openstack.identity.url'],
      'tenantid' => self::$settings['openstack.identity.tenantId'],
      'token' => $this->objectStore()->token(),
      'swift_endpoint' => $this->objectStore()->url(),
    );
    \OpenStack\Bootstrap::setConfiguration($opts);

  }

  // Canary. There are UTF-8 encoding issues in stream wrappers.
  public function testStreamContext() {
    // Clear old values.
    \OpenStack\Bootstrap::setConfiguration(array(
      'token' => NULL,
    ));

    $cxt = $this->authSwiftContext();
    $array = stream_context_get_options($cxt);

    $opts = $array['swiftfs'];
    $endpoint = self::conf('openstack.identity.url');

    $this->assertEquals($endpoint, $opts['endpoint'], 'A UTF-8 encoding issue.');
  }

  /**
   * @depends testStreamContext
   */
  public function testRegister() {
    // Canary
    $this->assertNotEmpty(StreamWrapperFS::DEFAULT_SCHEME);

    $klass = '\OpenStack\Storage\ObjectStorage\StreamWrapperFS';
    stream_wrapper_register(StreamWrapperFS::DEFAULT_SCHEME, $klass);

    $wrappers = stream_get_wrappers();

    $this->assertContains(StreamWrapperFS::DEFAULT_SCHEME, $wrappers);
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
    $cname = self::$settings['openstack.swift.container'];

    // Create a fresh container.
    $this->eradicateContainer($cname);
    $this->containerFixture();

    // Simple write test.
    $oUrl = $this->newUrl('foo→/test.csv');

    $res = fopen($oUrl, 'nope', FALSE, $this->authSwiftContext());

    $this->assertTrue(is_resource($res));

    $md = stream_get_meta_data($res);
    $wrapper = $md['wrapper_data'];

    fclose($res);

    // Now we test the same, but re-using the auth token:
    $cxt = $this->basicSwiftContext(array('token' => $wrapper->token()));

    $res = fopen($oUrl, 'nope', FALSE, $cxt);

    $this->assertTrue(is_resource($res));

    fclose($res);

  }

  /**
   * @depends testOpen
   */
  public function testOpenFailureWithRead() {
    $url = $this->newUrl(__FUNCTION__);
    $res = @fopen($url, 'r', FALSE, $this->basicSwiftContext());

    $this->assertFalse($res);

  }

  // DO we need to test other modes?

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
  public function testStreamGetMetadata($res) {
    // Grab a copy of the object.
    $url = $this->newUrl(self::FNAME);
    $newObj = fopen($url, 'r', FALSE, $this->basicSwiftContext());

    $md = stream_get_meta_data($newObj);
    //throw new \Exception(print_r($md, true));
    $obj = $md['wrapper_data']->object();

    $this->assertInstanceOf('\OpenStack\Storage\ObjectStorage\RemoteObject', $obj);

    $this->assertEquals(self::FTYPE, $obj->contentType());

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

  /**
   * @depends testUnlink
   */
  public function testRename(){
    $url = $this->newUrl('rename.foo');
    $fake = fopen($url, 'w+', FALSE, $this->basicSwiftContext());
    fwrite($fake, 'test');
    fclose($fake);

    $this->assertTrue(file_exists($url));

    $url2 = $this->newUrl('rename.txt');

    rename($url, $url2, $this->basicSwiftContext());

    $this->assertTrue(file_exists($url2));
    $this->assertFalse(file_exists($url));

    unlink($url2, $this->basicSwiftContext());
  }

  /**
   * @depends testUnlink
   */
  public function testOpenDir() {
    $urls = array('test1.txt', 'foo/test2.txt', 'foo/test3.txt', 'bar/test4.txt');
    foreach ($urls as $base) {
      $url = $this->newUrl($base);
      $f = fopen($url, 'c+', FALSE, $this->basicSwiftContext());
      fwrite($f, 'Test.');
      fclose($f);
    }

    $dirUrl = $this->newUrl('');
    $dir = opendir($dirUrl, $this->basicSwiftContext());

    $this->assertTrue(is_resource($dir));

    return $dir;

  }

  /**
   * @depends testOpenDir
   */
  public function testReaddir($dir){
    // Order should be newest to oldest.
    $expects = array('bar/', 'foo/', 'test1.txt');

    $buffer = array();
    while (($entry = readdir($dir)) !== FALSE) {
      $should_be = array_shift($expects);
      $this->assertEquals($should_be, $entry);
    }
    $this->assertFalse(readdir($dir));

    return $dir;
  }
  /**
   * @depends testReaddir
   */
  public function testRewindDir($dir){
    $this->assertFalse(readdir($dir));
    rewinddir($dir);
    $this->assertEquals('bar/', readdir($dir));
    return $dir;
  }

  /**
   * @depends testRewindDir
   */
  public function testCloseDir($dir) {
    $this->assertTrue(is_resource($dir));
    closedir($dir);

    // There is a bug in PHP where a
    // resource buffer is not getting cleared.
    // So this might return a value even though
    // the underlying stream is cleared.
    //$this->assertFalse(readdir($dir));
  }

  /**
   * @depends testCloseDir
   */
  public function testOpenSubdir() {

    // Opening foo we should find test2.txt and test3.txt.
    $url = $this->newUrl('foo/');
    $dir = opendir($url, $this->basicSwiftContext());

    // I don't know why, but these are always returned in
    // lexical order.
    $this->assertEquals('test2.txt', readdir($dir));
    $this->assertEquals('test3.txt', readdir($dir));

    $array = scandir($url, -1, $this->basicSwiftContext());
    $this->assertEquals(2, count($array));
    $this->assertEquals('test3.txt', $array[0]);

  }

  /**
   * @depends testReaddir
   */
  public function testIsdir($dir) {

    // Object names are pathy. If objects exist starting with this path we can
    // consider the directory to exist.
    $url = $this->newUrl('baz/');
    $this->assertFALSE(is_dir($url));

    $url = $this->newUrl('foo/');
    $this->assertTRUE(is_dir($url));

  }

  /**
   * @depends testReaddir
   */
  public function testMkdir() {

    // Object names are pathy. If no object names start with the a path we can
    // consider mkdir passed. If object names exist we should fail mkdir.
    $url = $this->newUrl('baz/');
    $this->assertTrue(mkdir($url, 0700, TRUE, $this->basicSwiftContext()));

    // Test the case for an existing directory.
    $url = $this->newUrl('foo/');
    $this->assertFalse(mkdir($url, 0700, TRUE, $this->basicSwiftContext()));
  }

  /**
   * @depends testReaddir
   */
  public function testRmdir() {

    // Object names are pathy. If no object names start with the a path we can
    // consider rmdir passed. If object names exist we should fail rmdir.
    $url = $this->newUrl('baz/');
    $this->assertTrue(rmdir($url, $this->basicSwiftContext()));

    // Test the case for an existing directory.
    $url = $this->newUrl('foo/');
    $this->assertFalse(rmdir($url, $this->basicSwiftContext()));
  }

}
