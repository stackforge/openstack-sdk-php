<?php
/**
 * @file
 * HP Cloud configuration.
 *
 * This file contains the HP Cloud autoloader. It also automatically
 * register the HPCloud stream wrappers.
 */

namespace HPCloud;

/**
 * Bootstrapping services.
 *
 * There is no requirement that this class be used. HPCloud is
 * built to be flexible, and any individual component can be
 * used directly, with one caveat: No explicit `require` or
 * `include` calls are made. See the "autoloaders" discussion
 * below.
 *
 * This class provides the following services:
 *
 * - Stream Wrappers: This class can initialize a set of stream
 *   wrappers which will make certain HPCloud services available
 *   through the core PHP stream support.
 * - Autoloader: It provides a special-purpose autoloader that can
 *   load the HPCloud classes, but which will not interfere with
 *   other autoloading facilities.
 *
 * AUTOLOADING
 *
 * The structure of the HPCloud file hierarchy is PSR-0 compliant.
 * This means that you can use any standard PSR-0 classloader to
 * load all of the classes here.
 *
 * That said, many projects rely upon packages to handle their own
 * class loading. To provide this, this package contains a custom
 * classloader that will load JUST the HPCloud classes. See
 * the Bootstrap::useAutoloader() static method.
 *
 * STREAM WRAPPERS
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
    'transport' => '\HPCloud\Transport\PHPStreamTransport',
  );

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
   */
  public static function useAutoloader() {
    spl_autoload_register(__NAMESPACE__ . '\Bootstrap::autoload');
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
   * This is a special-purpose autoloader for loading
   * only the HPCloud classes. It will not attempt to
   * autoload anything outside of the \HPCloud namespace.
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
    $local_path = substr(self::$basedir, 0, strrpos(self::$basedir, '/HPCloud'));


    array_unshift($components, $local_path);
    $path = implode(DIRECTORY_SEPARATOR, $components) . '.php';

    if (file_exists($path)) {
      require $path;
      return;
    }
  }
}
