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
 * Contains the stream wrapper for `swiftfs://` URLs.
 * 
 * The stream wrapper implemented in HPCloud\Storage\ObjectStorage\StreamWrapper
 * only supports the elements of a stream
 */

namespace HPCloud\Storage\ObjectStorage;

use \HPCloud\Bootstrap;
use \HPCloud\Storage\ObjectStorage;

/**
 * Provides stream wrapping for Swift like a file system.
 *
 * This provides a full stream wrapper to expose `swiftfs://` URLs to the
 * PHP stream system.
 *
 *
 * @see http://us3.php.net/manual/en/class.streamwrapper.php
 */
class StreamWrapperFS extends StreamWrapper {

  const DEFAULT_SCHEME = 'swiftfs';
  protected $schemeName = self::DEFAULT_SCHEME;

  /**
   * Fake a make a dir.
   *
   * ObjectStorage has pathy objects not directories. If no objects with a path
   * prefix exist we can pass creating a directory. If objects with a path
   * prefix exist adding the directory will fail.
   */
  public function mkdir($uri, $mode, $options) {

    return !($this->testDirectoryExists($uri));

  }

  /**
   * Fake Remove a directory.
   * 
   * ObjectStorage has pathy objects not directories. If no objects with a path
   * prefix exist we can pass removing it. If objects with a path prefix exist
   * removing the directory will fail.
   */
  public function rmdir($path, $options) {

    return !($this->testDirectoryExists($path));

  }

  /**
   * @see stream_stat().
   */
  public function url_stat($path, $flags) {
    $stat = parent::url_stat($path, $flags);

    // If the file stat setup returned anything return it.
    if ($stat) {
      return $stat;
    }
    // When FALSE is returned there is no file to stat. So, we attempt to handle
    // it like a directory.
    else {
      if ($this->testDirectoryExists($path)) {
        // The directory prefix exists. Fake the directory file permissions.
        return $this->fakeStat(TRUE);
      }
      else {
        // The directory does not exist as a prefix.
        return FALSE;
      }
    }
  }

  ///////////////////////////////////////////////////////////////////
  // INTERNAL METHODS
  // All methods beneath this line are not part of the Stream API.
  ///////////////////////////////////////////////////////////////////

  /**
   * Test if a path prefix (directory like) esits.
   * 
   * ObjectStorage has pathy objects not directories. If objects exist with a
   * path prefix we can consider that the directory exists. For example, if
   * we have an object at foo/bar/baz.txt and test the existance of the
   * directory foo/bar/ we sould see it.
   * 
   * @param  string $path
   *   The directory path to test.
   * @retval bool
   *   TRUE if the directory prefix exists and FALSE otherwise.
   */
  protected function testDirectoryExists($path) {
    $url = $this->parseUrl($path);

    if (empty($url['host'])) {
      trigger_error('Container name is required.' , E_USER_WARNING);
      return FALSE;
    }

    try {
      $this->initializeObjectStorage();
      $container = $this->store->container($url['host']);

      if (empty($url['path'])) {
        $this->dirPrefix = '';
      }
      else {
        $this->dirPrefix = $url['path'];
      }

      $sep = '/';


      $dirListing = $container->objectsWithPrefix($this->dirPrefix, $sep);

      return !empty($dirListing);
    }
    catch (\HPCloud\Exception $e) {
      trigger_error('Path could not be opened: ' . $e->getMessage(), E_USER_WARNING);
      return FALSE;
    }
  }

  /**
   * Fake stat data.
   *
   * Under certain conditions we have to return totally trumped-up
   * stats. This generates those.
   */
  protected function fakeStat($dir = FALSE) {

    $request_time = time();

    // Set inode type to directory or file.
    $type = $dir ? 040000 : 0100000;
    // Fake world-readible
    $mode = $type + 0777;

    $values = array(
      'dev' => 0,
      'ino' => 0,
      'mode' => $mode,
      'nlink' => 0,
      'uid' => posix_getuid(),
      'gid' => posix_getgid(),
      'rdev' => 0,
      'size' => 0,
      'atime' => $request_time,
      'mtime' => $request_time,
      'ctime' => $request_time,
      'blksize' => -1,
      'blocks' => -1,
    );

    $final = array_values($values) + $values;

    return $final;
  }

}
