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
 * This file contains the main IdentityServices class.
 */

namespace HPCloud\Services;

/**
 * IdentityServices provides authentication and authorization.
 *
 * IdentityServices (a.k.a. Keystone) provides a central service for managing
 * other services. Through it, you can do the following:
 *
 * - Authenticate
 * - Obtain tokens valid accross services
 * - Obtain a list of the services currently available with a token
 * - Associate with tenants using tenant IDs.
 *
 * @b AUTHENTICATION
 *
 * The authentication process consists of a single transaction during which the
 * client (us) submits credentials and the server verifies those credentials,
 * returning a token (for subsequent requests), account information, and the
 * service catalog.
 *
 * Authentication credentials:
 *
 * - Username and password
 * - Account ID and Secret Key
 *
 * Other mechanisms may be supported in the future.
 *
 * @b TENANTS
 *
 * Services are associated with tenants. A token is returned when
 * authentication succeeds. It *may* be associated with a tenant. If it is not,
 * it is called "unscoped", and it will not have access to any services.
 *
 * A token that is associated with a tenant is considered "scoped". This token
 * can be used to access any of the services attached to that tenant.
 *
 * There are two different ways to attach a tenant to a token:
 *
 * - During authentication, provide a tenant ID. This will attach a tenant at
 *   the outset.
 * - After authentication, "rescope" the token to attach it to a tenant. This
 *   is done with the rescope() method.
 *
 * <b>Where do I get a tenant ID?</b>
 *
 * There are two notable places to get this information:
 *
 * A list of tenants associated with this account can be obtain programatically
 * using the tenants() method on this object.
 *
 * HPCloud customers can find their tenant ID in the console along with their
 * account ID and secret key.
 *
 * @b EXAMPLE
 *
 * The following example illustrates typical use of this class.
 *
 * @code
 * <?php
 * // You may need to use \HPCloud\Bootstrap to set things up first.
 *
 * use \HPCloud\Services\IdentityServices;
 *
 * // Create a new object with the endpoint URL (no version number)
 * $ident = new IdentityServices('https://example.com:35357');
 *
 * // Authenticate and set the tenant ID simultaneously.
 * $ident->authenticateAsUser('butcher@hp.com', 'password', '1234567');
 *
 * // The token to use when connecting to other services:
 * $token = $ident->token();
 *
 * // The tenant ID.
 * $tenant = $ident->tenantId();
 *
 * // Details about what services this token can access.
 * $services = $ident->serviceCatalog();
 *
 * // List all available tenants.
 * $tenants = $ident->tenants();
 *
 * // Switch to a different tenant.
 * $ident->rescope($tenants[0]['id']);
 *
 * ?>
 * @endcode
 *
 * <b>PERFORMANCE CONSIDERATIONS</b>
 *
 * The following methods require network requests:
 *
 * - authenticate()
 * - authenticateAsUser()
 * - authenticateAsAccount()
 * - tenants()
 * - rescope()
 *
 * <b>Serializing</b>
 *
 * IdentityServices has been intentionally built to serialize well.
 * This allows implementors to cache IdentityServices objects rather
 * than make repeated requests for identity information.
 *
 */
class IdentityServices /*implements Serializable*/ {
  /**
   * The version of the API currently supported.
   */
  const API_VERSION = '2.0';
  /**
   * The full OpenStack accept type.
   */
  const ACCEPT_TYPE = 'application/json';
  // This is no longer supported.
  //const ACCEPT_TYPE = 'application/vnd.openstack.identity+json;version=2.0';

  /**
   * The URL to the CS endpoint.
   */
  protected $endpoint;

  /**
   * The details sent with the token.
   *
   * The exact details of this array will differ depending on what type of
   * authentication is used. For example, authenticating by username and
   * password will set tenant information. Authenticating by account ID and
   * secret, however, will leave the tenant section empty.
   *
   * This is an associative array looking like this:
   *
   * @code
   * <?php
   * array(
   *   'id' => 'auth_123abc321defef99',
   *   // Only non-empty for username/password auth.
   *   'tenant' => array(
   *     'id' => '123456',
   *     'name' => 'matt.butcher@hp.com',
   *   ),
   *   'expires' => '2012-01-24T12:46:01.682Z'
   * );
   * @endcode
   */
  protected $tokenDetails;

