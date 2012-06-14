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
 * This is a simple command-line test for authentication.
 *
 * You can run the test with `php test/AuthTest.php username key`.
 */

$base = dirname(__DIR__);
require_once $base . '/src/HPCloud/Bootstrap.php';

use \HPCloud\Storage\ObjectStorage;
use \HPCloud\Services\IdentityServices;

$config = array(
  'transport' => '\HPCloud\Transport\CURLTransport',
  'transport.timeout' => 240,
  //'transport.debug' => 1,
  'transport.ssl.verify' => 0,
);

\HPCloud\Bootstrap::useAutoloader();
\HPCloud\Bootstrap::setConfiguration($config);

$help = "Authenticate against HPCloud Identity Services.

You can authenticate either by account number and access key, or (by using the
-u flag) by username, password.

While Tenant ID is optional, it is recommended.

In both cases, you must supply a URL to the Identity Services endpoint.
";

$usage = "php {$argv[0]} [-u] ID SECRET URL [TENANT_ID]";

if ($argc > 1 && $argv[1] == '--help') {
  print PHP_EOL . "\t" . $usage . PHP_EOL;
  print PHP_EOL . $help . PHP_EOL;
  exit(1);
}
elseif ($argc < 4) {
  print 'ID, Key, and URL are all required.' . PHP_EOL;
  print $usage . PHP_EOL;
  exit(1);
}

$asUser = FALSE;
$offset = 0;
if ($argv[1] == '-u') {
  $asUser = TRUE;
  ++$offset;
}

$user = $argv[1 + $offset];
$key = $argv[2 + $offset];
$uri = $argv[3 + $offset];

$tenantId = NULL;
if (!empty($argv[4 + $offset])) {
  $tenantId = $argv[4 + $offset];
}

/*
$store = ObjectStorage::newFromSwiftAuth($user, $key, $uri);

$token = $store->token();
 */
$cs = new IdentityServices($uri);

if ($asUser) {
  $token = $cs->authenticateAsUser($user, $key, $tenantId);
}
else {
  $token = $cs->authenticateAsAccount($user, $key, $tenantId);
}

if (empty($token)) {
  print "Authentication seemed to succeed, but no token was returned." . PHP_EOL;
  exit(1);
}

$t = "You are logged in as %s with token %s (good until %s)." . PHP_EOL;
$tokenDetails = $cs->tokenDetails();
$user = $cs->user();

printf($t, $user['name'], $cs->token(), $tokenDetails['expires']);

print "The following services are available on this account:" . PHP_EOL;

$services = $cs->serviceCatalog();
foreach ($services as $service) {
  print "\t" . $service['name'] . PHP_EOL;
}

//print_r($services);
