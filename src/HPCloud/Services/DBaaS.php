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
use \HPCloud\Services\DBaaS\Flavor;

/**
 * Database As A Service.
 *
 * This package provides access to HP Cloud's Database As A Service (DBaaS)
 * service.
 *
 * ## About DBaaS
 *
 * DBaaS is a service for creating and managing database instances and
 * snapshots (backups) of that service. It is not a data access layer.
 * That is, SQL is not proxied through this service. Rather, once a
 * database instance is set up, apps may connect directly to that service
 * using built-in drivers. See, for example,
 * HPCloud::Services::DBaaS::Instance::dsn(), which returns the PDO DSN for
 * connecting to a MySQL database.
 *
 * To create and manage new database servers, you will work with
 * HPCloud::Services::DBaaS::instance().
 *
 * To take snapshots of an existing database server (which is recommended
 * at regular intervals), you will work with 
 * HPCloud::Services::DBaaS::snapshot().
 *
 * ## Authentication to the Service
 *
 * To authenticate to the service, you will use IdentityServices. Note,
 * however, that DBaaS requires a Tenant Name (not Tenant ID). After
 * authentication, this will attempt to retrieve the name from IdentityServices.
 *
 * ## Authentication to an Instance
 *
 * Upon creating a new database instance, this library will return login
 * credentials (username and password) from
 * HPCloud::Services::DBaaS::Instance::create(). <i>This is the only time
 * that credentials are returned</i>. Make note of them.
 *
 * Those credentials can then be used to connect to the database instance,
 * and create new databases, users, and tables.
 */
class DBaaS {

  const SERVICE_TYPE = 'hpext:dbaas';

  const API_VERSION = '1';

  const DEFAULT_REGION = 'region-a.geo-1';

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

  public static function newFromIdentity($identity, $region = DBaaS::DEFAULT_REGION) {

    $catalog = $identity->serviceCatalog();

    $c = count($catalog);
    for ($i = 0; $i < $c; ++$i) {
      if ($catalog[$i]['type'] == self::SERVICE_TYPE) {
        foreach ($catalog[$i]['endpoints'] as $endpoint) {
          if (isset($endpoint['publicURL']) && $endpoint['region'] == $region) {
            $dbaas = new DBaaS($identity->token(), $endpoint['publicURL'], $identity->tenantName());

            return $dbaas;
          }
        }
      }
    }
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

  public function flavor() {
    return new Flavor($this->token, $this->projectId, $this->url);
  }

  /**
   * Get the project ID for this session.
   *
   * @retval string
   * @return string
   *   The project ID.
   */
  public function projectId() {
    return $this->projectId;
  }

  /**
   * Get the endpoint URL to the DBaaS session.
   *
   * @retval string
   * @return string
   *   The URL.
   */
  public function url() {
    return $this->url;
  }
}
