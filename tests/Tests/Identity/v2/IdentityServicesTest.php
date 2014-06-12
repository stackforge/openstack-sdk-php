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

namespace OpenStack\Tests\Identity\v2;

use \OpenStack\Identity\v2\IdentityService;
use \OpenStack\Bootstrap;

class IdentityServicesTest extends \OpenStack\Tests\TestCase
{
    public function testConstructor()
    {
        $endpoint = self::conf('openstack.identity.url');
        $this->assertNotEmpty($endpoint);

        $service = new IdentityService($endpoint, $this->getTransportClient());

        $this->assertInstanceOf('\OpenStack\Identity\v2\IdentityService', $service);

        return $service;
    }

    /**
     * @depends testConstructor
     */
    public function testUrl()
    {
        $endpoint = self::conf('openstack.identity.url');
        $service = new IdentityService($endpoint, $this->getTransportClient());

        // If there is a trailing / we remove that from the endpoint. Our calls add
        // the / back where appropriate.
        $this->assertStringStartsWith(rtrim($endpoint, '/'), $service->url());

        return $service;
    }

    /**
     * @depends testUrl
     */
    public function testAuthenticate($service)
    {
        // Canary: Make sure all the required params are declared.
        $settings = [
            'openstack.identity.username',
            'openstack.identity.password',
            'openstack.identity.tenantId',
        ];
        foreach ($settings as $setting) {
            $this->assertNotEmpty(self::conf($setting), "Required param: " . $setting);
        }

        // Test username/password auth.
        $auth = [
            'passwordCredentials' => [
                'username' => self::conf('openstack.identity.username'),
                'password' => self::conf('openstack.identity.password'),
            ],
            'tenantId' => self::conf('openstack.identity.tenantId'),
        ];
        $tok = $service->authenticate($auth);
        $this->assertNotEmpty($tok);

        // Again with no tenant ID.
        $auth = [
            'passwordCredentials' => [
                'username' => self::conf('openstack.identity.username'),
                'password' => self::conf('openstack.identity.password'),
            ],
            //'tenantId' => self::conf('openstack.identity.tenantId'),
        ];
        $tok = $service->authenticate($auth);
        $this->assertNotEmpty($tok);
    }

    /**
     * @depends testAuthenticate
     */
    public function testAuthenticateAsUser()
    {
        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());

        $user = self::conf('openstack.identity.username');
        $pass = self::conf('openstack.identity.password');
        $tenantId = self::conf('openstack.identity.tenantId');

        $tok = $service->authenticateAsUser($user, $pass, $tenantId);
        $this->assertNotEmpty($tok);

