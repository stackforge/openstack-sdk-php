<?php
/**
 * @file
 *
 * Unit tests for the Bootstrap.
 */
namespace HPCloud\Tests;

//require_once  'mageekguy.atoum.phar';
require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

//use \mageekguy\atoum;

class Bootstrap extends \HPCloud\Tests\TestCase {

  /**
   * Canary test.
   */
  public function testSettings() {
    //$this->assert->array(self::$settings)->isNotEmpty();
    $this->assertTrue(!empty(self::$settings));
  }

  /**
   * Test the BaseDir.
   */
  public function testBasedir() {
    $basedir = \HPCloud\Bootstrap::$basedir;

    //$this->assert->string($basedir)->match('/HPCloud/');
    $this->assertRegExp('/HPCloud/', $basedir);
  }

  /**
   * Test the autoloader.
   */
  public function testAutoloader() {
    \HPCloud\Bootstrap::useAutoloader();

    // If we can construct a class, we are okay.
    $test = new \HPCloud\Exception("TEST");

    //$this->assert->object($test)->isInstanceOf('\HPCloud\Exception');
    $this->assertInstanceOf('\HPCloud\Exception', $test);
  }
}
