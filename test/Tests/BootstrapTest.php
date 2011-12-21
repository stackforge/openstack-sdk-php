<?php
/**
 * @file
 *
 * Unit tests for the Bootstrap.
 */
namespace HPCloud\Tests\Units;

require_once  'mageekguy.atoum.phar';
require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \mageekguy\atoum;

class Bootstrap extends \HPCloud\TestCase {

  /**
   * Canary test.
   */
  public function testSettings() {
    $this->assert->array($this->settings)->isNotEmpty();
  }

  /**
   * Test the BaseDir.
   */
  public function testBasedir() {
    $basedir = \HPCloud\Bootstrap::$basedir;

    $this->assert->string($basedir)->match('/HPCloud/');
  }

  /**
   * Test the autoloader.
   */
  public function testAutoloader() {
    \HPCloud\Bootstrap::useAutoloader();

    // If we can construct a class, we are okay.
    $test = new \HPCloud\Exception("TEST");

    $this->assert->object($test)->isInstanceOf('\HPCloud\Exception');
  }
}
