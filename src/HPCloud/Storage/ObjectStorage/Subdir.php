<?php
/**
 * @file
 * Contains the Subdir class.
 */

namespace HPCloud\Storage\ObjectStorage;

/**
 * Represent a subdirectory (subdir) entry.
 *
 * Depending on the method with which Swift container requests are 
 * executed, Swift may return subdir entries instead of Objects.
 *
 * Subdirs are used for things that are directory-like.
 */
class Subdir {

  protected $path;
  protected $delimiter;


  /**
   * Create a new subdirectory.
   *
   * This represents a remote response's tag for a subdirectory.
   *
   * @param string $path
   *   The path string that this subdir describes.
   * @param string $delimiter
   *   The delimiter used in this path.
   */
  public function __construct($path, $delimiter = '/') {
    $this->path = $path;
    $this->delimiter = $delimiter;
  }

  /**
   * Get the path.
   *
   * The path is delimited using the string returned by delimiter().
   *
   * @return string
   *   The path.
   */
  public function path() {
    return $this->path;
  }
  /**
   * Get the delimiter used by the server.
   *
   * @return string
   *   The value used as a delimiter.
   */
  public function delimiter() {
    return $this->delimiter;
  }
}
