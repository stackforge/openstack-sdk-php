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

use OpenStack\Common\Transport\Guzzle\ResponseAdapter;
use OpenStack\Tests\TestCase;

class ResponseAdapterTest extends TestCase
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
        $this->mock = $this->getStub('GuzzleHttp\Message\Response');
        $this->adapter = new ResponseAdapter($this->mock);
    }

    public function testGetStatusCode()
    {
        $this->mock->expects($this->once())->method('getStatusCode');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getStatusCode();
    }

    public function testGetReasonPhrase()
    {
        $this->mock->expects($this->once())->method('getReasonPhrase');
        $this->adapter->setMessage($this->mock);
        $this->adapter->getReasonPhrase();
    }
}