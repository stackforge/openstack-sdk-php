<?php
/**
 * @file
 * The parent exception class for HPCloud.
 */
namespace HPCloud;
/**
 * The top-level HPCloud exception.
 *
 * In most cases, the library will throw a more finely
 * grained exception, but all exceptions thrown directly
 * by HPCloud will be an instance of this exception.
 */
class Exception extends \Exception {}
