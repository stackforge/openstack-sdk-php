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
 * Unit tests for Containers.
 */
namespace HPCloud\Tests\Storage\ObjectStorage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\ObjectStorage\Container;
use \HPCloud\Storage\ObjectStorage\Object;
use \HPCloud\Storage\ObjectStorage\ACL;

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

    // These will now cause the system to try to fetch a remote
    // container.
    //$this->assertEquals(0, $container->bytes());
    //$this->assertEquals(0, $container->count());
  }

  /**
   * @expectedException \HPCloud\Exception
   */
  public function testConstructorFailure() {
    $container = new Container('foo');
    $this->assertEquals('foo', $container->name());

    // These will now cause the system to try to fetch a remote
    // container. This is a failure condition.
    $this->assertEquals(0, $container->bytes());
  }

  public function testCountable() {
    // Verify that the interface Countable is properly
    // implemented.

    $mockJSON = array('count' => 5, 'bytes' => 128, 'name' => 'foo');

    $container = Container::newFromJSON($mockJSON, 'fake', 'fake');

    $this->assertEquals(5, count($container));

  }



  const FNAME = 'testSave';
  const FCONTENT = 'This is a test.';
  const FTYPE = 'application/x-monkey-file';

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

    // Make sure I can do this twice (regression).
    // Note that this SHOULD perform another request.
    $this->assertEquals(self::FCONTENT, $object->content());

    // Overwrite the copy:
    $object->setContent('HI');
    $this->assertEquals('HI', $object->content());

    // Make sure I can do this twice (regression check).
    $this->assertEquals('HI', $object->content());
  }


  /**
   * @depends testRemoteObject
   */
  public function testRefresh() {
    $container = $this->containerFixture();
    $object = $container->remoteObject(self::FNAME);

    $content = $object->content();
    $object->setContent('FOO');
    $this->assertEquals('FOO', $object->content());

    $object->refresh(TRUE);
    $this->assertEquals($content, $object->content());

    $object->refresh(FALSE);
    $this->assertEquals($content, $object->content());

  }

  /**
   * @depends testRemoteObject
   */
  public function testObject() {
    $container = $this->containerFixture();
    $object = $container->object(self::FNAME);

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

    // Make sure this throws a 404.
    try {
      $foo = $container->object('no/such');
    }
    catch (\HPCloud\Exception $e) {
      $this->assertInstanceOf('\HPCloud\Transport\FileNotFoundException', $e);
    }
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
   * @depends testRemoteObject
   */
  public function testUpdateMetadata() {
    $container = $this->containerFixture();
    $object = $container->remoteObject(self::FNAME);

    $md = $object->metadata();

    $this->assertEquals('1234', $md['Foo']);

    $md['Foo'] = 456;
    $md['Bar'] = 'bert';
    $object->setMetadata($md);

    $container->updateMetadata($object);

    $copy = $container->remoteObject(self::FNAME);

    $this->assertEquals('456', $md['Foo']);
    $this->assertEquals('bert', $md['Bar']);

    // Now we need to canary test:
    $this->assertEquals($object->contentType(), $copy->contentType());
    $this->assertEquals($object->contentLength(), $copy->contentLength());


  }

  /**
   * @depends testRemoteObject
   */
  public function testCopy() {
    $container = $this->containerFixture();
    $object = $container->remoteObject(self::FNAME);

    $container->copy($object, 'FOO-1.txt');

    $copy = $container->remoteObject('FOO-1.txt');

    $this->assertEquals($object->contentType(), $copy->contentType());
    $this->assertEquals($object->etag(), $copy->etag());

    $container->delete('foo-1.txt');

  }

  /**
   * @depends testCopy
   */
  public function testCopyAcrossContainers() {

    // Create a new container.
    $store = $this->objectStore();
    $cname = self::$settings['hpcloud.swift.container'] . 'COPY';
    if ($store->hasContainer($cname)) {
      $this->eradicateContainer($cname);
    }

    $store->createContainer($cname);
    $newContainer = $store->container($cname);

    // Get teh old container and its object.
    $container = $this->containerFixture();
    $object = $container->remoteObject(self::FNAME);

    $ret = $container->copy($object, 'foo-1.txt', $cname);

    $this->assertTrue($ret);

    $copy = $newContainer->remoteObject('foo-1.txt');

    $this->assertEquals($object->etag(), $copy->etag());

    $this->eradicateContainer($cname);

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

  /**
   * @group public
   */
  public function testAcl() {
    $store = $this->objectStore();
    $cname = self::$settings['hpcloud.swift.container'] . 'PUBLIC';

    if ($store->hasContainer($cname)) {
      $store->deleteContainer($cname);
    }

    $store->createContainer($cname, ACL::makePublic());


    $store->containers();
    $container = $store->container($cname);

    $acl = $container->acl();

    $this->assertInstanceOf('\HPCloud\Storage\ObjectStorage\ACL', $acl);
    $this->assertTrue($acl->isPublic());

    $store->deleteContainer($cname);

  }

}
