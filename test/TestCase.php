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

  public static $ostore = NULL;


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
    \HPCloud\Bootstrap::setConfiguration(self::$settings);

    //parent::__construct($score, $locale, $adapter);
  }

  protected $containerFixture = NULL;

  /**
   * Authenticate to a Swift account.
   */
  protected function swiftAuth() {

    if (empty(self::$ostore)) {
      $user = self::$settings['hpcloud.swift.account'];
      $key = self::$settings['hpcloud.swift.key'];
      $url = self::$settings['hpcloud.swift.url'];

      self::$ostore = \HPCloud\Storage\ObjectStorage::newFromSwiftAuth($user, $key, $url);

    }

    return self::$ostore;
  }

  /**
   * Get a container from the server.
   */
  protected function containerFixture() {

    if (empty($this->containerFixture)) {
      $store = $this->swiftAuth();
      $cname = self::$settings['hpcloud.swift.container'];

      try {
        $store->createContainer($cname);
        $this->containerFixture = $store->container($cname);

      }
      // This is why PHP needs 'finally'.
      catch (\Exception $e) {
        // Delete the container.
        $store->deleteContainer($cname);
        throw $e;
      }

    }

    return $this->containerFixture;
  }

  /**
   * Clear and destroy a container.
   *
   * Destroy all of the files in a container, then destroy the 
   * container.
   *
   * If the container doesn't exist, this will silently return.
   *
   * @param string $cname
   *   The name of the container.
   */
  protected function eradicateContainer($cname) {
    $store = $this->swiftAuth();
    try {
      $container = $store->container($cname);
    }
    // The container was never created.
    catch (\HPCloud\Transport\FileNotFoundException $e) {
      return;
    }

    foreach ($container as $object) {
      try {
        $container->delete($object->name());
      }
      catch (\Exception $e) {}
    }

    $store->deleteContainer($cname);

  }

  /**
   * Destroy a container fixture.
   *
   * This should be called in any method that uses containerFixture().
   */
  protected function destroyContainerFixture() {
    $store = $this->swiftAuth();
    $cname = self::$settings['hpcloud.swift.container'];

    try {
      $container = $store->container($cname);
    }
    // The container was never created.
    catch (\HPCloud\Transport\FileNotFoundException $e) {
      return;
    }

    foreach ($container as $object) {
      try {
        $container->delete($object->name());
      }
      catch (\Exception $e) {
        syslog(LOG_WARNING, $e);
      }
    }

    $store->deleteContainer($cname);
  }
}
