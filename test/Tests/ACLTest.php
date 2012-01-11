<?php
/**
 * @file
 *
 * Unit tests for ObjectStorage ACLs.
 */
namespace HPCloud\Tests\Storage\ObjectStorage;

require_once 'src/HPCloud/Bootstrap.php';
require_once 'test/TestCase.php';

use \HPCloud\Storage\ObjectStorage\ACL;

class ACLTest extends \HPCloud\Tests\TestCase {

  public function testConstructor() {
    $acl = new ACL();
    $this->assertEmpty($acl->rules());

  }

  public function testAddAccount() {
    $acl = new ACL();

    $acl->addAccount(ACL::READ, 'test');

    $rules = $acl->rules();

    $this->assertEquals(1, count($rules));

    $rule = array_shift($rules);

    $this->assertEquals(ACL::READ, $rule['mask']);
    $this->assertEquals('test', $rule['account']);

    // Test with user
    $acl = new ACL();
    $acl->addAccount(ACL::WRITE, 'admin', 'earnie');
    $rules = $acl->rules();
    $rule = array_shift($rules);

    $this->assertEquals(ACL::WRITE, $rule['mask']);
    $this->assertEquals('admin', $rule['account']);
    $this->assertEquals('earnie', $rule['user']);

    // Test with multiple users:
    $acl = new ACL();
    $acl->addAccount(ACL::WRITE, 'admin', array('earnie', 'bert'));
    $rules = $acl->rules();
    $rule = array_shift($rules);

    $this->assertEquals(ACL::WRITE, $rule['mask']);
    $this->assertEquals('admin', $rule['account']);
    $this->assertEquals('earnie', $rule['user'][0]);
    $this->assertEquals('bert', $rule['user'][1]);

  }

  public function testAddReferrer() {
    $acl = new ACL();
    $acl->addReferrer(ACL::READ, '.example.com');
    $acl->addReferrer(ACL::READ_WRITE, '-bad.example.com');

    $rules = $acl->rules();

    $this->assertEquals(2, count($rules));

    $first = array_shift($rules);
    $this->assertEquals(ACL::READ, $first['mask']);
    $this->assertEquals('.example.com', $first['host']);
  }

  public function testAllowListings() {
    $acl = new ACL();
    $acl->allowListings();
    $rules = $acl->rules();

    $this->assertEquals(1, count($rules));
    $this->assertTrue($rules[0]['rlistings']);
    $this->assertEquals(ACL::READ, $rules[0]['mask']);
  }

  public function testHeaders() {
    $acl = new ACL();
    $acl->addAccount(ACL::READ_WRITE, 'test');

    $headers = $acl->headers();

    $this->assertEquals(2, count($headers));
    $read = $headers[ACL::HEADER_READ];
    $write = $headers[ACL::HEADER_WRITE];

    $this->assertEquals('test', $read);
    $this->assertEquals('test', $write);

    // Test hostname rules, which should only appear in READ.
    $acl = new ACL();
    $acl->addReferrer(ACL::READ_WRITE, '.example.com');
    $headers = $acl->headers();

    $this->assertEquals(1, count($headers), print_r($headers, TRUE));
    $read = $headers[ACL::HEADER_READ];

    $this->assertEquals('.r:.example.com', $read);
  }

  public function testToString() {
    $acl = new ACL();
    $acl->addReferrer(ACL::READ_WRITE, '.example.com');

    $str = (string) $acl;

    $this->assertEquals('X-Container-Read: .r:.example.com', $str);
  }

  public function testPublicRead() {
    $acl = (string) ACL::publicRead();

    $this->assertEquals('X-Container-Read: .r:*,.rlistings', $acl);
  }

  public function testNonPublic() {
    $acl = (string) ACL::nonPublic();

    $this->assertEmpty($acl);
  }

}
