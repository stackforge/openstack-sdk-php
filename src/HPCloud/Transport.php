<?php
/**
 * @file
 * The Transport class.
 */
namespace HPCloud;
/**
 * Provide an HTTP client (Transporter) for interaction with HPCloud.
 *
 * Interaction with the OpenStack/HPCloud services is handled via
 * HTTPS/REST requests. This class provides transport for requests.
 *
 */
class Transport {

  protected static $inst = NULL;

  /**
   * Get an instance of a Transporter.
   *
   * @return \HPCloud\Transport\Transporter
   *   An initialized transporter.
   */
  public static function instance() {

    if (empty(self::$inst)) {
      $klass = \HPCloud\Bootstrap::config('transport');
      self::$inst = new $klass();
    }
    return self::$inst;
  }

  /**
   * Rebuild the transporter.
   *
   * This will rebuild the client transporter,
   * re-reading any configuration data in the process.
   */
  public static function reset() {
    self::$inst = NULL;
  }
}
