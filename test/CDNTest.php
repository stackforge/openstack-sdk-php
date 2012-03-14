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
 * A commandline demonstration of the PHP API.
 */

// Name of the container to test. It must have
// CDN enabled. Using the one you use for standard
// tests is ill advised.
define('TEST_CONTAINER', 'mycontainer');

$base = __dir__ . '/../src';

require_once $base . '/HPCloud/Bootstrap.php';

\HPCloud\Bootstrap::useAutoloader();

$inifile = __DIR__ . '/settings.ini';
if (!is_readable($inifile)) {
  die('Could not find ' . $inifile);
}

$ini = parse_ini_file($inifile, FALSE);

\HPCloud\Bootstrap::setConfiguration($ini);
\HPCloud\Bootstrap::useStreamWrappers();


$id = new \HPCloud\Services\IdentityServices($ini['hpcloud.identity.url']);
//$token = $id->authenticateAsAccount($ini['hpcloud.identity.account'], $ini['hpcloud.identity.secret'], $ini['hpcloud.identity.tenantId']);
$token = $id->authenticateAsUser($ini['hpcloud.identity.username'], $ini['hpcloud.identity.password'], $ini['hpcloud.identity.tenantId']);

$objstore = \HPCloud\Storage\ObjectStorage::newFromServiceCatalog($id->serviceCatalog(), $token);
$cdn = \HPCloud\Storage\CDN::newFromServiceCatalog($id->serviceCatalog(), $token);

$objstore->useCDN($cdn);

//var_dump($cdn->containers());

// Check that the container has CDN.
$cname = TEST_CONTAINER; //$ini['hpcloud.swift.container'];
$isEnabled = FALSE;

$cdnData = $cdn->container($cname);
print "***** TESTING CDN ENABLED" . PHP_EOL;
if ($cdnData['cdn_enabled'] != 1) {
  die('Cannot test CDN: You must enable CDN on ' . $cname);
}

$container = $objstore->container($cname);

print "***** TESTING CDN URL" . PHP_EOL;
if ($container->cdnUrl() == NULL) {
  die('No CDN URL for Container ' . $cname);
}

$o = new \HPCloud\Storage\ObjectStorage\Object('CDNTest.txt', 'TEST');

$container->save($o);

$copy = $container->object($o->name());

print "***** TESTING THAT CDN WAS USED." . PHP_EOL;
if ($copy->url() == $copy->url(TRUE)) {
  die('Object Storage not used for ' . $o->name());
}

print "***** TESTING STREAM WRAPPERS " . PHP_EOL;
$cxt = stream_context_create(array(
  'swift' => array(
    //'token' => $token,
    'tenantid' => $ini['hpcloud.identity.tenantId'],
    'account' => $ini['hpcloud.identity.account'],
    'key' => $ini['hpcloud.identity.secret'],
    'endpoint' => $ini['hpcloud.identity.url'],
    'use_cdn' => TRUE,
  ),
));

print "***** TESTING RETURNED DATA" . PHP_EOL;
print file_get_contents('swift://' . TEST_CONTAINER . '/CDNTest.txt', FALSE, $cxt);

print PHP_EOL . "***** All tests passed." . PHP_EOL;
