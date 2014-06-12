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

use OpenStack\ObjectStore\v1\Resource\StreamWrapperFS;

/**
 * @group streamWrapper
 */
class StreamWrapperFSTest extends StreamWrapperTestCase
{
    const SCHEME = StreamWrapperFS::DEFAULT_SCHEME;

    public function testStreamContext()
    {
        $context = stream_context_get_options($this->context)['swiftfs'];
        $this->assertNotEmpty($context['token']);
        $this->assertNotEmpty($context['swift_endpoint']);
        $this->assertEquals(self::FTYPE, $context['content_type']);
    }

    public function testRegister()
    {
        $this->assertNotEmpty(StreamWrapperFS::DEFAULT_SCHEME);
        $this->assertContains(StreamWrapperFS::DEFAULT_SCHEME, stream_get_wrappers());
    }

    public function testOpenFailureWithoutContext()
    {
        $url = $this->createNewUrl('non_existent_container/foo.txt');
        $this->assertFalse(@fopen($url, 'r'));
    }

    public function testResourceType()
    {
        $this->assertInternalType('resource', $this->resource);
    }

    public function testCreatingResourceInWriteMode()
    {
        $resource = $this->createNewResource($this->createNewUrl(), 'w+');
        $this->assertInternalType('resource', $resource);
        fclose($resource);
    }

    public function testCreatingResourceInCreateMode()
    {
        $resource = $this->createNewResource($this->createNewUrl(), 'c+');
        $this->assertInternalType('resource', $resource);
        fclose($resource);
    }

    public function testTell()
    {
        // Sould be at the beginning of the buffer.
        $this->assertEquals(0, ftell($this->resource));
    }

    public function testWrite()
    {
        $string = 'To be is to be the value of a bound variable. -- Quine';
        fwrite($this->resource, $string);
        $this->assertGreaterThan(0, ftell($this->resource));
    }

    public function testStat()
    {
        $this->assertEquals(0, fstat($this->resource)['size']);

        fwrite($this->resource, 'foo');
        fflush($this->resource);
        $this->assertGreaterThan(0, fstat($this->resource)['size']);
    }

    public function testSeek()
    {
        $text = 'Foo bar';
        fwrite($this->resource, $text);

        fseek($this->resource, 0, SEEK_END);
        $pointer = ftell($this->resource);

        $this->assertGreaterThan(0, $pointer);
    }

    public function testEof()
    {
        $this->assertFalse(feof($this->resource));

        fwrite($this->resource, 'foo');
        rewind($this->resource);
        stream_get_contents($this->resource);

        $this->assertTrue(feof($this->resource));
    }

    public function testFlush()
    {
        $content = str_repeat('foo', 50);

        fwrite($this->resource, $content);
        fflush($this->resource);
        rewind($this->resource);

        $this->assertEquals($content, stream_get_contents($this->resource));
    }

    public function testStreamGetMetadata()
    {
        $object = stream_get_meta_data($this->resource)['wrapper_data']->object();
        $this->assertInstanceOf('OpenStack\ObjectStore\v1\Resource\Object', $object);
        $this->assertEquals(self::FTYPE, $object->contentType());
    }

    public function testClose()
    {
        fclose($this->resource);
        $this->assertFalse(is_resource($this->resource));
    }

    public function testCast()
    {
        $read   = [$this->resource];
        $write  = [];
        $except = [];
        $this->assertGreaterThan(0, stream_select($read, $write, $except, 0));
    }

    public function testUrlStat()
    {
        $stat = stat($this->url);

        // Check that the array looks right.
        $this->assertCount(26, $stat);
        $this->assertEquals(0, $stat[3]);
        $this->assertEquals($stat[2], $stat['mode']);
    }

    public function testFileExists()
    {
        $this->assertTrue(file_exists($this->url));
    }

    public function testFileIsReadable()
    {
        $this->assertTrue(is_readable($this->url));
    }

    public function testFileIsWritable()
    {
        $this->assertTrue(is_writeable($this->url));
    }

    public function testFileModifyTime()
    {
        $this->assertGreaterThan(0, filemtime($this->url));
    }

    public function testFileSize()
    {
        $url = $this->createNewUrl('file_size_test');

        $resource = $this->createNewResource($url, 'w+');
        fwrite($resource, '!');
        fclose($resource);

        $this->assertEquals(1, filesize($url));
        unlink($url);
    }

    public function testPermissions()
    {
        $perm = fileperms($this->url);

        // Assert that this is a file. Objects are *always* marked as files.
        $this->assertEquals(0x8000, $perm & 0x8000);

        // Assert writeable by owner.
        $this->assertEquals(0x0080, $perm & 0x0080);

        // Assert not world writable.
        $this->assertEquals(0, $perm & 0x0002);
    }

