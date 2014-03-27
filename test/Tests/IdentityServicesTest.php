<?php
/* ============================================================================
(c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
============================================================================ */
/**
 * Unit tests for IdentityService.
 */
namespace OpenStack\Tests\Services;

require_once 'test/TestCase.php';

use \OpenStack\Services\IdentityService;
use \OpenStack\Bootstrap;


class IdentityServiceTest extends \OpenStack\Tests\TestCase {

  public function testConstructor(){
    $endpoint = self::conf('openstack.identity.url');
    $this->assertNotEmpty($endpoint);

    $service = new IdentityService($endpoint);

    $this->assertInstanceOf('\OpenStack\Services\IdentityService', $service);

    return $service;
  }

  /**
   * @depends testConstructor
   */
  public function testUrl() {
    $endpoint = self::conf('openstack.identity.url');
    $service = new IdentityService($endpoint);

    // If there is a trailing / we remove that from the endpoint. Our calls add
    // the / back where appropriate.
    $this->assertStringStartsWith(rtrim($endpoint, '/'), $service->url());

    return $service;
  }

  /**
   * @depends testUrl
   */
  public function testAuthenticate($service){

    // Canary: Make sure all the required params are declared.
    $settings = array(
      'openstack.identity.username',
      'openstack.identity.password',
      'openstack.identity.tenantId',
    );
    foreach ($settings as $setting) {
      $this->assertNotEmpty(self::conf($setting), "Required param: " . $setting);
    }

    // Test username/password auth.
    $auth = array(
      'passwordCredentials' => array(
        'username' => self::conf('openstack.identity.username'),
        'password' => self::conf('openstack.identity.password'),
      ),
      'tenantId' => self::conf('openstack.identity.tenantId'),
    );
    $tok = $service->authenticate($auth);
    $this->assertNotEmpty($tok);

    // We should get the same token if we request again.
    $service = new IdentityService(self::conf('openstack.identity.url'));
    $tok2 = $service->authenticate($auth);
    $this->assertEquals($tok, $tok2);

    // Again with no tenant ID.
    $auth = array(
      'passwordCredentials' => array(
        'username' => self::conf('openstack.identity.username'),
        'password' => self::conf('openstack.identity.password'),
      ),
      //'tenantId' => self::conf('openstack.identity.tenantId'),
    );
    $tok = $service->authenticate($auth);
    $this->assertNotEmpty($tok);
  }

  /**
   * @depends testAuthenticate
   */
  public function testAuthenticateAsUser() {
    $service = new IdentityService(self::conf('openstack.identity.url'));

    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantId = self::conf('openstack.identity.tenantId');

    $tok = $service->authenticateAsUser($user, $pass, $tenantId);

    $this->assertNotEmpty($tok);

    // Try again, this time with no tenant ID.
    $tok2 = $service->authenticateAsUser($user, $pass);
    $this->assertNotEmpty($tok2);

    $details = $service->tokenDetails();
    $this->assertFalse(isset($details['tenant']));

    return $service;
  }

  /**
   * @depends testAuthenticateAsUser
   */
  public function testToken($service) {
    $this->assertNotEmpty($service->token());
  }

  /**
   * @depends testAuthenticateAsUser
   */
  public function testIsExpired($service) {
    $this->assertFalse($service->isExpired());

    $service2 = new IdentityService(self::conf('openstack.identity.url'));
    $this->assertTrue($service2->isExpired());
  }

  /**
   * @depends testAuthenticateAsUser
   */
  public function testTenantName() {
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantName = self::conf('openstack.identity.tenantName');

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $this->assertNull($service->tenantName());

    $service->authenticateAsUser($user, $pass);
    $this->assertEmpty($service->tenantName());

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $ret = $service->authenticateAsUser($user, $pass, NULL, $tenantName);
    $this->assertNotEmpty($service->tenantName());

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $this->assertNull($service->tenantName());
  }

  /**
   * @depends testAuthenticateAsUser
   */
  public function testTenantId() {
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantId = self::conf('openstack.identity.tenantId');

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $this->assertNull($service->tenantId());

    $service->authenticateAsUser($user, $pass);
    $this->assertEmpty($service->tenantId());

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $service->authenticateAsUser($user, $pass, $tenantId);
    $this->assertNotEmpty($service->tenantId());
  }

  /**
   * @depends testAuthenticateAsUser
   */
  public function testTokenDetails() {
    $now = time();
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantId = self::conf('openstack.identity.tenantId');

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $service->authenticateAsUser($user, $pass);

    // Details for user auth.
    $details = $service->tokenDetails();
    $this->assertNotEmpty($details['id']);
    $this->assertFalse(isset($details['tenant']));

    $ts = strtotime($details['expires']);
    $this->assertGreaterThan($now, $ts);


    // Test details for username auth.
    $service = new IdentityService(self::conf('openstack.identity.url'));
    $service->authenticateAsUser($user, $pass, $tenantId);

    $details = $service->tokenDetails();

    $expectUser = self::conf('openstack.identity.username');

    $this->assertStringStartsWith($expectUser, $details['tenant']['name']);
    $this->assertNotEmpty($details['id']);
    $this->assertNotEmpty($details['tenant']['id']);

    $this->assertEquals($tenantId, $details['tenant']['id']);

    $ts = strtotime($details['expires']);
    $this->assertGreaterThan($now, $ts);
  }

