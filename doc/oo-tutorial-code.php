<?php
require_once __DIR__ . '/../vendor/autoload.php';

use \OpenStack\Identity\v2\IdentityService;
use \OpenStack\ObjectStore\v1\ObjectStorage;
use \OpenStack\ObjectStore\v1\ObjectStorage\Object;

// Load these from an ini file.
$ini = parse_ini_file(getenv('HOME') . '/.OpenStack.ini');
$username = $ini['username'];
$password = $ini['password'];
$tenantId = $ini['tenantId'];
$endpoint = $ini['url'];

$idService = new IdentityService($endpoint);
$token = $idService->authenticateAsUser($username, $password, $tenantId);

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
