<?php
/**
 * @file
 *
 * Unit tests for IdentityServices.
 */
namespace HPCloud\Tests\Services;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Services\IdentityServices;


class IdentityServicesTest extends \HPCloud\Tests\TestCase {

  public function testConstructor(){
    $endpoint = self::conf('hpcloud.identity.url');
    $this->assertNotEmpty($endpoint);

    $service = new IdentityServices($endpoint);

    $this->assertInstanceOf('\HPCloud\Services\IdentityServices', $service);

    return $service;
  }

  /**
   * @depends testConstructor
   */
  public function testUrl() {
    $endpoint = self::conf('hpcloud.identity.url');
    $service = new IdentityServices($endpoint);

    $this->assertStringStartsWith($endpoint, $service->url());

    return $service;
  }

  /**
   * @depends testUrl
   */
  public function testAuthenticate($service){

    // Canary: Make sure all the required params are declared.
    $settings = array(
      'hpcloud.identity.username',
      'hpcloud.identity.password',
      'hpcloud.identity.tenantId',
      'hpcloud.identity.account',
      'hpcloud.identity.secret',
    );
    foreach ($settings as $setting) {
      $this->assertNotEmpty(self::conf($setting), "Required param: " . $setting);
    }

    // Test username/password auth.
    $auth = array(
      'passwordCredentials' => array(
        'username' => self::conf('hpcloud.identity.username'),
        'password' => self::conf('hpcloud.identity.password'),
      ),
      'tenantId' => self::conf('hpcloud.identity.tenantId'),
    );
    $tok = $service->authenticate($auth);
    $this->assertNotEmpty($tok);

    // We should get the same token if we request again.
    $service = new IdentityServices(self::conf('hpcloud.identity.url'));
    $tok2 = $service->authenticate($auth);
    $this->assertEquals($tok, $tok2);


    // Test account ID/secret key auth.
    $auth = array(
      'apiAccessKeyCredentials' => array(
        'accessKey' => self::conf('hpcloud.identity.account'),
        'secretKey' => self::conf('hpcloud.identity.secret'),
      ),
    );
    $service = new IdentityServices(self::conf('hpcloud.identity.url'));
    $tok3 = $service->authenticate($auth);

    $this->assertNotEmpty($tok3);

  }

  /**
   * @depends testAuthenticate
   */
  public function testAuthenticateAsUser() {
    $service = new IdentityServices(self::conf('hpcloud.identity.url'));

    $user = self::conf('hpcloud.identity.username');
    $pass = self::conf('hpcloud.identity.password');
    $tenantId = self::conf('hpcloud.identity.tenantId');

    $tok = $service->authenticateAsUser($user, $pass, $tenantId);

    $this->assertNotEmpty($tok);
  }

  /**
   * @depends testAuthenticate
   */
  public function testAuthenticateAsAccount() {
    $service = new IdentityServices(self::conf('hpcloud.identity.url'));

    $account = self::conf('hpcloud.identity.account');
    $secret = self::conf('hpcloud.identity.secret');

    $tok = $service->authenticateAsAccount($account, $secret);
    $this->assertNotEmpty($tok);

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
  public function testTokenDetails($service) {
    $now = time();

    // Details for account auth.
    $details = $service->tokenDetails();
    $this->assertNotEmpty($details['id']);
    $this->assertEmpty($details['tenant']);

    $ts = strtotime($details['expires']);
    $this->assertGreaterThan($now, $ts);


    // Test details for username auth.
    $service = new IdentityServices(self::conf('hpcloud.identity.url'));
    $user = self::conf('hpcloud.identity.username');
    $pass = self::conf('hpcloud.identity.password');
    $tenantId = self::conf('hpcloud.identity.tenantId');
    $service->authenticateAsUser($user, $pass, $tenantId);

    $details = $service->tokenDetails();

    $expectUser = self::conf('hpcloud.identity.username');

    $this->assertStringStartsWith($expectUser, $details['tenant']['name']);
    $this->assertNotEmpty($details['id']);
    $this->assertNotEmpty($details['tenant']['id']);

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
  }


  /**
   * @depends testAuthenticateAsAccount
   */
  public function testUser($service) {
    $user = $service->user();

    $this->assertEquals(self::conf('hpcloud.identity.username'), $user['name']);
    $this->assertNotEmpty($user['roles']);
  }

  /**
   * @group tenant
   */
  public function testTenants() {
    $service = new IdentityServices(self::conf('hpcloud.identity.url'));
    $service2 = new IdentityServices(self::conf('hpcloud.identity.url'));
    $user = self::conf('hpcloud.identity.username');
    $pass = self::conf('hpcloud.identity.password');
    $tenantId = self::conf('hpcloud.identity.tenantId');
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
    $this->markTestIncomplete();
  }

}
