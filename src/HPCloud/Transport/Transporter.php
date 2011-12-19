<?php
/**
 * This file contains the interface for transporters.
 */

namespace HPCloud\Transport;

/**
 * Describes a Transporter.
 *
 * Transporters are responsible for moving data from the remote cloud to
 * the local host. Transporters are responsible only for the transport
 * protocol, not for the payloads.
 *
 * The current OpenStack services implementation is oriented toward
 * REST-based services, and consequently the transport layers are
 * HTTP/HTTPS, and perhaps SPDY some day. The interface reflects this.
 * it is not designed as a protocol-neutral transport layer
 */
interface Transporter {

  const HTTP_USER_AGENT = 'HPCloud-PHP/1.0';

  /**
   * Perform a request.
   *
   * Invoking this method causes a single request to be relayed over the
   * transporter. The transporter MUST be capable of handling multiple
   * invocations of a doRequest() call.
   *
   * @param string $uri
   *   The target URI.
   * @param string $method
   *   The method to be sent.
   * @param array $headers
   *   An array of name/value header pairs.
   * @param string $body
   *   The string containing the request body.
   */
  public function doRequest($uri, $method = 'GET', $headers = array(), $body = '');
}
