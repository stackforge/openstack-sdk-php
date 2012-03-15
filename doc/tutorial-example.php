<?php
require_once __DIR__ . '/../src/HPCloud/Bootstrap.php';

use \HPCloud\Bootstrap;
use \HPCloud\Services\IdentityServices;
use \HPCloud\Storage\ObjectStorage;
use \HPCloud\Storage\ObjectStorage\Object;

Bootstrap::useAutoloader();

// Load these from an ini file.
$ini = parse_ini_file(getenv('HOME') . '/.hpcloud.ini');
$account = $ini['account'];
$key = $ini['secret'];
$tenantId = $ini['tenantId'];
$endpoint = $ini['url'];

$idService = new IdentityServices($endpoint);
$token = $idService->authenticateAsAccount($account, $key, $tenantId);

$catalog = $idService->serviceCatalog();

$store = ObjectStorage::newFromServiceCatalog($catalog, $token);

$store->createContainer('Example');
$container = $store->container('Example');

$name = 'hello.txt';
$content = 'Hello World';
$mime = 'text/plain';

$localObject = new Object($name, $content, $mime);
$container->save($localObject);

$object = $container->object('hello.txt');
printf("Name: %s \n", $object->name());
printf("Size: %d \n", $object->contentLength());
printf("Type: %s \n", $object->contentType());
print $object->content() . PHP_EOL;



