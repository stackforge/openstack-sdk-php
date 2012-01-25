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

  /**
   * The IdentityServices instance.
   */
  public static $ident;


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

  /**
   * Get a configuration value.
   *
   * Optionally, specify a default value to be used
   * if none was found.
   */
  public static function conf($name, $default = NULL) {
    if (isset(self::$settings[$name])) {
      return self::$settings[$name];
    }
    return $default;
  }

  protected $containerFixture = NULL;

  /**
   * @deprecated
   */
  protected function swiftAuth() {

    $user = self::$settings['hpcloud.swift.account'];
    $key = self::$settings['hpcloud.swift.key'];
    $url = self::$settings['hpcloud.swift.url'];

    return \HPCloud\Storage\ObjectStorage::newFromSwiftAuth($user, $key, $url);

  }

  /**
   * Get a handle to an IdentityServices object.
   *
   * Authentication is performed, and the returned
   * service has its tenant ID set already.
   *
   * @code
   * <?php
   * // Get the current token.
   * $this->identity()->token();
   * ?>
   * @endcode
   */
  protected function identity($reset = FALSE) {

    if ($reset || empty(self::$ident)) {
      $user = self::conf('hpcloud.identity.username');
      $pass = self::conf('hpcloud.identity.password');
      $tenantId = self::conf('hpcloud.identity.tenantId');
      $url = self::conf('hpcloud.identity.url');

      $is = new \HPCloud\Services\IdentityServices($url);

      $token = $is->authenticateAsUser($user, $pass, $tenantId);

      self::$ident = $is;

    }
    return self::$ident;
  }

  protected function objectStore($reset = FALSE) {

    if ($reset || empty(self::$ostore)) {
      $ident = $this->identity($reset);

      $services = $ident->serviceCatalog(\HPCloud\Storage\ObjectStorage::SERVICE_TYPE);

      if (empty($services)) {
        throw new \Exception('No object-store service found.');
      }

      //$serviceURL = $services[0]['endpoints'][0]['adminURL'];
      $serviceURL = $services[0]['endpoints'][0]['publicURL'];

      $objStore = new \HPCloud\Storage\ObjectStorage($ident->token(), $serviceURL);

      self::$ostore = $objStore;

    }

    return self::$ostore;
  }

  /**
   * Get a container from the server.
   */
  protected function containerFixture() {

    if (empty($this->containerFixture)) {
      $store = $this->objectStore();
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
    $store = $this->objectStore();
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
    $store = $this->objectStore();
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
