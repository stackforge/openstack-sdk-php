<?php
/**
 * @file
 * Implements a transporter with the PHP HTTP Stream Wrapper.
 */

namespace HPCloud\Transport;

/**
 * Provide HTTP transport with the PHP HTTP stream wrapper.
 */
class PHPStreamTransport implements Transporter {

  public function doRequest($uri, $method = 'GET', $headers = array(), $body = '') {

  }

}
