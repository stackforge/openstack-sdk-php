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
 * The Transport class.
 */
namespace OpenStack;
/**
 * Provide an HTTP client (Transporter) for interaction with OpenStack.
 *
 * Interaction with the OpenStack services is handled via
 * HTTPS/REST requests. This class provides transport for requests.
 *
 * Usage
 *
 *     <?php
 *     // Create a new transport.
 *     $client = Transport::instance();
 *
 *     // Send a request.
 *     $response = $client->doRequest($uri, $method, $headerArray, $body);
 *
 *     print $response->content();
 *     ?>
 *
 */
class Transport {

  protected static $inst = NULL;

  /**
   * Get an instance of a Transporter.
   *
   * @see \OpenStack\Transport\CURLTransport and \OpenStack\Transport\PHPStreamTransport
   * for implementations of an \OpenStack\Transport\Transporter.
   *
   * To set the transport, the suggested method is this:
   *
   *     <?php
   *     // Set the 'transport' config option.
   *     $settings = array(
   *       // Make sure you use the entire namespace, and that
   *       // your classloader can find this namespace.
   *       'transport' => '\OpenStack\Transport\CURLTransport',
   *     );
   *
   *     // Merge $settings into existing configuration.
   *     \OpenStack\Bootstrap::setConfiguration($settings);
   *     ?>
   *
   * @return \OpenStack\Transport\Transporter An initialized transporter.
   */
  public static function instance() {

    if (empty(self::$inst)) {
      $klass = \OpenStack\Bootstrap::config('transport');
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
