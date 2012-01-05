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


class ObjectStorageTest extends \HPCloud\Tests\TestCase {

  /**
   * Canary test.
   */
  public function testSettings() {
    $this->assertTrue(!empty(self::$settings));
  }

  /**
   * Test Swift-based authentication.
   * */
  public function testAuthentication() {

    $ostore = $this->swiftAuth();

    $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage', $ostore);
    $this->assertTrue(strlen($ostore->token()) > 0);
  }

  public function testCreateContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];

    $this->assertNotEmpty($testCollection, "Canary: container name must be in settings file.");

    $store = $this->swiftAuth();

    if ($store->hasContainer($testCollection)) {
      $store->deleteContainer($testCollection);
    }
    $ret = $store->createContainer($testCollection);
    $this->assertTrue($ret, "Create container");

  }

  /**
   * @depends testCreateContainer
   */
  public function testAccountInfo () {
    $store = $this->swiftAuth();

    $info = $store->accountInfo();

    $this->assertTrue($info['count'] > 0);
    $this->assertTrue($info['bytes'] > 0);
  }

  /**
   * @depends testCreateContainer
   */
  public function testContainers() {
    $store = $this->swiftAuth();
    $containers = $store->containers();

    $this->assertNotEmpty($containers);

    //$first = array_shift($containers);

    $testCollection = self::$settings['hpcloud.swift.container'];
    $testContainer = $containers[$testCollection];
    $this->assertEquals($testCollection, $testContainer->name());
    $this->assertEquals(0, $testContainer->bytes());
    $this->assertEquals(0, $testContainer->count());

  }

  /**
   * @depends testCreateContainer
   */
  public function testContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];
    $store = $this->swiftAuth();

    $container = $store->container($testCollection);

    $this->assertEquals(0, $container->bytes());
    $this->assertEquals(0, $container->count());
    $this->assertEquals($testCollection, $container->name());
  }

  /**
   * @depends testCreateContainer
   */
  public function testHasContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];
    $store = $this->swiftAuth();

    $this->assertTrue($store->hasContainer($testCollection));
    $this->assertFalse($store->hasContainer('nihil'));
  }

  /**
   * @depends testHasContainer
   */
  public function testDeleteContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];

    $store = $this->swiftAuth();
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

    $store = $this->swiftAuth();
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
    $container->delete('test');
    $store->deleteContainer($testCollection);
  }

}
