<?php

/*
 * (c) Copyright 2014 Rackspace US, Inc.
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

namespace OpenStack\Tests\Common\Transport;

use OpenStack\Tests\TestCase;

class AbstractClientTest extends TestCase
{
    const URI = 'http://openstack.org';

    private $client;
    private $request;
    private $options = ['foo' => 'bar'];
    private $body = 'baz';

    public function setUp()
    {
        $this->request = $this->getMockBuilder('OpenStack\Common\Transport\RequestInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $this->client = $this->getMockForAbstractClass('OpenStack\Common\Transport\AbstractClient');

        $this->client->expects($this->once())
            ->method('send')
            ->with($this->request);
    }

    public function testGet()
    {
        $this->client->expects($this->once())
            ->method('createRequest')
            ->with('GET', self::URI, null, $this->options)
            ->will($this->returnValue($this->request));

        $this->client->get(self::URI, $this->options);
    }

    public function testHead()
    {
        $this->client->expects($this->once())
            ->method('createRequest')
            ->with('HEAD', self::URI, null, $this->options)
            ->will($this->returnValue($this->request));

        $this->client->head(self::URI, $this->options);
    }

    public function testPost()
    {
        $this->client->expects($this->once())
            ->method('createRequest')
            ->with('POST', self::URI, $this->body, $this->options)
            ->will($this->returnValue($this->request));

        $this->client->post(self::URI, $this->body, $this->options);
    }

    public function testPut()
    {
        $this->client->expects($this->once())
            ->method('createRequest')
            ->with('PUT', self::URI, $this->body, $this->options)
            ->will($this->returnValue($this->request));

        $this->client->put(self::URI, $this->body, $this->options);
    }

    public function testDelete()
    {
        $this->client->expects($this->once())
            ->method('createRequest')
            ->with('DELETE', self::URI, null, $this->options)
            ->will($this->returnValue($this->request));

        $this->client->delete(self::URI, $this->options);
    }
}