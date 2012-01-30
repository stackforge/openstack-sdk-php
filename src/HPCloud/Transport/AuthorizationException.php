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
 * Represents an HTTP 401 or 403 exception. In the future,
 * 401 and 403 will each have their own exceptions.
 */
class AuthorizationException extends \HPCloud\Exception {}
