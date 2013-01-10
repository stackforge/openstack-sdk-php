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
 * Unit tests for HPCloud::DBaaS::Instance.
 */
namespace HPCloud\Tests\Services\DBaaS;

require_once __DIR__ . '/DBaaSTestCase.php';

use \HPCloud\Services\DBaaS;
use \HPCloud\Services\DBaaS\Instance;

/**
 * @group dbaas
 */
class DBaaSInstanceTest extends DBaaSTestCase {


  public function testConstruct() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);

    // $endpoint = self::conf('hpcloud.dbaas.endpoint') . '/' . $ident->tenantId();

    // // Test #1: Build from scratch.
    // $inst = new Instance($ident->token(), $ident->tenantName(), $endpoint);
    // $this->assertInstanceOf('\HPCloud\Services\DBaaS\Instance', $inst);

    // Test #2: Build from DBaaS.
    $inst = $dbaas->instance();
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Instance', $inst);
  }

  public function testCreate() {
    // Make sure there aren't old fixtures hanging around from a
    // failed run.
    $this->destroyDatabase();

    //throw new \Exception("Stopped here.");

    $dbName = self::conf('hpcloud.dbaas.database');

    $details = $this->inst()->create($dbName, 'small');

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\InstanceDetails', $details);

    $this->assertNotEmpty($details->username());
    $this->assertNotEmpty($details->password());
    $this->assertNotEmpty($details->id());
    // This is now no longer returned from a create().
    //$this->assertNotEmpty($details->hostname());
    $this->assertNotEmpty($details->createdOn());
    $this->assertEquals($dbName, $details->name());

    $dsn = sprintf('mysql:host=%s;port=3306;dbname=foo;charset=utf-8', $details->hostname());

    $this->assertEquals($dsn, $details->dsn('foo', 'utf-8'));

    $this->credentials = array(
      'name' => $details->username(),
      'pass' => $details->password(),
    );
    //$db->id() = $details->id();
    //$this->created = $details->createdOn();

    return $details;
  }

  /**
   * @depends testCreate
   */
  public function testDescribe($db) {
    $dbName = self::conf('hpcloud.dbaas.database');

    // Canary.
    $this->assertNotEmpty($db->id());

    $details = $this->inst()->describe($db->id());
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\InstanceDetails', $details);

    $db->setHostname($details->hostname());

    $this->assertEmpty($details->username());
    $this->assertEmpty($details->password());

    // This is no longer available until after the server is up.
    //$this->assertNotEmpty($details->hostname());
    $this->assertNotEmpty($details->createdOn());

    $this->assertEquals($db->id(), $details->id());
    $this->assertEquals($dbName, $details->name());

  }

  /**
   * @depends testCreate
   */
  public function testRestart($db) {
    // Canary.
    $this->assertNotEmpty($db->id());

    $inst = $this->inst();

    $this->waitUntilRunning($inst, $db, TRUE);

    $inst->restart($db->id());
    $this->waitUntilRunning($inst, $db, TRUE);

    $details = $this->inst()->describe($db->id());

    $this->assertEquals($db->createdOn(), $details->createdOn());
  }

  /**
   * @depends testCreate
   */
  public function testListInstances($db) {

    $instances = $this->inst()->listInstances();

    $this->assertNotEmpty($instances);

    $match = 0;
    $dbName = self::conf('hpcloud.dbaas.database');

    foreach ($instances as $server) {
      $this->assertInstanceOf('\HPCloud\Services\DBaaS\InstanceDetails', $server);
      $this->assertNotEmpty($server->id());
      if ($server->name() == $dbName) {
        ++$match;
      }
    }
    $this->assertEquals(1, $match);

    return $db;
  }

  /**
   * @depends testListInstances
   */
  public function testIsItAllWorthIt($db) {
    $inst = $this->inst();

    $this->waitUntilRunning($inst, $db, TRUE);
    $dsn = $db->dsn();

    $this->assertNotEmpty($dsn);

    try {
      $conn = new \PDO($dsn, $db->username(), $db->password());
    }
    catch (\PDOException $e) {
      throw new \Exception("Could not connect. "
        . " This is normal if unit tests are not run on HP Cloud Compute instances. Exception: "
        . $e->getMessage()
      );
    }

    $affected = $conn->exec('SELECT 1');

    $this->assertEquals(0, $affected, "Connect and run a SELECT.");

    unset($conn);
  }
  /**
   * @depends testCreate
   */
  public function testResetPassword($db) {
    $pass = $db->password();
    $this->assertNotEmpty($pass);

    $newPass = $this->inst()->resetPassword($db->id());

    $this->assertNotEmpty($newPass);
    $this->assertNotEquals($pass, $newPass);
  }


  /**
   * @depends testCreate
   */
  public function testDelete($db) {
    $match = 0;
    $dbName = self::conf('hpcloud.dbaas.database');

    $inst = $this->inst();

    $inst->delete($db->id());

    $list = $inst->listInstances();
    foreach ($list as $server) {
      if ($server->name() == $dbName) {
        ++$match;
      }
    }
    $this->assertEquals(0, $match);
  }

}
