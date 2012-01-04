<?php
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

  public function testConstructor() {
    $o = new Object('foo.txt');

    $this->assertEquals('foo.txt', $o->name());
  }

  public function testContentType() {
    $o = new Object('foo.txt');

    $this->assertEquals('application/octet-stream', $o->contentType());

    $o->setContentType('text/plain; charset=UTF-8');
    $this->assertEquals('text/plain; charset=UTF-8', $o->contentType());
  }
}
