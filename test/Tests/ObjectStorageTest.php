<?php
/**
 * @file
 *
 * Unit tests for ObjectStorage.
 */
namespace HPCloud\Storage\Tests\Units;

require_once 'mageekguy.atoum.phar';
require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \mageekguy\atoum;

class ObjectStorage extends \HPCloud\TestCase {

  protected function auth() {

    static $ostore = NULL;

    if (empty($ostore)) {
      $user = $this->settings['hpcloud.swift.account'];
      $key = $this->settings['hpcloud.swift.key'];
      $url = $this->settings['hpcloud.swift.url'];

      $ostore = \HPCloud\Storage\ObjectStorage::newFromSwiftAuth($user, $key, $url);
    }

    return $ostore;
  }

  /**
   * Canary test.
   */
  public function testSettings() {
    $this->assert->array($this->settings)->isNotEmpty();
  }

  /**
   * Test Swift-based authentication.
   * */
  public function testAuthentication() {

    $ostore = $this->auth();

    $this
      ->assert->object($ostore)->isInstanceOf('\HPCloud\Storage\ObjectStorage')
      ->assert->string($ostore->token())->isNotEmpty();
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

    $this->assert->array($containers)->isNotEmpty();

    $first = array_shift($containers);

    $this->assert->string($first->name())->isNotEmpty();
  }

  public function testCreateContainer() {
    $testCollection = $this->settings['hpcloud.swift.container'];

    $this->assert->boolean(empty($testCollection))->isFalse("CANARY FAILED");

    $store = $this->auth();

    $store->deleteContainer($testCollection);
    $ret = $store->createContainer($testCollection);

    $this->assert->boolean($ret)->isTrue("Create container");
  }

  public function testHasContainer() {
    $testCollection = $this->settings['hpcloud.swift.container'];
    $store = $this->auth();
    $store->createContainer($testCollection);

    $this->assert->boolean($store->hasContainer($testCollection))->isTrue("Verify that container exists");

  }
  public function testDeleteContainer() {
    $testCollection = $this->settings['hpcloud.swift.container'];

    $this->assert->boolean(empty($testCollection))->isFalse("CANARY FAILED");

    $store = $this->auth();
    $ret = $store->createContainer($testCollection);
    $this->assert->boolean($store->hasContainer($testCollection))->isTrue("Verify that container exists.");

    $ret = $store->deleteContainer($testCollection);

    $this->assert->boolean($ret)->isTrue();
  }

}
