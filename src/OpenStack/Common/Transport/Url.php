<?php

/*
 * (c) Copyright 2014 Rackspace US, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace OpenStack\Common\Transport;

/**
 * Represents a URL, containing its various syntax components. Please note that
 * this class does not validate input or enforce RFC3986 standards; instead it
 * is meant to serve as a usable model of a URL within our SDK.
 *
 * @link http://tools.ietf.org/html/rfc3986
 */
class Url
{
    private $scheme;
    private $host;
    private $port;
    private $user;
    private $password;
    private $path;
    private $query = [];
    private $fragment;

    /**
     * @param array|string $value Either a string or array input value that
     *                            will be parsed and populated
     *
     * @throws \InvalidArgumentException If argument is not a string or array
     */
    public function __construct($value)
    {
        if (is_string($value)) {
            $value = parse_url($value);
        } elseif (!is_array($value)) {
            throw new \InvalidArgumentException(
                "Url can only be populated with a string or array of values"
            );
        }

        $this->populateFromArray($value);
    }

    /**
     * Internal method that allows for the hydration of this object with an
     * array. It iterates through each element and calls the necessary setter
     * method if it exists.
     *
     * @param array $array The input array
     */
    private function populateFromArray(array $array)
    {
        foreach ($array as $key => $val) {
            if ($key == 'pass') {
                $key = 'password';
            }
            $method = 'set' . $key;
            if ($val && method_exists($this, $method)) {
                $this->$method($val);
            }
        }
    }

    /**
     * @param string $scheme
     */
    public function setScheme($scheme)
    {
        $this->scheme = (string)$scheme;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = (string)$host;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param int $port
     */
    public function setPort($port)
    {
        $this->port = (int)$port;
    }

    /**
     * @return int|null
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user = (string)$user;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = (string)$password;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the path to a string value, ensuring that a trailing slash is always
     * added.
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = rtrim((string)$path, '/');
    }

    /**
     * Adds a string path to the existing path value.
     *
     * @param string $path
     */
    public function addPath($path)
    {
        $path = '/' . ltrim((string)$path, '/');
        $this->setPath($this->path . $path);
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets the query value. If a string is provided, it is expanded according
     * to conventional key=pair representation, where `&' is a delimeter. An
     * array can also be provided.
     *
     * @param string|array $query
     *
     * @throws \InvalidArgumentException
     */
    public function setQuery($query)
    {
        if (is_string($query)) {
            $query = $this->expandQueryString($query);
        } elseif (!is_array($query)) {
            throw new \InvalidArgumentException("Query must be an array");
        }

        $this->query = $query;
    }

    /**
     * Internal method for expanding a string representation of a query into an
     * array. The return value should be a simple key/value pair. Query arrays
     * are also supported.
     *
     * @param  string $value A string based query representation, in the form of
     *                       ?foo=val&bar=val&baz[]=val_1&baz[]=val_2
     *
     * @return array
     */
    private function expandQueryString($value)
    {
        $parts = explode('&', $value);
        $array = [];
        foreach ($parts as $partArray) {
            $inner = explode('=', $partArray);
            $key   = str_replace('[]', '', $inner[0]);
            $val   = $inner[1];

            if (isset($array[$key])) {
                $array[$key] = [$array[$key], $val];
            } else {
                $array[$key] = $val;
            }
        }

        return $array;
    }

    /**
     * @param array $query
     */
    public function addQuery(array $query)
    {
        $this->setQuery((array)$this->query + $query);
    }

    /**
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $fragment
     */
    public function setFragment($fragment)
    {
        $this->fragment = (string)$fragment;
    }

    /**
     * @return string
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * Shrinks the query array and returns as a string representation.
     *
     * @return string
     */
    private function shrinkQueryArray()
    {
        $url = '?';
        foreach ($this->query as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $subVal) {
                    $url .= $key . '[]=' . $subVal . '&';
                }
            } else {
                $url .= $key . '=' . $val . '&';
            }
        }

        return rtrim($url, '&');
    }

    /**
     * Cast this URL object into a string representation
     *
     * @return string
     */
    public function __toString()
    {
        $url = ($this->scheme) ? $this->scheme . '://' : '//';

        if ($this->user && $this->password) {
            $url .= sprintf("%s:%s@", $this->user, $this->password);
        }

        $url .= $this->host;

        if ($this->port) {
            $url .= ':' . (int)$this->port;
        }

        $url .= $this->path;

        if (!empty($this->query)) {
            $url .= $this->shrinkQueryArray();
        }

        if ($this->fragment) {
            $url .= '#' . $this->fragment;
        }

        return $url;
    }
}