    public function testFileGetContents()
    {
        $url = $this->createNewUrl('get_contents');
        $resource = $this->createNewResource($url, 'w+');

        fwrite($resource, '!');
        fclose($resource);

        $contents = file_get_contents($url, null, $this->context);
        $this->assertEquals('!', $contents);
        unlink($url);
    }

    public function testCopy()
    {
        $newUrl = '/tmp/new_file_from_swift.txt';
        copy($this->url, $newUrl, $this->context);

        $this->assertTrue(file_exists($newUrl));
        unlink($newUrl);
    }

    public function testUnlink()
    {
        unlink($this->url, $this->context);
        $this->assertFalse(file_exists($this->url));
    }

    public function testSetOption()
    {
        $this->assertTrue(stream_set_blocking($this->resource, 1));

        // Returns 0 on success.
        $this->assertEquals(0, stream_set_write_buffer($this->resource, 8192));

        // Cannot set a timeout on a tmp storage:
        $this->assertFalse(stream_set_timeout($this->resource, 10));
    }

    public function testRename()
    {
        $oldUrl = $this->createNewUrl('old');
        $newUrl = $this->createNewUrl('new');

        $original = $this->createNewResource($oldUrl, 'w+');
        fwrite($original, 'fooooo');
        fclose($original);

        rename($oldUrl, $newUrl, $this->context);

        $this->assertTrue(file_exists($newUrl));
        $this->assertFalse(file_exists($this->url));

        unlink($newUrl, $this->context);
    }

    public function testOpenDir()
    {
        $baseDirectory = opendir($this->createNewUrl(''), $this->context);
        $this->assertInternalType('resource', $baseDirectory);
        closedir($baseDirectory);
    }

    public function testReadDir()
    {
        $paths = ['test1.txt', 'foo/test2.txt', 'foo/test3.txt', 'bar/test4.txt'];

        foreach ($paths as $path) {
            $file = fopen($this->createNewUrl($path), 'c+', false, $this->context);
            fwrite($file, 'Test.');
            fclose($file);
        }

        $baseDirectory = opendir($this->createNewUrl(''), $this->context);

        $expectedPaths = ['bar/', 'foo/', 'test1.txt'];
        while (false !== ($currentEntry = readdir($baseDirectory))) {
            $nextPath = array_shift($expectedPaths);
            $this->assertEquals($nextPath, $currentEntry);
        }

        $this->assertFalse(readdir($baseDirectory));

        closedir($baseDirectory);
    }

    public function testRewindDir()
    {
        $baseDirectory = opendir($this->createNewUrl(''), $this->context);
        rewinddir($baseDirectory);

        $this->assertEquals('bar/', readdir($baseDirectory));

        closedir($baseDirectory);
    }

    public function testCloseDir()
    {
        $baseDirectory = opendir($this->createNewUrl(''), $this->context);
        closedir($baseDirectory);
        $this->assertFalse(is_resource($baseDirectory));
    }

    public function testOpenSubdir()
    {
        // Opening foo we should find test2.txt and test3.txt.
        $url = $this->createNewUrl('foo/');
        $dir = opendir($url, $this->context);

        $this->assertEquals('test2.txt', readdir($dir));
        $this->assertEquals('test3.txt', readdir($dir));

        $array = scandir($url, -1, $this->context);
        $this->assertEquals(2, count($array));
        $this->assertEquals('test3.txt', $array[0]);
    }

    public function testIsDir()
    {
        // Object names are pathy. If objects exist starting with this path we can
        // consider the directory to exist.
        $url = $this->createNewUrl('baz/');
        $this->assertFalse(is_dir($url));

        $url = $this->createNewUrl('foo/');
        $this->assertTrue(is_dir($url));
    }

    public function testMkdir()
    {
        // Object names are pathy. If no object names start with the a path we can
        // consider mkdir passed. If object names exist we should fail mkdir.
        $url = $this->createNewUrl('baz/');
        $this->assertTrue(mkdir($url, 0700, true, $this->context));

        // Test the case for an existing directory.
        $url = $this->createNewUrl('foo/');
        $this->assertFalse(mkdir($url, 0700, true, $this->context));
    }

    public function testRmdir()
    {
        // Object names are pathy. If no object names start with the a path we can
        // consider rmdir passed. If object names exist we should fail rmdir.
        $url = $this->createNewUrl('baz/');
        $this->assertTrue(rmdir($url, $this->context));

        // Test the case for an existing directory.
        $url = $this->createNewUrl('foo/');
        $this->assertFalse(rmdir($url, $this->context));
    }
}