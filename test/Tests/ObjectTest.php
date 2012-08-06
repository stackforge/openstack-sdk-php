<?php
/* ============================================================================
(c) Copyright 2012 Hewlett-Packard Development Company, L.P.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights to
use, copy, modify, merge,publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE  LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
============================================================================ */
/**
 * @file
 *
 * Unit tests for ObjectStorage Object.
 */
namespace HPCloud\Tests\Storage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\ObjectStorage\Object;

class ObjectTest extends \HPCloud\Tests\TestCase {

  const FNAME = 'descartes.txt';
  const FCONTENT = 'Cogito ergo sum.';
  const FTYPE = 'text/plain; charset=ISO-8859-1';

  /**
   * Set up a basic object fixture.
   *
   * This provides an Object initialized with the main constants defined
   * for this class. Use this as a fixture to avoid repetition.
   *
   * @return Object
   *   An initialized object.
   */
  public function basicObjectFixture() {

    $o = new Object(self::FNAME);
    $o->setContent(self::FCONTENT, self::FTYPE);

    return $o;
  }

  public function testConstructor() {
    $o = $this->basicObjectFixture();

    $this->assertEquals(self::FNAME, $o->name());

    $o = new Object('a', 'b', 'text/plain');

    $this->assertEquals('a', $o->name());
    $this->assertEquals('b', $o->content());
    $this->assertEquals('text/plain', $o->contentType());
  }

  public function testContentType() {
    // Don't use the fixture, we want to test content
    // type in its raw state.
    $o = new Object('foo.txt');

    $this->assertEquals('application/octet-stream', $o->contentType());

    $o->setContentType('text/plain; charset=UTF-8');
    $this->assertEquals('text/plain; charset=UTF-8', $o->contentType());
  }

  public function testContent() {
    $o = $this->basicObjectFixture();

    $this->assertEquals(self::FCONTENT, $o->content());

    // Test binary data.
    $bin = sha1(self::FCONTENT, TRUE);
    $o->setContent($bin, 'application/octet-stream');

    $this->assertEquals($bin, $o->content());
  }

  public function testEtag() {
    $o = $this->basicObjectFixture();
    $md5 = md5(self::FCONTENT);

    $this->assertEquals($md5, $o->eTag());
  }

  public function testIsChunked() {
    $o = $this->basicObjectFixture();
    $this->assertFalse($o->isChunked());
  }

  public function testContentLength() {
    $o = $this->basicObjectFixture();
    $this->assertEquals(strlen(self::FCONTENT), $o->contentLength());

    // Test on binary data.
    $bin = sha1(self::FCONTENT, TRUE);

    $o->setContent($bin);
    $this->assertFalse($o->contentLength() == 0);
    $this->assertEquals(strlen($bin), $o->contentLength());
  }

  public function testMetadata() {
    $md = array(
      'Immanuel' => 'Kant',
      'David' => 'Hume',
      'Gottfried' => 'Leibniz',
      'Jean-Jaques' => 'Rousseau',
    );

    $o = $this->basicObjectFixture();
    $o->setMetadata($md);

    $got = $o->metadata();

    $this->assertEquals(4, count($got));
    $this->assertArrayHasKey('Immanuel', $got);
    $this->assertEquals('Leibniz', $got['Gottfried']);

  }

  public function testAdditionalHeaders() {
    $o = $this->basicObjectFixture();

    $extra = array(
      'a' => 'b',
      'aaa' => 'bbb',
      'ccc' => 'bbb',
    );
    $o->setAdditionalHeaders($extra);

    $got = $o->additionalHeaders();
    $this->assertEquals(3, count($got));

    $o->removeHeaders(array('ccc'));


    $got = $o->additionalHeaders();
    $this->assertEquals(2, count($got));
  }
}
