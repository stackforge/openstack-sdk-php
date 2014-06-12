<?php

/*
 * (c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.
 * (c) Copyright 2014      Rackspace US, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace OpenStack\Identity\v2;
use OpenStack\Common\Transport\ClientInterface;
use OpenStack\Common\Transport\Guzzle\GuzzleAdapter;

/**
 * IdentityService provides authentication and authorization.
 *
 * IdentityService (a.k.a. Keystone) provides a central service for managing
 * other services. Through it, you can do the following:
 *
 * - Authenticate
 * - Obtain tokens valid accross services
 * - Obtain a list of the services currently available with a token
 * - Associate with tenants using tenant IDs.
 *
 * AUTHENTICATION
 *
 * The authentication process consists of a single transaction during which the
 * client (us) submits credentials and the server verifies those credentials,
 * returning a token (for subsequent requests), user information, and the
 * service catalog.
 *
 * Authentication credentials:
 *
 * - Username and password
 * - Account ID and Secret Key
 *
 * Other mechanisms may be supported in the future.
 *
 * TENANTS
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
 *   is done with either the rescopeUsingTenantId() or rescopeUsingTenantName()
 *   method.
 *
 * Where do I get a tenant ID?
 *
 * There are two notable places to get this information:
 *
 * A list of tenants associated with this user can be obtain programatically
 * using the tenants() method on this object.
 *
 * OpenStack users can find their tenant ID in the console along with their
 * username and password.
 *
 * EXAMPLE
 *
 * The following example illustrates typical use of this class.
 *
 *     <?php
 *     // You may need to use \OpenStack\Bootstrap to set things up first.
 *
 *     use \OpenStack\Identity\v2\IdentityService;
 *
 *     // Create a new object with the endpoint URL (no version number)
 *     $ident = new IdentityService('https://example.com:35357');
 *
 *     // Authenticate and set the tenant ID simultaneously.
 *     $ident->authenticateAsUser('me@example.com', 'password', '1234567');
 *
 *     // The token to use when connecting to other services:
 *     $token = $ident->token();
 *
 *     // The tenant ID.
 *     $tenant = $ident->tenantId();
 *
 *     // Details about what services this token can access.
 *     $services = $ident->serviceCatalog();
 *
 *     // List all available tenants.
 *     $tenants = $ident->tenants();
 *
 *     // Switch to a different tenant.
 *     $ident->rescopeUsingTenantId($tenants[0]['id']);
 *
 *     ?>
 *
 * PERFORMANCE CONSIDERATIONS
 *
 * The following methods require network requests:
 *
 * - authenticate()
 * - authenticateAsUser()
 * - tenants()
 * - rescopeUsingTenantId()
 * - rescopeUsingTenantName()
 *
 * Serializing
 *
 * IdentityService has been intentionally built to serialize well.
 * This allows implementors to cache IdentityService objects rather
 * than make repeated requests for identity information.
 *
 */
class IdentityService
{
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
     * password will set tenant information. Authenticating by username and
     * password, however, will leave the tenant section empty.
     *
     * This is an associative array looking like this:
     *
     *     <?php
     *     array(
     *       'id' => 'auth_123abc321defef99',
     *       // Only non-empty for username/password auth.
     *       'tenant' => array(
     *         'id' => '123456',
     *         'name' => 'matt.butcher@hp.com',
     *       ),
     *       'expires' => '2012-01-24T12:46:01.682Z'
     *     );
     */
    protected $tokenDetails;

    /**
     * The service catalog.
     */
    protected $catalog = [];

    protected $userDetails;

    /**
     * The HTTP Client
     */
    protected $client;

