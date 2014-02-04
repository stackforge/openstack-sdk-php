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
 * Base test case.
 */
/**
 * @defgroup Tests
 *
 * The OpenStack library is tested with PHPUnit tests.
 *
 * This group contains all of the unit testing classes.
 */


namespace OpenStack\Tests;

require_once 'PHPUnit/Autoload.php';
require_once 'src/OpenStack/Bootstrap.php';

/**
 * @ingroup Tests
 */
class TestCase extends \PHPUnit_Framework_TestCase {

  public static $settings = array();

  public static $ostore = NULL;

  /**
   * The IdentityService instance.
   */
  public static $ident;


  //public function __construct(score $score = NULL, locale $locale = NULL, adapter $adapter = NULL) {
  public static function setUpBeforeClass() {
    global $bootstrap_settings;

    if (!isset($bootstrap_settings)) {
      $bootstrap_settings = array();
    }
    self::$settings = $bootstrap_settings;


    //$this->setTestNamespace('Tests\Units');
    if (file_exists('test/settings.ini')) {
      self::$settings += parse_ini_file('test/settings.ini');
    }
    else {
      throw new \Exception('Could not access test/settings.ini');
    }


    \OpenStack\Bootstrap::useAutoloader();
    \OpenStack\Bootstrap::setConfiguration(self::$settings);

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

    $user = self::$settings['openstack.swift.account'];
    $key = self::$settings['openstack.swift.key'];
    $url = self::$settings['openstack.swift.url'];
    //$url = self::$settings['openstack.identity.url'];

    return \OpenStack\Storage\ObjectStorage::newFromSwiftAuth($user, $key, $url);

  }

  /**
   * Get a handle to an IdentityService object.
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
      $user = self::conf('openstack.identity.username');
      $pass = self::conf('openstack.identity.password');
      $tenantId = self::conf('openstack.identity.tenantId');
      $url = self::conf('openstack.identity.url');

      $is = new \OpenStack\Services\IdentityService($url);

      $token = $is->authenticateAsUser($user, $pass, $tenantId);

      self::$ident = $is;

    }
    return self::$ident;
  }

  protected function objectStore($reset = FALSE) {

    if ($reset || empty(self::$ostore)) {
      $ident = $this->identity($reset);

      $objStore = \OpenStack\Storage\ObjectStorage::newFromIdentity($ident);

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
      $cname = self::$settings['openstack.swift.container'];

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
    catch (\OpenStack\Transport\FileNotFoundException $e) {
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
    $cname = self::$settings['openstack.swift.container'];

    try {
      $container = $store->container($cname);
    }
    // The container was never created.
    catch (\OpenStack\Transport\FileNotFoundException $e) {
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
