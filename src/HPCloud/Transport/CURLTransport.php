<?php
/**
 * @file
 * Implements a transporter with CURL.
 */

namespace HPCloud\Transport;

/**
 * Provide HTTP transport with CURL.
 *
 * You should choose the Curl backend if...
 *
 * - You KNOW Curl support is compiled into your PHP version
 * - You do not like the built-in PHP HTTP handler
 * - Performance is a big deal to you
 * - You will be sending large objects (>2M)
 * - Or PHP stream wrappers for URLs are not supported on your system.
 *
 * CURL is demonstrably faster than the built-in PHP HTTP handling, so
 * ths library gives a performance boost. Error reporting is slightly
 * better too.
 *
 * But the real strong point to Curl is that it can take file objects
 * and send them over HTTP without having to buffer them into strings
 * first. This saves memory and processing.
 *
 * The only downside to Curl is that it is not available on all hosts.
 * Some installations of PHP do not compile support.
 */
class CURLTransport implements Transporter {

  public function doRequest($uri, $method = 'GET', $headers = array(), $body = '') {

    $in = NULL;
    if (!empty($body)) {
      // First we turn our body into a temp-backed buffer.
      $in = fopen('php://temp', 'wr', FALSE);
      fwrite($in, $body, strlen($body));
      rewind($in);
    }
    return $this->handleDoRequest($uri, $method, $headers, $in);

  }

  public function doRequestWithResource($uri, $method, $headers, $resource) {
    if (is_string($resource)) {
      $in = open($resource, 'rb', FALSE);
    }
    else {
      $in = $resource;
    }
    return $this->handleDoRequest($uri, $method, $headers, $resource);
  }

  /**
   * Internal workhorse.
   */
  protected function handleDoRequest($uri, $method, $headers, $in = NULL) {


    // Write to in-mem handle backed by a temp file.
    $out = fopen('php://temp', 'w');

    $curl = curl_init($uri);

    // Set method
    $this->determineMethod($curl, $method);

    // Set headers
    $this->setHeaders($curl, $headers);

    // Set the upload
    if (!empty($in)) {
      curl_setopt($curl, CURLOPT_INFILE, $in);
    }

    // Get the output.
    curl_setopt($curl, CURLOPT_FILE, $out);

    curl_exec($curl);

    // Now we need to build a response.
    // Option 1: Subclass response.
    // Option 2: Build an adapter.

    curl_close($curl);

    fclose($in);
  }

  /**
   * Set the appropriate constant on the CURL object.
   *
   * Curl handles method name setting in a slightly counter-intuitive 
   * way, so we have a special function for setting the method 
   * correctly. Note that since we do not POST as www-form-*, we 
   * use a custom post.
   *
   * @param resource $curl
   *   A curl object.
   * @param string $method
   *   An HTTP method name.
   */
  protected function determineMethod($curl, $method) {
    $method = strtoupper($method);

    switch ($method) {
      case 'GET':
        curl_setopt($curl, CURLOPT_HTTPGET, TRUE);
        break;
      case 'HEAD':
        curl_setopt($curl, CURLOPT_NOBODY, TRUE);
        break;

      // Put is problematic: Some PUT requests might not have
      // a body.
      case 'PUT':
        curl_setopt($curl, CURLOPT_PUT, TRUE);
        break;

      // We use customrequest for post because we are
      // not submitting form data.
      case 'POST':
      case 'DELETE':
      case 'COPY':
      default:
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, TRUE);
    }

  }

  public function setHeaders($curl, $headers) {
    $buffer = array();
    $format = '%s: %s';

    foreach ($headers as $name => $value) {
      $buffer[] = sprintf($format, $name, $value);
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $buffer);
  }

}
