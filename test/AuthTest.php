<?php
/**
 * @file
 * This is a simple command-line test for authentication.
 *
 * You can run the test with `php test/AuthTest.php username key`.
 */

//require_once 'src/HPCloud/Transport/Transporter.php';
//require_once 'src/HPCloud/Transport/PHPStreamTransport.php';
require_once 'src/HPCloud/Bootstrap.php';

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

$headers = array(
  'X-Auth-User' => $user,
  'X-Auth-Key' => $key,
);

$t = new \HPCloud\Transport\PHPStreamTransport();
$res = $t->doRequest($uri, 'GET', $headers);


print_r($res);
