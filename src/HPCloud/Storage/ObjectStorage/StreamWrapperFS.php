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

  /**
   * Cache of auth token -> service catalog.
   *
   * This will eventually be replaced by a better system, but for a system of
   * moderate complexity, many, many file operations may be run during the
   * course of a request. Caching the catalog can prevent numerous calls
   * to identity services.
   */
  protected static $serviceCatalogCache = array();

  /**
   * The stream context.
   *
   * This is set automatically when the stream wrapper is created by
   * PHP. Note that it is not set through a constructor.
   */
  public $context;
  protected $contextArray = array();

  protected $schemeName = self::DEFAULT_SCHEME;
  protected $authToken;


  // File flags. These should probably be replaced by O_ const's at some point.
  protected $isBinary = FALSE;
  protected $isText = TRUE;
  protected $isWriting = FALSE;
  protected $isReading = FALSE;
  protected $isTruncating = FALSE;
  protected $isAppending = FALSE;
  protected $noOverwrite = FALSE;
  protected $createIfNotFound = TRUE;

  /**
   * If this is TRUE, no data is ever sent to the remote server.
   */
  protected $isNeverDirty = FALSE;

  protected $triggerErrors = FALSE;

  /**
   * Indicate whether the local differs from remote.
   *
   * When the file is modified in such a way that 
   * it needs to be written remotely, the isDirty flag
   * is set to TRUE.
   */
  protected $isDirty = FALSE;

  /**
   * Object storage instance.
   */
  protected $store;

  /**
   * The Container.
   */
  protected $container;

  /**
   * The Object.
   */
  protected $obj;

  /**
   * The IO stream for the Object.
   */
  protected $objStream;

  /**
   * Directory listing.
   *
   * Used for directory methods.
   */
  protected $dirListing = array();
  protected $dirIndex = 0;
  protected $dirPrefix = '';


  /**
   * Open a directory for reading.
   *
   * @code
   * <?php
   *
   * // Assuming a valid context in $cxt...
   *
   * // Get the container as if it were a directory.
   * $dir = opendir('swift://mycontainer', $cxt);
   *
   * // Do something with $dir
   *
   * closedir($dir);
   * ?>
   * @endcode
   *
   * See opendir() and scandir().
   *
   * @param string $path
   *   The URL to open.
   * @param int $options
   *   Unused.
   * @retval boolean
   *   TRUE if the directory is opened, FALSE otherwise.
   */
  public function dir_opendir($path, $options) {
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


      $this->dirListing = $container->objectsWithPrefix($this->dirPrefix, $sep);
    }
    catch (\HPCloud\Exception $e) {
      trigger_error('Directory could not be opened: ' . $e->getMessage(), E_USER_WARNING);
      return FALSE;
    }

    return TRUE;
  }

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
   * Perform stat()/lstat() operations.
   *
   * @code
   * <?php
   *   $file = fopen('swift://foo/bar', 'r+', FALSE, $cxt);
   *   $stats = fstat($file);
   * ?>
   * @endcode
   *
   * To use standard \c stat() on a Swift stream, you will
   * need to set account information (tenant ID, account ID, secret,
   * etc.) through HPCloud::Bootstrap::setConfiguration().
   *
   * @retval array
   *   The stats array.
   */
  public function stream_stat() {
    $stat = fstat($this->objStream);

    // FIXME: Need to calculate the length of the $objStream.
    //$contentLength = $this->obj->contentLength();
    $contentLength = $stat['size'];

    return $this->generateStat($this->obj, $this->container, $contentLength);
  }

  /**
   * @see stream_stat().
   */
  public function url_stat($path, $flags) {
    $url = $this->parseUrl($path);

    if (empty($url['host']) || empty($url['path'])) {
      if ($flags & STREAM_URL_STAT_QUIET) {
        trigger_error('Container name (host) and path are required.', E_USER_WARNING);
      }
      return FALSE;
    }

    try {
      $this->initializeObjectStorage();

      // Since we are throwing the $container away without really using its
      // internals, we create an unchecked container. It may not actually
      // exist on the server, which will cause a 404 error.
      //$container = $this->store->container($url['host']);
      $name = $url['host'];
      $token = $this->store->token();
      $endpoint_url = $this->store->url() . '/' . rawurlencode($name);
      $container = new \HPCloud\Storage\ObjectStorage\Container($name, $endpoint_url, $token);
      $obj = $container->remoteObject($url['path']);
    }
    catch(\HPCloud\Exception $e) {
      // Apparently file_exists does not set STREAM_URL_STAT_QUIET.
      //if ($flags & STREAM_URL_STAT_QUIET) {
        //trigger_error('Could not stat remote file: ' . $e->getMessage(), E_USER_WARNING);
      //}
      return FALSE;
    }

    if ($flags & STREAM_URL_STAT_QUIET) {
      try {
        return @$this->generateStat($obj, $container, $obj->contentLength());
      }
      catch (\HPCloud\Exception $e) {
        return FALSE;
      }
    }
    return $this->generateStat($obj, $container, $obj->contentLength());
  }

  /**
   * Generate a reasonably accurate STAT array.
   *
   * Notes on mode:
   * - All modes are of the (octal) form 100XXX, where
   *   XXX is replaced by the permission string. Thus,
   *   this always reports that the type is "file" (100).
   * - Currently, only two permission sets are generated:
   *   - 770: Represents the ACL::makePrivate() perm.
   *   - 775: Represents the ACL::makePublic() perm.
   *
   * Notes on mtime/atime/ctime:
   * - For whatever reason, Swift only stores one timestamp.
   *   We use that for mtime, atime, and ctime.
   *
   * Notes on size:
   * - Size must be calculated externally, as it will sometimes
   *   be the remote's Content-Length, and it will sometimes be
   *   the cached stat['size'] for the underlying buffer.
   */
  protected function generateStat($object, $container, $size) {
    // This is not entirely accurate. Basically, if the 
    // file is marked public, it gets 100775, and if
    // it is private, it gets 100770.
    //
    // Mode is always set to file (100XXX) because there
    // is no alternative that is more sensible. PHP docs
    // do not recommend an alternative.
    //
    // octdec(100770) == 33272
    // octdec(100775) == 33277
    $mode = $container->acl()->isPublic() ? 33277 : 33272;

    // We have to fake the UID value in order for is_readible()/is_writable()
    // to work. Note that on Windows systems, stat does not look for a UID.
    if (function_exists('posix_geteuid')) {
      $uid = posix_geteuid();
      $gid = posix_getegid();
    }
    else {
      $uid = 0;
      $gid = 0;
    }

    if ($object instanceof \HPCloud\Storage\ObjectStorage\RemoteObject) {
      $modTime = $object->lastModified();
    }
    else {
      $modTime = 0;
    }
    $values = array(
      'dev' => 0,
      'ino' => 0,
      'mode' => $mode,
      'nlink' => 0,
      'uid' => $uid,
      'gid' => $gid,
      'rdev' => 0,
      'size' => $size,
      'atime' => $modTime,
      'mtime' => $modTime,
      'ctime' => $modTime,
      'blksize' => -1,
      'blocks' => -1,
    );

    $final = array_values($values) + $values;

    return $final;

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

}
