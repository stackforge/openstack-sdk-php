<?php
/**
 * @file
 *
 * Unit tests for ObjectStorage.
 */
namespace HPCloud\Tests\Storage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\ObjectStorage\Object;
use \HPCloud\Storage\ObjectStorage\ACL;


class ObjectStorageTest extends \HPCloud\Tests\TestCase {

  /**
   * Canary test.
   */
  public function testSettings() {
    $this->assertTrue(!empty(self::$settings));
  }

  /**
   * Test Swift-based authentication.
   * @group deprecated
   */
  public function testSwiftAuthentication() {

    $ostore = $this->swiftAuth();

    $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage', $ostore);
    $this->assertTrue(strlen($ostore->token()) > 0);
  }

  /**
   * @group auth
   */
  public function testConstructor() {
    $ostore = $this->objectStore();

    $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage', $ostore);
    $this->assertTrue(strlen($ostore->token()) > 0);

  }

  /**
   * @group auth
   * @group acl
   */
  public function testCreateContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];

    $this->assertNotEmpty($testCollection, "Canary: container name must be in settings file.");

    $store = $this->objectStore();//swiftAuth();

    if ($store->hasContainer($testCollection)) {
      $store->deleteContainer($testCollection);
    }

    $md = array('Foo' => 1234);

    $ret = $store->createContainer($testCollection, NULL, $md);
    $this->assertTrue($ret, "Create container");

  }

  /**
   * @group auth
   * @depends testCreateContainer
   */
  public function testAccountInfo () {
    $store = $this->objectStore();

    $info = $store->accountInfo();

    $this->assertGreaterThan(0, $info['containers']);
    $this->assertGreaterThanOrEqual(0, $info['bytes']);
    $this->assertGreaterThanOrEqual(0, $info['objects']);
  }

  /**
   * @depends testCreateContainer
   */
  public function testContainers() {
    $store = $this->objectStore();
    $containers = $store->containers();

    $this->assertNotEmpty($containers);

    //$first = array_shift($containers);

    $testCollection = self::conf('hpcloud.swift.container');
    $testContainer = $containers[$testCollection];
    $this->assertEquals($testCollection, $testContainer->name());
    $this->assertEquals(0, $testContainer->bytes());
    $this->assertEquals(0, $testContainer->count());

    // Make sure we get back an ACL:
    $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage\ACL', $testContainer->acl());

  }

  /**
   * @depends testCreateContainer
   */
  public function testContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];
    $store = $this->objectStore();

    $container = $store->container($testCollection);

    $this->assertEquals(0, $container->bytes());
    $this->assertEquals(0, $container->count());
    $this->assertEquals($testCollection, $container->name());

    $md = $container->metadata();
    $this->assertEquals(1, count($md));
    $this->assertEquals('1234', $md['Foo']);
  }

  /**
   * @depends testCreateContainer
   */
  public function testHasContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];
    $store = $this->objectStore();

    $this->assertTrue($store->hasContainer($testCollection));
    $this->assertFalse($store->hasContainer('nihil'));
  }

  /**
   * @depends testHasContainer
   */
  public function testDeleteContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];

    $store = $this->objectStore();
    //$ret = $store->createContainer($testCollection);
    //$this->assertTrue($store->hasContainer($testCollection));

    $ret = $store->deleteContainer($testCollection);

    $this->assertTrue($ret);

    // Now we try to delete a container that does not exist.
    $ret = $store->deleteContainer('nihil');
    $this->assertFalse($ret);
  }

  /**
   * @expectedException \HPCloud\Storage\ObjectStorage\ContainerNotEmptyException
   */
  public function testDeleteNonEmptyContainer() {

    $testCollection = self::$settings['hpcloud.swift.container'];

    $this->assertNotEmpty($testCollection);

    $store = $this->objectStore();
    $store->createContainer($testCollection);

    $container = $store->container($testCollection);
    $container->save(new Object('test', 'test', 'text/plain'));

    try {
      $ret = $store->deleteContainer($testCollection);
    }
    catch (\Exception $e) {
      $container->delete('test');
      $store->deleteContainer($testCollection);
      throw $e;
    }

    try {
      $container->delete('test');
    }
    // Skip 404s.
    catch (\Exception $e) {}

    $store->deleteContainer($testCollection);
  }

  /**
   * @depends testCreateContainer
   * @group acl
   */
  public function testCreateContainerPublic() {
    $testCollection = self::$settings['hpcloud.swift.container'] . 'PUBLIC';
    $store = $this->objectStore();
    if ($store->hasContainer($testCollection)) {
      $store->deleteContainer($testCollection);
    }

    $ret = $store->createContainer($testCollection, ACL::makePublic());
    $container = $store->container($testCollection);

    // Now test that we can get the container contents. Since there is
    // no content in the container, we use the format=xml to make sure
    // we get some data back.
    $url = $container->url() . '?format=xml';
    $data = file_get_contents($url);
    $this->assertNotEmpty($data, $url);

    $containers = $store->containers();
    //throw new \Exception(print_r($containers, TRUE));

    $store->deleteContainer($testCollection);
  }

  /**
   * @depends testCreateContainerPublic
   */
  public function testChangeContainerACL() {
    $testCollection = self::$settings['hpcloud.swift.container'] . 'PUBLIC';
    $store = $this->objectStore();
    if ($store->hasContainer($testCollection)) {
      $store->deleteContainer($testCollection);
    }
    $ret = $store->createContainer($testCollection);


    $acl = \HPCloud\Storage\ObjectStorage\ACL::makePublic();
    $ret = $store->changeContainerACL($testCollection, $acl);

    $this->assertFalse($ret);

    $container = $store->container($testCollection);
    $url = $container->url() . '?format=xml';
    $data = file_get_contents($url);
    $this->assertNotEmpty($data, $url);

  }
}
