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
   * @retval string
   * @return string
   *   The path.
   */
  public function path() {
    return $this->path;
  }
  /**
   * Get the delimiter used by the server.
   *
   * @retval string
   * @return string
   *   The value used as a delimiter.
   */
  public function delimiter() {
    return $this->delimiter;
  }
}
