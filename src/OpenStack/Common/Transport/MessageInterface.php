<?php

/*
 * (c) Copyright 2014 Rackspace US, Inc.
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
 * HTTP messages consist of requests from a client to a server and responses
 * from a server to a client.
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/proposed/http-message.md#31-psrhttpmessageinterface
 */
interface MessageInterface
{
    /**
     * Gets the HTTP protocol version.
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion();

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface|null Returns the body, or null if not set.
     */
    public function getBody();

    /**
     * Sets the body of the message.
     *
     * The body MUST be a StreamInterface object. Setting the body to null MUST
     * remove the existing body.
     *
     * @param StreamInterface|null $body Body.
     *
     * @return self Returns the message.
     *
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function setBody(/* StreamInterface */ $body = null);

    /**
     * Gets all message headers.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     * @return array Returns an associative array of the message's headers.
     */
    public function getHeaders();

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $header Case-insensitive header name.
     *
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($header);

    /**
     * Retrieve a header by the given case-insensitive name.
     *
     * By default, this method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma. Because some header should not be concatenated together using a
     * comma, this method provides a Boolean argument that can be used to
     * retrieve the associated header values as an array of strings.
     *
     * @param string $header  Case-insensitive header name.
     * @param bool   $asArray Set to true to retrieve the header value as an
     *                        array of strings.
     *
     * @return array|string
     */
    public function getHeader($header, $asArray = false);

    /**
     * Sets a header, replacing any existing values of any headers with the
     * same case-insensitive name.
     *
     * The header values MUST be a string or an array of strings.
     *
     * @param string       $header Header name
     * @param string|array $value  Header value(s)
     *
     * @return self Returns the message.
     */
    public function setHeader($header, $value);

    /**
     * Sets headers, replacing any headers that have already been set on the
     * message.
     *
     * The array keys MUST be a string. The array values must be either a
     * string or an array of strings.
     *
     * @param array $headers Headers to set.
     *
     * @return self Returns the message.
     */
    public function setHeaders(array $headers);

    /**
     * Appends a header value to any existing values associated with the
     * given header name.
     *
     * @param string $header Header name to add
     * @param string $value  Value of the header
     *
     * @return self
     */
    public function addHeader($header, $value);

    /**
     * Merges in an associative array of headers.
     *
     * Each array key MUST be a string representing the case-insensitive name
     * of a header. Each value MUST be either a string or an array of strings.
     * For each value, the value is appended to any existing header of the same
     * name, or, if a header does not already exist by the given name, then the
     * header is added.
     *
     * @param array $headers Associative array of headers to add to the message
     *
     * @return self
     */
    public function addHeaders(array $headers);

    /**
     * Remove a specific header by case-insensitive name.
     *
     * @param string $header HTTP header to remove
     *
     * @return self
     */
    public function removeHeader($header);
}