  /**
   * The service catalog.
   */
  protected $catalog = array();

  protected $userDetails;

  /**
   * Build a new IdentityServices object.
   *
   * Each object is bound to a particular identity services endpoint.
   *
   * For the URL, you are advised to use the version <i>without</i> a
   * version number at the end, e.g. http://cs.example.com/ rather
   * than http://cs.example.com/v2.0. The version number must be
   * controlled by the library.
   *
   * @attention
   * If a version is included in the URI, the library will attempt to use
   * that URI.
   *
   * @code
   * <?php
   * $cs = new \HPCloud\Services\IdentityServices('http://example.com');
   * $token = $cs->authenticateAsAccount($accountId, $accessKey);
   * ?>
   * @endcode
   *
   * @param string $url
   *   An URL pointing to the Identity Services endpoint. Note that you do
   *   not need the version identifier in the URL, as version information
   *   is sent in the HTTP headers rather than in the URL. <b>The URL
   *   should <i>always</i> be to an SSL/TLS encrypted endpoint.</b>.
   */
  public function __construct($url) {
    $parts = parse_url($url);

    if (!empty($parts['path'])) {
      $this->endpoint = rtrim($url, '/');
    }
    else {
      $this->endpoint = rtrim($url, '/') . '/v' . self::API_VERSION;
    }
  }

  /**
   * Get the endpoint URL.
   *
   * This includes version number, so in that regard it is not an identical
   * URL to the one passed into the constructor.
   *
   * @retval string
   * @return string
   *   The complete URL to the identity services endpoint.
   */
  public function url() {
    return $this->endpoint;
  }

  /**
   * Send an authentication request.
   *
   * @remark EXPERT: This allows authentication requests at a low level. For simple
   * authentication requests using account number or username, see the
   * authenticateAsUser() and authenticateAsAccount() methods.
   *
   * Here is an example of username/password-based authentication done with
   * the authenticate() method:
   * @code
   * <?php
   * $cs = new \HPCloud\Services\IdentityServices($url);
   * $ops = array(
   *   'passwordCredentials' => array(
   *     'username' => $username,
   *     'password' => $password,
   *   ),
   *   'tenantId' => $tenantId,
   * );
   * $token = $cs->authenticate($ops);
   * ?>
   * @endcode
   *
   * Note that the same authentication can be done by authenticateAsUser().
   *
   * @param array $ops
   *   An associative array of authentication operations and their respective
   *   parameters.
   * @retval string
   * @return string
   *   The token. This is returned for simplicity. The full response is used
   *   to populate this object's service catalog, etc. The token is also
   *   retrievable with token().
   * @throws HPCloud::Transport::AuthorizationException
   *   If authentication failed.
   * @throws HPCloud::Exception
   *   For abnormal network conditions. The message will give an indication as
   *   to the underlying problem.
   */
  public function authenticate(array $ops) {
    $url = $this->url() . '/tokens';
    $envelope = array(
      'auth' => $ops,
    );

    $body = json_encode($envelope);

    $headers = array(
      'Content-Type' => 'application/json',
      'Accept' => self::ACCEPT_TYPE,
      'Content-Length' => strlen($body),
    );

    //print $body . PHP_EOL;

    $client = \HPCloud\Transport::instance();

    $response = $client->doRequest($url, 'POST', $headers, $body);

    $this->handleResponse($response);


    return $this->token();
  }

