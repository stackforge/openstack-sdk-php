<?php
/**
 * @file
 *
 * A response from a transport.
 */
namespace HPCloud\Transport;

/**
 * A Transport response.
 *
 * When one of the transporters makes a request, it will
 * return one of these as a response. The response is simplified
 * to the needs of the HP Cloud services and isn't a
 * general purpose HTTP response object.
 *
 * The transport wraps three pieces of information:
 *   - The response body.
 *   - The HTTP headers.
 *   - Other metadata about the request.
 *
 * There are two different "modes" for working with a response
 * object. Either you can work with the raw file data directly
 * or you can work with the string content of the file.
 *
 * You should work with the raw file directly (using file())
 * if you are working with large objects, such as those returned
 * from Object Storage.
 *
 * You may prefer to work with string data when you are working
 * with the JSON data returned by the vast majority of requests.
 */
class Response {

  protected $handle;
  protected $metadata;
  protected $headers;

  /**
   * Handle error response.
   *
   * When a response is a failure, it should pass through this function,
   * which generates the appropriate exception and then throws it.
   *
   * @param int $code
   *   The HTTP status code, e.g. 404, 500.
   * @param string $err
   *   The error string, as bubbled up.
   * @param string $uri
   *   The URI.
   * @param string $method
   *   The HTTP method, e.g. 'HEAD', 'GET', 'DELETE'.
   * @param string $extra
   *   An extra string of debugging information. (NOT USED)
   * @throws \HPCloud\Exception
   *   A wide variety of \HPCloud\Transport exceptions.
   */
  public static function failure($code, $err = 'Unknown', $uri = '', $method = '', $extra = '') {

    // syslog(LOG_WARNING, print_r($extra, TRUE));
    switch ($code) {

      case '403':
      case '401':
        throw new \HPCloud\Transport\AuthorizationException($err);
      case '404':
        throw new \HPCloud\Transport\FileNotFoundException($err . " ($uri)");
      case '405':
        throw new \HPCloud\Transport\MethodNotAllowedException($err . " ($method $uri)");
      case '409':
        throw new \HPCloud\Transport\ConflictException($err);
      case '412':
        throw new \HPCloud\Transport\LengthRequiredException($err);
      case '422':
        throw new \HPCloud\Transport\UnprocessableEntityException($err);
      case '500':
        throw new \HPCloud\Transport\ServerException($err);
      default:
        throw new \HPCloud\Exception($err);

    }

  }

  /**
   * Construct a new Response.
   *
   * The Transporter implementations use this to
   * construct a response.
   */
  public function __construct($handle, $metadata, $headers = NULL) {
    $this->handle = $handle;
    $this->metadata = $metadata;

    if (!isset($headers) && isset($metadata['wrapper_data'])) {
      $headers = $metadata['wrapper_data'];
    }

    $this->headers = $this->parseHeaders($headers);
  }

  /**
   * Destroy the object.
   */
  public function __destruct() {
    // There are two issues with fclosing here:
    // 1. file()'s handle can get closed on it.
    // 2. If anything else closes the handle, this generates a warning.

    //fclose($this->handle);
  }

  /**
   * Get the file handle.
   * This provides raw access to the IO stream. Users
   * are responsible for all IO management.
   *
   * Note that if the handle is closed through this object,
   * the handle returned by file() will also be closed
   * (they are one and the same).
   *
   * @return resource
   *   A file handle.
   */
  public function file() {
    return $this->handle;
  }

