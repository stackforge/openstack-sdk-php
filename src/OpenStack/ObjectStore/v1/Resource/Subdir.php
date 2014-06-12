<?php

/*
 * (c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.
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

namespace OpenStack\ObjectStore\v1\Resource;

/**
 * Represent a subdirectory (subdir) entry.
 *
 * Depending on the method with which Swift container requests are
 * executed, Swift may return subdir entries instead of Objects.
 *
 * Subdirs are used for things that are directory-like.
 */
class Subdir
{
    /**
     * @var string The path string that this subdir describes
     */
    protected $path;

    /**
     * @var string The delimiter used in this path
     */
    protected $delimiter;

    /**
     * Create a new subdirectory.
     *
     * This represents a remote response's tag for a subdirectory.
     *
     * @param string $path      The path string that this subdir describes.
     * @param string $delimiter The delimiter used in this path.
     */
    public function __construct($path, $delimiter = '/')
    {
        $this->path = $path;
        $this->delimiter = $delimiter;
    }

    /**
     * Get the path.
     *
     * The path is delimited using the string returned by delimiter().
     *
     * @return string The path
     */
    public function path()
    {
        return $this->path;
    }
    /**
     * Get the delimiter used by the server.
     *
     * @return string The value used as a delimiter.
     */
    public function delimiter()
    {
        return $this->delimiter;
    }
}
