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
 *
 * This file contains the main Database as a Service class.
 */

namespace HPCloud\Services;

use \HPCloud\Services\DBaaS\Instance;
use \HPCloud\Services\DBaaS\Snapshot;

class DBaaS {

  /**
   * The auth token for the current session.
   */
  protected $token;
  /**
   * The base URL to the DBaaS for a given account.
   */
  protected $url;
  /**
   * The tenant name.
   *
   * Typically, this is an email address.
   */
  protected $projectId;

  public static function newFromIdentity($identity) {

    $endpoint = 'https://region-a.geo-1.dbaas-mysql.hpcloudsvc.com:443/v1.0/' . $identity->tenantId();
    $dbaas = new DBaaS($identity->token(), $endpoint, $identity->tenantName());

    return $dbaas;
    /*
    return self::newFromServiceCatalog(
      $identity->serviceCatalog(),
      $identity->token(),
      $identity->tenantName()
    );
     */
  }

  public static function newFromServiceCatalog($catalog, $token, $projectId) {
    // FIXME: Temporary until DBaaS lands in the service catalog.
    $endpoint = 'https://region-a.geo-1.dbaas-mysql.hpcloudsvc.com:443/v1.0/';
    return new DBaaS($token, $endpoint, $projectId);
  }

  /**
   * Build a new DBaaS object.
   *
   * @param string $token
   *   The auth token from identity services.
   * @param string $endpoint
   *   The endpoint URL, typically from IdentityServices.
   * @param string $projectId
   *   The project ID. Typically, this is the tenant name.
   */
  public function __construct($token, $endpoint, $projectId) {
    $this->token = $token;
    $this->url= $endpoint;
    $this->projectId = $projectId;
  }


  public function instance() {
    return new Instance($this->token, $this->projectId, $this->url);
  }

  public function snapshot() {
    return new Snapshot($this->token, $this->projectId, $this->url);
  }

  /**
   * Get the project ID for this session.
   *
   * @retval string
   *   The project ID.
   */
  public function projectId() {
    return $this->projectId;
  }

  /**
   * Get the endpoint URL to the DBaaS session.
   *
   * @retval string
   *   The URL.
   */
  public function url() {
    return $this->url;
  }
}
