<?php
/**
 * @file
 *
 * The permission denied exception.
 */
namespace HPCloud\Transport;
/**
 * Thrown when an access constraint is not met.
 *
 * Represents an HTTP 403 exception.
 */
class ForbiddenException extends AuthorizationException {}
