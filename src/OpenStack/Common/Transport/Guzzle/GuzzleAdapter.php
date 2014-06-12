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

namespace OpenStack\Common\Transport\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use OpenStack\Bootstrap;
use OpenStack\Common\Transport\AbstractClient;
use OpenStack\Common\Transport\Exception;
use OpenStack\Common\Transport\RequestInterface;

/**
 * An adapter class which wraps the Guzzle HTTP client. This adapter satisfies
 * {@see OpenStack\Common\Transport\ClientInterface}, acting as an intermediary
 * between Guzzle and our interface, reconciling their differences.
 */
class GuzzleAdapter extends AbstractClient
{
    /**
     * @var \GuzzleHttp\Client The client being wrapped.
     */
    protected $client;

    /**
     * A factory method that allows for the easy creation of this adapter. It
     * accepts an array of options which will be fed into the Guzzle client.
     * This method also handles the configuration of the client being wrapped,
     * such as overriding error handling and the default User-Agent header.
     *
     * @param array $options The options passed in to the Guzzle client. For a
     *                       full run-through of available configuration values,
     *                       view the {@link http://docs.guzzlephp.org/en/latest/clients.html#creating-a-client official docs}.
     * @return self
     */
    public static function create(array $options = [])
    {
        if (empty($options['defaults'])) {
            $options['defaults'] = [];
        }

        // Disable Guzzle error handling and define our own error subscriber.
        // Also override default User-Agent header with our own version.
        $options['defaults'] += ['exceptions'  => false,
            'subscribers' => [new HttpError()],
            'headers'     => ['User-Agent' => self::getDefaultUserAgent()]
        ];

        // Inject client and pass in options for adapter
        return new self(new Client($options));
    }

    /**
     * Instantiate a new Adapter which wraps a Guzzle client.
     *
     * @param \GuzzleHttp\ClientInterface $guzzle  The Client being wrapped
     */
    public function __construct(GuzzleClientInterface $guzzle)
    {
        $this->client = $guzzle;
    }

    public function createRequest($method, $uri = null, $body = null, array $options = [])
    {
        $headers = isset($options['headers']) ? $options['headers'] : [];

        $request = $this->client->createRequest($method, $uri, [
            'headers' => $headers,
            'body'    => $body,
        ]);

        return new RequestAdapter($request);
    }

    /**
     * @inheritDoc
     * @param \OpenStack\Common\Transport\RequestInterface $adapter
     * @return \OpenStack\Common\Transport\ResponseInterface
     * @throws \OpenStack\Common\Transport\Exception\RequestException
     * @throws \GuzzleHttp\Exception\RequestException
     */
    public function send(RequestInterface $adapter)
    {
        try {
            $guzzleResponse = $this->client->send($adapter->getMessage());
            return new ResponseAdapter($guzzleResponse);
        } catch (GuzzleRequestException $e) {
            // In order to satisfy {@see GuzzleHttp\ClientInterface}, Guzzle
            // wraps all exceptions in its own RequestException class. This is
            // not useful for our end-users, so we need to make sure our own
            // versions are returned (Guzzle buffers them).
            $previous = $e->getPrevious();
            if ($previous instanceof Exception\RequestException) {
                throw $previous;
            }
            throw $e;
        }
    }

    /**
     * Guzzle handles options using the defaults/ prefix. So if a key is passed
     * in to be set, or got, that contains this prefix - assume that its a
     * Guzzle option, not an adapter one.
     *
     * @inheritDoc
     */
    public function setOption($key, $value)
    {
        $this->client->setDefaultOption($key, $value);
    }

    /**
     * Guzzle handles options using the defaults/ prefix. So if a key is passed
     * in to be set, or got, that contains this prefix - assume that its a
     * Guzzle option, not an adapter one.
     *
     * @inheritDoc
     */
    public function getOption($key)
    {
        if ($key == 'base_url') {
            return $this->getBaseUrl();
        } else {
            return $this->client->getDefaultOption($key);
        }
    }

    public function getBaseUrl()
    {
        return $this->client->getBaseUrl();
    }

    /**
     * Prepends the SDK's version number to the standard Guzzle string.
     *
     * @return string
     */
    public static function getDefaultUserAgent()
    {
        return sprintf("OpenStack/%f %s", Bootstrap::VERSION, Client::getDefaultUserAgent());
    }
}