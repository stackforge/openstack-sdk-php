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

use OpenStack\Common\Transport\Guzzle\RequestAdapter;
use OpenStack\Tests\TestCase;

class RequestAdapterTest extends TestCase
{
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
        $this->mock = $this->getStub('GuzzleHttp\Message\Request');
        $this->adapter = new RequestAdapter($this->mock);
    }

    public function testGetMethod()
    {
        $this->mock->expects($this->once())->method('getMethod');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getMethod();
    }

    public function testSetMethod()
    {
        $this->mock->expects($this->once())->method('setMethod')->with('foo');
        $this->adapter->setMessage($this->mock);
        $this->adapter->setMethod('foo');
    }

    public function testGetUrl()
    {
        $this->mock->expects($this->once())->method('getUrl');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getUrl();
    }

    public function testSetUrl()
    {
        $this->mock->expects($this->once())->method('setUrl')->with('foo');
        $this->adapter->setMessage($this->mock);
        $this->adapter->setUrl('foo');
    }
}