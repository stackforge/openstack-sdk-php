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

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Services\DBaaS;
use \HPCloud\Services\DBaaS\Instance;

/**
 * @group dbaas
 */
class DBaaSInstanceTest extends \HPCloud\Tests\TestCase {

  public function inst() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);
    return $dbaas->instance();
  }

  public function destroyDatabase() {
    $inst = $this->inst();
    $list = $inst->listInstances();

    fwrite(STDOUT, print_r($list, TRUE));
    if (!empty($list['instances'])) {
      $dbName = self::conf('hpcloud.dbaas.database');
      foreach ($list['instances'] as $item) {
        if ($item['name'] == $dbName) {
          fprintf(STDOUT, "Deleting %s (%s)\n", $item['name'], $item['id']);
          $inst->delete($item['id']);
        }
      }
    }

  }

  public function testConstruct() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);

    $endpoint = self::conf('hpcloud.dbaas.endpoint') . '/' . $ident->tenantId();

    // Test #1: Build from scratch.
    $inst = new Instance($ident->token(), $ident->tenantName(), $endpoint);
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Instance', $inst);

    // Test #2: Build from DBaaS.
    $inst = $dbaas->instance();
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Instance', $inst);
  }

  public function testCreate() {
    // Make sure there aren't old fixtures hanging around from a
    // failed run.
    $this->destroyDatabase();

    $dbName = self::conf('hpcloud.dbaas.database');

    $details = $this->inst()->create($dbName, 'small', '3307');

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\InstanceDetails', $details);

    $this->assertNotEmpty($details->username());
    $this->assertNotEmpty($details->password());
    $this->assertNotEmpty($details->id());
    $this->assertNotEmpty($details->hostname());
    $this->assertNotEmpty($details->port());
    $this->assertNotEmpty($details->createdOn());
    $this->assertEquals($dbName, $details->name());

    $dsn = sprint('mysql:host=%s;port=3307;dbname=foo;charset=utf-8', $details->hostname());

    $this->assertEquals($dsn, $details->dsn('foo', 'utf-8'));

    $this->credentials = array(
      'name' => $details->username(),
      'pass' => $details->password(),
    );
    $this->dbId = $details->id();
    $this->created = $details->createdOn();
  }

  /**
   * @depends testCreate
   */
  public function testDescribe() {
    $dbName = self::conf('hpcloud.dbaas.database');

    // Canary.
    $this->assertNotEmpty($this->dbId);

    $details = $this->inst()->describe($this->dbId);
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\InstanceDetails', $details);

    $this->assertEmpty($details->username());
    $this->assertEmpty($details->password());

    $this->assertNotEmpty($details->hostname());
    $this->assertNotEmpty($details->createdOn());

    $this->assertEquals($this->dbId, $details->id());
    $this->assertEquals($dbName, $details->name());

  }

  /**
   * @depends testCreate
   */
  public function testRestart() {
    // Canary.
    $this->assertNotEmpty($this->dbId);

    $this->inst()->restart($this->dbId);
    sleep(5);
    $details = $this->inst()->details($this->dbId);

    $this->assertEquals($this->created, $details->createdOn());
  }

  /**
   * @depends testCreate
   */
  public function testResetPassword() {
    $pass = $this->credentials['pass'];
    $this->assertNotEmpty($pass);

    $newPass = $this->inst()->resetPassword($this->dbId);

    $this->assertNotEmpty($newPass);
    $this->assertNotEquals($pass, $newPass);
  }

  /**
   * @depends testCreate
   */
  public function testListInstances() {

    $instances = $this->inst()->listInstances();

    $this->assertNotEmpty($instances);
    $this->assertGreaterThan(0, count($instances['instances']));

    $match = 0;
    $dbName = self::conf('hpcloud.dbaas.database');

    foreach ($instances['instances'] as $server) {
      $this->assertInstanceOf('\HPCloud\Services\DBaaS\InstanceDetails', $server);
      $this->assertNotEmpty($server->id());
      if ($server->name() == $dbName) {
        ++$match;
      }
    }
    $this->assertEquals(1, $match);
  }

  /**
   * @depends testListInstances
   */
  public function testIsItAllWorthIt() {
    $inst = $this->inst();

    $maxAttempts = 5;
    for($i = 0; $i < $maxAttempts; ++$i) {
      $details = $inst->describe($this->dbId);

      if ($details->status == 'ready') {
        $dsn = $details->dsn();
        break;
      }

    }

    $this->assertNotEmpty($dsn);

    $conn = new PDO($dsn, $this->credentials['user'], $this->credentials['pass']);

    $affected = $conn->execute('SELECT 1');

    $this->assertEquals(0, $affected);

    unset($conn);
  }

  /**
   * @depends testCreate
   */
  public function testDelete() {
    $match = 0;
    $dbName = self::conf('hpcloud.dbaas.database');

    $inst = $this->inst();

    $inst->delete($this->dbId);

    foreach ($instances['instances'] as $server) {
      if ($server->name() == $dbName) {
        ++$match;
      }
    }
    $this->assertEquals(0, $match);
  }

}
