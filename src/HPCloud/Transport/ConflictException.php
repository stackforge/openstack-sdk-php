<?php
/**
 * @file
 */
namespace HPCloud\Transport;
/**
 * Represents an HTTP 409 error.
 *
 * During DELETE requests, this occurs when a remote resource cannot be
 * deleted because the resource is not empty or deleteable. (viz.
 * containers).
 */
class ConflictException extends \HPCloud\Exception {}
