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
 * Unit tests for HPCloud::DBaaS::Snapshot.
 */
namespace HPCloud\Tests\Services\DBaaS;

require_once __DIR__ . '/DBaaSTestCase.php';

use \HPCloud\Services\DBaaS;
use \HPCloud\Services\DBaaS\Snapshot;

/**
 * @group dbaas
 */
class DBaaSSnapshot extends DBaaSTestCase {

  public function testConstruct() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);

    $snap = $dbaas->snapshot();

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Snapshot', $snap);
  }

  public function testCreate() {
    $dbname = self::conf('hpcloud.dbaas.database');
    $this->assertNotEmpty($dbname);

    $this->destroyDatabase();
    //$this->destroySnapshots();

    $dbaas = $this->dbaas();
    $inst = $dbaas->instance();

    $details = $inst->create($dbname);
    $this->waitUntilRunning($inst, $details, TRUE);

    $id = $details->id();

    $this->assertNotEmpty($id);

    $snap = $dbaas->snapshot();
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Snapshot', $snap);

    $name = $id . self::SNAPSHOT_SUFFIX;

    $snap->listSnapshots();

    $details = $snap->create($id, $name);
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\SnapshotDetails', $details);

    //$this->waitUntilSnapshotReady($snap, $details, TRUE);

    $this->assertNotEmpty($details->id());
    $this->assertNotEmpty($details->instanceId());
    //$this->assertNotEmpty($details->status());
    $this->assertNotEmpty($details->createdOn());
    $this->assertNotEmpty($details->links());
    $links = $details->links();
    $this->assertEquals('self', $links[0]['rel']);
    $this->assertNotEmpty($links[0]['href']);

    return $details;
  }

  /**
   * @depends testCreate
   */
  public function testDescribe($info) {
    $snap = $this->dbaas()->snapshot();

    $details = $snap->describe($info->id());

    $this->assertEquals($info->id(), $details->id());
    $this->assertEquals($info->instanceId(), $details->instanceId());
  }

  /**
   * @depends testCreate
   */
  public function testListSnapshots($info) {
    $snap = $this->dbaas()->snapshot();

    // Test listing all
    $all = $snap->listSnapshots();
    $this->assertNotEmpty($all);

    $found;
    foreach ($all as $item) {
      if ($item->id() == $info->id()) {
        $found = $item;
      }
    }

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\SnapshotDetails', $found);


    // Test listing just for specific instance ID.
    $all = $snap->listSnapshots($info->instanceId());
    $this->assertEquals(1, count($all));

    $found = NULL;
    foreach ($all as $item) {
      if ($item->id() == $info->id()) {
        $found = $item;
      }
    }

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\SnapshotDetails', $found);
    $this->assertEquals($item->id(), $found->id());
  }

  /**
   * @depends testCreate
   */
  public function testDelete($info) {
    $snap = $this->dbaas()->snapshot();

    $res = $snap->delete($info->id());

    $this->assertTrue($res);

    $snaps = $snap->listSnapshots($info->id());

    $this->assertEmpty($snaps);
  }

}
