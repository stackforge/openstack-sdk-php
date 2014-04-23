# Running Tests for the PHP-Client bindings

This file explains how to configured your environment for running the
PHP-Client automated testing.

The OpenStack bindings offer a few stand-alone tests for testing basic
connectivity to OpenStack services, but most tests are of the
automated variety.

*IMPORTANT*: Make sure your settings.ini file is up-to-date! Options
have changed!

## Stand-alone Tests

Stand-alone tests are designed to verify that certain preconditions of
the libary are met.

### AuthTest.php

The AuthTest test is a simple commandline program that allows you to
verify that your PHP client can successfully connect to OpenStack. To
run this test, do the following:

1. Begin from the root directory of this project, where you should see
   the directories `tests/` and `src/`, among others.
2. Execute the following command on the commandline:

```
$ php tests/AuthTest.php
```

This will instruct you to use a more complete version of the command,
including:

* USERNAME: The username given to you.
* PASSWORD: The password associated with the username.
* URL: The Endpoint URL.
* TENANT ID: Your users's tenant ID.

All four pieces of information can be found by logging into the
console. From there, you can execute a command like this:

```
$ php tests/AuthTest.php myusername apassword https://region-a.geo-1.identity.hpcloudsvc.com:35357/v2.0/ 1234567

```

If successfull, it should return details about your username, token, and
the services in your service catalog.

## Unit Tests

Unit and behavioral tests are built using [PHPUnit](http://www.phpunit.de/). Before you can
test this package, you will need to [install that tool](http://www.phpunit.de/manual/3.7/en/installation.html).

Next, you need to create your own `settings.ini` file to contain your HP
Cloud credentials, along with your preferred testing parameters.

### Creating settings.ini

The easiest way to do this is to copy the example settings file, and
then make the necessary changes:

	$ cd tests/
	$ cp example.settings.ini settings.ini
	$ edit settings.ini

### Running Tests

The test suite uses PHPUnit and can generate a code coverage report if
xdebug is installed. To run the test suite make sure PHPUnit is installed
via composer by using `composer install` or `composer update`. Once PHPUnit is
installed execute the following command from the root of the project.

    $ ./vendor/bin/phpunit

This should generate output looking something like this:

	PHPUnit 4.0.13 by Sebastian Bergmann.

	Configuration read from /path/to/openstack-sdk-php/phpunit.xml.dist
	
	...............................................................  63 / 146 ( 43%)
	............................................................... 126 / 146 ( 86%)
	....................
	
	Time: 4.94 minutes, Memory: 17.50Mb
	
	OK (146 tests, 413 assertions)
	
	Generating code coverage report in Clover XML format ... done
	
	Generating code coverage report in HTML format ... done

If the tests fail, detailed information about the failure will be
displayed.

PHPUnit has a wide variety of commandline options. Other sorts of
reports and analyses can be done using those.

## Writing Tests

Tests should be written according to the PHPUnit documentation. Tests
should follow the same coding standards as all other parts of the
library.