        return $service;
    }

    public function testAuthenticatingAsUserWithoutTenant()
    {
        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());

        $username = self::conf('openstack.identity.username');
        $password = self::conf('openstack.identity.password');

        $this->assertNotEmpty($service->authenticateAsUser($username, $password));
    }

    /**
     * @depends testAuthenticateAsUser
     */
    public function testToken($service)
    {
        $this->assertNotEmpty($service->token());
    }

    /**
     * @depends testAuthenticateAsUser
     */
    public function testIsExpired($service)
    {
        $this->assertFalse($service->isExpired());

        $service2 = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $this->assertTrue($service2->isExpired());
    }

    /**
     * @depends testAuthenticateAsUser
     */
    public function testTenantName()
    {
        $user = self::conf('openstack.identity.username');
        $pass = self::conf('openstack.identity.password');
        $tenantName = self::conf('openstack.identity.tenantName');

        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $this->assertNull($service->tenantName());

        $service->authenticateAsUser($user, $pass);
        $this->assertEmpty($service->tenantName());

        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $ret = $service->authenticateAsUser($user, $pass, null, $tenantName);
        $this->assertNotEmpty($service->tenantName());

        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $this->assertNull($service->tenantName());
    }

    /**
     * @depends testAuthenticateAsUser
     */
    public function testTenantId()
    {
        $user = self::conf('openstack.identity.username');
        $pass = self::conf('openstack.identity.password');
        $tenantId = self::conf('openstack.identity.tenantId');

        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $this->assertNull($service->tenantId());

        $service->authenticateAsUser($user, $pass);
        $this->assertEmpty($service->tenantId());

        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $service->authenticateAsUser($user, $pass, $tenantId);
        $this->assertNotEmpty($service->tenantId());
    }

    /**
     * @depends testAuthenticateAsUser
     */
    public function testTokenDetails()
    {
        $now = time();
        $user = self::conf('openstack.identity.username');
        $pass = self::conf('openstack.identity.password');
        $tenantId = self::conf('openstack.identity.tenantId');

        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $service->authenticateAsUser($user, $pass);

        // Details for user auth.
        $details = $service->tokenDetails();
        $this->assertNotEmpty($details['id']);
        $this->assertFalse(isset($details['tenant']));

        $ts = strtotime($details['expires']);
        $this->assertGreaterThan($now, $ts);

        // Test details for username auth.
        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
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
    public function testServiceCatalog($service)
    {
        $catalog = $service->serviceCatalog();

        $this->assertGreaterThan(0, count($catalog));

        $idService = null;
        foreach ($catalog as $item) {
            if ($item['type'] == 'identity') {
                $idService = $item;
            }
        }

        $this->assertNotEmpty($idService['endpoints']);
        $this->assertNotEmpty($idService['endpoints'][0]['publicURL']);

        // Test filters.
        $justID = $service->serviceCatalog('identity');
        $this->assertEquals(1, count($justID));

        $idService = $justID[0];
        $this->assertNotEmpty($idService['endpoints']);
        $this->assertNotEmpty($idService['endpoints'][0]['publicURL']);

        // Make sure a missed filter returns an empty set.
        $expectEmpty = $service->serviceCatalog('no-such-servicename');
        $this->assertEmpty($expectEmpty);
    }

    /**
     * @depends testAuthenticateAsUser
     */
    public function testUser($service)
    {
        $user = $service->user();

        $this->assertEquals(self::conf('openstack.identity.username'), $user['name']);
        $this->assertNotEmpty($user['roles']);
    }

    /**
     * @depends testAuthenticateAsUser
     * @group serialize
     */
    public function testSerialization($service)
    {
        $ser = serialize($service);

        $this->assertNotEmpty($ser);

        $again = unserialize($ser);

        $this->assertInstanceOf('\OpenStack\Identity\v2\IdentityService', $again);

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
    public function testTenants()
    {
        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
        $service2 = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
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
    public function testRescopeUsingTenantId()
    {
        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
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
    public function testRescopeByTenantName()
    {
        $service = new IdentityService(self::conf('openstack.identity.url'), $this->getTransportClient());
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
        $service->rescopeUsingTenantName('');
        $details = $service->tokenDetails();
        $this->assertFalse(isset($details['tenant']));
    }

    /**
     * Test the bootstrap identity factory.
     * @depends testAuthenticateAsUser
     */
    public function testBootstrap()
    {
        // We need to save the config settings and reset the bootstrap to this.
        // It does not remove the old settings. The means the identity fall through
        // for different settings may not happen because of ordering. So, we cache
        // and reset back to the default for each test.
        $reset = Bootstrap::$config;

        // Test authenticating as a user.
        $settings = [
            'username' => self::conf('openstack.identity.username'),
            'password' => self::conf('openstack.identity.password'),
            'endpoint' => self::conf('openstack.identity.url'),
            'tenantid' => self::conf('openstack.identity.tenantId'),
            'transport' => self::conf('transport'),
            'transport.debug' => self::conf('transport.debug', false),
            'transport.ssl_verify' => self::conf('transport.ssl', true),
        ];
        if (self::conf('transport.timeout')) {
            $setting['transport.timeout'] = self::conf('transport.timeout');
        }
        if (self::conf('transport.proxy')) {
            $setting['transport.proxy'] = self::conf('transport.proxy');
        }
        Bootstrap::setConfiguration($settings);

        $is = Bootstrap::identity(true);
        $this->assertInstanceOf('\OpenStack\Identity\v2\IdentityService', $is);

        // Test getting a second instance from the cache.
        $is2 = Bootstrap::identity();
        $this->assertEquals($is, $is2);

        // Test that forcing a refresh does so.
        $is2 = Bootstrap::identity(true);
        $this->assertNotEquals($is, $is2);

        Bootstrap::$config = $reset;

        // Test with tenant name
        $settings = [
            'username' => self::conf('openstack.identity.username'),
            'password' => self::conf('openstack.identity.password'),
            'endpoint' => self::conf('openstack.identity.url'),
            'tenantname' => self::conf('openstack.identity.tenantName'),
            'transport' => self::conf('transport'),
            'transport.debug' => self::conf('transport.debug', false),
            'transport.ssl_verify' => self::conf('transport.ssl', true),
        ];
        if (self::conf('transport.timeout')) {
            $setting['transport.timeout'] = self::conf('transport.timeout');
        }
        if (self::conf('transport.proxy')) {
            $setting['transport.proxy'] = self::conf('transport.proxy');
        }
        Bootstrap::setConfiguration($settings);

        $is = Bootstrap::identity(true);
        $this->assertInstanceOf('\OpenStack\Identity\v2\IdentityService', $is);
    }
}
