<?php
/**
 * @file
 * Implements a transporter with the PHP HTTP Stream Wrapper.
 */

namespace HPCloud\Transport;

/**
 * Provide HTTP transport with the PHP HTTP stream wrapper.
 *
 * PHP comes with a stream wrapper for HTTP. Actually, it comes with two such
 * stream wrappers, and the compile-time options determine which is used.
 * This transporter uses the stream wrapper library to send requests to the
 * remote host.
 *
 * Several properties are declared public, and can be changed to suite your
 * needs.
 *
 * You can use a single PHPStreamTransport object to execute multiple requests.
 */
class PHPStreamTransport implements Transporter {

  const HTTP_USER_AGENT_SUFFIX = ' (b2d770) PHP/1.0';

  /**
   * The HTTP version this should use.
   *
   * By default, this is set to 1.1, which is not PHP's default. We do
   * this to take advantage of chunked encoding. While this requires PHP
   * 5.3.0 or greater, this is not viewed as a problem, given that the
   * entire library requires PHP 5.3.
   */
  public $httpVersion = '1.1';

  /**
   * The length of time, in seconds, to wait for a response.
   *
   * If this is an empty value (NULL, 0, FALSE), the socket system's
   * timeout is used.
   */
  public $requestTimeout = NULL;

  /**
   * The event watcher callback.
   *
   */
  protected $notificationCallback = NULL;

  public function doRequest($uri, $method = 'GET', $headers = array(), $body = '') {
    $cxt = $this->buildStreamContext($method, $headers, $body);

    $res = @fopen($uri, 'rb', FALSE, $cxt);

    // If there is an error, we try to react
    // intelligently.
    if ($res === FALSE) {
      $err = error_get_last();

      if (empty($err['message'])) {
        throw new \HPCloud\Exception("An unknown exception occurred while sending a request.");
      }
      $this->guessError($err['message'], $uri, $method);

      // Should not get here.
      return;
    }

    $metadata = stream_get_meta_data($res);

    $response = new Response($res, $metadata);

    return $response;
  }

  /**
   * Given an error, this tries to guess the cause and throw an exception.
   *
   * Stream wrappers do not deal with error conditions gracefully. (For starters,
   * during an error one cannot access the HTTP headers). The only useful piece
   * of data given is the contents of the last error buffer.
   *
   * This uses the contents of that buffer to attempt to learn what happened
   * during the request. It then throws an exception that seems appropriate for the
   * given context.
   */
  protected function guessError($err, $uri, $method) {

    $regex = '/HTTP\/1\.[01]? ([0-9]+) ([ a-zA-Z]+)/';
    $matches = array();
    preg_match($regex, $err, $matches);

    if (count($matches) < 3) {
      throw new \HPCloud\Exception($err);
    }

    switch ($matches[1]) {

      case '403':
      case '401':
        throw new \HPCloud\Transport\AuthorizationException($matches[0]);
      case '404':
        throw new \HPCloud\Transport\FileNotFoundException($matches[0] . "($uri)");
      case '405':
        throw new \HPCloud\Transport\MethodNotAllowedException($matches[0] . " ($method $uri)");
      case '409':
        throw new \HPCloud\Transport\ConflictException($matches[0]);
      case '412':
        throw new \HPCloud\Transport\LengthRequiredException($matches[0]);
      case '422':
        throw new \HPCloud\Transport\UnprocessableEntityException($matches[0]);
      case '500':
        throw new \HPCloud\Transport\ServerException($matches[0]);
      default:
        throw new \HPCloud\Exception($matches[0]);

    }
  }

  /**
   * Register an event handler for notifications.
   * During the course of a transaction, the stream wrapper emits a variety
   * of notifications. This function can be used to register an event
   * handler to listen for notifications.
   *
   * @param callable $callable
   *   Any callable, including an anonymous function or closure.
   *
   * @see http://us3.php.net/manual/en/function.stream-notification-callback.php
   */
  public function onNotification(callable $callable) {
    $this->notificationCallback = $callable;
  }

  /**
   * Given an array of headers, build a header string.
   *
   * This builds an HTTP header string in the form required by the HTTP stream
   * wrapper for PHP.
   *
   * @param array $headers
   *   An associative array of header names to header values.
   * @return string
   *   A string containing formatted headers.
   */
  protected function smashHeaders($headers) {

    if (empty($headers)) {
      return;
    }

    $buffer = array();
    foreach ($headers as $name => $value) {
      // $buffer[] = sprintf("%s: %s", $name, urlencode($value));
      $buffer[] = sprintf("%s: %s", $name, $value);
    }
    $headerStr = implode("\r\n", $buffer);

    return $headerStr . "\r\n";
  }

  /**
   * Build the stream context for a request.
   *
   * All of the HTTP transport data is passed into PHP's stream wrapper via a
   * stream context. This builds the context.
   */
  protected function buildStreamContext($method, $headers, $body) {

    // Construct the stream options.
    $config = array(
      'http' => array(
        'protocol_version' => $this->httpVersion,
        'method' => strtoupper($method),
        'header' => $this->smashHeaders($headers),
        'user_agent' => Transporter::HTTP_USER_AGENT . self::HTTP_USER_AGENT_SUFFIX,
      ),
    );

    if (!empty($body)) {
      $config['http']['content'] = $body;
    }

    if (!empty($this->requestTimeout)) {
      $config['http']['timeout'] = (float) $this->requestTimeout;
    }

    // Set the params. (Currently there is only one.)
    $params = array();
    if (!empty($this->notificationCallback)) {
      $params['notification_callback'] = $this->notificationCallback;
    }

    // Build the context.
    $context = stream_context_create($config, $params);

    return $context;
  }

}
