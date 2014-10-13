<?php

/*
 * (c) Copyright 2014 Rackspace US, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace OpenStack\Tests\Common\Transport;

use OpenStack\Common\Transport\Url;
use OpenStack\Tests\TestCase;

class UrlTest extends TestCase
{
    const URL_STRING = 'https://username:password@openstack.org:80/community/members#anchor';

    private $url;

    public function setUp()
    {
        $this->url = new Url(self::URL_STRING);
    }

    public function testIsConstructedWithProperties()
    {
        $this->assertEquals('https', $this->url->getScheme());
        $this->assertEquals('openstack.org', $this->url->getHost());
        $this->assertEquals('80', $this->url->getPort());
        $this->assertEquals('/community/members', $this->url->getPath());
        $this->assertEquals('username', $this->url->getUser());
        $this->assertEquals('password', $this->url->getPassword());
        $this->assertEquals('anchor', $this->url->getFragment());
    }

    public function testSettingStringUrlResultsInArrayBasedQuery()
    {
        $url = new Url('//foo.com?bar=a&baz=b');
        $this->assertEquals(['bar' => 'a', 'baz' => 'b'], $url->getQuery());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionIsThrownWhenPopulatingWithInvalidDataType()
    {
        $value = (object) ['path' => 'https', 'host' => 'openstack.org'];
        new Url($value);
    }

    public function testSettingQueryWithString()
    {
        $this->url->setQuery('foo=bar&baz=boo');
        $this->assertEquals(['foo' => 'bar', 'baz' => 'boo'], $this->url->getQuery());
    }

    public function testSettingQueryWithStringArray()
    {
        $this->url->setQuery('foo[]=bar&foo[]=baz');
        $this->assertEquals(['foo' => ['bar', 'baz']], $this->url->getQuery());
    }

    public function testSettingQueryWithArray()
    {
        $query = ['foo' => 'bar'];
        $this->url->setQuery($query);
        $this->assertEquals($query, $this->url->getQuery());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionIsThrownWhenSettingQueryWithInvalidDataType()
    {
        $this->url->setQuery(false);
    }

    public function testAddPath()
    {
        $this->url->addPath('foo');
        $this->assertEquals('/community/members/foo', $this->url->getPath());
    }

    public function testAddQuery()
    {
        $this->url->setQuery(['foo' => 'bar']);
        $this->url->addQuery(['baz' => 'boo']);
        $this->assertEquals(['foo' => 'bar', 'baz' => 'boo'], $this->url->getQuery());
    }

    public function testCastingToString()
    {
        $this->assertEquals(self::URL_STRING, (string) $this->url);
    }

    public function testCastingToStringForQueryArrays()
    {
        $url = new Url('http://openstack.org');
        $url->setQuery(['foo' => ['val1', 'val2'], 'bar' => 'val3']);

        $this->assertEquals('http://openstack.org?foo[]=val1&foo[]=val2&bar=val3', (string) $url);
    }
}