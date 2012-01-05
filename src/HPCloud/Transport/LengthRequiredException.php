<?php
/**
 * @file
 */
namespace HPCloud\Transport;
/**
 * Represents an HTTP 412 error.
 *
 * During some PUT requests, Content-Length is a required header.
 */
class LengthRequiredException extends \HPCloud\Exception {}
