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
use OpenStack\ObjectStore\v1\Resource\StreamWrapper;
use OpenStack\Tests\TestCase;

abstract class StreamWrapperTestCase extends TestCase
{
    const FTYPE        = 'application/foo-bar; charset=iso-8859-13';
    const DEFAULT_MODE = 'nope';
    const FILE_PATH    = 'foo→/test.csv';
    const SCHEME       = StreamWrapper::DEFAULT_SCHEME;

    protected static $container;
    protected $resource;
    protected $context;
    protected $url;

    public static function setUpBeforeClass()
    {
        self::setConfiguration();

        $service = self::createObjectStoreService();
        $containerName = self::$settings['openstack.swift.container'];

        $service->createContainer($containerName);

        try {
            self::$container = $service->container($containerName);
        } catch (\Exception $e) {
            $service->deleteContainer($containerName);
            throw $e;
        }

        self::$settings += [
            'username'       => self::$settings['openstack.identity.username'],
            'password'       => self::$settings['openstack.identity.password'],
            'endpoint'       => self::$settings['openstack.identity.url'],
            'tenantid'       => self::$settings['openstack.identity.tenantId'],
            'token'          => $service->token(),
            'swift_endpoint' => $service->url(),
        ];
        Bootstrap::setConfiguration(self::$settings);
    }

    public static function tearDownAfterClass()
    {
        if (!self::$container) {
            return;
        }

        foreach (self::$container as $object) {
            try {
                self::$container->delete($object->name());
            } catch (\Exception $e) {}
        }

        $service = self::createObjectStoreService();
        $service->deleteContainer(self::$container->name());
    }

    public function setUp()
    {
        Bootstrap::useStreamWrappers();

        $this->url = $this->createNewUrl();
        $this->context  = $this->createStreamContext();
        $this->resource = $this->createNewResource($this->url);
    }

    public function tearDown()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }

        $this->resource = null;
        stream_wrapper_unregister(static::SCHEME);
    }

    protected function createNewResource($url, $mode = self::DEFAULT_MODE)
    {
        return fopen($url, $mode, false, $this->context);
    }

    protected function createNewUrl($objectName = self::FILE_PATH)
    {
        return sprintf("%s://%s/%s",
            static::SCHEME,
            urlencode(self::$settings['openstack.swift.container']),
            join('/', array_map('urlencode', explode('/', $objectName)))
        );
    }

    private function createStreamContext(array $params = [], $scheme = null)
    {
        if (!$scheme) {
            $scheme = static::SCHEME;
        }

        if (!($objectStore = $this->objectStore())) {
            throw new \Exception('Object storage service could not be created');
        }

        $params += [
            'token'            => $objectStore->token(),
            'swift_endpoint'   => $objectStore->url(),
            'content_type'     => self::FTYPE,
            'transport_client' => $this->getTransportClient(),
        ];

        return stream_context_create([
            $scheme => $params
        ]);
    }
} 