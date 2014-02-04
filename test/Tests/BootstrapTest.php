<?php
/* ============================================================================
(c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
============================================================================ */
/**
 * @file
 *
 * Unit tests for the Bootstrap.
 */
namespace OpenStack\Tests;

require_once 'src/OpenStack/Bootstrap.php';
require_once 'test/TestCase.php';

class BootstrapTest extends \OpenStack\Tests\TestCase {

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
    $basedir = \OpenStack\Bootstrap::$basedir;
    $this->assertRegExp('/OpenStack/', $basedir);
  }

  /**
   * Test the autoloader.
   */
  public function testAutoloader() {
    \OpenStack\Bootstrap::useAutoloader();

    // If we can construct a class, we are okay.
    $test = new \OpenStack\Exception("TEST");

    $this->assertInstanceOf('\OpenStack\Exception', $test);
  }
}
