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
 * OpenStacl PHP-Client configuration.
 *
 * It also automatically register the OpenStack stream wrappers.
 */

namespace OpenStack;

use OpenStack\Services\IdentityService;
use OpenStack\Exception;

/**
 * Bootstrapping services.
 *
 * There is no requirement that this class be used. OpenStack is
 * built to be flexible, and any individual component can be
 * used directly, with one caveat: No explicit @c require or
 * @c include calls are made.
 *
 * This class provides the following services:
 *
 * - <em>Configuration:</em> "global" settings are set here.
 *   See the setConfiguration() method to see how they
 *   can be set, and the config() and hasConfig() methods to see
 *   how configuration might be checked.
 * - <em>Stream Wrappers:</em> This class can initialize a set of stream
 *   wrappers which will make certain OpenStack services available
 *   through the core PHP stream support.
 *
 * <b>Configuration</b>
 *
 * Configuration directives can be merged into the existing confiuration
 * using the setConfiguration method.
 *
 * @code
 * <?php
 * $config = array(
 *   // Use the faster and better CURL transport.
 *   'transport' => '\OpenStack\Transport\CURLTransport',
 *   // Set the HTTP max wait time to 500.
 *   'transport.timeout' => 500,
 * );
 * Bootstrap::setConfiguration($config);
 *
 * // Check and get params.
 * if (Bootstrap::hasConf('transport.timeout') {
 *   $to = Bootstrap::conf('transport.timeout');
 * }
 *
 * // Or get a param with a default value:
 * $val = Bootstrap::conf('someval', 'default value');
 *
 * // $val will be set to 'default value' because there
 * // is no 'someval' configuration param.
 *
 * ?>
 * @endcode
 *
 * <b>STREAM WRAPPERS</b>
 *
 * Stream wrappers allow you to use the built-in file manipulation
 * functions in PHP to interact with other services. Specifically,
 * the OpenStack stream wrappers allow you to use built-in file commands
 * to access Object Storage (Swift) and other OpenStack services using
 * commands like file_get_contents() and fopen().
 *
 * It's awesome. Trust me.
 *
 */
class Bootstrap {

  public static $config = array(
    // The transport implementation. By default, we use the PHP stream
    // wrapper's HTTP mechanism to process transactions.
    //'transport' => '\OpenStack\Transport\PHPStreamTransport',

    // This is the default transport while a bug persists in the 
    // Identity Services REST service.
    'transport' => '\OpenStack\Transport\CURLTransport',
  );

  /**
   * An identity services object created from the global settings.
   * @var object OpenStack::Services::IdentityService
   */
  public static $identity = NULL;

  /**
   * Register stream wrappers for OpenStack.
   *
   * This register the ObjectStorage stream wrappers, which allow you to access
   * ObjectStorage through standard file access mechanisms.
   *
   * @code
   * // Enable stream wrapper.
   * Bootstrap::useStreamWrappers();
   *
   * // Create a context resource.
   * $cxt = stream_context_create(array(
   *   'tenantid' => '12de21',
   *   'account' => '123454321',
   *   'secret' => 'f78saf7hhlll',
   *   'endpoint' => 'https://identity.hpcloud.com' // <-- not real URL!
   * ));
   *
   * // Get the contents of a Swift object.
   * $content = file_get_contents('swift://public/notes.txt', 'r', FALSE, $cxt);
   * @endcode
   */
  public static function useStreamWrappers() {
    $swift = stream_wrapper_register(
      \OpenStack\Storage\ObjectStorage\StreamWrapper::DEFAULT_SCHEME,
      '\OpenStack\Storage\ObjectStorage\StreamWrapper'
    );

    $swiftfs = stream_wrapper_register(
      \OpenStack\Storage\ObjectStorage\StreamWrapperFS::DEFAULT_SCHEME,
      '\OpenStack\Storage\ObjectStorage\StreamWrapperFS'
    );

    return ($swift && $swiftfs);
  }

