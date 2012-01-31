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
 * proper.
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
  }

  /**
   * Enable a container.
   *
   * This turns on caching for the specified container.
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
  public function enable($name, $ttl = 3600) {
    $url = $this->url . '/' . urlencode(rtrim($name, '/'));
    $headers = array(
      'X-Auth-Token' => $this->token,
      'X-TTL' => (int) $ttl,
    );

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, 'PUT', $headers);

    // 201 = success, 202 = already enabled.
    return $response->status() == 201;
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
    $url = $this->url . '/' . urlencode($name);
    $headers = array(
      'X-Auth-Token' => $this->token,
    );

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, 'DELETE', $headers);

    return $response->status() == 204;
  }

}
