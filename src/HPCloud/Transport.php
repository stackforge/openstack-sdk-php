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
 * The Transport class.
 */
namespace HPCloud;
/**
 * Provide an HTTP client (Transporter) for interaction with HPCloud.
 *
 * Interaction with the OpenStack/HPCloud services is handled via
 * HTTPS/REST requests. This class provides transport for requests.
 *
 * <b>Usage</b>
 *
 * @code
 * <?php
 * // Create a new transport.
 * $client = Transport::instance();
 *
 * // Send a request.
 * $response = $client->doRequest($uri, $method, $headerArray, $body);
 *
 * print $response->content();
 * ?>
 * @endcode
 *
 */
class Transport {

  protected static $inst = NULL;

  /**
   * Get an instance of a Transporter.
   *
   * See HPCloud::Transport::CURLTransport and HPCloud::Transport::PHPStreamTransport
   * for implementations of an HPCloud::Transport::Transporter.
   *
   * To set the transport, the suggested method is this:
   *
   * @code
   * <?php
   * // Set the 'transport' config option.
   * $settings = array(
   *   // Make sure you use the entire namespace, and that
   *   // your classloader can find this namespace.
   *   'transport' => '\HPCloud\Transport\CURLTransport',
   * );
   *
   * // Merge $settings into existing configuration.
   * \HPCloud\Bootstrap::setConfiguration($settings);
   * ?>
   * @endcode
   *
   * @retval HPCloud::Transport::Transporter
   * @return \HPCloud\Transport\Transporter
   *   An initialized transporter.
   */
  public static function instance() {

    if (empty(self::$inst)) {
      $klass = \HPCloud\Bootstrap::config('transport');
      self::$inst = new $klass();
    }
    return self::$inst;
  }

  /**
   * Rebuild the transporter.
   *
   * This will rebuild the client transporter,
   * re-reading any configuration data in the process.
   */
  public static function reset() {
    self::$inst = NULL;
  }
}
