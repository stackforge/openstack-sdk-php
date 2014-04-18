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
 * This file contains the interface for transporters.
 */

namespace OpenStack\Transport;

/**
 * Describes a transport client.
 *
 * Transport clients are responsible for moving data from the remote cloud to
 * the local host. Transport clinets are responsible only for the transport
 * protocol, not for the payloads.
 *
 * The current OpenStack services implementation is oriented toward
 * REST-based services, and consequently the transport layers are
 * HTTP/HTTPS, and perhaps SPDY some day. The interface reflects this.
 * it is not designed as a protocol-neutral transport layer
 */
interface ClientInterface
{
    const HTTP_USER_AGENT = 'OpenStack-PHP/1.0';

    /**
     * Setup for the HTTP Client.
     *
     * @param array $options Options for the HTTP Client including:
     *                       - headers  (array) A key/value mapping of default headers for each request.
     *                       - proxy    (string) A proxy specified as a URI.
     *                       - debug      (bool) True if debug output should be displayed.
     *                       - timeout    (int) The timeout, in seconds, a request should wait before
     *                       timing out.
     *                       - ssl_verify (bool|string) True, the default, verifies the SSL certificate,
     *                       false disables verification, and a string is the path to a CA to verify
     *                       against.
     */
    public function __construct(array $options = []);

    /**
     * Perform a request.
     *
     * Invoking this method causes a single request to be relayed over the
     * transporter. The transporter MUST be capable of handling multiple
     * invocations of a doRequest() call.
     *
     * @param string $uri     The target URI.
     * @param string $method  The method to be sent.
     * @param array  $headers An array of name/value header pairs.
     * @param string $body    The string containing the request body.
     *
     * @return \OpenStack\Transport\ResponseInterface The response. The response
     *                                                is implicit rather than explicit. The interface is based on a draft for
     *                                                messages from PHP FIG. Individual implementing libraries will have their
     *                                                own reference to interfaces. For example, see Guzzle.
     *
     * @throws \OpenStack\Transport\ForbiddenException
     * @throws \OpenStack\Transport\UnauthorizedException
     * @throws \OpenStack\Transport\FileNotFoundException
     * @throws \OpenStack\Transport\MethodNotAllowedException
     * @throws \OpenStack\Transport\ConflictException
     * @throws \OpenStack\Transport\LengthRequiredException
     * @throws \OpenStack\Transport\UnprocessableEntityException
     * @throws \OpenStack\Transport\ServerException
     * @throws \OpenStack\Exception
     */
    public function doRequest($uri, $method = 'GET', array $headers = [], $body = '');

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
     * @param string $uri      The target URI.
     * @param string $method   The method to be sent.
     * @param array  $headers  An array of name/value header pairs.
     * @param mixed  $resource The string with a file path or a stream URL; or a
     *                         file object resource. If it is a string, then it will be opened with the
     *                         default context. So if you need a special context, you should open the
     *                         file elsewhere and pass the resource in here.
     *
     * @return \OpenStack\Transport\ResponseInterface The response. The response
     *                                                is implicit rather than explicit. The interface is based on a draft for
     *                                                messages from PHP FIG. Individual implementing libraries will have their
     *                                                own reference to interfaces. For example, see Guzzle.
     *
     * @throws \OpenStack\Transport\ForbiddenException
     * @throws \OpenStack\Transport\UnauthorizedException
     * @throws \OpenStack\Transport\FileNotFoundException
     * @throws \OpenStack\Transport\MethodNotAllowedException
     * @throws \OpenStack\Transport\ConflictException
     * @throws \OpenStack\Transport\LengthRequiredException
     * @throws \OpenStack\Transport\UnprocessableEntityException
     * @throws \OpenStack\Transport\ServerException
     * @throws \OpenStack\Exception
     */
    public function doRequestWithResource($uri, $method, array $headers = [], $resource);
}
