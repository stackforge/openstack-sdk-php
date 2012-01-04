# HPCloud-PHP

This package provides PHP OpenStack bindings for the HP Cloud.

You can use this library to:

* Authenticate your application to the HP Cloud.
* Interact with Object Storage (aka Swift).

Coming soon:

* Intect with the Compute (Nova) manager.
* Interact with other HP Cloud services

## Requirements

* PHP 5.3
* An active HPCloud account with the desired services.

### Suggestions

* Enable the cURL extension for full protocol support.

## Installation

There are two methods for installing HPCloud-PHP. You may manually
install, or you may use the PEAR installer.

## Usage

### Importing the Library

The HPCloud PHP library follows the PHP 5.3 recommended practices for
including and loading. In short: Use an autoloader.

#### Autoloading

HPCloud is [PSR-0 compliant](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md),
which means that it should work with any PSR-0 autoloader. However,
it also comes with its own autoloader for apps that don't yet make use
of a standard autoloader.

##### PSR-0 Autoloading

For any PSR-0 autoloader, just ensure that the `HPCloud` directory (in
`src`) is available in your PHP include path. A PSR-0 autoloader can
take it from there.

#### Using the Built-In Autoloader

If your project does not include its own autoloader, you can use the one
that comes built-in. This is not a full autoloader. It's a
special-purpose one that works only for the HPCloud source (and this is
by design -- it's supposed to play nicely with other autoloaders).

To use it, you can do the following:

```php
<?php
require_once 'HPCloud/Bootstrap.php';

\HPCloud\Bootstrap::useAutoloader();
?>
```

This will register the autoloader as an SPL autoloader. From here,
HPCloud classes should "just work", with no further `require` statements
necessary.

You can see this in action in `test/TestCase.php`, the base class for
unit tests.

### Authenticating

As the Component Services framework is rolled out, a unified
authentication layer will become available.

Prior to that, however, each service may have its own authentication.

### Working with Object Storage

The central class for Object Storage is, appropriately enough,
`\HPCloud\Storage\ObjectStorage`.

## More information

[HP Cloud](http://hpcloud.com) is a cloud computing platform that
provides many services, inlcuding compute installs, object and block
storage, and a host of hosted services.

This library provides access to those services.

----
HPCloud-PHP is maintained by HP Cloud Services.
