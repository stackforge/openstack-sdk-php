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
$cdnSsl = $objstore->cdnUrl($cname);
$cdnPlain = $objstore->cdnUrl($cname, FALSE);
if ($cdnSsl == $cdnPlain) {
  die(sprintf("Surprise! %s matches %s\n", $cdnSsl, $cdnPlain));
}
print 'SSL CDN: ' . $cdnSsl. PHP_EOL;
print 'Plain CDN: ' . $cdnPlain . PHP_EOL;
print 'Container CDN URL: ' . $container->cdnUrl() . PHP_EOL;
if ($container->cdnUrl() == NULL) {
  die('No CDN URL for Container ' . $cname);
}

if ($cdnSsl != $container->cdnUrl()) {
  die(sprintf("Expected SSL CDN %s to match Container CDN %s\n", $cdnSsl, $container->cdnUrl()));
}

$o = new \HPCloud\Storage\ObjectStorage\Object('CDNTest.txt', 'TEST');

$container->save($o);

$copy = $container->object($o->name());

print "***** TESTING OBJECT CDN URLS." . PHP_EOL;
print "Object SSL URL: " . $copy->url() . PHP_EOL;
print "Object CDN SSL URL: " . $copy->url(TRUE) . PHP_EOL;
print "Object CDN URL: " . $copy->url(TRUE, FALSE) . PHP_EOL;
if ($copy->url(TRUE) == $copy->url(TRUE, FALSE)) {
  die(sprintf("Object SSL URL %s should not match non-SSL URL %s\n", $copy->url(TRUE), $copy->url(TRUE, FALSE)));
}

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
$cxt2 = stream_context_create(array(
  'swift' => array(
    //'token' => $token,
    'tenantid' => $ini['hpcloud.identity.tenantId'],
    'account' => $ini['hpcloud.identity.account'],
    'key' => $ini['hpcloud.identity.secret'],
    'endpoint' => $ini['hpcloud.identity.url'],
    'use_cdn' => TRUE,
    'cdn_require_ssl' => FALSE,
  ),
));

print "***** TESTING RETURNED DATA" . PHP_EOL;
$res = array(
  'internal'       => file_get_contents('swift://' . TEST_CONTAINER . '/CDNTest.txt', FALSE, $cxt),
  'internalNoSSL'  => file_get_contents('swift://' . TEST_CONTAINER . '/CDNTest.txt', FALSE, $cxt2),
  'external'       => file_get_contents($copy->url()),
  'externalSslCdn' => file_get_contents($copy->url(TRUE)),
  'externalCdn'    => file_get_contents($copy->url(TRUE, FALSE)),
);

foreach ($res as $name => $val) {
  if ($val != 'TEST') {
    die(sprintf("Facility %s failed, returning '%s' instead of TEST.", $name, $val));
  }
}

print PHP_EOL . "***** All tests passed." . PHP_EOL;
