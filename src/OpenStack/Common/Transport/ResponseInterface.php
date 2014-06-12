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

namespace OpenStack\Common\Transport;

/**
 * A HTTP response message.
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/proposed/http-message.md#33-psrhttpresponseinterface
 * @link http://tools.ietf.org/html/rfc2616#section-6
 */
interface ResponseInterface extends MessageInterface
{
    /**
     * Gets the response Status-Code, a 3-digit integer result code of the
     * server's attempt to understand and satisfy the request.
     *
     * @return integer Status code.
     */
    public function getStatusCode();

    /**
     * Gets the response Reason-Phrase, a short textual description of the
     * Status-Code.
     *
     * Because a Reason-Phrase is not a required element in response
     * Status-Line, the Reason-Phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 2616 recommended reason phrase for the
     * response's Status-Code.
     *
     * @return string|null Reason phrase, or null if unknown.
     */
    public function getReasonPhrase();
}