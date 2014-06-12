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

namespace OpenStack\Tests\Common\Transport\Guzzle;

use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use OpenStack\Common\Transport\Guzzle\HttpError;
use OpenStack\Tests\TestCase;

class HttpErrorTest extends TestCase
{
    public function testInheritance()
    {
        $sub = new HttpError();
        $this->assertInstanceOf('OpenStack\Common\Transport\Guzzle\HttpError', $sub);
    }

    private function getEvent()
    {
        return new CompleteEvent(new Transaction(new Client(), new Request('GET', '/')));
    }

    public function testSuccessfulResponsesThrowNothing()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(200));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\ConflictException
     */
    public function testConflictExceptionRaisedFor409Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(409));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\ForbiddenException
     */
    public function testConflictExceptionRaisedFor403Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(403));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\LengthRequiredException
     */
    public function testConflictExceptionRaisedFor411Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(411));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\MethodNotAllowedException
     */
    public function testConflictExceptionRaisedFor405Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(405));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\ResourceNotFoundException
     */
    public function testConflictExceptionRaisedFor404Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(404));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\ServerException
     */
    public function testConflictExceptionRaisedFor500Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(500));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\UnauthorizedException
     */
    public function testConflictExceptionRaisedFor401Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(401));
        (new HttpError())->onComplete($event);
    }

    /**
     * @expectedException \OpenStack\Common\Transport\Exception\UnprocessableEntityException
     */
    public function testConflictExceptionRaisedFor422Error()
    {
        $event = $this->getEvent();
        $event->intercept(new Response(422));
        (new HttpError())->onComplete($event);
    }
} 