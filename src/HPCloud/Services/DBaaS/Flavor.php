<?php
/* ============================================================================
(c) Copyright 2013 Hewlett-Packard Development Company, L.P.
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
 * This file contains the Database Flavor class.
 */

namespace HPCloud\Services\DBaaS;

use \HPCloud\Transport;

/**
 * Class for working with Database Flavors.
 */
class Flavor extends Operations {

	protected $token;
  protected $projectId;
  protected $url;
  protected $client;

	public function __construct($token, $projectId, $endpoint) {
		$this->token = $token;
    $this->projectId = $projectId;
    $this->url = $endpoint;
    $this->client = Transport::instance();
	}

	/**
   * Retrieve a list of available instance flavors.
   *
   * @retval array
   * @return array
   *   An array of \HPCloud\Service\DBaaS\Flavor objects listing the available
   *   flavors.
   */
  public function listFlavors() {
    $url = $this->url . '/flavors';
    $res = $this->client->doRequest($url, 'GET', $this->headers());
    $json = json_decode($res->content(), TRUE);

    $list = array();
    foreach ($json['flavors'] as $instance) {
      $list[] = FlavorDetails::newFromArray($instance);
    }

    return $list;
  }
}