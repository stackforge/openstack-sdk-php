<?php
require_once __DIR__ . '/../src/OpenStack/Bootstrap.php';

use \OpenStack\Bootstrap;
use \OpenStack\Services\IdentityService;
use \OpenStack\Storage\ObjectStorage;
use \OpenStack\Storage\ObjectStorage\Object;

Bootstrap::useAutoloader();

// Load these from an ini file.
$ini = parse_ini_file(getenv('HOME') . '/.OpenStack.ini');
$account = $ini['account'];
$key = $ini['secret'];
$tenantId = $ini['tenantId'];
$endpoint = $ini['url'];

$idService = new IdentityService($endpoint);
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



