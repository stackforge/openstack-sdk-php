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
 * Unit tests for IdentityService.
 */
namespace OpenStack\Tests\Services;

require_once 'src/OpenStack/Bootstrap.php';
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
      'openstack.identity.access',
      'openstack.identity.secret',
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


    // Test account ID/secret key auth.
    $auth = array(
      'apiAccessKeyCredentials' => array(
        'accessKey' => self::conf('openstack.identity.access'),
        'secretKey' => self::conf('openstack.identity.secret'),
      ),
    );
    $service = new IdentityService(self::conf('openstack.identity.url'));
    $tok3 = $service->authenticate($auth);

    $this->assertNotEmpty($tok3);

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
  }

  /**
   * @depends testAuthenticate
   */
  public function testAuthenticateAsAccount() {
    $service = new IdentityService(self::conf('openstack.identity.url'));

    $account = self::conf('openstack.identity.access');
    $secret = self::conf('openstack.identity.secret');
    $tenantId = self::conf('openstack.identity.tenantId');

    // No tenant ID.
    $tok = $service->authenticateAsAccount($account, $secret);
    $this->assertNotEmpty($tok);
    $this->assertEmpty($service->tenantId());

    // No tenant ID.
    $service = new IdentityService(self::conf('openstack.identity.url'));
    $tok = $service->authenticateAsAccount($account, $secret, $tenantId);
    $this->assertNotEmpty($tok);
    $this->assertEquals($tenantId, $service->tenantId());

    return $service;
  }

  /**
   * @depends testAuthenticateAsAccount
   */
  public function testToken($service) {
    $this->assertNotEmpty($service->token());
  }

  /**
   * @depends testAuthenticateAsAccount
   */
  public function testIsExpired($service) {
    $this->assertFalse($service->isExpired());

    $service2 = new IdentityService(self::conf('openstack.identity.url'));
    $this->assertTrue($service2->isExpired());
  }

  /**
   * @depends testAuthenticateAsAccount
   */
  public function testTenantName() {
    $account = self::conf('openstack.identity.access');
    $secret = self::conf('openstack.identity.secret');
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

    $service->authenticateAsAccount($account, $secret);
    $this->assertEmpty($service->tenantName());

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $ret = $service->authenticateAsAccount($account, $secret, NULL, $tenantName);
    $this->assertNotEmpty($service->tenantName());
    $this->assertEquals($tenantName, $service->tenantName());
  }

  /**
   * @depends testAuthenticateAsAccount
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
   * @depends testAuthenticateAsAccount
   */
  public function testTokenDetails() {
    $now = time();
    $user = self::conf('openstack.identity.username');
    $pass = self::conf('openstack.identity.password');
    $tenantId = self::conf('openstack.identity.tenantId');

    $service = new IdentityService(self::conf('openstack.identity.url'));
    $service->authenticateAsUser($user, $pass);

    // Details for account auth.
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
   * @depends testAuthenticateAsAccount
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
   * @depends testAuthenticateAsAccount
   */
  public function testUser($service) {
    $user = $service->user();

    $this->assertEquals(self::conf('openstack.identity.username'), $user['name']);
    $this->assertNotEmpty($user['roles']);
  }

  /**
   * @depends testAuthenticateAsAccount
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

    // With no tenant ID, there should be only
    // one entry in the catalog.
    $catalog = $service->serviceCatalog();
    $this->assertEquals(1, count($catalog));

    $service->rescopeUsingTenantId($tenantId);

    $details = $service->tokenDetails();
    $this->assertEquals($tenantId, $details['tenant']['id']);

    $catalog = $service->serviceCatalog();
    $this->assertGreaterThan(1, count($catalog));

    // Test unscoping
    $service->rescopeUsingTenantId('');
    $details = $service->tokenDetails();
    $this->assertFalse(isset($details['tenant']));
    $catalog = $service->serviceCatalog();
    $this->assertEquals(1, count($catalog));

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

    // With no tenant ID, there should be only
    // one entry in the catalog.
    $catalog = $service->serviceCatalog();
    $this->assertEquals(1, count($catalog));

    $service->rescopeUsingTenantName($tenantName);

    $details = $service->tokenDetails();
    $this->assertEquals($tenantName, $details['tenant']['name']);

    $catalog = $service->serviceCatalog();
    $this->assertGreaterThan(1, count($catalog));

    // Test unscoping
    $service->rescope('');
    $details = $service->tokenDetails();
    $this->assertFalse(isset($details['tenant']));
    $catalog = $service->serviceCatalog();
    $this->assertEquals(1, count($catalog));

  }

  /**
   * Test the bootstrap identity factory.
   * @depends testAuthenticateAsAccount
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

    Bootstrap::$config = $reset;

    // Test authenticating as an account.
    $settings = array(
      'account' => self::conf('openstack.identity.access'),
      'secret' => self::conf('openstack.identity.secret'),
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
      'account' => self::conf('openstack.identity.access'),
      'secret' => self::conf('openstack.identity.secret'),
      'endpoint' => self::conf('openstack.identity.url'),
      'tenantname' => self::conf('openstack.identity.tenantName'),
    );
    Bootstrap::setConfiguration($settings);

    $is = Bootstrap::identity(TRUE);
    $this->assertInstanceOf('\OpenStack\Services\IdentityService', $is);

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
