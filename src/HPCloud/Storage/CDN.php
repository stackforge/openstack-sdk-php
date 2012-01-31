<?php
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
 * - Enable or Disable containers: CDN::enable() and CDN::disable().
 * - Remove a Container from CDN: CDN::delete().
 * - Modify the caching properties of a Container: CDN::update().
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
 * $cdn->disable('myContainer');
 *
 * //This can be re-enabled again:
 * $cdn->enable('myContainer');
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

  /**
   * The URL to the CDN endpoint.
   */
  protected $url;
  /**
   * The authentication/authorization token.
   */
  protected $token;

  /**
   * Create a new CDN object based on a service catalog.
   *
   * This assumes that the appropriate Tenant ID was already set.
   *
   * @param array $catalog
   *   A service catalog; see HPCloud::Services::IdentityServices::serviceCatalog().
   * @param string $token
   *   The token.
   * @retval object
   *   A CDN object or FALSE if no CDN services could be found
   *   in the catalog.
   */
  public static function newFromServiceCatalog($catalog, $token) {
    $c = count($catalog);
    for ($i = 0; $i < $c; ++$i) {
      if ($catalog[$i]['type'] == self::SERVICE_TYPE) {
        foreach ($catalog[$i]['endpoints'] as $endpoint) {
          if (isset($endpoint['publicURL'])) {
            //$parts = parse_url($endpoint['publicURL']);
            //$base = $parts['scheme'] . '://' . $parts['host'];
            //if (isset($parts['port'])) {
              //$base .= ':' . $parts['port'];
            //}
            $base = $endpoint['publicURL'];
            return new CDN($base, $token);
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
   * @param string $endpoint
   *   The URL of the CDN service. It should look something like this:
   *   @code https://cdnmgmt.rndd.aw1.hpcloud.net @endcode
   *   NOT 
   *   @code https://cdnmgmt.rndd.aw1.hpcloud.net/v1.0/72020596871800 @endcode
   * @param string $token
   *   The authentication token. This can be retrieved from IdentityServices::token().
   */
  public function __construct($endpoint, $token/*, $account*/) {
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
   * @retval array
   *   An indexed array of associative arrays. The format of each
   *   associative array is explained on container().
   * @throws HPCloud::Exception
   *   An HTTP-level exception on error.
   */
  public function containers() {
    $client = \HPCloud\Transport::instance();
    $url = $this->url . '/?format=json';
    //$url = 'https://cdnmgmt.rndd.aw1.hpcloud.net/v1.0/';
    $headers = array(
      'X-Auth-Token' => $this->token,
    );

    $response = $client->doRequest($url, 'GET', $headers);

    $raw = $response->content();
    // throw new \Exception($url . ' ' . $raw);
    $json = json_decode($raw, TRUE);

    return $json;
  }

  /**
   * Get a container by name.
   *
   * @fixme The current (1.0) version does not support a verb for getting
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
   * This turns on caching for the specified container.
   *
   * In the CDN API, this one operation accomplishes two different things:
   *
   * - If the container is not in the CDN list, it is added and enabled.
   * - If the container <em>is</em> in the CDN list, it is (re-)enabled.
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
   * @return boolean
   *   TRUE if the container was enabled, FALSE if the container was already
   *   CDN-enabled (and thus nothing happened).
   * @throws HPCloud::Exception
   *   Several HTTP-level exceptions can be thrown.
   */
  public function enable($name, $ttl = NULL) {
    $headers = array();
    if (!empty($ttl)) {
      $headers['X-TTL'] = (int) $ttl;
    }
    $res = $this->modifyContainer($name, 'PUT', $headers);

    // 201 = success, 202 = already enabled.
    return $res->status() == 201;
  }

  /**
   * Set attributes on a CDN container.
   *
   * This updates the attributes (that is, properties) of a container.
   *
   * The following attributes are supported:
   *
   * - 'ttl': Time to life in seconds (int).
   * - 'enabled': Whether the CDN is enabled (boolean).
   * - 'logs': Whether logs are retained (boolean). UNSUPPORTED.
   *
   * @param string $name
   *   The name of the container.
   * @param array $attrs
   *   An associative array of attributes.
   * @retval boolean
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
          $headers['X-CDN-Enabled'] = 'True';
          break;
        case 'logs':
          $headers['X-Log-Retention'] = 'True';
          break;
        default:
          $headers[$item] = (string) $val;
          break;
      }

    }

    $response = $this->modifyContainer($name, 'POST', $headers);

    return $response->status() == 204;
  }

  /**
   * Temporarily disable CDN for a container.
   *
   * This will suspend caching on the named container. It is intended to be a
   * temporary measure. See delete() for completely removing a container from
   * CDN service.
   *
   * @param string $name
   *   The name of the container whose cache should be suspended.
   * @retval boolean
   *   TRUE if the container is disabled.
   * @throws HPCloud::Exception
   *   HTTP exceptions may be thrown if an error occurs.
   */
  public function disable($name) {
    $headers = array('X-CDN-Enabled' => 'False');
    $res = $this->modifyContainer($name, 'POST', $headers);
    return $res->status() == 204;
  }

  /**
   * Attempt to remove a container from CDN.
   *
   * This will remove a container from CDN services,
   * completely stopping all caching on that container.
   *
   * @param string $name
   *   The Container name.
   * @retval boolean
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
    $url = $this->url . '/' . urlencode($name) . $qstring;
    $headers['X-Auth-Token'] = $this->token;

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, $method, $headers);

    return $response;
  }
}
