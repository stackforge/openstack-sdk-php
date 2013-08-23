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
 * This file contains the CDN (Content Distribution Network) class.
 */

namespace HPCloud\Storage;

/**
 * Provides CDN services for ObjectStorage.
 *
 * CDN stands for "Content Distribution Network." It provides distributed
 * caching in the cloud. When a Container is CDN-enabled, objects will be
 * stored not @em just in the ObjectStorage, but temporary cached copies
 * will be stored on servers around the world.
 *
 * @attention
 * Caches are not protected by authentication. If you store an object in
 * a CDN, cached versions of that object are publically accessible. Setting
 * an ACL will have very little impact on this.
 *
 * CDN is an HPCloud extension service, and is not presently part of OpenStack
 * proper. The current REST API documentation can be found at
 * http://api-docs.hpcloud.com/
 *
 * <b>Usage</b>
 *
 * The CDN service functions as an <i>add-on</i> to ObjectStorage. It adds a
 * caching layer. So terms used here, such as Container and Object, refer
 * to the ObjectStorage items.
 *
 * For the most part, CDN operates on the Container level. You can choose to
 * tell the CDN about a particular Container in ObjectStorage, and it will
 * cache items in that container.
 *
 * The CDN service keeps a list of ObjectStorage Containers that it knows
 * about. CDN does not automatically discover ObjectStorage Containers; you
 * must tell CDN about the ObjectStorage instances you want it to know
 * about. This is done using CDN::enable().
 *
 * Once the CDN service knows about an ObjectStorage Container, it will
 * begin caching objects in that container.
 *
 * This library gives the the ability to do the following:
 *
 * - List the containers that CDN knows about: CDN::containers()
 * - Retrieve the CDN properties for a particular Container: CDN::container().
 * - Add and enable containers: CDN::enable().
 * - Remove a Container from CDN: CDN::delete().
 * - Modify the caching properties of a Container: CDN::update().
 * - Temporarily enable or disable caching for a container with
 *   CDN::update().
 *
 * <b>Example</b>
 *
 * @code
 * <?php
 * // Authentication info:
 * $endpoint = 'https://auth.example.com';
 * $username = 'butcher@hp.com';
 * $password = 'secret';
 * $tenantId = '123456789';
 *
 * // First we need to authenticate:
 * $identity = new \HPCloud\Services\IdentityServices($endpoint);
 * $token = $identity->authenticateAsUser($username, $password, $tenantId);
 *
 * // Get the service catalog. We will try to have CDN build itself from
 * // the service catalog.
 * $catalog = $identity->serviceCatalog();
 *
 * // Get a new CDN instance:
 * $cdn = CDN::newFromServiceCatalog($catalog, $token);
 *
 * // Add a container to CDN; set cache lifetime to an hour:
 * $cdn->enable('myContainer', 3600);
 *
 * // Get a list of all containers that CDN knows about,
 * // and print cache lifetime for each:
 * foreach ($cdn->containers() as $container) {
 *   print $container['name'] . ':' . $container['ttl'] . PHP_EOL;
 * }
 *
 * // Change the cache lifetime on our container
 * $cdn->update('myContainer', array('ttl' => 7200));
 *
 * // Temporarily stop the container from caching:
 * $cdn->update('myContainer', array('cdn_enabled' => FALSE);
 *
 * //This can be re-enabled again:
 * $cdn->update('myContainer', array('cdn_enabled' => TRUE);
 *
 * // If we no longer want this Container in CDN, we
 * // should delete it, not just disable it:
 * $cdn->delete('myContainer');
 *
 * ?>
 * @endcode
 */
class CDN {

  /**
   * The name of the CDN service type.
   */
  const SERVICE_TYPE = 'hpext:cdn';
  /**
   * The API version.
   */
  const API_VERSION = '1.0';

  const DEFAULT_REGION = 'region-a.geo-1';

  /**
   * The URL to the CDN endpoint.
   */
  protected $url;
  /**
   * The authentication/authorization token.
   */
  protected $token;

  /**
   * Create a new instance from an IdentityServices object.
   *
   * This builds a new CDN instance form an authenticated
   * IdentityServices object.
   *
   * In the service catalog, this selects the first service entry
   * for CDN. At this time, that is sufficient.
   *
   * @param HPCloud::Services::IdentityServices $identity
   *   The identity to use.
   * @retval boolean
   * @retval HPCloud::Storage::CDN
   * @return \HPCloud\Storage\CDN|boolean
   *   A CDN object or FALSE if no CDN services could be found
   *   in the catalog.
   */
  public static function newFromIdentity($identity, $region = CDN::DEFAULT_REGION) {
    $tok = $identity->token();
    $cat = $identity->serviceCatalog();

    return self::newFromServiceCatalog($cat, $tok, $region);
  }

