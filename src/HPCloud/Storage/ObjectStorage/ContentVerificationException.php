<?php
/**
 * @file
 *
 * Contains the ContentVerificationException object.
 */
namespace HPCloud\Storage\ObjectStorage;

/**
 * Content Verification error condition.
 *
 * This occurs when the server sends content whose value does
 * not match the supplied checksum. See
 * RemoteObject::setContentVerification().
 *
 */
class ContentVerificationException extends \HPCloud\Exception {}
