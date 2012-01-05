<?php
/**
 * @file
 *
 * Unit tests for Containers.
 */
namespace HPCloud\Tests\Storage\ObjectStorage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\ObjectStorage\Container;
use \HPCloud\Storage\ObjectStorage\Object;

class ContainerTest extends \HPCloud\Tests\TestCase {

  const FILENAME = 'unit-test-dummy.txt';
  const FILESTR = 'This is a test.';

  // The factory functions (newFrom*) are tested in the
  // ObjectStorage tests, as they are required there.
  // Rather than build a Mock to achieve the same test here,
  // we just don't test them again.

  public function testConstructor() {
    $container = new Container('foo');
    $this->assertEquals('foo', $container->name());
    $this->assertEquals(0, $container->bytes());
    $this->assertEquals(0, $container->count());
  }

  public function testCountable() {
    // Verify that the interface Countable is properly
    // implemented.

    $mockJSON = array('count' => 5, 'bytes' => 128, 'name' => 'foo');

    $container = Container::newFromJSON($mockJSON, 'fake', 'fake');

    $this->assertEquals(5, count($container));

  }

  protected $containerFixture = NULL;

  /**
   * Get a container from the server.
   */
  protected function containerFixture() {

    if (empty($this->containerFixture)) {
      $store = $this->swiftAuth();
      $cname = self::$settings['hpcloud.swift.container'];

      try {
        $store->createContainer($cname);
        $this->containerFixture = $store->container($cname);

      }
      // This is why PHP needs 'finally'.
      catch (\Exception $e) {
        // Delete the container.
        $store->deleteContainer($cname);
        throw $e;
      }

    }

    return $this->containerFixture;
  }

  /**
   * Destroy a container fixture.
   *
   * This should be called in any method that uses containerFixture().
   */
  protected function destroyContainerFixture() {
    $store = $this->swiftAuth();
    $cname = self::$settings['hpcloud.swift.container'];

    $container = $store->container($cname);

    foreach ($container as $object) {
      try {
        $container->delete($object->name());
      }
      catch (\Exception $e) {}
    }

    $store->deleteContainer($cname);
  }

  const FNAME = 'testSave';
  const FCONTENT = 'This is a test.';
  const FTYPE = 'text/plain';

  public function testSave() {

    // Clean up anything left.
    $this->destroyContainerFixture();

    $container = $this->containerFixture();

    $obj = new Object(self::FNAME, self::FCONTENT, self::FTYPE);

    try {
      $ret = $container->save($obj);
    }
    catch (\Exception $e) {
      $this->destroyContainerFixture();
      throw $e;
    }

    $this->assertTrue($ret);
  }

  /**
   * @depends testSave
   */
  public function testObjects() {
    $container = $this->containerFixture();
    $obj1 = new Object(self::FNAME . '-2', self::FCONTENT, self::FTYPE);
    $obj2 = new Object(self::FNAME . '-3', self::FCONTENT, self::FTYPE);

    $container->save($obj1);
    $container->save($obj2);


  }

  /**
   * @depends testObjects
   */
  public function testObjectsWithPrefix() {

  }

  /**
   * @depends testObjects
   */
  public function testObjectsWithPath() {

  }

  /**
   * @depends testObjects
   */
  public function testGetIterator() {

    $container = $this->containerFixture();

    $it = $container->getIterator();
    $this->assertInstanceOf('Traversable', $it);

    $i = 0;
    foreach ($container as $item) {
      ++$i;
    }
    $this->assertEquals(3, $i);

  }

  /**
   * @depends testSave
   */
  public function testDelete() {
    $container = $this->containerFixture();

    $ret = $container->delete(self::FNAME);

    $fail = $container->delete('no_such_file.txt');

    $this->destroyContainerFixture();
    $this->assertTrue($ret);
    $this->assertFalse($fail);

  }


}
