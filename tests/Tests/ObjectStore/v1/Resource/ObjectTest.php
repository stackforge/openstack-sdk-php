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

class ObjectTest extends \OpenStack\Tests\TestCase
{
    const FNAME = 'descartes.txt';
    const FCONTENT = 'Cogito ergo sum.';
    const FTYPE = 'text/plain; charset=ISO-8859-1';

    /**
     * Set up a basic object fixture.
     *
     * This provides an Object initialized with the main constants defined
     * for this class. Use this as a fixture to avoid repetition.
     *
     * @return Object An initialized object.
     */
    public function basicObjectFixture()
    {
        $o = new Object(self::FNAME);
        $o->setContent(self::FCONTENT, self::FTYPE);

        return $o;
    }

    public function testConstructor()
    {
        $o = $this->basicObjectFixture();

        $this->assertEquals(self::FNAME, $o->name());

        $o = new Object('a', 'b', 'text/plain');

        $this->assertEquals('a', $o->name());
        $this->assertEquals('b', $o->content());
        $this->assertEquals('text/plain', $o->contentType());
    }

    public function testContentType()
    {
        // Don't use the fixture, we want to test content
        // type in its raw state.
        $o = new Object('foo.txt');

        $this->assertEquals('application/octet-stream', $o->contentType());

        $o->setContentType('text/plain; charset=UTF-8');
        $this->assertEquals('text/plain; charset=UTF-8', $o->contentType());
    }

    public function testContent()
    {
        $o = $this->basicObjectFixture();

        $this->assertEquals(self::FCONTENT, $o->content());

        // Test binary data.
        $bin = sha1(self::FCONTENT, true);
        $o->setContent($bin, 'application/octet-stream');

        $this->assertEquals($bin, $o->content());
    }

    public function testEtag()
    {
        $o = $this->basicObjectFixture();
        $md5 = md5(self::FCONTENT);

        $this->assertEquals($md5, $o->eTag());
    }

    public function testIsChunked()
    {
        $o = $this->basicObjectFixture();
        $this->assertFalse($o->isChunked());
    }

    public function testContentLength()
    {
        $o = $this->basicObjectFixture();
        $this->assertEquals(strlen(self::FCONTENT), $o->contentLength());

        // Test on binary data.
        $bin = sha1(self::FCONTENT, true);

        $o->setContent($bin);
        $this->assertFalse($o->contentLength() == 0);
        $this->assertEquals(strlen($bin), $o->contentLength());
    }

    public function testMetadata()
    {
        $md = [
            'Immanuel' => 'Kant',
            'David' => 'Hume',
            'Gottfried' => 'Leibniz',
            'Jean-Jaques' => 'Rousseau',
        ];

        $o = $this->basicObjectFixture();
        $o->setMetadata($md);

        $got = $o->metadata();

        $this->assertEquals(4, count($got));
        $this->assertArrayHasKey('Immanuel', $got);
        $this->assertEquals('Leibniz', $got['Gottfried']);

    }

    public function testAdditionalHeaders()
    {
        $o = $this->basicObjectFixture();

        $extra = [
            'a' => 'b',
            'aaa' => 'bbb',
            'ccc' => 'bbb',
        ];
        $o->setAdditionalHeaders($extra);

        $got = $o->additionalHeaders();
        $this->assertEquals(3, count($got));

        $o->removeHeaders(['ccc']);

        $got = $o->additionalHeaders();
        $this->assertEquals(2, count($got));
    }
}
