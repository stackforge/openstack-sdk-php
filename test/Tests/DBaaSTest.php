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
 * Unit tests for DBaaS.
 */
namespace HPCloud\Tests\Services;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Services\DBaaS;

/**
 * @group dbaas
 */
class DBaaSTest extends \HPCloud\Tests\TestCase {

  protected function dbaas() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);

    return $dbaas;
  }

  public function testNewFromIdentity() {
    $ident = $this->identity();

    // Canaries
    $this->assertNotEmpty($ident->token());
    $this->assertNotEmpty($ident->tenantName());
    $this->assertNotEmpty($ident->serviceCatalog());

    // TODO: Add Canary tp check that DBaaS is in the
    // service catalog.

    $dbaas = DBaaS::newFromIdentity($ident);
    $this->assertInstanceOf("\HPCloud\Services\DBaaS", $dbaas);
    $this->assertStringEndsWith($ident->tenantId(), $dbaas->url());
    // $this->markTestIncomplete();
  }

  public function testConstructor() {
    $ident = $this->identity();
    $dbaas = new DBaaS($ident->token(), self::conf('hpcloud.dbaas.endpoint'), $ident->tenantName());

    $this->assertInstanceOf("\HPCloud\Services\DBaaS", $dbaas);
    $this->assertEquals($ident->tenantName(), $dbaas->projectId());
  }

  /**
   * @depends testConstructor
   */
  public function testProjectId() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);

    $this->assertEquals($ident->tenantName(), $dbaas->projectId());
  }

  /**
   * @depends testConstructor
   */
  public function testInstance() {
    $inst = $this->dbaas()->instance();
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Instance', $inst);
  }

  /**
   * @depends testConstructor
   */
  public function testSnapshot() {
    $snap = $this->dbaas()->snapshot();
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Snapshot', $snap);
  }
}
