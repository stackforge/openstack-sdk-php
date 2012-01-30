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
 * <b>Usage</b>
 *
 * @code
 * <?php
 * // Create a new transport.
 * $client = Transport::instance();
 *
 * // Send a request.
 * $response = $client->doRequest($uri, $method, $headerArray, $body);
 *
 * print $response->content();
 * ?>
 * @endcode
 *
 */
class Transport {

  protected static $inst = NULL;

  /**
   * Get an instance of a Transporter.
   *
   * See HPCloud::Transport::CURLTransport and HPCloud::Transport::PHPStreamTransport
   * for implementations of an HPCloud::Transport::Transporter.
   *
   * To set the transport, the suggested method is this:
   *
   * @code
   * <?php
   * // Set the 'transport' config option.
   * $settings = array(
   *   // Make sure you use the entire namespace, and that
   *   // your classloader can find this namespace.
   *   'transport' => '\HPCloud\Transport\CURLTransport',
   * );
   *
   * // Merge $settings into existing configuration.
   * \HPCloud\Bootstrap::setConfiguration($settings);
   * ?>
   * @endcode
   *
   * @retval HPCloud::Transport::Transporter
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
