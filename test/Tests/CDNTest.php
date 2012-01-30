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

  public function testConstructor() {
    $ident = $this->identity();

    $catalog = $ident->serviceCatalog(CDN::SERVICE_TYPE);
    $token = $ident->token();

    $this->assertNotEmpty($catalog[0]['endpoints'][0]['publicURL']);
    $url = $catalog[0]['endpoints'][0]['publicURL'];

    $cdn = new CDN($url, $token);

    $this->assertInstanceOf('\HPCloud\Storage\CDN', $cdn);

  }

  public function testNewFromServiceCatalog() {
    $ident = $this->identity();
    $token = $ident->token();
    $catalog = $ident->serviceCatalog();

    $cdn = CDN::newFromServiceCatalog($catalog, $token);

    $this->assertInstanceOf('\HPCloud\Storage\CDN', $cdn);
  }

}
