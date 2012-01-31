<?php
/**
 * @file
 *
 * Unit tests for CDN.
 */
namespace HPCloud\Tests\Storage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\CDN;

/**
 * @ingroup Tests
 */
class CDNTest extends \HPCloud\Tests\TestCase {

  const TTL = 1234;

  protected function destroyCDNFixture($cdn) {
    $cname = $this->conf('hpcloud.swift.container');
    $cdn->delete($cname);
  }

  public function testConstructor() {
    $ident = $this->identity();

    $catalog = $ident->serviceCatalog(CDN::SERVICE_TYPE);
    $token = $ident->token();

    $this->assertNotEmpty($catalog[0]['endpoints'][0]['publicURL']);
    $parts = parse_url($catalog[0]['endpoints'][0]['publicURL']);
    $url = 'https://' . $parts['host'];

    $cdn = new CDN($url, $token);

    $this->assertInstanceOf('\HPCloud\Storage\CDN', $cdn);

  }

  /**
   * @depends testConstructor
   */
  public function testNewFromServiceCatalog() {
    $ident = $this->identity();
    $token = $ident->token();
    $catalog = $ident->serviceCatalog();

    $cdn = CDN::newFromServiceCatalog($catalog, $token);

    $this->assertInstanceOf('\HPCloud\Storage\CDN', $cdn);

    return $cdn;
  }

  /**
   * @depends testNewFromServiceCatalog
   */
  public function testEnable($cdn) {
    $container = $this->conf('hpcloud.swift.container');

    $this->destroyCDNFixture($cdn);

    $retval = $cdn->enable($container, self::TTL);
    $this->assertTrue($retval);

    // enabling twice STILL returns 201.
    // $retval = $cdn->enable($container, self::TTL);
    // $this->assertFalse($retval);
    $cdn->enable('test');

    return $cdn;
  }

  /**
   * @depends testEnable
   */
  public function testContainers($cdn) {
    $containerList = $cdn->containers();
    $cname = $this->conf('hpcloud.swift.container');

    $this->assertTrue(is_array($containerList));

    $this->assertGreaterThanOrEqual(1, count($containerList));

    $find = NULL;
    foreach ($containerList as $container) {
      if ($container['name'] == $cname) {
        $find = $container;
      }
    }

    $this->assertNotEmpty($find);
    $this->assertEquals(self::TTL, $find['ttl']);
    $this->assertNotEmpty($find['x-cdn-uri']);
    $this->assertFalse($find['log_retention']);
    $this->assertTrue($find['cdn_enabled']);

    return $cdn;
  }

  /**
   * @depends testContainers
   */
  public function testContainer($cdn) {
    $cname = $this->conf('hpcloud.swift.container');
    $properties = $cdn->container($cname);

    //throw new \Exception(print_r($properties, TRUE));

    $this->assertNotEmpty($properties);

    $this->assertEquals(self::TTL, $properties['ttl']);
    $this->assertNotEmpty($properties['x-cdn-uri']);
    $this->assertFalse($properties['log_retention']);
    $this->assertTrue($properties['cdn_enabled']);

    return $cdn;
  }

  /**
   * @depends testContainer
   */
  public function testUpdate($cdn) {
    $cname = $this->conf('hpcloud.swift.container');

    $cdn->update($cname, array('ttl' => '4321'));

    $props = $cdn->container($cname);

    $this->assertEquals('4321', $props['ttl']);

    return $cdn;
  }

  /**
   * @depends testUpdate
   */
  public function testDisable($cdn) {
    $this->markTestIncomplete();
    return $cdn;
  }

  /**
   * @depends testDisableContainer
   */
  public function testDelete($cdn) {
    $this->markTestIncomplete();
    return $cdn;
  }

}
