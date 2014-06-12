<?php

/*
 * (c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.
 * (c) Copyright 2014      Rackspace US, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace OpenStack\Common\Transport;

/**
 * Describes a transport client.
 *
 * Transport clients are responsible for moving data from the remote cloud to
 * the local host. Transport clients are responsible only for the transport
 * protocol, not for the payloads.
 *
 * The current OpenStack services implementation is oriented toward
 * REST-based services, and consequently the transport layers are
 * HTTP/HTTPS, and perhaps SPDY some day. The interface reflects this.
 * it is not designed as a protocol-neutral transport layer
 */
interface ClientInterface
{
    /**
     * Create a new Request object. To send, use the {see send()} method.
     *
     * @param string                                       $method  HTTP method
     * @param string|array|\OpenStack\Common\Transport\Url $uri     URL the request will send to
     * @param string|resource                              $body    Entity body being sent
     * @param array                                        $options Configuration options, such as headers
     *
     * @return \OpenStack\Common\Transport\RequestInterface
     */
    public function createRequest($method,
                                  $uri = null,
                                  $body = null,
                                  array $options = []);

    /**
     * Sends a request.
     *
     * @param \OpenStack\Common\Transport\RequestInterface $request Request to execute
     *
     * @return \OpenStack\Common\Transport\ResponseInterface
     */
    public function send(RequestInterface $request);

    /**
     * Execute a GET request.
     *
     * @param string|array|\OpenStack\Common\Transport\Url $uri     URL the request will send to
     * @param array                                        $options Configuration options, such as headers
     *
     * @return \OpenStack\Common\Transport\ResponseInterface
     */
    public function get($uri, array $options = []);

    /**
     * Execute a HEAD request.
     *
     * @param string|array|\OpenStack\Common\Transport\Url $uri     URL the request will send to
     * @param array                                        $options Configuration options, such as headers
     *
     * @return \OpenStack\Common\Transport\ResponseInterface
     */
    public function head($uri, array $options = []);

    /**
     * Execute a POST request.
     *
     * @param string|array|\OpenStack\Common\Transport\Url $uri     URL the request will send to
     * @param mixed                                        $body    Entity body being sent
     * @param array                                        $options Configuration options, such as headers
     *
     * @return \OpenStack\Common\Transport\ResponseInterface
     */
    public function post($uri, $body, array $options = []);

    /**
     * Execute a PUT request.
     *
     * @param string|array|\OpenStack\Common\Transport\Url $uri     URL the request will send to
     * @param mixed                                        $body    Entity body being sent
     * @param array                                        $options Configuration options, such as headers
     *
     * @return \OpenStack\Common\Transport\ResponseInterface
     */
    public function put($uri, $body, array $options = []);

    /**
     * Execute a DELETE request.
     *
     * @param string|array|\OpenStack\Common\Transport\Url $uri     URL the request will send to
     * @param array                                        $options Configuration options, such as headers
     *
     * @return \OpenStack\Common\Transport\ResponseInterface
     */
    public function delete($uri, array $options = []);

    /**
     * Sets a particular configuration option, depending on how the client
     * implements it. It could, for example, alter cURL configuration or a
     * default header.
     *
     * @param string $key   The key being updated
     * @param mixed  $value The value being set
     */
    public function setOption($key, $value);

    /**
     * Returns the value of a particular configuration option. If the options
     * is not set, NULL is returned.
     *
     * @param string $key The option name
     *
     * @return mixed|null
     */
    public function getOption($key);

    /**
     * Returns the base URL that the client points towards.
     *
     * @return string
     */
    public function getBaseUrl();
}