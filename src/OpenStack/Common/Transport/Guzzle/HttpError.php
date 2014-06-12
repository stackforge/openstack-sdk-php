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

use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Subscriber\HttpError as GuzzleHttpError;
use OpenStack\Common\Transport\Exception\RequestException;

/**
 * A subscriber for capturing Guzzle's HTTP error events and processing them in
 * a standardised manner.
 */
class HttpError implements SubscriberInterface
{
    public function getEvents()
    {
        return ['complete' => ['onComplete', RequestEvents::VERIFY_RESPONSE]];
    }

    /**
     * When a request completes, this method is executed. Because this class
     * checks for HTTP errors and handles them, this method checks the HTTP
     * status code and invokes {@see RequestException} if necessary.
     *
     * @param CompleteEvent $event
     * @throws \OpenStack\Common\Transport\Exception\RequestException
     */
    public function onComplete(CompleteEvent $event)
    {
        $status = (int) $event->getResponse()->getStatusCode();

        // Has an error occurred (4xx or 5xx status)?
        if ($status >= 400 && $status <= 505) {
            $request  = new RequestAdapter($event->getRequest());
            $response = new ResponseAdapter($event->getResponse());
            throw RequestException::create($request, $response);
        }
    }
} 