  /**
   * Get the contents of this response as a string.
   *
   * This returns the body of the response (no HTTP headers)
   * as a single string.
   *
   * IMPORTANT: This can only be called once. HTTP streams
   * handled by PHP's stream wrapper cannot be rewound, and
   * to keep memory usage low, we don't want to store the
   * entire content in a string.
   *
   * @return string
   *   The contents of the response body.
   */
  public function content() {

    // This should always be set... but...
    if (isset($this->metadata['unread_bytes'])) {
      $out = fread($this->handle, $this->metadata['unread_bytes']);
    }
    // XXX: In cases of large files, isn't this the safer default?
    else {
      $out = '';
      while (!feof($this->handle)) {
        $out .= fread($this->handle, 8192);
      }
    }

    // Should we close or rewind?
    // Cannot rewind PHP HTTP streams.
    fclose($this->handle);
    //rewind($this->handle);

    return $out;
  }

  /**
   * Get metadata.
   *
   * This returns any available metadata on the file. Not
   * all Transporters will have any associated metadata.
   * Some return extra information on the processing of the
   * data.
   *
   * @return array
   *   An associative array of metadata about the
   *   transaction resulting in this response.
   */
  public function metadata() {
    return $this->metadata;
  }

  /**
   * Convenience function to retrieve a single header.
   */
  public function header($name, $default = NULL) {
    if (isset($this->headers[$name])) {
      return $this->headers[$name];
    }
    return $default;
  }


  /**
   * Get the HTTP headers.
   *
   * This returns an associative array of all of the
   * headers returned by the remote server.
   *
   * These are available even if the stream has been closed.
   */
  public function headers() {
    return $this->headers;
  }

  /**
   * Get the HTTP status code.
   *
   * This will give the HTTP status codes on successful
   * transactions.
   *
   * A successful transaction is one that does not generate an HTTP
   * error. This does not necessarily mean that the REST-level request
   * was fulfilled in the desired way.
   *
   * Example: Attempting to create a container in object storage when
   * such a container already exists results in a 202 response code,
   * which is an HTTP success code, but indicates failure to fulfill the
   * requested action.
   *
   * Unsuccessful transactions throw exceptions and do not return
   * Response objects. Example codes of this sort: 403, 404, 500.
   *
   * Redirects are typically followed, and thus rarely (if ever)
   * appear in a Response object.
   *
   * @return int
   *   The HTTP code, e.g. 200 or 202.
   */
  public function status() {
    return $this->code;
  }

  /**
   * The server-returned status message.
   *
   * Typically these follow the HTTP protocol specification's
   * recommendations. e.g. 200 returns 'OK'.
   *
   * @return string
   *  A server-generated status message.
   */
  public function statusMessage() {
    return $this->message;
  }

  /**
   * The protocol and version used for this transaction.
   *
   * Example: HTTP/1.1
   *
   * @return string
   *   The protocol name and version.
   */
  public function protocol() {
    return $this->protocol;
  }

  public function __toString() {
    return $this->content();
  }

  /**
   * Parse the HTTP headers.
   *
   * @param array $headerArray
   *   An indexed array of headers, as returned by the PHP stream
   *   library.
   * @return array
   *   An associative array of header name/value pairs.
   */
  protected function parseHeaders($headerArray) {
    $ret = array_shift($headerArray);
    $responseLine = preg_split('/\s/', $ret);

    $count = count($headerArray);
    $this->protocol = $responseLine[0];
    $this->code = (int) $responseLine[1];
    $this->message = $responseLine[2];

    // A CONTINUE response means that we will get
    // a second HTTP status code. Since we have
    // shifted it off, we recurse. Note that 
    // only CURL returns the 100. PHP's stream
    // wrapper eats the 100 for us.
    if ($this->code == 100) {
      return $this->parseHeaders($headerArray);
    }

    $buffer = array();
    //syslog(LOG_WARNING, $ret);
    //syslog(LOG_WARNING, print_r($headerArray, TRUE));

    for ($i = 0; $i < $count; ++$i) {
      list($name, $value) = explode(':', $headerArray[$i], 2);
      $name = filter_var($name, FILTER_SANITIZE_STRING);
      $value = filter_var(trim($value), FILTER_SANITIZE_STRING);
      $buffer[$name] = $value;
    }

    return $buffer;
  }

}
