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

use \OpenStack\ObjectStore\v1\Resource\RemoteObject;
use \OpenStack\ObjectStore\v1\Resource\Object;
use \OpenStack\ObjectStore\v1\Resource\Container;

class RemoteObjectTest extends \OpenStack\Tests\TestCase
{
    const FNAME = 'RemoteObjectTest';
    //const FTYPE = 'text/plain; charset=UTF-8';
    const FTYPE = 'application/octet-stream; charset=UTF-8';
    const FCONTENT = 'Rah rah ah ah ah. Roma roma ma. Gaga oh la la.';
    const FMETA_NAME = 'Foo';
    const FMETA_VALUE = 'Bar';
    const FDISPOSITION = 'attachment; roma.gaga';
    const FENCODING = 'gzip';
    const FCORS_NAME = 'Access-Control-Max-Age';
    const FCORS_VALUE = '2000';

    protected function createAnObject()
    {
        $container = $this->containerFixture();

        $object = new Object(self::FNAME, self::FCONTENT, self::FTYPE);
        $object->setMetadata([self::FMETA_NAME => self::FMETA_VALUE]);
        $object->setDisposition(self::FDISPOSITION);
        $object->setEncoding(self::FENCODING);
        $object->setAdditionalHeaders([
            'Access-Control-Allow-Origin' => 'http://example.com',
            'Access-control-allow-origin' => 'http://example.com',
        ]);

        // Need some headers that Swift actually stores and returns. This
        // one does not seem to be returned ever.
        //$object->setAdditionalHeaders(array(self::FCORS_NAME => self::FCORS_VALUE));

        $container->save($object);
    }

    public function testNewFromHeaders()
    {
        // This is tested via the container.

        $this->destroyContainerFixture();
        $container = $this->containerFixture();
        $this->createAnObject();

        $obj = $container->proxyObject(self::FNAME);

        $this->assertInstanceOf('\OpenStack\ObjectStore\v1\Resource\RemoteObject', $obj);

        return $obj;
    }
    /**
     * @depends testNewFromHeaders
     */
    public function testContentLength($obj)
    {
        $len = strlen(self::FCONTENT);

        $this->assertEquals($len, $obj->contentLength());

        return $obj;
    }

    /**
     * @depends testContentLength
     */
    public function testContentType($obj)
    {
        $this->assertEquals(self::FTYPE, $obj->contentType());

        return $obj;
    }

    /**
     * @depends testContentType
     */
    public function testEtag($obj)
    {
        $hash = md5(self::FCONTENT);

        $this->assertEquals($hash, $obj->eTag());

        return $obj;
    }

    /**
     * @depends testContentType
     */
    public function testLastModified($obj)
    {
        $date = $obj->lastModified();

        $this->assertTrue(is_int($date));
        $this->assertTrue($date > 0);
    }

    /**
     * @depends testNewFromHeaders
     */
    public function testMetadata($obj)
    {
        $md = $obj->metadata();

        $this->assertArrayHasKey(self::FMETA_NAME, $md);
        $this->assertEquals(self::FMETA_VALUE, $md[self::FMETA_NAME]);
    }

    /**
     * @depends testNewFromHeaders
     */
    public function testDisposition($obj)
    {
        $this->assertEquals(self::FDISPOSITION, $obj->disposition());
    }

    /**
     * @depends testNewFromHeaders
     */
    public function testEncoding($obj)
    {
        $this->assertEquals(self::FENCODING, $obj->encoding());
    }

    /**
     * @depends testNewFromHeaders
     */
    public function testHeaders($obj)
    {
        $headers = $obj->headers();
        $this->assertTrue(count($headers) > 1);

        //fwrite(STDOUT, print_r($headers, true));

        $this->assertNotEmpty($headers['Date']);

        $obj->removeHeaders(['Date']);

        $headers = $obj->headers();
        $this->assertFalse(isset($headers['Date']));

        // Swift doesn't return CORS headers even though it is supposed to.
        //$this->assertEquals(self::FCORS_VALUE, $headers[self::FCORS_NAME]);
    }

