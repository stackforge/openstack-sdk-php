<?php
require_once __DIR__ . '/../vendor/autoload.php';

use \OpenStack\Bootstrap;

Bootstrap::useStreamWrappers();

$ini = parse_ini_file(getenv('HOME') . '/.OpenStack.ini');
$settings = [
    'account' => $ini['account'],
    'key' => $ini['secret'],
    'tenantid' => $ini['tenantId'],
    'endpoint' => $ini['url'],
];
Bootstrap::setConfiguration($settings);

// Create a new file and write it to the object store.
$newfile = fopen('swift://Example/my_file.txt', 'w');
fwrite($newfile, "Good Morning!");
fclose($newfile);

// Check for an object:
if (file_exists('swift://Example/my_file.txt')) {
    print "Found my_file.txt." . PHP_EOL;
}

// Get an entire object at once:
$file = file_get_contents('swift://Example/my_file.txt');
print 'File: ' . $file . PHP_EOL;

$cxt = stream_context_create([
    'swift' => [
        'account' => $ini['account'],
        'key' => $ini['secret'],
        'tenantid' => $ini['tenantId'],
        'endpoint' => $ini['url'],
    ],
]);

print file_get_contents('swift://Example/my_file.txt', FALSE, $cxt);
