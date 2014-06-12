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

use OpenStack\Common\Transport\MessageInterface;
use GuzzleHttp\Message\MessageInterface as GuzzleMessageInterface;

/**
 * An adapter class which wraps {@see GuzzleHttp\Message\MessageInterface}
 * objects. Until PSR releases a standardised interface that all projects can
 * share, we need to adapt the different interfaces.
 *
 * As you will notice, most of this adapter is a like-for-like method
 * translation. Although it seems verbose, it is actually a lot more explicit,
 * clearer and easier to debug than using magic methods.
 */
class MessageAdapter implements MessageInterface
{
    /** @var \GuzzleHttp\Message\MessageInterface The Guzzle message being wrapped */
    protected $message;

    /**
     * @param \GuzzleHttp\Message\MessageInterface $guzzleMessage
     */
    public function __construct(GuzzleMessageInterface $guzzleMessage)
    {
        $this->setMessage($guzzleMessage);
    }

    /**
     * This sets the Guzzle object being wrapped.
     *
     * @param \GuzzleHttp\Message\MessageInterface $guzzleMessage The object being wrapped.
     */
    public function setMessage(GuzzleMessageInterface $guzzleMessage)
    {
        $this->message = $guzzleMessage;
    }

    /**
     * @return \GuzzleHttp\Message\MessageInterface
     */
    public function getMessage()
    {
        return $this->message;
    }

    public function getProtocolVersion()
    {
        return $this->message->getProtocolVersion();
    }

    public function getBody()
    {
        return $this->message->getBody();
    }

    public function setBody(/* StreamInterface */ $body = null)
    {
        $this->message->setBody($body);
    }

    public function getHeaders()
    {
        return $this->message->getHeaders();
    }

    public function hasHeader($header)
    {
        return $this->message->hasHeader($header);
    }

    public function getHeader($header, $asArray = false)
    {
        return $this->message->getHeader($header, $asArray);
    }

    public function setHeader($header, $value)
    {
        $this->message->setHeader($header, $value);
    }

    public function setHeaders(array $headers)
    {
        $this->message->setHeaders($headers);
    }

    public function addHeader($header, $value)
    {
        $this->message->addHeader($header, $value);
    }

    public function addHeaders(array $headers)
    {
        $this->message->addHeaders($headers);
    }

    public function removeHeader($header)
    {
        $this->message->removeHeader($header);
    }
}