  /**
   * Authenticate to Identity Services with username, password, and either 
   * tenant ID or tenant Name.
   *
   * Given an HPCloud username and password, authenticate to Identity Services.
   * Identity Services will then issue a token that can be used to access other
   * HPCloud services.
   *
   * If a tenant ID is provided, this will also associate the user with the
   * given tenant ID. If a tenant Name is provided, this will associate the user
   * with the given tenant Name. Only the tenant ID or tenant Name needs to be
   * given, not both.
   *
   * If no tenant ID or tenant Name is given, it will likely be necessary to
   * rescope() the request (See also tenants()).
   *
   * Other authentication methods:
   *
   * - authenticateAsAccount()
   * - authenticate()
   *
   * @param string $username
   *   A valid username.
   * @param string $password
   *   A password string.
   * @param string $tenantId
   *   The tenant ID for this account. This can be obtained through the
   *   HPCloud console.
   * @param string $tenantName
   *   The tenant Name for this account. This can be obtained through the
   *   HPCloud console.
   * @throws HPCloud::Transport::AuthorizationException
   *   If authentication failed.
   * @throws HPCloud::Exception
   *   For abnormal network conditions. The message will give an indication as
   *   to the underlying problem.
   */
  public function authenticateAsUser($username, $password, $tenantId = NULL, $tenantName = NULL) {
    $ops = array(
      'passwordCredentials' => array(
        'username' => $username,
        'password' => $password,
      ),
    );

    // If a tenant ID is provided, added it to the auth array.
    if (!empty($tenantId)) {
      $ops['tenantId'] = $tenantId;
    }
    elseif (!empty($tenantName)) {
      $ops['tenantName'] = $tenantName;
    }


    return $this->authenticate($ops);
  }
  /**
   * Authenticate to HPCloud using your account ID and access key.
   *
   * Given an account ID and and access key (secret key), authenticate
   * to Identity Services. Identity Services will then issue a token that can be
   * used with other HPCloud services, such as Object Storage (aka Swift).
   *
   * The account ID and access key information can be found in the account
   * section of the console.
   *
   * The third and fourth paramaters allow you to specify a tenant ID or 
   * tenantName. In order to access services, this object will need a tenant ID
   * or tenant name. If none is specified, it can be set later using rescope().
   * The tenants() method can be used to get a list of all available tenant IDs
   * for this token.
   *
   * Other authentication methods:
   *
   * - authenticateAsUser()
   * - authenticate()
   *
   * @param string $account
   *   The account ID. It should look something like this:
   *   1234567890:abcdef123456.
   * @param string $key
   *   The access key (i.e. secret key), which should be a series of
   *   ASCII letters and digits.
   * @param string $tenantId
   *   A valid tenant ID. This will be used to associate a tenant's services
   *   with this token.
   * @param string $tenantName
   *   The tenant Name for this account. This can be obtained through the
   *   HPCloud console.
   * @retval string
   * @return string
   *   The auth token.
   * @throws HPCloud::Transport::AuthorizationException
   *   If authentication failed.
   * @throws HPCloud::Exception
   *   For abnormal network conditions. The message will give an indication as
   *   to the underlying problem.
   */
  public function authenticateAsAccount($account, $key, $tenantId = NULL, $tenantName = NULL) {
    $ops = array(
      'apiAccessKeyCredentials' => array(
        'accessKey' => $account,
        'secretKey' => $key,
      ),
    );

    if (!empty($tenantId)) {
      $ops['tenantId'] = $tenantId;
    }
    elseif (!empty($tenantName)) {
      $ops['tenantName'] = $tenantName;
    }

    return $this->authenticate($ops);
  }


  /**
   * Get the token.
   *
   * This will not be populated until after one of the authentication
   * methods has been run.
   *
   * @retval string
   * @return string
   *   The token ID to be used in subsequent calls.
   */
  public function token() {
    return $this->tokenDetails['id'];
  }

  /**
   * Get the tenant ID associated with this token.
   *
   * If this token has a tenant ID, the ID will be returned. Otherwise, this
   * will return NULL.
   *
   * This will not be populated until after an authentication method has been
   * run.
   *
   * @retval string
   * @return string
   *   The tenant ID if available, or NULL.
   */
  public function tenantId() {
    if (!empty($this->tokenDetails['tenant']['id'])) {
      return $this->tokenDetails['tenant']['id'];
    }
  }

