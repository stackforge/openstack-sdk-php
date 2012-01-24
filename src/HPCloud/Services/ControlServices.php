<?php
/**
 * @file
 *
 * This file contains the main ControlServices class.
 */
namespace HPCloud\Services;

/**
 *
 * Identity Services (a.k.a. Keystone) provides a central service for managing
 * other services. Through it, you can do the following:
 *
 * - Authenticate
 * - Obtain tokens valid accross services
 * - Obtain a list of the account's current services (i.e. the Service Catalog)
 *
 * AUTHENTICATION
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
 */
class ControlServices {
  /**
   * The version of the API currently supported.
   *
   * This must match the ControlServices::ACCEPT_TYPE.
   */
  const API_VERSION = '2.0';
  /**
   * The full OpenStack accept type.
   *
   * This must match the ControlServices::API_VERSION.
   */
  const ACCEPT_TYPE = 'application/json';
  //const ACCEPT_TYPE = 'application/vnd.openstack.identity+json;version=2.0';

  /**
   * The URL to the CS endpoint.
   */
  protected $endpoint;

  /**
   * The details sent with the token.
   *
   * This is an associative array looking like this:
   *
   * @code
   * <?php
   * array(
   *   'id' => 'auth_123abc321defef99',
   *   'tenant_id' => '123456',
   *   'tenant_name' => 'matt.butcher@hp.com',
   *   'expires' => '2012-01-24T12:46:01.682Z'
   * );
   * @endcode
   */
  protected $tokenDetails;

  /**
   * The service catalog.
   */
  protected $catalog = array();

  /**
   * Build a new ControlServices object.
   *
   * Each object is bound to a particular identity services endpoint.
   *
   * For the URL, you are advised to use the version <i>without</i> a
   * version number at the end, e.g. http://cs.example.com/ rather
   * than http://cs.example.com/v2.0. The version number must be
   * controlled by the library.
   *
   * @code
   * <?php
   * $cs = new \HPCloud\Services\ControlServices('http://example.com');
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
    $this->endpoint = rtrim($url, '/') . '/v' . self::API_VERSION;
  }

  /**
   * Send an authentication request.
   *
   * EXPERT: This allows authentication requests at a low level. For simple
   * authentication requests using account number or username, see the
   * authenticateAsUser() and authenticateAsAccount() methods.
   *
   * Here is an example of username/password-based authentication done with
   * the authenticate() method:
   * @code
   * <?php
   * $cs = new \HPCloud\Services\ControlServices($url);
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
   * @return string
   *   The token. This is returned for simplicity. The full response is used
   *   to populate this object's service catalog, etc. The token is also
   *   retrievable with token().
   */
  public function authenticate(array $ops) {
    $url = $this->endpoint .= '/tokens';
    $envelope = array(
      'auth' => $ops,
    );


    $body = json_encode($envelope);

    $headers = array(
      'Content-Type' => 'application/json',
      'Accept' => self::ACCEPT_TYPE,
      'Content-Length' => strlen($body),
    );

    print $body . PHP_EOL;

    $client = \HPCloud\Transport::instance();

    $response = $client->doRequest($url, 'POST', $headers, $body);

    $this->handleResponse($response);


    return $this->token();
  }

  /**
   * Authenticate to Identity Services with username, password, and tenant ID.
   *
   * Given an HPCloud username and password, and also the account's tenant ID,
   * authenticate to Identity Services. Identity Services will then issue a token
   * that can be used to access other HPCloud services.
   *
   * @param string $username
   *   A valid username.
   * @param string $password
   *   A password string.
   * @param string $tenantId
   *   The tenant ID for this account. This can be obtained through the
   *   HPCloud management console.
   */
  public function authenticateAsUser($username, $password, $tenantId) {
    $ops = array(
      'passwordCredentials' => array(
        'username' => $username,
        'password' => $password,
      ),
      'tenantId' => $tenantId,
    );
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
   * section of the management console.
   *
   * @param string $account
   *   The account ID. It should look something like this:
   *   1234567890:abcdef123456.
   * @param string $key
   *   The access key (i.e. secret key), which should be a series of
   *   ASCII letters and digits.
   * @return string
   *   The auth token.
   */
  public function authenticateAsAccount($account, $key) {
    $ops = array(
      'apiAccessKeyCredentials' => array(
        'accessKey' => $account,
        'secretKey' => $key,
      ),
    );
    return $this->authenticate($ops);
  }


  /**
   * Get the token.
   *
   * This will not be populated until after one of the authentication
   * methods has been run.
   *
   * @return string
   *   The token ID to be used in subsequent calls.
   */
  public function token() {
    return $this->tokenDetails['id'];
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
   *   'tenant_id' => '123456',
   *   'tenant_name' => 'matt.butcher@hp.com',
   *   'expires' => '2012-01-24T12:46:01.682Z'
   * );
   * @endcode
   *
   * @returns array
   *   An associative array of details.
   */
  public function tokenDetails() {
    return $this->tokenDetails;
  }

  /**
   * Get the service catalog.
   *
   * This returns the service catalog (largely unprocessed) that
   * is returned during an authentication request.
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
   *     'type' => 'object-storage',
   *     'endpoints' => array(
   *       'tenantId' => '123456',
   *       'adminURL' => 'https://example.hpcloud.net/1.0',
   *       'publicUrl' => 'https://example.hpcloud.net/1.0/123456',
   *       'region' => 'region-a.geo-1',
   *       'id' => '1.0',
   *     ),
   *   ),
   *   array(
   *     'name' => 'Identity',
   *     'type' => 'identity'
   *     'endpoints' => array(
   *       'publicUrl' => 'https://example.hpcloud.net/1.0/123456',
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
   * @todo Paging on the service catalog is not yet implemented.
   *
   * @return array
   *   An associative array representing
   *   the service catalog.
   */
  public function serviceCatalog() {
    return $this->serviceCatalog;
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
   *   'user' => 'matthew.butcher@hp.com',
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
   * @return array
   *   An associative array, as described above.
   */
  public function user() {
    return $this->userDetails;
  }

  /**
   * Given a response object, populate this object.
   *
   * This parses the JSON data and parcels out the data to the appropriate
   * fields.
   *
   * @param \HPCloud\Transport\Response $response
   *   A response object.
   */
  protected function handleResponse($response) {
    $json = json_decode($response->content(), TRUE);

    $this->tokenDetails = $json['access']['token'];
    $this->userDetails = $json['access']['user'];
    $this->serviceCatalog = $json['access']['serviceCatalog'];

  }

}