  /**
   * Set configuration directives for OpenStack.
   *
   * This merges the provided associative array into the existing
   * configuration parameters (Bootstrap::$config).
   *
   * All of the OpenStack classes share the same configuration. This
   * ensures that a stable runtime environment is maintained.
   *
   * Common configuration directives:
   *
   * - 'transport': The namespaced classname for the transport that
   *   should be used. Example: @code \OpenStack\Transport\CURLTransport @endcode
   * - 'transport.debug': The integer 1 for enabling debug, 0 for
   *   disabling. Enabling will turn on verbose debugging output
   *   for any transport that supports it.
   * - 'transport.timeout': An integer value indicating how long
   *   the transport layer should wait for an HTTP request. A
   *   transport MAY ignore this parameter, but the ones included
   *   with the library honor it.
   * - 'transport.ssl.verify': Set this to FALSE to turn off SSL certificate
   *   verification. This is NOT recommended, but is sometimes necessary for
   *   certain proxy configurations.
   * - 'account' and 'secret'
   * - 'username' and 'password'
   * - 'tenantid'
   * - 'endpoint': The full URL to identity services. This is used by stream
   *   wrappers.
   *
   * The CURL wrapper supports proxy settings:
   *
   * - proxy: the proxy server URL (CURLOPT_PROXY)
   * - proxy.userpwd: the proxy username:password (CURLOPT_PROXYUSERPWD)
   * - proxy.auth: See CURLOPT_PROXYAUTH
   * - proxy.port: The proxy port. (CURLOPT_PROXYPORT)
   * - proxy.type: see CURLOPT_PROXYTYPE
   * - proxy.tunnel: If this is set to TRUE, attempt to tunnel through the
   *   proxy. This is recommended when using a proxy. (CURLOPT_HTTPPROXYTUNNEL)
   *
   * @param array $array
   *   An associative array of configuration directives.
   */
  public static function setConfiguration($array) {
    self::$config = $array + self::$config;
  }

  /**
   * Get a configuration option.
   *
   * Get a configuration option by name, with an optional default.
   *
   * @param string $name
   *   The name of the configuration option to get.
   * @param mixed $default
   *   The default value to return if the name is not found.
   * @retval mixed
   * @return mixed
   *   The value, if found; or the default, if set; or NULL.
   */
  public static function config($name = NULL, $default = NULL) {

    // If no name is specified, return the entire config array.
    if (empty($name)) {
      return self::$config;
    }

    // If the config value exists, return that.
    if (isset(self::$config[$name])) {
      return self::$config[$name];
    }

    // Otherwise, just return the default value.
    return $default;
  }

  /**
   * Check whether the given configuration option is set.
   *
   * @code
   * if (Bootstrap::hasConfig('transport')) {
   *   syslog(LOG_INFO, 'An alternate transport is supplied.');
   * }
   * @endcode
   *
   * @param string $name
   *   The name of the item to check for.
   * @retval boolean
   * @return boolean
   *   TRUE if the named option is set, FALSE otherwise. Note that the value may
   *   be falsey (FALSE, 0, etc.), but if the value is NULL, this will return
   *   false.
   */
  public static function hasConfig($name) {
    return isset(self::$config[$name]);
  }

  /**
   * Get a OpenStack::Services::IdentityService object from the bootstrap config.
   *
   * A factory helper function that uses the bootstrap configuration to create
   * a ready to use OpenStack::Services::IdentityService object.
   *
   * @param bool $force
   *   Whether to force the generation of a new object even if one is already
   *   cached.
   * @retval OpenStack::Services::IdentityService
   * @return \OpenStack\Services\:IdentityService
   *   An authenticated ready to use OpenStack::Services::IdentityService object.
   * @throws OpenStack::Exception
   *   When the needed configuration to authenticate is not available.
   */
  public static function identity($force = FALSE) {

    // If we already have an identity make sure the token is not expired.
    if ($force || is_null(self::$identity) || self::$identity->isExpired()) {

      // Make sure we have an endpoint to use
      if (!self::hasConfig('endpoint')) {
        throw new Exception('Unable to authenticate. No endpoint supplied.');
      }

      // Neither user nor account can be an empty string, so we need
      // to do more checking than self::hasConfig(), which returns TRUE
      // if an item exists and is an empty string.
      $user = self::config('username', NULL);
      $account = self::config('account', NULL);

      // Check if we have a username/password
      if (!empty($user) && self::hasConfig('password')) {
        $is = new IdentityService(self::config('endpoint'));
        $is->authenticateAsUser($user, self::config('password'), self::config('tenantid', NULL), self::config('tenantname', NULL));
        self::$identity = $is;
      }

      // Otherwise we go with access/secret keys
      elseif (!empty($account) && self::hasConfig('secret')) {
        $is = new IdentityService(self::config('endpoint'));
        $is->authenticateAsAccount($account, self::config('secret'), self::config('tenantid', NULL), self::config('tenantname', NULL));
        self::$identity = $is;
      }

      else {
        throw new Exception('Unable to authenticate. No account credentials supplied.');
      }
    }

    return self::$identity;
  }
}
