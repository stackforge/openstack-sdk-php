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

use GuzzleHttp\Message\ResponseInterface as GuzzleResponseInterface;
use OpenStack\Common\Transport\ResponseInterface;

/**
 * This class wraps {@see \GuzzleHttp\Message\ResponseInterface}.
 *
 * @inheritDoc
 */
class ResponseAdapter extends MessageAdapter implements ResponseInterface
{
    public function __construct(GuzzleResponseInterface $guzzleResponse)
    {
        $this->setMessage($guzzleResponse);
    }

    public function getStatusCode()
    {
        return $this->message->getStatusCode();
    }

    public function getReasonPhrase()
    {
        return $this->message->getReasonPhrase();
    }

    public function json()
    {
        return $this->message->json();
    }
}