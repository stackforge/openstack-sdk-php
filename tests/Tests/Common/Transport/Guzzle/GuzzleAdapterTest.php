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

namespace OpenStack\Tests\Common\Transport\Guzzle;

use OpenStack\Common\Transport\Guzzle\GuzzleAdapter;
use OpenStack\Tests\TestCase;

class GuzzleClientTest extends TestCase
{
    const TEST_URL = 'http://openstack.org';

    private $mockClient;
    private $adapter;

    public function setUp()
    {
        $this->mockClient = $this->getMockBuilder('GuzzleHttp\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $this->adapter = new GuzzleAdapter($this->mockClient);
    }

    public function testFactoryReturnsInstance()
    {
        $this->assertInstanceOf(
            'OpenStack\Common\Transport\Guzzle\GuzzleAdapter',
            $this->adapter
        );
    }

    public function testFactoryMethod()
    {
        $this->assertInstanceOf(
            'OpenStack\Common\Transport\Guzzle\GuzzleAdapter',
            GuzzleAdapter::create()
        );
    }

    public function testCreateRequestCallsClientAndReturnsAdapter()
    {
        $this->mockClient
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET')
            ->will($this->returnValue(
                $this->getMock('GuzzleHttp\Message\RequestInterface')
            ));

        $adapter = (new GuzzleAdapter($this->mockClient))->createRequest('GET');
        $this->assertInstanceOf('OpenStack\Common\Transport\Guzzle\RequestAdapter', $adapter);
        $this->assertInstanceOf('GuzzleHttp\Message\RequestInterface', $adapter->getMessage());
    }

    public function testSetOptionCallsClient()
    {
        $key = 'foo';
        $value = 'bar';
        $this->mockClient->expects($this->once())->method('setDefaultOption')->with($key, $value);

        (new GuzzleAdapter($this->mockClient))->setOption($key, $value);
    }

    public function testGetBaseUrlWithOption()
    {
        $this->mockClient->expects($this->once())->method('getBaseUrl');
        (new GuzzleAdapter($this->mockClient))->getOption('base_url');
    }

    public function testGetOption()
    {
        $this->mockClient->expects($this->once())->method('getDefaultOption')->with('foo');
        (new GuzzleAdapter($this->mockClient))->getOption('foo');
    }
}