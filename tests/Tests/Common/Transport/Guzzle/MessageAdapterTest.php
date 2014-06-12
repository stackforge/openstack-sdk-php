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

namespace OpenStack\Tests\Common\Transport\Guzzle;

use OpenStack\Common\Transport\Guzzle\MessageAdapter;
use OpenStack\Tests\TestCase;

class MessageAdapterTest extends TestCase
{
    const REQUEST_CLASS = 'GuzzleHttp\Message\Request';
    const RESPONSE_CLASS = 'GuzzleHttp\Message\Response';

    private $mock;
    private $adapter;

    private function getStub($class)
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $this->mock = $this->getStub(self::REQUEST_CLASS);
        $this->adapter = new MessageAdapter($this->mock);
    }

    public function testConstructorSetsMessage()
    {
        $this->assertInstanceOf(self::REQUEST_CLASS, $this->adapter->getMessage());
    }

    public function testSettingMessage()
    {
        $this->adapter->setMessage($this->getStub(self::RESPONSE_CLASS));
        $this->assertInstanceOf(self::RESPONSE_CLASS, $this->adapter->getMessage());
    }

    public function testGetProtocol()
    {
        $this->mock->expects($this->once())->method('getProtocolVersion');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getProtocolVersion();
    }

    public function testSetBody()
    {
        $body = $this->getMock('GuzzleHttp\Stream\StreamInterface');
        $this->mock->expects($this->once())->method('setBody')->with($body);
        $this->adapter->setMessage($this->mock);
        $this->adapter->setBody($body);
    }

    public function testGetBody()
    {
        $this->mock->expects($this->once())->method('getBody');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getBody();
    }

    public function testGetHeaders()
    {
        $this->mock->expects($this->once())->method('getHeaders');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getHeaders();
    }

    public function testHasHeader()
    {
        $this->mock->expects($this->once())->method('hasHeader')->with('foo');
        $this->adapter->setMessage($this->mock);
        $this->adapter->hasHeader('foo');
    }

    public function testSetHeader()
    {
        $header = 'foo';
        $value  = 'bar';
        $this->mock->expects($this->once())->method('setHeader')->with($header, $value);
        $this->adapter->setMessage($this->mock);
        $this->adapter->setHeader($header, $value);
    }

    public function testGetHeader()
    {
        $this->mock->expects($this->once())->method('getHeader')->with('foo');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getHeader('foo');
    }

    public function testSetHeaders()
    {
        $headers = ['foo' => 'bar'];
        $this->mock->expects($this->once())->method('setHeaders')->with($headers);
        $this->adapter->setMessage($this->mock);
        $this->adapter->setHeaders($headers);
    }

    public function testAddHeader()
    {
        $header = 'foo';
        $value  = 'bar';
        $this->mock->expects($this->once())->method('addHeader')->with($header, $value);
        $this->adapter->setMessage($this->mock);
        $this->adapter->addHeader($header, $value);
    }

    public function testAddHeaders()
    {
        $headers = ['foo' => 'bar'];
        $this->mock->expects($this->once())->method('addHeaders')->with($headers);
        $this->adapter->setMessage($this->mock);
        $this->adapter->addHeaders($headers);
    }

    public function testRemoveHeader()
    {
        $this->mock->expects($this->once())->method('removeHeader')->with('foo');
        $this->adapter->setMessage($this->mock);
        $this->adapter->removeHeader('foo');
    }
}