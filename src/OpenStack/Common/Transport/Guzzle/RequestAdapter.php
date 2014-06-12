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

namespace OpenStack\Common\Transport\Guzzle;

use GuzzleHttp\Message\RequestInterface as GuzzleRequestInterface;
use OpenStack\Common\Transport\RequestInterface;

/**
 * This class wraps {@see \GuzzleHttp\Message\RequestInterface}.
 *
 * @inheritDoc
 */
class RequestAdapter extends MessageAdapter implements RequestInterface
{
    public function __construct(GuzzleRequestInterface $guzzleRequest)
    {
        $this->setMessage($guzzleRequest);
    }

    public function getMethod()
    {
        return $this->message->getMethod();
    }

    public function setMethod($method)
    {
        $this->message->setMethod($method);
    }

    public function getUrl()
    {
        return $this->message->getUrl();
    }

    public function setUrl($url)
    {
        $this->message->setUrl($url);
    }
} 