OpenStack PHP-Client
====================

This package provides PHP OpenStack bindings.

You can use this library to:

-  Authenticate your application to OpenStack.
-  Interact with Object Storage (aka Swift).

Coming soon:

-  Intect with the Compute (Nova) manager.
-  Interact with other OpenStack services

Requirements
------------

-  PHP 5.3
-  An active OpenStack account with the desired services.

Suggestions
~~~~~~~~~~~

-  Enable the cURL extension for full protocol support.

We also have support for using PHP's native HTTP stream wrapper, but it
is not as reliable. We recommend cURL.

Versioning
----------

We have a goal to be as consistent as possible with `Semantic
Versioning <http://semver.org/>`__. For released HP Cloud services this
is what you can expect.

Installation
------------

There are currently two methods of installation. We've been considering
PEAR and Phar releases, but have currently limited to only Composer and
builds because these cover our needs.

Method #1:
~~~~~~~~~~

Use `Composer <http://getcomposer.org>`__ to download and install the
latest version of OpenStack.

Method #2:
~~~~~~~~~~

Download a tagged release and include it in your project.

Features
--------

Identity Services
~~~~~~~~~~~~~~~~~

Authenticate, authorize service usage, and retrieve account information.

Object Storage
~~~~~~~~~~~~~~

Store files or other data objects in containers on your OpenStack object
storage instance. Create, modify and delete containers. Manage ACLs.
Read, write, and delete objects. Expose objects in your object storage
to other services.

With full stream wrapper support, you can use built-in PHP functions
like ``file_get_contents()``, ``fopen()``, and ``stat()`` for reading
and writing files into object storage.

Autoloading
^^^^^^^^^^^

OpenStack SDK for PHP is `PSR-4
compliant <https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4.md>`__,
which means that it should work with any PSR-4 autoloader. However, it
also comes with its own autoloader for apps that don't yet make use of a
standard autoloader.

Composer Support
^^^^^^^^^^^^^^^^

OpenStack PHP-Client is available as part of the Packagist archive,
which means you can use Composer to automatically download, install, and
manage revisions to OpenStack from within your project.

We're big fans of `Composer <http://getcomposer.org>`__.

More information
----------------

`OpenStack <http://OpenStack.org>`__ is a cloud computing platform that
provides many services, inlcuding compute installs, object and block
storage, and a host of hosted services.

This library provides access to those services.

The best source of documentation is the official API documentation,
which is available at http://FIXME
