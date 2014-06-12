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

use \OpenStack\ObjectStore\v1\Resource\Object;
use \OpenStack\ObjectStore\v1\Resource\ACL;

class ObjectStorageTest extends \OpenStack\Tests\TestCase
{

    public function testSettings()
    {
        $this->assertTrue(!empty(self::$settings));
    }

    /**
     * @group auth
     */
    public function testConstructor()
    {
        $ident = $this->identity();

        $services = $ident->serviceCatalog(\OpenStack\ObjectStore\v1\ObjectStorage::SERVICE_TYPE);

        if (empty($services)) {
            throw new \Exception('No object-store service found.');
        }

        //$serviceURL = $services[0]['endpoints'][0]['adminURL'];
        $serviceURL = $services[0]['endpoints'][0]['publicURL'];

        $ostore = new \OpenStack\ObjectStore\v1\ObjectStorage($ident->token(), $serviceURL, $this->getTransportClient());

        $this->assertInstanceOf('\OpenStack\ObjectStore\v1\ObjectStorage', $ostore);
        $this->assertTrue(strlen($ostore->token()) > 0);

    }

    public function testNewFromServiceCatalog()
    {
        $ident = $this->identity();
        $tok = $ident->token();
        $cat = $ident->serviceCatalog();
        $region = self::$settings['openstack.swift.region'];
        $client = $this->getTransportClient();
        $ostore = \OpenStack\ObjectStore\v1\ObjectStorage::newFromServiceCatalog($cat, $tok, $region, $client);
        $this->assertInstanceOf('\OpenStack\ObjectStore\v1\ObjectStorage', $ostore);
        $this->assertTrue(strlen($ostore->token()) > 0);
    }

    public function testFailedNewFromServiceCatalog()
    {
        $ident = $this->identity();
        $tok = $ident->token();
        $cat = $ident->serviceCatalog();
        $client = $this->getTransportClient();
        $ostore = \OpenStack\ObjectStore\v1\ObjectStorage::newFromServiceCatalog($cat, $tok, 'region-w.geo-99999.fake');
        $this->assertEmpty($ostore);
    }

    public function testNewFromIdentity()
    {
        $ident = $this->identity();
        $region = self::$settings['openstack.swift.region'];
        $client = $this->getTransportClient();
        $ostore = \OpenStack\ObjectStore\v1\ObjectStorage::newFromIdentity($ident, $region, $client);
        $this->assertInstanceOf('\OpenStack\ObjectStore\v1\ObjectStorage', $ostore);
        $this->assertTrue(strlen($ostore->token()) > 0);
    }

    /**
     * @group auth
     * @group acl
     */
    public function testCreateContainer()
    {
        $testCollection = self::$settings['openstack.swift.container'];

        $this->assertNotEmpty($testCollection, "Canary: container name must be in settings file.");

        $store = $this->objectStore();

        $this->destroyContainerFixture();
        /*
        if ($store->hasContainer($testCollection)) {
            $store->deleteContainer($testCollection);
        }
         */

        $md = ['Foo' => 1234];

        $ret = $store->createContainer($testCollection, null, $md);
        $this->assertTrue($ret, "Create container");

    }

    /**
     * @group auth
     * @depends testCreateContainer
     */
    public function testAccountInfo()
    {
        $store = $this->objectStore();

        $info = $store->accountInfo();

        $this->assertGreaterThan(0, $info['containers']);
        $this->assertGreaterThanOrEqual(0, $info['bytes']);
        $this->assertGreaterThanOrEqual(0, $info['objects']);
    }

    /**
     * @depends testCreateContainer
     */
    public function testContainers()
    {
        $store = $this->objectStore();
        $containers = $store->containers();

        $this->assertNotEmpty($containers);

        //$first = array_shift($containers);

        $testCollection = self::conf('openstack.swift.container');
        $testContainer = $containers[$testCollection];
        $this->assertEquals($testCollection, $testContainer->name());
        $this->assertEquals(0, $testContainer->bytes());
        $this->assertEquals(0, $testContainer->count());

        // Make sure we get back an ACL:
        $this->assertInstanceOf('\OpenStack\ObjectStore\v1\Resource\ACL', $testContainer->acl());
    }

    /**
     * @depends testCreateContainer
     */
    public function testContainer()
    {
        $testCollection = self::$settings['openstack.swift.container'];
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
    public function testHasContainer()
    {
        $testCollection = self::$settings['openstack.swift.container'];
        $store = $this->objectStore();

        $this->assertTrue($store->hasContainer($testCollection));
        $this->assertFalse($store->hasContainer('nihil'));
    }

    /**
     * @depends testHasContainer
     */
    public function testDeleteContainer()
    {
        $testCollection = self::$settings['openstack.swift.container'];

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
     * @expectedException \OpenStack\ObjectStore\v1\Exception\ContainerNotEmptyException
     */
    public function testDeleteNonEmptyContainer()
    {
        $testCollection = self::$settings['openstack.swift.container'];

        $this->assertNotEmpty($testCollection);

        $store = $this->objectStore();
        $store->createContainer($testCollection);

        $container = $store->container($testCollection);
        $container->save(new Object('test', 'test', 'text/plain'));

        try {
            $ret = $store->deleteContainer($testCollection);
        } catch (\Exception $e) {
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
    public function testCreateContainerPublic()
    {
        $testCollection = self::$settings['openstack.swift.container'] . 'PUBLIC';
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

        $store->deleteContainer($testCollection);
    }

    /**
     * @depends testCreateContainerPublic
     */
    public function testChangeContainerACL()
    {
        $testCollection = self::$settings['openstack.swift.container'] . 'PUBLIC';
        $store = $this->objectStore();
        if ($store->hasContainer($testCollection)) {
            $store->deleteContainer($testCollection);
        }
        $ret = $store->createContainer($testCollection);

        $acl = ACL::makePublic();
        $ret = $store->changeContainerACL($testCollection, $acl);

        $this->assertFalse($ret);

        $container = $store->container($testCollection);
        $url = $container->url() . '?format=xml';

        $data = file_get_contents($url);
        $this->assertNotEmpty($data, $url);

        $store->deleteContainer($testCollection);
    }
}
