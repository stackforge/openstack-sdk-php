<?php
/* ============================================================================
(c) Copyright 2012 Hewlett-Packard Development Company, L.P.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights to
use, copy, modify, merge,publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE  LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
============================================================================ */
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
 *
 * @attention This class should not be constructed directly.
 * Use HPCloud::Transport::instance() to get an instance.
 *
 * See HPCloud::Transport and HPCloud::Bootstrap.
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
        // FIXME: Under certain circumstances, all this really means is that
        // there is a 404. So we pretend that it's always a 404.
        // throw new \HPCloud\Exception("An unknown exception occurred while sending a request.");
        $msg = "File not found, perhaps due to a network failure.";
        throw new \HPCloud\Transport\FileNotFoundException($msg);
      }
      $this->guessError($err['message'], $uri, $method);

      // Should not get here.
      return;
    }

    $metadata = stream_get_meta_data($res);
    if (\HPCloud\Bootstrap::hasConfig('transport.debug')) {
      $msg = implode(PHP_EOL, $metadata['wrapper_data']);
      $msg .= sprintf("\nWaiting to read %d bytes.\n", $metadata['unread_bytes']);

      if (defined('STDOUT')) {
        fwrite(STDOUT, $msg);
      }
      else {
        print $msg;
      }
    }

    $response = new Response($res, $metadata);

    return $response;
  }

  /**
   * Implements Transporter::doRequestWithResource().
   *
   * Unfortunately, PHP Stream Wrappers do not allow HTTP data to be read
   * out of a file resource, so using this method will allow some
   * performance improvement (because grabage collection can collect faster),
   * but not a lot.
   *
   * While PHP's underlying architecture should still adequately buffer large
   * strings, the effects of this buffering on really large data (5G or so)
   * is unknown.
   */
  public function doRequestWithResource($uri, $method, $headers, $resource) {


    // In a PHP stream there is no way to buffer content for sending.
    // XXX: Could we create a class with a __tostring that read data in piecemeal?
    // That wouldn't solve the problem, but it might minimize damage.
    if (is_string($resource)) {
      $in = fopen($resource, 'rb', FALSE);
    }
    else {
      $in = $resource;
    }
    $body = '';
    while (!feof($in)) {
      $body .= fread($in, 8192);
    }

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

    Response::failure($matches[1], $matches[0], $uri, $method);
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
   * @retval string
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
    $headers['Connection'] = 'close';
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

    if (\HPCloud\Bootstrap::hasConfig('transport.timeout')) {
      $config['http']['timeout'] = (float) \HPCloud\Bootstrap::config('transport.timeout');
    }

    // Set the params. (Currently there is only one.)
    $params = array();
    if (!empty($this->notificationCallback)) {
      $params['notification'] = $this->notificationCallback;
    }
    // Enable debugging:
    elseif (\HPCloud\Bootstrap::hasConfig('transport.debug')) {
      //fwrite(STDOUT, "Sending debug messages to STDOUT\n");
      $params['notification'] = array($this, 'printNotifications');
    }

    // Build the context.
    $context = stream_context_create($config, $params);

    return $context;
  }
  public function printNotifications($code, $severity, $msg, $msgcode, $bytes, $len) {
    static $filesize = 'Unknown';

    switch ($code) {
      case STREAM_NOTIFY_RESOLVE:
        $out = sprintf("Resolved. %s\n", $msg);
        break;
      case STREAM_NOTIFY_FAILURE:
        $out = sprintf("socket-level failure: %s\n", $msg);
        break;
      case STREAM_NOTIFY_COMPLETED:
        $out = sprintf("Transaction complete. %s\n", $msg);
        break;
      //case STREAM_NOTIFY_REDIRECT:
      //  $out = sprintf("Redirect... %s\n", $msg);
      //  break;
      case STREAM_NOTIFY_CONNECT:
        $out = sprintf("Connect... %s\n", $msg);
        break;
      case STREAM_NOTIFY_FILE_SIZE_IS:
        $out = sprintf("Content-length: %d\n", $len);
        $filesize = $len;
        break;
      case STREAM_NOTIFY_MIME_TYPE_IS:
        $out = sprintf("Content-Type: %s\n", $msg);
        break;
      case STREAM_NOTIFY_PROGRESS:
        $out = sprintf($msg . PHP_EOL);
        $out .= sprintf("%d bytes of %s\n", $bytes, $filesize);
        break;
      default:
        $out = sprintf("Code: %d, Message: %s\n", $code, $msg);
        break;
    }

    // Circumvent output buffering for PHPUnit.
    if (defined('STDOUT')) {
      fwrite(STDOUT, $out);
    }
    else {
      print $out;
    }

  }
}