    /**
     * Build a new IdentityService object.
     *
     * Each object is bound to a particular identity services endpoint.
     *
     * For the URL, you are advised to use the version without a
     * version number at the end, e.g. http://cs.example.com/ rather
     * than http://cs.example.com/v2.0. The version number must be
     * controlled by the library.
     *
     * If a version is included in the URI, the library will attempt to use
     * that URI.
     *
     *     <?php
     *     $cs = new \OpenStack\Identity\v2\IdentityService('http://example.com');
     *     $token = $cs->authenticateAsUser($username, $password);
     *     ?>
     *
     * @param string $url An URL pointing to the Identity Service endpoint.
     *                    Note that you do not need the version identifier in the URL, as version
     *                    information is sent in the HTTP headers rather than in the URL. The URL
     *                    should always be to an SSL/TLS encrypted endpoint.
     *
     * @param \OpenStack\Common\Transport\ClientInterface $client An optional HTTP client to use when making the requests.
     */
    public function __construct($url, ClientInterface $client = null)
    {
        $parts = parse_url($url);

        if (!empty($parts['path'])) {
            $this->endpoint = rtrim($url, '/');
        } else {
            $this->endpoint = rtrim($url, '/') . '/v' . self::API_VERSION;
        }

        // Guzzle is the default client to use.
        if (is_null($client)) {
            $this->client = GuzzleAdapter::create();
        } else {
            $this->client = $client;
        }
    }

    /**
     * Get the endpoint URL.
     *
     * This includes version number, so in that regard it is not an identical
     * URL to the one passed into the constructor.
     *
     * @return string The complete URL to the identity services endpoint.
     */
    public function url()
    {
        return $this->endpoint;
    }

    /**
     * Send an authentication request.
     *
     * EXPERT: This allows authentication requests at a low level. For simple
     * authentication requests using a username, see the
     * authenticateAsUser() method.
     *
     * Here is an example of username/password-based authentication done with
     * the authenticate() method:
     *
     *     <?php
     *     $cs = new \OpenStack\Identity\v2\IdentityService($url);
     *     $ops = array(
     *       'passwordCredentials' => array(
     *         'username' => $username,
     *         'password' => $password,
     *       ),
     *       'tenantId' => $tenantId,
     *     );
     *     $token = $cs->authenticate($ops);
     *     ?>
     *
     * Note that the same authentication can be done by authenticateAsUser().
     *
     * @param array $ops An associative array of authentication operations and
     *                   their respective parameters.
     *
     * @return string The token. This is returned for simplicity. The full
     *                response is used to populate this object's service catalog, etc. The
     *                token is also retrievable with token().
     *
     * @throws \OpenStack\Common\Transport\Exception\AuthorizationException If authentication failed.
     * @throws \OpenStack\Common\Exception For abnormal network conditions. The message
     *                              will give an indication as to the underlying problem.
     */
    public function authenticate(array $ops)
    {
        $url = $this->url() . '/tokens';
        $envelope = [
            'auth' => $ops,
        ];

        $body = json_encode($envelope);

        $headers = [
            'Content-Type'   => 'application/json',
            'Accept'         => self::ACCEPT_TYPE,
            'Content-Length' => strlen($body),
        ];

        $response = $this->client->post($url, $body, ['headers' => $headers]);

        $this->handleResponse($response);

        return $this->token();
    }