  /**
   * Create a new CDN object based on a service catalog.
   *
   * The IdentityServices class contains a service catalog, which tracks all
   * services that the present account can access. The service catalog
   * contains data necessary to connect to a CDN endpoint. This builder
   * simplifies the process of creating a new CDN by accepting a service
   * catalog and discovering the CDN service automatically.
   *
   * In the vast majority of cases, this is the easiest way to proceed. If,
   * however, a service catalog has multiple CDN instances (a possibility,
   * though not currently supported), the present method has no means of
   * determining which should be used. It simply chooses the first CDN
   * service endpoint.
   *
   * This uses the tenant ID that is found in the service catalog.
   *
   * Either of the following work:
   * @code
   * <?php
   *
   * // Use a full service catalog:
   * $fullCatalog = $identityService->serviceCatalog();
   * $cdn = CDN::newFromServiceCatalog($fullCatalog);
   *
   * // Use a filtered service catalog:
   * $catalog = $identitySerice->serviceCatalog(CDN::SERVICE_TYPE);
   * $cdn = CDN::newFromServiceCatalog($catalog);
   * ?>
   * @endcode
   *
   * @param array $catalog
   *   A service catalog; see HPCloud::Services::IdentityServices::serviceCatalog().
   * @param string $token
   *   The token.
   * @retval boolean
   * @retval HPCloud::Storage::CDN
   * @return boolean|\HPCloud\Storage\CDN
   *   A CDN object or FALSE if no CDN services could be found
   *   in the catalog.
   */
  public static function newFromServiceCatalog($catalog, $token, $region = CDN::DEFAULT_REGION) {
    $c = count($catalog);
    for ($i = 0; $i < $c; ++$i) {
      if ($catalog[$i]['type'] == self::SERVICE_TYPE) {
        foreach ($catalog[$i]['endpoints'] as $endpoint) {
          if (isset($endpoint['publicURL']) && $endpoint['region'] == $region) {
            /*
            $parts = parse_url($endpoint['publicURL']);
            $base = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
              $base .= ':' . $parts['port'];
            }
            //$base = $endpoint['publicURL'];
            $cdn = new CDN($token, $base, $endpoint['tenantId']);
            //$cdn->url = $endpoint['publicURL'];
             */
            $cdn = new CDN($token, $endpoint['publicURL']);

            return $cdn;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Build a new CDN object.
   *
   * This object facilitates communication with the CDN cloud service.
   *
   * This creates a new CDN object that will view as its endpoint the server
   * with the URL $endpoint, which has the form:
   *
   * @code
   * https://ENDPOINT/API_VERSION/ACCOUNT
   * @endcode
   *
   *
   * On older SwiftAuth-based services, the token should be the swauth token.
   * On newer releaes, the token is retrieved from IdentityServices.
   *
   * @param string $endpoint
   *   The URL of the CDN service. It should look something like this:
   *   @c https://cdnmgmt.rndd.aw1.hpcloud.net/v1.0/72020596871800
   * @param string $token
   *   The authentication token. This can be retrieved from IdentityServices::token().
   */
  public function __construct($token, $endpoint/*, $account*/) {
    //$this->url = $endpoint . '/v' . self::API_VERSION . '/' . $account;
    $this->url = $endpoint;
    $this->token = $token;
  }

  /**
   * Get a list of containers that the CDN system knows of.
   *
   * This returns a list of ObjectStorage Containers that the
   * CDN service knows of. These containers can be either enabled or
   * disabled.
   *
   * The CDN service does not attempt to discover all of the containers
   * from a Swift endpoint. Instead, it passively acquires a list of
   * containers (added via, for example, enabledContainer()).
   *
   * Once a container has been added to the CDN service, it can be in
   * one of two states:
   *
   * - enabled (\c cdn_enabled=TRUE)
   * - disabled (\c cdn_enabled=FALSE)
   *
   * This listing will retrieve both enabled and disabled unless
   * $enabledOnly is set to TRUE.
   *
   * Returned data is in this format:
   * @code
   * <?php
   * array(
   *   array(
   *     'log_retention' => 0
   *     'cdn_enabled' => 1
   *     'name' => 'I♡HPCloud'
   *     'x-cdn-uri' => 'http://hcf937838.cdn.aw1.hpcloud.net'
   *     'x-cdn-ssl-uri' => 'https://hcf937838.cdn.aw1.hpcloud.net'
   *     'ttl' => 1234
   *   ),
   *   array(
   *     'log_retention' => 0
   *     'cdn_enabled' => 0
   *     'name' => 'HPCloud2'
   *     'x-cdn-uri' => 'http://hcf9abc38.cdn.aw1.hpcloud.net'
   *     'x-cdn-ssl-uri' => 'https://hcf937838.cdn.aw1.hpcloud.net'
   *     'ttl' => 1234
   *   ),
   * );
   * ?>
   * @endcode
   *
   * @attention
   * The $enabledOnly flag sendes \c enabled_only to the
   * endpoint. The endpoint may or may not honor this.
   *
   * @param boolean $enabledOnly
   *   If this is set to TRUE, then only containers that are
   *   CDN-enabled will be returned.
   * @retval array
   * @return array
   *   An indexed array of associative arrays. The format of each
   *   associative array is explained on container().
   * @throws HPCloud::Exception
   *   An HTTP-level exception on error.
   */
  public function containers($enabledOnly = NULL) {
    $client = \HPCloud\Transport::instance();
    $url = $this->url . '/?format=json';

    if ($enabledOnly) {
      $url .= '&enabled_only=true';
    }
    // DEVEX-1733 suggests that this should result in the
    // server listing only DISABLED containers.
    elseif ($enabledOnly === FALSE) {
      $url .= '&enabled_only=false';
    }

    $headers = array(
      'X-Auth-Token' => $this->token,
    );

    $response = $client->doRequest($url, 'GET', $headers);

    $raw = $response->content();
    $json = json_decode($raw, TRUE);

    return $json;
  }

  /**
   * Get a container by name.
   *
   * @todo The current (1.0) version does not support a verb for getting
   * just one container, so we have to get the entire list of containers.
   *
   * Example return value:
   * @code
   * <?php
   * array(
   *   'log_retention' => 1
   *   'cdn_enabled' => 1
   *   'name' => 'I♡HPCloud'
   *   'x-cdn-uri' => 'http://hcf937838.cdn.aw1.hpcloud.net'
   *   'ttl' => 1234
   * );
   * ?>
   * @endcode
   *
   * @param string $name
   *   The name of the container to fetch.
   * @retval array
   * @return array
   *   An associative array in the exact format as in containers.
   */
  public function container($name) {
    //$result = $this->modifyContainer($name, 'GET', array(), '?format=json');

    $containers = $this->containers();
    foreach ($containers as $container) {
      if ($container['name'] == $name) {
        return $container;
      }
    }
    return FALSE;
  }

  /**
   * Enable a container.
   *
   * This adds the container to the CDN service and turns on caching.
   *
   * In the CDN API, there are two meanings for the term "enable":
   *
   * 1. To "CDN-enable" a container means to add that container to the CDN
   * service. There is no "CDN-disable".
   * 2. To "enable" a container means to cache that container's
   * content in a publically available CDN server. There is also a
   * way to "disable" in this sense -- which blocks a container from 
   * caching.
   *
   * This method does the first -- it adds a container to the CDN
   * service. It so happens that adding a container also enables (in the
   * second sense) the
   * container. (This is a feature of the remote service, not the API).
   *
   * Enabling and disabling (in the second sense) are considered temporary operations
   * to switch on and off caching on a particular container. Both of
   * these operations are done with the update() method.
   *
   * The endpoint is supposed to return different results based on the above;
   * accordingly this method should return TRUE if the container was added
   * to the list, and FALSE if it was already there. HOWEVER, in some versions
   * of the CDN service the endpoint returns the same code for both operations,
   * so the result cannot be relied upon.
   *
   * @param string $name
   *   The name of the container.
   * @param int $ttl
   *   Time to live.
   *   The number of seconds an object may stay in the cache. This is the
   *   maximum amount of time. There is, however, no assurance that the object
   *   will remain for the full TTL. 15 minutes is the minimum time. Five years
   *   is the max.
   * @param boolean $created
   *   If this is passed, then its value will be set to TRUE if the
   *   container was created in the CDN, or FALSE if the container
   *   already existed in CDN.
   * @retval string
   * @return string
   *   TRUE if the container was created, FALSE if the container was already
   *   added to the CDN (and thus nothing happened).
   * @throws HPCloud::Exception
   *   Several HTTP-level exceptions can be thrown.
   * @see http://api-docs.hpcloud.com/hpcloud-cdn-storage/1.0/content/cdn-enable-container.html
   */
  public function enable($name, $ttl = NULL, &$created = FALSE) {
    $headers = array();
    if (!empty($ttl)) {
      $headers['X-TTL'] = (int) $ttl;
    }
    $res = $this->modifyContainer($name, 'PUT', $headers);
    $created = $res->status() == 201;

    $url = $res->header('X-Cdn-Uri', 'UNKNOWN');
    return $url;
  }

  /**
   * Set attributes on a CDN container.
   *
   * This updates the attributes (that is, properties) of a container.
   *
   * The following attributes are supported:
   *
   * - 'ttl': Time to life in seconds (int).
   * - 'cdn_enabled': Whether the CDN is enabled (boolean).
   * - 'log_retention': Whether logs are retained (boolean). UNSUPPORTED.
   *
   * Future versions of the CDN service will likely provide other
   * properties.
   *
   * @param string $name
   *   The name of the container.
   * @param array $attrs
   *   An associative array of attributes.
   * @retval boolean
   * @return boolean
   *   TRUE if the update was successful.
   * @throws HPCloud::Exception
   *   Possibly throws one of the HTTP exceptions.
   */
  public function update($name, $attrs) {

    $headers = array();
    foreach ($attrs as $item => $val) {
      switch ($item) {
        case 'ttl':
          $headers['X-TTL'] = (int) $val;
          break;
        case 'enabled':
        case 'cdn_enabled':
          if (isset($val) && $val == FALSE) {
            $flag = 'False';
          }
          // Default is TRUE.
          else {
            $flag = 'True';
          }
          $headers['X-CDN-Enabled'] = $flag;
          break;
        case 'logs':
        case 'log_retention':
          // The default is TRUE.
          if (isset($val) && $val == FALSE) {
            $flag = 'False';
          }
          else {
            $flag = 'True';
          }
          $headers['X-Log-Retention'] = $flag;
          break;
        default:
          $headers[$item] = (string) $val;
          break;
      }

    }

    $response = $this->modifyContainer($name, 'POST', $headers);

    return $response->status() == 204;
  }

  /*
   * Temporarily disable CDN for a container.
   *
   * This will suspend caching on the named container. It is intended to be a
   * temporary measure. See delete() for completely removing a container from
   * CDN service.
   *
   * Disabled items will still show up in the list returned by containers(),
   * and will also be retrievable via container().
   *
   * @param string $name
   *   The name of the container whose cache should be suspended.
   * @retval boolean
   * @return boolean
   *   TRUE if the container is disabled.
   * @throws HPCloud::Exception
   *   HTTP exceptions may be thrown if an error occurs.
   */
  /*
  public function disable($name) {
    $headers = array('X-CDN-Enabled' => 'False');
    $res = $this->modifyContainer($name, 'POST', $headers);
    return $res->status() == 204;
  }
   */

  /**
   * Attempt to remove a container from CDN.
   *
   * This will remove a container from CDN services,
   * completely stopping all caching on that container.
   *
   * Deleted containers will no longer show up in the containers()
   * list, nor will they be accessible via container().
   *
   * Deleted containers can be added back with enable().
   *
   * @param string $name
   *   The Container name.
   * @retval boolean
   * @return boolean
   *   TRUE if the container was successfully deleted,
   *   FALSE if the container was not removed, but no
   *   error occurred.
   * @throws HPCloud::Exception
   *   Any of the HTTP error subclasses can be thrown.
   */
  public function delete($name) {
    $res = $this->modifyContainer($name, 'DELETE');
    return $res->status() == 204;
  }

  /**
   * Run the given method on the given container.
   *
   * Checks to see if the expected result is returned.
   *
   * @param string $name
   *   The name of the container.
   * @param string $method
   *   The appropriate HTTP verb.
   * @param int $expects
   *   The expected HTTP code.
   */
  protected function modifyContainer($name, $method, $headers = array(), $qstring = '') {
    $url = $this->url . '/' . rawurlencode($name) . $qstring;
    $headers['X-Auth-Token'] = $this->token;

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, $method, $headers);

    return $response;
  }
}
