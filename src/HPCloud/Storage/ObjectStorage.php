<?php
/**
 * @file
 *
 * This file provides the ObjectStorage class, which is the primary
 * representation of the ObjectStorage system.
 *
 * ObjectStorage (aka Swift) is the OpenStack service for providing
 * storage of complete and discrete pieces of data (e.g. an image file,
 * a text document, a binary).
 */

namespace HPCloud\Storage;

/**
 * Access to ObjectStorage (Swift).
 *
 * This is the primary piece of the Object Oriented representation of
 * the Object Storage service. Developers wishing to work at a low level
 * should use this API.
 *
 * There is also a stream wrapper interface that exposes ObjectStorage
 * to PHP's streams system. For common use of an object store, you may
 * prefer to use that system. (See \HPCloud\Bootstrap).
 *
 * When constructing a new ObjectStorage object, you will need to know 
 * what kind of authentication you are going to perform. Older 
 * implementations of OpenStack provide a separate authentication 
 * mechanism for Swift. You can use ObjectStorage::newFromSwiftAuth() to 
 * perform this type of authentication.
 *
 * Newer versions use the Control Services authentication mechanism (see 
 * \HPCloud\Services\ControlServices). That method is the preferred 
 * method.
 */
class ObjectStorage {


  /**
   * Create a new instance after getting an authenitcation token.
   *
   * This uses the legacy Swift authentication facility to authenticate
   * to swift, get a new token, and then create a new ObjectStorage 
   * instance with that token.
   *
   * To use the legacy Object Storage authentication mechanism, you will
   * need the follwing pieces of information:
   *
   * - Account ID: Your account username or ID. For HP Cloud customers,
   *   this is typically a long string of numbers and letters.
   * - Key: Your secret key. For HP Customers, this is a string of
   *   random letters and numbers.
   * - Endpoint URL: The URL given to you by your service provider.
   *
   * HP Cloud users can find all of this information on your Object
   * Storage account dashboard.
   *
   * @param string $account
   *   Your account name.
   * @param string $key
   *   Your secret key.
   * @param string $url
   *   The URL to the object storage endpoint.
   *
   * @throws \HPCloud\Transport\AuthorizationException if the 
   *   authentication failed.
   * @throws \HPCloud\Transport\FileNotFoundException if the URL is
   *   wrong.
   * @throws \HPCloud\Exception if some other exception occurs.
   */
  public static function newFromSwiftAuth($account, $key, $url) {
    $headers = array(
      'X-Auth-User' => $account,
      'X-Auth-Key' => $key,
    );

    $client = \HPCloud\Transport::instance();

    // This will throw an exception if it cannot connect or
    // authenticate.
    $res = $client->doRequest($url, 'GET', $headers);


    // Headers that come back:
    // X-Storage-Url: https://region-a.geo-1.objects.hpcloudsvc.com:443/v1/AUTH_d8e28d35-3324-44d7-a625-4e6450dc1683
    // X-Storage-Token: AUTH_tkd2ffb4dac4534c43afbe532ca41bcdba
    // X-Auth-Token: AUTH_tkd2ffb4dac4534c43afbe532ca41bcdba
    // X-Trans-Id: tx33f1257e09f64bc58f28e66e0577268a


    $token = $res->getHeader('X-Auth-Token');

    $store = new ObjectStorage($token, $url);

    return $store;
  }

  /**
   * The authorization token.
   */
  protected $token = NULL;
  /**
   * The URL to the Swift endpoint.
   */
  protected $url = NULL;

  /**
   * Construct a new ObjectStorage object.
   *
   * @param string $authToken
   *   A token that will be included in subsequent requests to validate
   *   that this client has authenticated correctly.
   */
  public function __construct($authToken, $url) {
    $this->token = $authToken;
    $this->url = $url;
  }

  /**
   * Get the authentication token.
   *
   * @return string
   *   The authentication token.
   */
  public function getAuthToken() {
    return $this->token;
  }

  /**
   * Get the URL endpoint.
   *
   * @return string
   *   The URL that is the endpoint for this service.
   */
  public function getUrl() {
    return $this->url;
  }
}
