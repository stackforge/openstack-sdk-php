<?php
/**
 * @file
 *
 * The authorization exception.
 */
namespace HPCloud\Transport;
/**
 * Thrown when an access constraint is not met.
 *
 * Represents an HTTP 401 or 403 exception.
 */
class AuthorizationException extends \HPCloud\Exception {}
