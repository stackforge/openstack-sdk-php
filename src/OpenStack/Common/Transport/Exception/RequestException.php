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

namespace OpenStack\Common\Transport\Exception;

use OpenStack\Common\Exception;
use OpenStack\Common\Transport\RequestInterface;
use OpenStack\Common\Transport\ResponseInterface;

/**
 * Base exception that is thrown for requests that result in a HTTP error.
 */
class RequestException extends Exception
{
    /** @var \OpenStack\Common\Transport\RequestInterface */
    protected $request;

    /** @var \OpenStack\Common\Transport\ResponseInterface */
    protected $response;

    /**
     * Construct this exception like any other, but also inject Request and
     * Response objects in case the user needs them for debugging.
     *
     * @param string                                        $errorMessage Human-readable explanation of error
     * @param \OpenStack\Common\Transport\RequestInterface  $request      The failed request
     * @param \OpenStack\Common\Transport\ResponseInterface $response     The server's response
     */
    public function __construct($errorMessage, RequestInterface $request, ResponseInterface $response)
    {
        parent::__construct($errorMessage, $response->getStatusCode());

        $this->request  = $request;
        $this->response = $response;
    }

    /**
     * Factory method that creates an appropriate Exception object based on the
     * Response's status code. The message is constructed here also.
     *
     * @param \OpenStack\Common\Transport\RequestInterface  $request  The failed request
     * @param \OpenStack\Common\Transport\ResponseInterface $response The API's response
     * @return self
     */
    public static function create(RequestInterface $request, ResponseInterface $response)
    {
        $label = 'A HTTP error occurred';

        $status = $response->getStatusCode();

        $exceptions = [
            401 => 'UnauthorizedException',
            403 => 'ForbiddenException',
            404 => 'ResourceNotFoundException',
            405 => 'MethodNotAllowedException',
            409 => 'ConflictException',
            411 => 'LengthRequiredException',
            422 => 'UnprocessableEntityException',
            500 => 'ServerException'
        ];

        $message = sprintf(
            "%s\n[Status] %s (%s)\n[URL] %s\n[Message] %s\n", $label,
            (string) $request->getUrl(),
            $status, $response->getReasonPhrase(),
            (string) $response->getBody()
        );

        // Find custom exception class or use default
        $exceptionClass = isset($exceptions[$status])
            ? sprintf("%s\\%s", __NAMESPACE__, $exceptions[$status])
            : __CLASS__;

        return new $exceptionClass($message, $request, $response);
    }

    /**
     * Returns the server response.
     *
     * @return \OpenStack\Common\Transport\ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Returns the request that caused error.
     *
     * @return \OpenStack\Common\Transport\RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
} 