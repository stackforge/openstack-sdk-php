<?php
/**
 * @file
 *
 * Unit tests for ObjectStorage.
 */
namespace HPCloud\Tests\Storage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';


class ObjectStorageTest extends \HPCloud\Tests\TestCase {

  protected function auth() {

    static $ostore = NULL;

    if (empty($ostore)) {
      $user = self::$settings['hpcloud.swift.account'];
      $key = self::$settings['hpcloud.swift.key'];
      $url = self::$settings['hpcloud.swift.url'];

      $ostore = \HPCloud\Storage\ObjectStorage::newFromSwiftAuth($user, $key, $url);
    }

    return $ostore;
  }

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

    $ostore = $this->auth();

    $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage', $ostore);
    $this->assertTrue(strlen($ostore->token()) > 0);
  }

  /**
   * Test the process of fetching a list of containers.
   *
   * @FIXME This needs to be updated to check an actual container.
   * @FIXME Needs to check byte and object count.
   */
  public function testContainers() {
    $store = $this->auth();
    $containers = $store->containers();

    $this->assertNotEmpty($containers);

    $first = array_shift($containers);
    $this->assertNotEmpty($first->name());
  }

  public function testCreateContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];

    $this->assertNotEmpty($testCollection, "Canary: container name must be in settings file.");

    $store = $this->auth();

    if ($store->hasContainer($testCollection)) {
      $store->deleteContainer($testCollection);
    }
    $ret = $store->createContainer($testCollection);
    $this->assertTrue($ret, "Create container");

  }

  public function testHasContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'];
    $store = $this->auth();
    $store->createContainer($testCollection);

    $this->assertTrue($store->hasContainer($testCollection));
  }
  public function XtestDeleteContainer() {
    $testCollection = self::$settings['hpcloud.swift.container'] . 'testDelete';

    $this->assertNotEmpty($testCollection);

    $store = $this->auth();
    $ret = $store->createContainer($testCollection);
    $this->assertTrue($store->hasContainer($testCollection));

    $ret = $store->deleteContainer($testCollection);

    $this->assertTrue($ret);
  }

}
