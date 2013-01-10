<?php
/* ============================================================================
(c) Copyright 2013 Hewlett-Packard Development Company, L.P.
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
 * Unit tests for HPCloud::DBaaS::Flavor.
 */
namespace HPCloud\Tests\Services\DBaaS;

require_once __DIR__ . '/DBaaSTestCase.php';

use \HPCloud\Services\DBaaS;
use \HPCloud\Services\DBaaS\Flavor;
use \HPCloud\Services\DBaaS\FlavorDetails;
use \HPCloud\Exception;

/**
 * @group dbaas
 */
class DBaaSFlavorTest extends DBaaSTestCase {
	public function testListFlavors() {
    $flavors = $this->dbaas()->flavor()->listFlavors();

    $this->assertNotEmpty($flavors);

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\FlavorDetails', $flavors[0]);
  }

  public function testGetFlavorByName() {
  	$flavor = $this->dbaas()->flavor();

  	$small = $flavor->getFlavorByName('small');

  	$this->assertInstanceOf('\HPCloud\Services\DBaaS\FlavorDetails', $small);
  	$this->assertEquals('small', $small->name());

  	// make sure a failure happens well
  	try {
  		$foo = $flavor->getFlavorByName('foo');  // This should be a non-real name.

  		$this->fail("Found flavor that should not exist.");

  	} catch (Exception $e) {
  		if ($e->getMessage() != 'DBaaS Flavor foo not available.') {
  			$this->fail('Flavor not found with wrong error message.');
  		}
  	}
  }
}