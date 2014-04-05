<?php
/* ============================================================================
(c) Copyright 2014 Hewlett-Packard Development Company, L.P.

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
 * An Autoloader to use for the case Composer isn't available.
 */

namespace OpenStack;

/**
 * Autoload the OpenStack library.
 *
 * The OpenStack library is natively designed to be available via Composer. When
 * Composer is not available and there is not another PSR-4 compatible autoloader
 * to use, this one can be used.
 *
 * The autoloader can be used like:
 *
 *     Autoloader::useAutoloader();
 *
 * The structure of the OpenStack file hierarchy is PSR-4 compliant.
 * This means that you can use any standard PSR-4 classloader to
 * load all of the classes here.
 *
 * That said, many projects rely upon packages to handle their own
 * class loading. To provide this, this package contains a custom
 * classloader that will load JUST the OpenStack classes. See
 * the Autoloader::useAutoloader() static method.
 *
 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4.md
 */
Class Autoloader {

  /**
   * @var string The directory where OpenStack is located.
   */
  public static $basedir = __DIR__;

  /**
   * Add the autoloader to PHP's autoloader list.
   *
   * This will add the internal special-purpose
   * autoloader to the list of autoloaders that PHP will
   * leverage to resolve class paths.
   *
   * Because OpenStack is PSR-4 compliant, any
   * full PSR-4 classloader should be capable of loading
   * these classes witout issue. You may prefer to use
   * a standard PSR-4 loader instead of this one.
   *
   * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4.md
   */
  public static function useAutoloader() {
    spl_autoload_register(__NAMESPACE__ . '\Autoloader::autoload');
  }

  /**
   * OpenStack autoloader.
   *
   * An implementation of a PHP autoload function. Use
   * OpenStack::useAutoloader() if you want PHP to automatically
   * load classes using this autoloader.
   *
   *     // Enable the autoloader.
   *     Autoloader::useAutoloader();
   *
   * This is a special-purpose autoloader for loading
   * only the OpenStack classes. It will not attempt to
   * autoload anything outside of the OpenStack namespace.
   *
   * Because this is a special-purpose autoloader, it
   * should be safe to use with other special-purpose
   * autoloaders (and also projects that don't
   * rely upon autoloaders).
   *
   * @param string $klass The fully qualified name of the class to be autoloaded.
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
    if ($components[0] != 'OpenStack') {
      return;
    }

    // We need the path up to, but not including, the root OpenStack dir:
    $loc = DIRECTORY_SEPARATOR . 'OpenStack';
    $local_path = substr(self::$basedir, 0, strrpos(self::$basedir, $loc));

    array_unshift($components, $local_path);
    $path = implode(DIRECTORY_SEPARATOR, $components) . '.php';

    if (file_exists($path)) {
      require $path;
      return;
    }
  }
}