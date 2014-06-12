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
 * Class that implements {@see ClientInterface} and contains common
 * functionality for clients or client adapters. Most of the methods defined
 * are purely for convenience, and don't necessarily need a client to implement
 * them with their own custom logic.
 */
abstract class AbstractClient implements ClientInterface
{
    public function get($uri, array $options = [])
    {
        return $this->send($this->createRequest('GET', $uri, null, $options));
    }

    public function head($uri, array $options = [])
    {
        return $this->send($this->createRequest('HEAD', $uri, null, $options));
    }

    public function post($uri, $body = null, array $options = [])
    {
        return $this->send($this->createRequest('POST', $uri, $body, $options));
    }

    public function put($uri, $body = null, array $options = [])
    {
        return $this->send($this->createRequest('PUT', $uri, $body, $options));
    }

    public function delete($uri, array $options = [])
    {
        return $this->send($this->createRequest('DELETE', $uri, null, $options));
    }
}