  /**
   * Get the tenant name associated with this token.
   *
   * If this token has a tenant name, the name will be returned. Otherwise, this
   * will return NULL.
   *
   * This will not be populated until after an authentication method has been
   * run.
   *
   * @retval string
   * @return string
   *   The tenant name if available, or NULL.
   */
  public function tenantName() {
    if (!empty($this->tokenDetails['tenant']['name'])) {
      return $this->tokenDetails['tenant']['name'];
    }
  }

  /**
   * Get the token details.
   *
   * This returns an associative array with several pieces of information
   * about the token, including:
   *
   * - id: The token itself
   * - expires: When the token expires
   * - tenant_id: The tenant ID of the authenticated user.
   * - tenant_name: The username of the authenticated user.
   *
   * @code
   * <?php
   * array(
   *   'id' => 'auth_123abc321defef99',
   *   'tenant' => array(
   *     'id' => '123456',
   *     'name' => 'matt.butcher@hp.com',
   *   ),
   *   'expires' => '2012-01-24T12:46:01.682Z'
   * );
   * @endcode
   *
   * This will not be populated until after authentication has been done.
   *
   * @retval array
   * @return array
   *   An associative array of details.
   */
  public function tokenDetails() {
    return $this->tokenDetails;
  }

  /**
   * Check whether the current identity has an expired token.
   *
   * This does not perform a round-trip to the server. Instead, it compares the
   * machine's local timestamp with the server's expiration time stamp. A
   * mis-configured machine timestamp could give spurious results.
   *
   * @retval boolean
   * @return boolean
   *   This will return FALSE if there is a current token and it has
   *   not yet expired (according to the date info). In all other cases
   *   it returns TRUE.
   */
  public function isExpired() {
    $details = $this->tokenDetails();

    if (empty($details['expires'])) {
      return TRUE;
    }

    $currentDateTime = new \DateTime('now');
    $expireDateTime = new \DateTime($details['expires']);

    return $currentDateTime > $expireDateTime;
  }

  /**
   * Get the service catalog, optionaly filtering by type.
   *
   * This returns the service catalog (largely unprocessed) that
   * is returned during an authentication request. If a type is passed in,
   * only entries of that type are returned. If no type is passed in, the
   * entire service catalog is returned.
   *
   * The service catalog contains information about what services (if any) are
   * available for the present user. Object storage (Swift) Compute instances
   * (Nova) and other services will each be listed here if they are enabled
   * on your account. Only services that have been turned on for the account
   * will be available. (That is, even if you *can* create a compute instance,
   * until you have actually created one, it will not show up in this list.)
   *
   * One of the authentication methods MUST be run before obtaining the service
   * catalog.
   *
   * The return value is an indexed array of associative arrays, where each assoc
   * array describes an individual service.
   * @code
   * <?php
   * array(
   *   array(
   *     'name' : 'Object Storage',
   *     'type' => 'object-store',
   *     'endpoints' => array(
   *       'tenantId' => '123456',
   *       'adminURL' => 'https://example.hpcloud.net/1.0',
   *       'publicURL' => 'https://example.hpcloud.net/1.0/123456',
   *       'region' => 'region-a.geo-1',
   *       'id' => '1.0',
   *     ),
   *   ),
   *   array(
   *     'name' => 'Identity',
   *     'type' => 'identity'
   *     'endpoints' => array(
   *       'publicURL' => 'https://example.hpcloud.net/1.0/123456',
   *       'region' => 'region-a.geo-1',
   *       'id' => '2.0',
   *       'list' => 'http://example.hpcloud.net/extension',
   *     ),
   *   )
   *
   * );
   * ?>
   * @endcode
   *
   * This will not be populated until after authentication has been done.
   *
   * Types:
   *
   * While this is by no means an exhaustive list, here are a few types that
   * might appear in a service catalog (and upon which you can filter):
   *
   * - identity: Identity Services (i.e. Keystone)
   * - compute: Compute instance (Nova)
   * - object-store: Object Storage (Swift)
   * - hpext:cdn: HPCloud CDN service (yes, the colon belongs in there)
   *
   * Other services will be added.
   *
   * @todo Paging on the service catalog is not yet implemented.
   *
   * @retval array
   * @return array
   *   An associative array representing
   *   the service catalog.
   */
  public function serviceCatalog($type = NULL) {
    // If no type is specified, return the entire
    // catalog.
    if (empty($type)) {
      return $this->serviceCatalog;
    }

    $list = array();
    foreach ($this->serviceCatalog as $entry) {
      if ($entry['type'] == $type) {
        $list[] = $entry;
      }
    }

    return $list;
  }

