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
   * Construct a new Response.
   *
   * The Transporter implementations use this to
   * construct a response.
   */
  public function __construct($handle, $metadata) {
    $this->handle = $handle;
    $this->metadata = $metadata;
    $this->headers = $this->parseHeaders($metadata['wrapper_data']);
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
   * @return string
   *   The contents of the response body.
   */
  public function content() {
    $out = fread($this->handle, $this->metadata['unread_bytes']);

    // Should we close or rewind?
    fclose($this->handle);

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
    $count = count($headerArray);

    $buffer = array();

    // Skip the HTTP header.
    for ($i = 1; $i < $count; ++$i) {
      list($name, $value) = explode(':', $headerArray[$i], 2);
      $name = filter_var($name, FILTER_SANITIZE_STRING);
      $value = filter_var(trim($value), FILTER_SANITIZE_STRING);
      $buffer[$name] = $value;
    }

    return $buffer;
  }

}