  /**
   * @depends testAuthenticateAsUser
   */
  public function testServiceCatalog($service) {
    $catalog = $service->serviceCatalog();

    $this->assertGreaterThan(0, count($catalog));

    $idService = NULL;
    foreach ($catalog as $item) {
      if ($item['type'] == 'identity') {
        $idService = $item;
      }
    }

    $this->assertEquals('Identity', $idService['name']);
    $this->assertNotEmpty($idService['endpoints']);
    $this->assertNotEmpty($idService['endpoints'][0]['publicURL']);

    // Test filters.
    $justID = $service->serviceCatalog('identity');
    $this->assertEquals(1, count($justID));

    $idService = $justID[0];
    $this->assertEquals('Identity', $idService['name']);
    $this->assertNotEmpty($idService['endpoints']);
    $this->assertNotEmpty($idService['endpoints'][0]['publicURL']);

    // Make sure a missed filter returns an empty set.
    $expectEmpty = $service->serviceCatalog('no-such-servicename');
    $this->assertEmpty($expectEmpty);
  }


  /**
   * @depends testAuthenticateAsUser
   */
  public function testUser($service) {
    $user = $service->user();

    $this->assertEquals(self::conf('openstack.identity.username'), $user['name']);
    $this->assertNotEmpty($user['roles']);
  }

  /**
   * @depends testAuthenticateAsUser
   * @group serialize
   */
  public function testSerialization($service) {

    $ser = serialize($service);

    $this->assertNotEmpty($ser);

    $again = unserialize($ser);

    $this->assertInstanceOf('\OpenStack\Services\IdentityService', $again);

    $this->assertEquals($service->tenantId(), $again->tenantId());
    $this->assertEquals($service->serviceCatalog(), $again->serviceCatalog());
    $this->assertEquals($service->tokenDetails(), $again->tokenDetails());
    $this->assertEquals($service->user(), $again->user());
    $this->assertFalse($again->isExpired());

    $tenantId = $again->tenantId();

    $newTok = $again->rescopeUsingTenantId($tenantId);

    $this->assertNotEmpty($newTok);
  }

  /**
   * @group tenant
   */
  public function testTenants() {
    $service = new IdentityService(self::conf('openstack.identity.url'));
    $service2 = new IdentityService(self::conf('openstack.identity.url'));
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantId = self::conf('openstack.identity.tenantId');
    $service->authenticateAsUser($user, $pass, $tenantId);


    $tenants = $service2->tenants($service->token());

    $this->assertGreaterThan(0, count($tenants));
    $this->assertNotEmpty($tenants[0]['name']);
    $this->assertNotEmpty($tenants[0]['id']);

    $tenants = $service->tenants();
    $this->assertGreaterThan(0, count($tenants));
    $this->assertNotEmpty($tenants[0]['name']);
    $this->assertNotEmpty($tenants[0]['id']);

  }

  /**
   * @group tenant
   * @depends testTenants
   */
  function testRescope() {
    $service = new IdentityService(self::conf('openstack.identity.url'));
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantId = self::conf('openstack.identity.tenantId');

    // Authenticate without a tenant ID.
    $token = $service->authenticateAsUser($user, $pass);

    $this->assertNotEmpty($token);

    $details = $service->tokenDetails();
    $this->assertFalse(isset($details['tenant']));

    $service->rescopeUsingTenantId($tenantId);

    $details = $service->tokenDetails();
    $this->assertEquals($tenantId, $details['tenant']['id']);

    // Test unscoping
    $service->rescopeUsingTenantId('');
    $details = $service->tokenDetails();
    $this->assertFalse(isset($details['tenant']));
  }

  /**
   * @group tenant
   * @depends testTenants
   */
  function testRescopeByTenantName() {
    $service = new IdentityService(self::conf('openstack.identity.url'));
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantName = self::conf('openstack.identity.tenantName');

    // Authenticate without a tenant ID.
    $token = $service->authenticateAsUser($user, $pass);

    $this->assertNotEmpty($token);

    $details = $service->tokenDetails();
    $this->assertFalse(isset($details['tenant']));

    $service->rescopeUsingTenantName($tenantName);

    $details = $service->tokenDetails();
    $this->assertEquals($tenantName, $details['tenant']['name']);

    // Test unscoping
    $service->rescope('');
    $details = $service->tokenDetails();
    $this->assertFalse(isset($details['tenant']));
  }

  /**
   * Test the bootstrap identity factory.
   * @depends testAuthenticateAsUser
   */
  function testBootstrap() {

    // We need to save the config settings and reset the bootstrap to this.
    // It does not remove the old settings. The means the identity fall through
    // for different settings may not happen because of ordering. So, we cache
    // and reset back to the default for each test.
    $reset = Bootstrap::$config;

    // Test authenticating as a user.
    $settings = array(
      'username' => self::conf('openstack.identity.username'),
      'password' => self::conf('openstack.identity.password'),
      'endpoint' => self::conf('openstack.identity.url'),
      'tenantid' => self::conf('openstack.identity.tenantId'),
    );
    Bootstrap::setConfiguration($settings);

    $is = Bootstrap::identity(TRUE);
    $this->assertInstanceOf('\OpenStack\Services\IdentityService', $is);

    // Test getting a second instance from the cache.
    $is2 = Bootstrap::identity();
    $this->assertEquals($is, $is2);

    // Test that forcing a refresh does so.
    $is2 = Bootstrap::identity(TRUE);
    $this->assertNotEquals($is, $is2);

    Bootstrap::$config = $reset;

    // Test with tenant name
    $settings = array(
      'username' => self::conf('openstack.identity.username'),
      'password' => self::conf('openstack.identity.password'),
      'endpoint' => self::conf('openstack.identity.url'),
      'tenantname' => self::conf('openstack.identity.tenantName'),
    );
    Bootstrap::setConfiguration($settings);

    $is = Bootstrap::identity(TRUE);
    $this->assertInstanceOf('\OpenStack\Services\IdentityService', $is);
  }
}
