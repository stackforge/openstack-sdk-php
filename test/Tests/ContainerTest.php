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

    try {
      $container = $store->container($cname);
    }
    // The container was never created.
    catch (\HPCloud\Transport\FileNotFoundException $e) {
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

  const FNAME = 'testSave';
  const FCONTENT = 'This is a test.';
  const FTYPE = 'text/plain';

  public function testSave() {

    // Clean up anything left.
    $this->destroyContainerFixture();

    $container = $this->containerFixture();

    $obj = new Object(self::FNAME, self::FCONTENT, self::FTYPE);
    $obj->setMetadata(array('foo' => '1234'));

    $this->assertEquals(self::FCONTENT, $obj->content());

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
  public function testRemoteObject() {
    $container = $this->containerFixture();
    $object = $container->remoteObject(self::FNAME);

    $this->assertEquals(self::FNAME, $object->name());
    $this->assertEquals(self::FTYPE, $object->contentType());

    $etag = md5(self::FCONTENT);
    $this->assertEquals($etag, $object->eTag());

    $md = $object->metadata();
    $this->assertEquals(1, count($md));

    // Note that headers are normalized remotely to have initial
    // caps. Since we have no way of knowing what the original
    // metadata casing is, we leave it with initial caps.
    $this->assertEquals('1234', $md['Foo']);

    $content = $object->content();

    $this->assertEquals(self::FCONTENT, $content);

    // Overwrite the copy:
    $object->setContent('HI');
    $this->assertEquals('HI', $object->content());
  }

  /**
   * @depends testSave
   */
  public function testObjects() {
    $container = $this->containerFixture();
    $obj1 = new Object('a/' . self::FNAME, self::FCONTENT, self::FTYPE);
    $obj2 = new Object('a/b/' . self::FNAME, self::FCONTENT, self::FTYPE);

    $container->save($obj1);
    $container->save($obj2);

    // Now we have a container with three items.
    $objects = $container->objects();

    $this->assertEquals(3, count($objects));

    $objects = $container->objects(1, 'a/' . self::FNAME);

    $this->assertEquals(1, count($objects));
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
   * @depends testObjects
   */
  public function testObjectsWithPrefix() {
    $container = $this->containerFixture();

    $objects = $container->objectsWithPrefix('a/');
    $this->assertEquals(2, count($objects));

    foreach ($objects as $o) {
      if ($o instanceof Object) {
        $this->assertEquals('a/' . self::FNAME, $o->name());
      }
      else {
        $this->assertEquals('a/b/', $o->path());
      }

    }

    // Since we set the delimiter to ':' we will get back
    // all of the objects in a/. This is because none of
    // the objects contain ':' in their names.
    $objects = $container->objectsWithPrefix('a/', ':');
    $this->assertEquals(2, count($objects));

    foreach ($objects as $o) {
      $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage\Object', $o);
    }

    // This should give us one file and one subdir.
    $objects = $container->objectsWithPrefix('', '/');
    $this->assertEquals(2, count($objects));

    foreach ($objects as $o) {
      if ($o instanceof Object) {
        $this->assertEquals(self::FNAME, $o->name());
      }
      else {
        $this->assertEquals('a/', $o->path());
      }
    }
  }

  /**
   * @depends testObjects
   */
  public function testObjectsWithPath() {
    $container = $this->containerFixture();
    $objects = $container->objectsByPath('a/b/');

    $this->assertEquals(1, count($objects));

    $o = array_shift($objects);
    $this->assertEquals('a/b/' . self::FNAME, $o->name());

    /*
     * The Open Stack documentation is unclear about how best to
     * use paths. Experimentation suggests that if you rely on paths
     * instead of prefixes, your best bet is to create directory
     * markers.
     */

    // Test subdir listings:
    // This does not work (by design?) with Path. You have to use prefix
    // or else create directory markers.
    // $obj1 = new Object('a/aa/aaa/' . self::FNAME, self::FCONTENT, self::FTYPE);
    // $container->save($obj1);
    // $objects = $container->objectsByPath('a/aaa', '/');

    // $this->assertEquals(1, count($objects), 'One subdir');

    // $objects = $container->objectsByPath('a/');
    // throw new \Exception(print_r($objects, TRUE));
    // $this->assertEquals(2, count($objects));

    // foreach ($objects as $o) {
    //   if ($o instanceof Object) {
    //     $this->assertEquals('a/' . self::FNAME, $o->name());
    //   }
    //   else {
    //     $this->assertEquals('a/b/', $o->path());
    //   }
    // }
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
