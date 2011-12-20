<?php

namespace HPCloud\tests\units;

require_once  'mageekguy.atoum.phar';
require_once 'src/HPCloud/Bootstrap.php';

use \mageekguy\atoum;

class Bootstrap extends atoum\test {

  public function testBasedir() {
    $basedir = \HPCloud\Bootstrap::$basedir;

    $this->assert->string($basedir)->match('/HPCloud/');
  }

  public function testAutoloader() {
    \HPCloud\Bootstrap::useAutoloader();

    // If we can construct a class, we are okay.
    $test = new \HPCloud\Exception("TEST");
  }
}