  /**
   * Get information about the currently authenticated user.
   *
   * This returns an associative array of information about the authenticated
   * user, including the user's username and roles.
   *
   * The returned data is structured like this:
   * @code
   * <?php
   * array(
   *   'name' => 'matthew.butcher@hp.com',
   *   'id' => '1234567890'
   *   'roles' => array(
   *     array(
   *       'name' => 'domainuser',
   *       'serviceId' => '100',
   *       'id' => '000100400010011',
   *     ),
   *     // One array for each role...
   *   ),
   * )
   * ?>
   * @endcode
   *
   * This will not have data until after authentication has been done.
   *
   * @retval array
   * @return array
   *   An associative array, as described above.
   */
  public function user() {
    return $this->userDetails;
  }

  /**
   * Get a list of all tenants associated with this account.
   *
   * If a valid token is passed into this object, the method can be invoked
   * before authentication. However, if no token is supplied, this attempts
   * to use the one returned by an authentication call.
   *
   * Returned data will follow this format:
   *
   * @code
   * <?php
   * array(
   *   array(
   *     "id" =>  "395I91234514446",
   *     "name" => "Banking Tenant Services",
   *     "description" => "Banking Tenant Services for TimeWarner",
   *     "enabled" => TRUE,
   *     "created" => "2011-11-29T16:59:52.635Z",
   *     "updated" => "2011-11-29T16:59:52.635Z",
   *   ),
   * );
   * ?>
   * @endcode
   *
   * Note that this method invokes a new request against the remote server.
   *
   * @retval array
   * @return array
   *   An indexed array of tenant info. Each entry will be an associative
   *   array containing tenant details.
   * @throws HPCloud::Transport::AuthorizationException
   *   If authentication failed.
   * @throws HPCloud::Exception
   *   For abnormal network conditions. The message will give an indication as
   *   to the underlying problem.
   */
  public function tenants($token = NULL) {
    $url = $this->url() . '/tenants';

    if (empty($token)) {
      $token = $this->token();
    }

    $headers = array(
      'X-Auth-Token' => $token,
      'Accept' => 'application/json',
      //'Content-Type' => 'application/json',
    );

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, 'GET', $headers);

    $raw = $response->content();
    $json = json_decode($raw, TRUE);

