<?php
/**
 * @file
 */
namespace HPCloud\Transport;
/**
 * Represents an HTTP 422 error.
 *
 * This often represents a case where a checksum or hash did not match
 * the generated checksum on the remote end.
 */
class UnprocessableEntityException extends \HPCloud\Exception {}
