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
 * Unit tests for the Bootstrap.
 */
namespace HPCloud\Tests;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

class BootstrapTest extends \HPCloud\Tests\TestCase {

  /**
   * Canary test.
   */
  public function testSettings() {
    $this->assertTrue(!empty(self::$settings));
  }

  /**
   * Test the BaseDir.
   */
  public function testBasedir() {
    $basedir = \HPCloud\Bootstrap::$basedir;
    $this->assertRegExp('/HPCloud/', $basedir);
  }

  /**
   * Test the autoloader.
   */
  public function testAutoloader() {
    \HPCloud\Bootstrap::useAutoloader();

    // If we can construct a class, we are okay.
    $test = new \HPCloud\Exception("TEST");

    $this->assertInstanceOf('\HPCloud\Exception', $test);
  }
}
