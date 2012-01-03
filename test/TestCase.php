<?php
/**
 * @file
 * Base test case.
 */


namespace HPCloud\Tests;

#require_once  'mageekguy.atoum.phar';
require_once 'PHPUnit/Autoload.php';
require_once 'src/HPCloud/Bootstrap.php';

//use \mageekguy\atoum;

class TestCase extends \PHPUnit_Framework_TestCase {

  public static $settings = array();

  //public function __construct(score $score = NULL, locale $locale = NULL, adapter $adapter = NULL) {
  public static function setUpBeforeClass() {


    //$this->setTestNamespace('Tests\Units');
    if (file_exists('test/settings.ini')) {
      self::$settings = parse_ini_file('test/settings.ini');
    }
    else {
      throw new Exception('Could not access test/settings.ini');
    }
    \HPCloud\Bootstrap::useAutoloader();

    //parent::__construct($score, $locale, $adapter);
  }
}