    /**
     * Authenticate to Identity Services with username, password, and either
     * tenant ID or tenant Name.
     *
     * Given a OpenStack username and password, authenticate to Identity Services.
     * Identity Services will then issue a token that can be used to access other
     * OpenStack services.
     *
     * If a tenant ID is provided, this will also associate the user with the
     * given tenant ID. If a tenant Name is provided, this will associate the user
     * with the given tenant Name. Only the tenant ID or tenant Name needs to be
     * given, not both.
     *
     * If no tenant ID or tenant Name is given, it will likely be necessary to
     * rescopeUsingTenantId() the request (See also tenants()).
     *
     * Other authentication methods:
     * - authenticate()
     *
     * @param string $username   A valid username.
     * @param string $password   A password string.
     * @param string $tenantId   The tenant ID. This can be obtained through the
     *                           OpenStack console.
     * @param string $tenantName The tenant Name. This can be obtained through the
     *                           OpenStack console.
     *
     * @throws \OpenStack\Common\Transport\Exception\AuthorizationException If authentication failed.
     * @throws \OpenStack\Common\Exception  For abnormal network conditions. The message will give an
     *                                      indication as to the underlying problem.
     */
    public function authenticateAsUser($username, $password, $tenantId = null, $tenantName = null)
    {
        $ops = [
            'passwordCredentials' => [
                'username' => $username,
                'password' => $password,
            ]
        ];

        // If a tenant ID is provided, added it to the auth array.
        if (!empty($tenantId)) {
            $ops['tenantId'] = $tenantId;
        } elseif (!empty($tenantName)) {
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
     * @return string The token ID to be used in subsequent calls.
     */
    public function token()
    {
        return $this->tokenDetails['id'];
    }

    /**
     * Get the tenant ID associated with this token.
     *
     * If this token has a tenant ID, the ID will be returned. Otherwise, this
     * will return null.
     *
     * This will not be populated until after an authentication method has been
     * run.
     *
     * @return string The tenant ID if available, or null.
     */
    public function tenantId()
    {
        if (!empty($this->tokenDetails['tenant']['id'])) {
            return $this->tokenDetails['tenant']['id'];
        }
    }

    /**
     * Get the tenant name associated with this token.
     *
     * If this token has a tenant name, the name will be returned. Otherwise, this
     * will return null.
     *
     * This will not be populated until after an authentication method has been
     * run.
     *
     * @return string The tenant name if available, or null.
     */
    public function tenantName()
    {
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
     *     <?php
     *     array(
     *       'id' => 'auth_123abc321defef99',
     *       'tenant' => array(
     *         'id' => '123456',
     *         'name' => 'matt.butcher@hp.com',
     *       ),
     *       'expires' => '2012-01-24T12:46:01.682Z'
     *     );
     *
     * This will not be populated until after authentication has been done.
     *
     * @return array An associative array of details.
     */
    public function tokenDetails()
    {
        return $this->tokenDetails;
    }

    /**
     * Check whether the current identity has an expired token.
     *
     * This does not perform a round-trip to the server. Instead, it compares the
     * machine's local timestamp with the server's expiration time stamp. A
     * mis-configured machine timestamp could give spurious results.
     *
     * @return boolean This will return false if there is a current token and it
     *                 has not yet expired (according to the date info). In all
     *                 other cases it returns true.
     */
    public function isExpired()
    {
        $details = $this->tokenDetails();

        if (empty($details['expires'])) {
            return true;
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
     * for your user in the current tenant. Only services that have been turned on
     * for the user on the tenant will be available. (That is, even if you *can*
     * create a compute instance, until you have actually created one, it will not
     * show up in this list.)
     *
     * One of the authentication methods MUST be run before obtaining the service
     * catalog.
     *
     * The return value is an indexed array of associative arrays, where each assoc
     * array describes an individual service.
     *
     *     <?php
     *     array(
     *       array(
     *         'name' : 'Object Storage',
     *         'type' => 'object-store',
     *         'endpoints' => array(
     *           'tenantId' => '123456',
     *           'adminURL' => 'https://example.hpcloud.net/1.0',
     *           'publicURL' => 'https://example.hpcloud.net/1.0/123456',
     *           'region' => 'region-a.geo-1',
     *           'id' => '1.0',
     *         ),
     *       ),
     *       array(
     *         'name' => 'Identity',
     *         'type' => 'identity'
     *         'endpoints' => array(
     *           'publicURL' => 'https://example.hpcloud.net/1.0/123456',
     *           'region' => 'region-a.geo-1',
     *           'id' => '2.0',
     *           'list' => 'http://example.hpcloud.net/extension',
     *         ),
     *       )
     *
     *     );
     *     ?>
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
     *
     * Other services will be added.
     *
     * @todo Paging on the service catalog is not yet implemented.
     *
     * @return array An associative array representing the service catalog.
     */
    public function serviceCatalog($type = null)
    {
        // If no type is specified, return the entire
        // catalog.
        if (empty($type)) {
            return $this->serviceCatalog;
        }

        $list = [];
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
     *
     *     <?php
     *     array(
     *       'name' => 'matthew.butcher@hp.com',
     *       'id' => '1234567890'
     *       'roles' => array(
     *         array(
     *           'name' => 'domainuser',
     *           'serviceId' => '100',
     *           'id' => '000100400010011',
     *         ),
     *         // One array for each role...
     *       ),
     *     )
     *     ?>
     *
     * This will not have data until after authentication has been done.
     *
     * @return array An associative array, as described above.
     */
    public function user()
    {
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
     *     <?php
     *     array(
     *       array(
     *         "id" =>  "395I91234514446",
     *         "name" => "Banking Tenant Services",
     *         "description" => "Banking Tenant Services for TimeWarner",
     *         "enabled" => true,
     *         "created" => "2011-11-29T16:59:52.635Z",
     *         "updated" => "2011-11-29T16:59:52.635Z",
     *       ),
     *     );
     *     ?>
     *
     * Note that this method invokes a new request against the remote server.
     *
     * @return array An indexed array of tenant info. Each entry will be an
     *               associative array containing tenant details.
     *
     * @throws \OpenStack\Common\Transport\Exception\AuthorizationException If authentication failed.
     * @throws \OpenStack\Common\Exception  For abnormal network conditions. The message will give an
     *                                      indication as to the underlying problem.
     */
    public function tenants($token = null)
    {
        $url = $this->url() . '/tenants';

        if (empty($token)) {
            $token = $this->token();
        }

        $headers = [
            'X-Auth-Token' => $token,
            'Accept'       => 'application/json'
        ];

        $response = $this->client->get($url, ['headers' => $headers]);

        return $response->json()['tenants'];
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
     * @param string $tenantId The tenant ID that this present token should be
     *                         bound to. If this is the empty string (`''`), the
     *                         present token will be "unscoped" and its tenant
     *                         ID will be removed.
     *
     * @return string The authentication token.
     *
     * @throws \OpenStack\Common\Transport\Exception\AuthorizationException If authentication failed.
     * @throws \OpenStack\Common\Exception For abnormal network conditions. The message will give an
     *                                     indication as to the underlying problem.
     */
    public function rescopeUsingTenantId($tenantId)
    {
        $url = $this->url() . '/tokens';

        $body = json_encode([
            'auth' => [
                'tenantId' => $tenantId,
                'token' => [
                    'id' => $this->token(),
                ]
            ]
        ]);

        $headers = [
            'Accept'         => self::ACCEPT_TYPE,
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen($body)
        ];

        $response = $this->client->post($url, $body, ['headers' => $headers]);

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
     * @param string $tenantName The tenant name that this present token should be
     *                           bound to. If this is the empty string (`''`), the
     *                           present token will be "unscoped" and its tenant
     *                           name will be removed.
     *
     * @return string The authentication token.
     *
     * @throws \OpenStack\Common\Transport\Exception\AuthorizationException If authentication failed.
     * @throws \OpenStack\Common\Exception For abnormal network conditions. The message will
     *                                     give an indication as to the underlying problem.
     */
    public function rescopeUsingTenantName($tenantName)
    {
        $url = $this->url() . '/tokens';

        $body = json_encode([
            'auth' => [
                'tenantName' => $tenantName,
                'token' => [
                    'id' => $this->token()
                ]
            ]
        ]);

        $headers = [
            'Accept'         => self::ACCEPT_TYPE,
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen($body)
        ];

        $response = $this->client->post($url, $body, ['headers' => $headers]);

        $this->handleResponse($response);

        return $this->token();
    }

    /**
     * Given a response object, populate this object.
     *
     * This parses the JSON data and parcels out the data to the appropriate
     * fields.
     *
     * @param \OpenStack\Common\Transport\ResponseInterface $response A response object.
     *
     * @return \OpenStack\Identity\v2\IdentityService $this for the current object so
     *                                                      it can be used in chaining.
     */
    protected function handleResponse($response)
    {
        $json = $response->json();

        $this->tokenDetails = $json['access']['token'];
        $this->userDetails = $json['access']['user'];
        $this->serviceCatalog = $json['access']['serviceCatalog'];

        return $this;
    }
}