    return $json['tenants'];

  }

  /**
   * @see HPCloud::Services::IdentityServices::rescopeUsingTenantId()
   * @deprecated
   */
  public function rescope($tenantId) {
    return $this->rescopeUsingTenantId($tenantId);
  }

    /**
   * Rescope the authentication token to a different tenant.
   *
   * Note that this will rebuild the service catalog and user information for
   * the current object, since this information is sensitive to tenant info.
   *
   * An authentication token can be in one of two states:
   *
   * - unscoped: It has no associated tenant ID.
   * - scoped: It has a tenant ID, and can thus access that tenant's services.
   *
   * This method allows you to do any of the following:
   *
   * - Begin with an unscoped token, and assign it a tenant ID.
   * - Change a token from one tenant ID to another (re-scoping).
   * - Remove the tenant ID from a scoped token (unscoping).
   *
   * @param string $tenantId
   *   The tenant ID that this present token should be bound to. If this is the
   *   empty string (`''`), the present token will be "unscoped" and its tenant
   *   ID will be removed.
   *
   * @retval string
   * @return string
   *   The authentication token.
   * @throws HPCloud::Transport::AuthorizationException
   *   If authentication failed.
   * @throws HPCloud::Exception
   *   For abnormal network conditions. The message will give an indication as
   *   to the underlying problem.
   */
  public function rescopeUsingTenantId($tenantId) {
    $url = $this->url() . '/tokens';
    $token = $this->token();
    $data = array(
      'auth' => array(
        'tenantId' => $tenantId,
        'token' => array(
          'id' => $token,
        ),
      ),
    );
    $body = json_encode($data);

    $headers = array(
      'Accept' => self::ACCEPT_TYPE,
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($body),
      //'X-Auth-Token' => $token,
    );

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, 'POST', $headers, $body);
    $this->handleResponse($response);

    return $this->token();
  }

  /**
   * Rescope the authentication token to a different tenant.
   *
   * Note that this will rebuild the service catalog and user information for
   * the current object, since this information is sensitive to tenant info.
   *
   * An authentication token can be in one of two states:
   *
   * - unscoped: It has no associated tenant ID.
   * - scoped: It has a tenant ID, and can thus access that tenant's services.
   *
   * This method allows you to do any of the following:
   *
   * - Begin with an unscoped token, and assign it a tenant ID.
   * - Change a token from one tenant ID to another (re-scoping).
   * - Remove the tenant ID from a scoped token (unscoping).
   *
   * @param string $tenantName
   *   The tenant name that this present token should be bound to. If this is the
   *   empty string (`''`), the present token will be "unscoped" and its tenant
   *   name will be removed.
   *
   * @retval string
   * @return string
   *   The authentication token.
   * @throws HPCloud::Transport::AuthorizationException
   *   If authentication failed.
   * @throws HPCloud::Exception
   *   For abnormal network conditions. The message will give an indication as
   *   to the underlying problem.
   */
  public function rescopeUsingTenantName($tenantName) {
    $url = $this->url() . '/tokens';
    $token = $this->token();
    $data = array(
      'auth' => array(
        'tenantName' => $tenantName,
        'token' => array(
          'id' => $token,
        ),
      ),
    );
    $body = json_encode($data);

    $headers = array(
      'Accept' => self::ACCEPT_TYPE,
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($body),
      //'X-Auth-Token' => $token,
    );

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, 'POST', $headers, $body);
    $this->handleResponse($response);

    return $this->token();
  }

  /**
   * Given a response object, populate this object.
   *
   * This parses the JSON data and parcels out the data to the appropriate
   * fields.
   *
   * @param object $response HPCloud::Transport::Response
   *   A response object.
   *
   * @retval HPCloud::Services::IdentityServices
   * @return \HPCloud\Services\IdentityServices
   *   $this for the current object so it can be used in chaining.
   */
  protected function handleResponse($response) {
    $json = json_decode($response->content(), TRUE);
    // print_r($json);

    $this->tokenDetails = $json['access']['token'];
    $this->userDetails = $json['access']['user'];
    $this->serviceCatalog = $json['access']['serviceCatalog'];

    return $this;
  }

  /* Not necessary.
  public function serialize() {
    $data = array(
      'tokenDetails' => $this->tokenDetails,
      'userDetails' => $this->userDetails,
      'serviceCatalog' => $this->serviceCatalog,
      'endpoint' => $this->endpoint,
    );
    return serialize($data);
  }

  public function unserialize($data) {
    $vals = unserialize($data);
    $this->tokenDetails = $vals['tokenDetails'];
    $this->userDetails = $vals['userDetails'];
    $this->serviceCatalog = $vals['serviceCatalog'];
    $this->endpoint = $vals['endpoint'];
  }
   */

}
