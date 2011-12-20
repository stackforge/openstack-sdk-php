<?php
/**
 * @file
 * This is a simple command-line test for authentication.
 *
 * You can run the test with `php test/AuthTest.php username key`.
 */

require_once 'src/HPCloud/Bootstrap.php';

use \HPCloud\Storage\ObjectStorage;

\HPCloud\Bootstrap::useAutoloader();

$usage = "php $0 ID KEY URL";

if ($argc < 4) {
  print 'ID, Key, and URL are all required.' . PHP_EOL;
  print $usage . PHP_EOL;
  exit(1);
}

$user = $argv[1];
$key = $argv[2];
$uri = $argv[3];

$store = ObjectStorage::newFromSwiftAuth($user, $key, $uri);

$token = $store->getAuthToken();

if (empty($token)) {
  print "Authentication seemed to succeed, but no token was return." . PHP_EOL;
  exit(1);
}

print "Success! The authentication token is $token." . PHP_EOL;
