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
 * HP Cloud configuration.
 *
 * This file contains the HP Cloud autoloader. It also automatically
 * register the HPCloud stream wrappers.
 */

namespace HPCloud;

use HPCloud\Services\IdentityServices;
use HPCloud\Exception;

/**
 * Bootstrapping services.
 *
 * There is no requirement that this class be used. HPCloud is
 * built to be flexible, and any individual component can be
 * used directly, with one caveat: No explicit @c require or
 * @c include calls are made. See the "autoloaders" discussion
 * below.
 *
 * This class provides the following services:
 *
 * - <em>Configuration:</em> "global" settings are set here.
 *   See the setConfiguration() method to see how they
 *   can be set, and the config() and hasConfig() methods to see
 *   how configuration might be checked.
 * - <em>Stream Wrappers:</em> This class can initialize a set of stream
 *   wrappers which will make certain HPCloud services available
 *   through the core PHP stream support.
 * - <em>Autoloader:</em> It provides a special-purpose autoloader that can
 *   load the HPCloud classes, but which will not interfere with
 *   other autoloading facilities.
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
 *   'transport' => '\HPCloud\Transport\CURLTransport',
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
 * <b>AUTOLOADING</b>
 *
 * HPCloud comes with a built-in autoloader that can be called like this:
 *
 * @code
 * Bootstrap::useAutoloader();
 * @endcode
 *
 * @attention
 * The structure of the HPCloud file hierarchy is PSR-0 compliant.
 * This means that you can use any standard PSR-0 classloader to
 * load all of the classes here.
 *
 * That said, many projects rely upon packages to handle their own
 * class loading. To provide this, this package contains a custom
 * classloader that will load JUST the HPCloud classes. See
 * the Bootstrap::useAutoloader() static method.
 *
 * See https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
 *
 * <b>STREAM WRAPPERS</b>
 *
 * Stream wrappers allow you to use the built-in file manipulation
 * functions in PHP to interact with other services. Specifically,
 * the HPCloud stream wrappers allow you to use built-in file commands
 * to access Object Storage (Swift) and other HPCloud services using
 * commands like file_get_contents() and fopen().
 *
 * It's awesome. Trust me.
 *
 */
class Bootstrap {

  /**
   * The directory where HPCloud is located.
   */
  public static $basedir = __DIR__;

  public static $config = array(
    // The transport implementation. By default, we use the PHP stream
    // wrapper's HTTP mechanism to process transactions.
    //'transport' => '\HPCloud\Transport\PHPStreamTransport',

    // This is the default transport while a bug persists in the 
    // Identity Services REST service.
    'transport' => '\HPCloud\Transport\CURLTransport',
  );

  /**
   * An identity services object created from the global settings.
   * @var object HPCloud::Services::IdentityServices
   */
  public static $identity = NULL;

  /**
   * Add the autoloader to PHP's autoloader list.
   *
   * This will add the internal special-purpose
   * autoloader to the list of autoloaders that PHP will
   * leverage to resolve class paths.
   *
   * Because HPCloud is PSR-0 compliant, any
   * full PSR-0 classloader should be capable of loading
   * these classes witout issue. You may prefer to use
   * a standard PSR-0 loader instead of this one.
   *
   * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
   */
  public static function useAutoloader() {
    spl_autoload_register(__NAMESPACE__ . '\Bootstrap::autoload');
  }

  /**
   * Register stream wrappers for HPCloud.
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
      \HPCloud\Storage\ObjectStorage\StreamWrapper::DEFAULT_SCHEME,
      '\HPCloud\Storage\ObjectStorage\StreamWrapper'
    );

    $swiftfs = stream_wrapper_register(
      \HPCloud\Storage\ObjectStorage\StreamWrapperFS::DEFAULT_SCHEME,
      '\HPCloud\Storage\ObjectStorage\StreamWrapperFS'
    );

    return ($swift && $swiftfs);
  }

  /**
   * Set configuration directives for HPCloud.
   *
   * This merges the provided associative array into the existing
   * configuration parameters (Bootstrap::$config).
   *
   * All of the HPCloud classes share the same configuration. This
   * ensures that a stable runtime environment is maintained.
   *
   * Common configuration directives:
   *
   * - 'transport': The namespaced classname for the transport that
   *   should be used. Example: @code \HPCloud\Transport\CURLTransport @endcode
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
   * HPCloud autoloader.
   *
   * An implementation of a PHP autoload function. Use
   * HPCloud::useAutoloader() if you want PHP to automatically
   * load classes using this autoloader.
   *
   * @code
   * // Enable the autoloader.
   * Bootstrap::useAutoloader();
   * @endcode
   *
   * This is a special-purpose autoloader for loading
   * only the HPCloud classes. It will not attempt to
   * autoload anything outside of the HPCloud namespace.
   *
   * Because this is a special-purpose autoloader, it
   * should be safe to use with other special-purpose
   * autoloaders (and also projects that don't
   * rely upon autoloaders).
   *
   * @param string $klass
   *   The fully qualified name of the class to be autoloaded.
   */
  public static function autoload($klass) {
    $components = explode('\\', $klass);
    if (empty($components[0])) {
      array_shift($components);
    }

    // This class loader ONLY loads
    // our classes. A general purpose
    // classloader should be used for
    // more sophisticated needs.
    if ($components[0] != 'HPCloud') {
      return;
    }

    // We need the path up to, but not including, the root HPCloud dir:
    $loc = DIRECTORY_SEPARATOR . 'HPCloud';
    $local_path = substr(self::$basedir, 0, strrpos(self::$basedir, $loc));

    array_unshift($components, $local_path);
    $path = implode(DIRECTORY_SEPARATOR, $components) . '.php';

    if (file_exists($path)) {
      require $path;
      return;
    }
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
   * Get a HPCloud::Services::IdentityService object from the bootstrap config.
   *
   * A factory helper function that uses the bootstrap configuration to create
   * a ready to use HPCloud::Services::IdentityService object.
   *
   * @param bool $force
   *   Whether to force the generation of a new object even if one is already
   *   cached.
   * @retval HPCloud::Services::IdentityService
   * @return \HPCloud\Services\:IdentityService
   *   An authenticated ready to use HPCloud::Services::IdentityService object.
   * @throws HPCloud::Exception
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
        $is = new IdentityServices(self::config('endpoint'));
        $is->authenticateAsUser($user, self::config('password'), self::config('tenantid', NULL), self::config('tenantname', NULL));
        self::$identity = $is;
      }

      // Otherwise we go with access/secret keys
      elseif (!empty($account) && self::hasConfig('secret')) {
        $is = new IdentityServices(self::config('endpoint'));
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
