# HPCloud-PHP

This package provides PHP OpenStack bindings for the HP Cloud.

You can use this library to:

* Authenticate your application to the HP Cloud.
* Interact with Object Storage (aka Swift).
* Interact with CDN service (Content Delivery Network).

Previously this library could be used to interact with our relational database (DBaaS and MySQL compatible). The API has changed and the bindings do not currently support this feature.

Coming soon:

* Intect with the Compute (Nova) manager.
* Interact with other HP Cloud services

## Requirements

* PHP 5.3
* An active HPCloud account with the desired services.

### Suggestions

* Enable the cURL extension for full protocol support.

We also have support for using PHP's native HTTP stream wrapper, but it
is not as reliable. We recommend cURL.

## Versioning

We have a goal to be as consistent as possible with [Semantic Versioning](http://semver.org/). For released HP Cloud services this is what you can expect. For products in beta expect the included components to be in beta. For example, [HP Cloud Relational Database for MySQL](https://www.hpcloud.com/products/RDB) (our DBaaS offering) is private beta.

## Installation

There are currently two methods of installation. We've been considering
PEAR and Phar releases, but have currently limited to only Composer and
builds because these cover our needs.

#### Method #1:

Use [Composer](http://getcomposer.org) to download and install the
latest version of HPCloud-PHP.

#### Method #2:

Download a tagged release and include it in your project.


## Features

#### Identity Services

Authenticate, authorize service usage, and retrieve account information.

#### Object Storage

Store files or other data objects in containers on your HP Cloud object
storage instance. Create, modify and delete containers. Manage ACLs.
Read, write, and delete objects. Expose objects in your object storage
to other services.

With full stream wrapper support, you can use built-in
PHP functions like `file_get_contents()`, `fopen()`, and `stat()` for
reading and writing files into object storage.

#### CDN

With CDN service enabled, objects in Object Storage can be pushed onto
the HP Cloud edge server network.

With this library, manage CDN integration for object storage containers,
and manage individual objects. The library allows you to fetch cached
objects either from object storage or from the CDN cache.

#### Autoloading

HPCloud is [PSR-0 compliant](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md),
which means that it should work with any PSR-0 autoloader. However,
it also comes with its own autoloader for apps that don't yet make use
of a standard autoloader.

#### Composer Support

HPCloud-PHP is available as part of the Packagist archive, which means
you can use Composer to automatically download, install, and manage
revisions to HPCloud-PHP from within your project.

We're big fans of [Composer](http://getcomposer.org).


## More information

[HP Cloud](http://hpcloud.com) is a cloud computing platform that
provides many services, inlcuding compute installs, object and block
storage, and a host of hosted services.

This library provides access to those services.

The best source of documentation is the official API documentation,
which is available at
http://hpcloud.github.com/HPCloud-PHP/doc/api/html/index.html

----
HPCloud-PHP is maintained by the Developer Experience team at HP Cloud Services.
