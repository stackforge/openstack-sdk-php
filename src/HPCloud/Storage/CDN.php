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
            return new CDN($endpoint['publicURL'], $token);
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
   *   @code https://cdnmgmt.rndd.aw1.hpcloud.net/v1.0/72020596871800 @endcode
   * @param string $token
   *   The authentication token. This can be retrieved from IdentityServices::token().
   */
  public function __construct($endpoint, $token) {
    $this->url = $endpoint;
    $this->token = $token;
  }

}
