<?php

/*
 * (c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.
 * (c) Copyright 2014      Rackspace US, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace OpenStack\Tests\ObjectStore\v1\Resource;

use OpenStack\Bootstrap;
use \OpenStack\ObjectStore\v1\Resource\Container;
use \OpenStack\ObjectStore\v1\Resource\Object;
use \OpenStack\ObjectStore\v1\Resource\ACL;
use OpenStack\Tests\TestCase;

class ContainerTest extends TestCase
{
    const FILENAME = 'unit-test-dummy.txt';
    const FILESTR = 'This is a test.';
    const FNAME = 'testSave';
    const FCONTENT = 'This is a test.';
    const FTYPE = 'application/x-monkey-file';

    public function testConstructorSetsName()
    {
        $container = new Container('foo');
        $this->assertEquals('foo', $container->name());
    }

    /**
     * @expectedException \OpenStack\Common\Exception
     */
    public function testExceptionIsThrownWhenContainerNotFound()
    {
        $container = new Container('foo');
        $container->bytes();
    }

    public function testCountable()
    {
        // Verify that the interface Countable is properly implemented.

        $mockJSON = ['count' => 5, 'bytes' => 128, 'name' => 'foo'];
        $container = Container::newFromJSON($mockJSON, 'fake', 'fake');
        $this->assertCount(5, $container);
    }

    public function testSave()
    {
        // Clean up anything left.
        $this->destroyContainerFixture();

        $container = $this->containerFixture();

        $object = new Object(self::FNAME, self::FCONTENT, self::FTYPE);
        $object->setMetadata(['foo' => '1234']);

        $this->assertEquals(self::FCONTENT, $object->content());

        try {
            $ret = $container->save($object);
        } catch (\Exception $e) {
            $this->destroyContainerFixture();
            throw $e;
        }

        $this->assertTrue($ret);
    }

    /**
     * @depends testSave
     */
    public function testProxyObject()
    {
        $container = $this->containerFixture();
        $object = $container->proxyObject(self::FNAME);

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
     * @depends testProxyObject
     */
    public function testRefresh()
    {
        $container = $this->containerFixture();
        $object = $container->proxyObject(self::FNAME);

        $content = (string) $object->content();
        $object->setContent('FOO');
        $this->assertEquals('FOO', $object->content());

        $object->refresh(true);
        $this->assertEquals($content, (string) $object->content());

        $object->refresh(false);
        $this->assertEquals($content, (string) $object->content());

    }

    /**
     * @depends testProxyObject
     */
    public function testObject()
    {
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
        } catch (\OpenStack\Common\Exception $e) {
            $this->assertInstanceOf('OpenStack\Common\Transport\Exception\ResourceNotFoundException', $e);
        }
    }

    /**
     * @depends testSave
     */
    public function testObjects()
    {
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
    public function testGetIterator()
    {
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
    public function testObjectsWithPrefix()
    {
        $container = $this->containerFixture();

        $objects = $container->objectsWithPrefix('a/');
        $this->assertEquals(2, count($objects));

        foreach ($objects as $o) {
            if ($o instanceof Object) {
                $this->assertEquals('a/' . self::FNAME, $o->name());
            } else {
                $this->assertEquals('a/b/', $o->path());
            }

        }

        // Since we set the delimiter to ':' we will get back
        // all of the objects in a/. This is because none of
        // the objects contain ':' in their names.
        $objects = $container->objectsWithPrefix('a/', ':');
        $this->assertEquals(2, count($objects));

        foreach ($objects as $o) {
            $this->assertInstanceOf('\OpenStack\ObjectStore\v1\Resource\Object', $o);
        }

        // This should give us one file and one subdir.
        $objects = $container->objectsWithPrefix('', '/');
        $this->assertEquals(2, count($objects));

        foreach ($objects as $o) {
            if ($o instanceof Object) {
                $this->assertEquals(self::FNAME, $o->name());
            } else {
                $this->assertEquals('a/', $o->path());
            }
        }
    }

    /**
     * @depends testObjects
     */
    public function testObjectsWithPath()
    {
        $container = $this->containerFixture();
        $objects = $container->objectsByPath('a/b/');

        $this->assertEquals(1, count($objects));

        $o = array_shift($objects);
        $this->assertEquals('a/b/' . self::FNAME, $o->name());
    }

    /**
     * @depends testProxyObject
     */
    public function testUpdateMetadata()
    {
        $container = $this->containerFixture();
        $object = $container->proxyObject(self::FNAME);

        $md = $object->metadata();

        $this->assertEquals('1234', $md['Foo']);

        $md['Foo'] = 456;
        $md['Bar'] = 'bert';
        $object->setMetadata($md);

        $container->updateMetadata($object);

        $copy = $container->proxyObject(self::FNAME);

        $this->assertEquals('456', $md['Foo']);
        $this->assertEquals('bert', $md['Bar']);

        // Now we need to canary test:
        $this->assertEquals($object->contentType(), $copy->contentType());
        $this->assertEquals($object->contentLength(), $copy->contentLength());


    }

    /**
     * @depends testProxyObject
     */
    public function testCopy()
    {
        $container = $this->containerFixture();
        $object = $container->proxyObject(self::FNAME);

        $container->copy($object, 'FOO-1.txt');

        $copy = $container->proxyObject('FOO-1.txt');

        $this->assertEquals($object->contentType(), $copy->contentType());
        $this->assertEquals($object->etag(), $copy->etag());

        $container->delete('foo-1.txt');

    }

    /**
     * @depends testCopy
     */
    public function testCopyAcrossContainers()
    {
        // Create a new container.
        $store = $this->objectStore();
        $cname = self::$settings['openstack.swift.container'] . 'COPY';
        if ($store->hasContainer($cname)) {
            $this->eradicateContainer($cname);
        }

        $store->createContainer($cname);
        $newContainer = $store->container($cname);

        // Get teh old container and its object.
        $container = $this->containerFixture();
        $object = $container->proxyObject(self::FNAME);

        $ret = $container->copy($object, 'foo-1.txt', $cname);

        $this->assertTrue($ret);

        $copy = $newContainer->proxyObject('foo-1.txt');

        $this->assertEquals($object->etag(), $copy->etag());

        $this->eradicateContainer($cname);

    }

    /**
     * @depends testSave
     */
    public function testDelete()
    {
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
    public function testAcl()
    {
        $store = $this->objectStore();
        $cname = self::$settings['openstack.swift.container'] . 'PUBLIC';

        if ($store->hasContainer($cname)) {
            $store->deleteContainer($cname);
        }

        $store->createContainer($cname, ACL::makePublic());

        $store->containers();
        $container = $store->container($cname);

        $acl = $container->acl();

        $this->assertInstanceOf('\OpenStack\ObjectStore\v1\Resource\ACL', $acl);
        $this->assertTrue($acl->isPublic());

        $store->deleteContainer($cname);
    }
}