    /**
     * @depends testNewFromHeaders
     */
    public function testUrl($obj)
    {
        $url = $obj->url();

        $this->assertTrue(strpos($obj->url(), $obj->name())> 0);
    }
    /**
     * @depends testNewFromHeaders
     */
    public function testStream($obj)
    {
        $res = $obj->stream();

        $this->assertTrue(is_resource($res));

        $res_md = stream_get_meta_data($res);

        $content = fread($res, $obj->contentLength());

        fclose($res);

        $this->assertEquals(self::FCONTENT, $content);

        // Now repeat the tests, only with a local copy of the data.
        // This allows us to test the local tempfile buffering.

        $obj->setContent($content);

        $res2 = $obj->stream();
        $res_md = stream_get_meta_data($res2);

        $this->assertEquals('PHP', $res_md['wrapper_type']);

        $content = fread($res2, $obj->contentLength());

        fclose($res2);

        $this->assertEquals(self::FCONTENT, $content);

        // Finally, we redo the first part of the test to make sure that
        // refreshing gets us a new copy:

        $res3 = $obj->stream(true);
        $res_md = stream_get_meta_data($res3);
        $this->assertEquals('PHP', $res_md['wrapper_type']);
        fclose($res3);

        return $obj;
    }

    // To avoid test tainting from testStream(), we start over.
    public function testContent()
    {
        $container = $this->containerFixture();
        $obj = $container->object(self::FNAME);

        $content = $obj->content();
        $this->assertEquals(self::FCONTENT, $content);

        // Make sure proxyObject retrieves the same content.
        $obj = $container->proxyObject(self::FNAME);
        $content = $obj->content();
        $this->assertEquals(self::FCONTENT, $content);

    }

    /**
     * @depends testStream
     */
    public function testCaching()
    {
        $container = $this->containerFixture();
        $obj = $container->proxyObject(self::FNAME);

        $this->assertFalse($obj->isCaching());

        $content = $obj->content();

        $res1 = $obj->stream();
        $md = stream_get_meta_data($res1);
        $this->assertEquals('PHP', $md['wrapper_type']);

        fclose($res1);

        // Enable caching and retest.
        $obj->setCaching(true);
        $this->assertTrue($obj->isCaching());

        // This will cache the content.
        $content = $obj->content();

        $res2 = $obj->stream();
        $md = stream_get_meta_data($res2);

        // If this is using the PHP version, it built content from the
        // cached version.
        $this->assertEquals('PHP', $md['wrapper_type']);

        fclose($res2);
    }

    /**
     * @depends testNewFromHeaders
     */
    public function testContentVerification($obj)
    {
        $this->assertTrue($obj->isVerifyingContent());
        $obj->setContentVerification(false);
        $this->assertfalse($obj->isVerifyingContent());
        $obj->setContentVerification(true);
    }

    /**
     * @depends testCaching
     */
    public function testIsDirty()
    {
        $container = $this->containerFixture();
        $obj = $container->proxyObject(self::FNAME);

        // THere is no content. Assert false.
        $this->assertFalse($obj->isDirty());

        $obj->setCaching(true);
        $obj->content();

        // THere is content, but it is unchanged.
        $this->assertFalse($obj->isDirty());

        // Change content and retest.
        $obj->setContent('foo');

        $this->assertTrue($obj->isDirty());
    }

    /**
     * @depends testIsDirty
     */
    public function testRefresh()
    {
        $container = $this->containerFixture();
        $obj = $container->proxyObject(self::FNAME);

        $obj->setContent('foo');
        $this->assertTrue($obj->isDirty());

        $obj->refresh(false);
        $this->assertFalse($obj->isDirty());
        $this->assertEquals(self::FCONTENT, $obj->content());

        $obj->setContent('foo');
        $this->assertTrue($obj->isDirty());

        $obj->refresh(true);
        $this->assertFalse($obj->isDirty());
        $this->assertEquals(self::FCONTENT, $obj->content());

        $this->destroyContainerFixture();

    }

}
