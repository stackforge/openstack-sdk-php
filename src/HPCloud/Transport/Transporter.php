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


  /**
   * Perform a request, but use a resource to read the body.
   *
   * This is a special version of the doRequest() function.
   * It handles a very spefic case where...
   * 
   * - The HTTP verb requires a body (viz. PUT, POST)
   * - The body is in a resource, not a string
   *
   * Examples of appropriate cases for this variant:
   *
   * - Uploading large files.
   * - Streaming data out of a stream and into an HTTP request.
   * - Minimizing memory usage ($content strings are big).
   *
   * Note that all parameters are required.
   *
   * @param string $uri
   *   The target URI.
   * @param string $method
   *   The method to be sent.
   * @param array $headers
   *   An array of name/value header pairs.
   * @param mixed $resource
   *   The string with a file path or a stream URL; or a file object resource.
   *   If it is a string, then it will be opened with the default context.
   *   So if you need a special context, you should open the file elsewhere
   *   and pass the resource in here.
   */
  public function doRequestWithResource($uri, $method, $headers, $resource);
}
