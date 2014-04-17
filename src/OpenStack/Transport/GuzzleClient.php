<?php
/* ============================================================================
(c) Copyright 2014 Hewlett-Packard Development Company, L.P.

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
 * This file contains the interface for transporter clients.
 */

namespace OpenStack\Transport;

class GuzzleClient implements ClientInterface, \Serializable
{
    const HTTP_USER_AGENT_SUFFIX = ' Guzzle/4.0';

    /**
     * The Guzzle client used for the implementation.
     */
    protected $client;

    protected $options;

    /**
     * Setup for the HTTP Client.
     *
     * @param array $options Options for the HTTP Client including:
     *                       - headers    (array) A key/value mapping of default headers for each request.
     *                       - proxy      (string) A proxy specified as a URI.
     *                       - debug      (bool) True if debug output should be displayed.
     *                       - timeout    (int) The timeout, in seconds, a request should wait before
     *                       timing out.
     *                       - ssl_verify (bool|string) True, the default, verifies the SSL certificate,
     *                       FALSE disables verification, and a string is the path to a CA to verify
     *                       against.
     *                       - client     (mixed)  A guzzle client object to use instead of the default.
     *                       This can be either a string to the class or an existing object. If an
     *                       existing object is passed in the other options will be ignored.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        $this->client = $this->setup($options);
    }

    /**
     * Setup is a protected method to setup the client.
     *
     * The functionality would typically be in the constructor. It was broken out
     * to be used by the constructor and serialization process.
     *
     * @param  array $options The options as passed to the constructor.
     * @return mixed The Guzzle based client.
     */
    protected function setup(array $options = [])
    {
        // If no client has been passed in we create one. This is the default case.
        if (!isset($options['client']) || is_string($options['client'])) {
            $defaultOptions = ['defaults' => []];
            if (isset($options['headers'])) {
                $defaultOptions['defaults']['headers'] = $options['headers'];
            }
            if (isset($options['proxy'])) {
                $defaultOptions['defaults']['proxy'] = $options['proxy'];
            }
            if (isset($options['debug'])) {
                $defaultOptions['defaults']['debug'] = $options['debug'];
            }
            if (isset($options['ssl'])) {
                $defaultOptions['defaults']['verify'] = $options['ssl_verify'];
            }
            if (isset($options['timeout'])) {
                $defaultOptions['defaults']['timeout'] = $options['timeout'];
            }

            // Add a user agent if not already specificed.
            if (!isset($defaultOptions['defaults']['headers']['User-Agent'])) {
                $defaultOptions['defaults']['headers']['User-Agent'] = self::HTTP_USER_AGENT . self::HTTP_USER_AGENT_SUFFIX;
            }

            $clientClass = '\GuzzleHttp\Client';
            if (isset($options['client']) && is_string($options['client'])) {
                $clientClass = $options['client'];
            }

            $options['client'] = new $clientClass($defaultOptions);
        }

        return $options['client'];
    }

    /**
     * {@inheritdoc}
     */
    public function doRequest($uri, $method = 'GET', array $headers = [], $body = '')
    {
        $options = [
            'headers' => $headers,
            'body'    => $body,
        ];

        // We use our own exceptions for errors to provide a common exception
        // interface to applications implementing the SDK.
        try {
            $response = $this->client->send($this->client->createRequest($method, $uri, $options));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->handleException($e);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->handleException($e);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->handleException($e);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function doRequestWithResource($uri, $method, array $headers = [], $resource)
    {
        // Guzzle messes with the resource in such a manner that it can no longer be
        // used by something else after the fact. So, we clone the content into
        // temporary stream.
        $tmp = $out = fopen('php://temp', 'wb+');
        stream_copy_to_stream($resource, $tmp);

        $options = [
            'headers' => $headers,
            'body'    => $tmp,
        ];

        // We use our own exceptions for errors to provide a common exception
        // interface to applications implementing the SDK.
        try {
            $response = $this->client->send($this->client->createRequest($method, $uri, $options));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->handleException($e);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->handleException($e);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->handleException($e);
        }

        return $response;
    }

    /**
     * Handle errors on a response.
     *
     * @param  mixed The Guzzle exception.
     *
     * @return \OpenStack\Transport\ResponseInterface The response.
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
    protected function handleException($exception)
    {
        $response = $exception->getResponse();
        $request = $exception->getRequest();

        if (!is_null($response)) {
            $code = $response->getStatusCode();

            switch ($code) {
                case '403':
                    throw new \OpenStack\Transport\ForbiddenException($response->getReasonPhrase());
                case '401':
                    throw new \OpenStack\Transport\UnauthorizedException($response->getReasonPhrase());
                case '404':
                    throw new \OpenStack\Transport\FileNotFoundException($response->getReasonPhrase() . " ({$response->getEffectiveUrl()})");
                case '405':
                    throw new \OpenStack\Transport\MethodNotAllowedException($response->getReasonPhrase() . " ({$request->getMethod()} {$response->getEffectiveUrl()})");
                case '409':
                    throw new \OpenStack\Transport\ConflictException($response->getReasonPhrase());
                case '412':
                    throw new \OpenStack\Transport\LengthRequiredException($response->getReasonPhrase());
                case '422':
                    throw new \OpenStack\Transport\UnprocessableEntityException($response->getReasonPhrase());
                case '500':
                    throw new \OpenStack\Transport\ServerException($response->getReasonPhrase());
                default:
                    throw new \OpenStack\Exception($response->getReasonPhrase());
            }
        }
        // The exception was one other than a HTTP error. For example, a HTTP layer
        // timeout occurred.
        else {
            throw new \OpenStack\Exception($exception->getMessage());
        }

        return $response;
    }

    public function serialize()
    {
        $data = ['options' => $this->options];

        return serialize($data);
    }

    public function unserialize($data)
    {
        $vals = unserialize($data);
        $this->options = $vals['options'];
        $this->client = $this->setup($vals['options']);
    }
}
