# Running Tests for the HPCloud-PHP bindings

This file explains how to configured your environment for running the
HPCloud automated testing.

The HPCloud bindings offer a few stand-alone tests for testing basic
connectivity to the HPCloud services, but most tests are of the
automated variety.

*IMPORTANT*: Make sure your settings.ini file is up-to-date! Options
have changed!

## Stand-alone Tests

Stand-alone tests are designed to verify that certain preconditions of
the libary are met.

### AuthTest.php

The AuthTest test is a simple commandline program that allows you to
verify that your PHP client can successfully connect to the HP Cloud. To
run this test, do the following:

1. Begin from the root directory of this project, where you should see
   the directories `test/` and `src/`, among others.
2. Execute the following command on the commandline:

```
$ php test/AuthTest.php
```

This will instruct you to use a more complete version of the command,
including:

* ID: The ID given to you by HP Cloud.
* KEY: Your account's key.
* TENANT ID: Your account's tenant ID.
* URL: The Endpoint URL.

All four pieces of information can be found by logging into [the
console](https://console.hpcloud.com) and going to the section called
*Storage*. There should be a link on that page that says *Get
Storage API Keys*. That page displays all four pieces of required
information.

From there, you can execute a command like this:

```
$ php test/AuthTest.php 123made-up-key  456made-up-secret https://region-a.geo-1.objects.hpcloudsvc.com/auth/v1.0/ 1234567

```

If successfull, it should return details about your username, token, and
the services in your service catalog.

## Unit Tests

Unit and behavioral tests are built using [PHPUnit](http://www.phpunit.de/). Before you can
test this package, you will need to [install that tool](http://www.phpunit.de/manual/3.6/en/installation.html).

Next, you need to create your own `settings.ini` file to contain your HP
Cloud credentials, along with your preferred testing parameters.

### Creating settings.ini

The easiest way to do this is to copy the example settings file, and
then make the necessary changes:

```
$ cd test/
$ cp example.settings.ini settings.ini
$ edit settings.ini
```

Your settings should look something like this:

```
; Settings to work with swift:
; hpcloud.swift.account = 12345678:87654321
; hpcloud.swift.key = abcdef123456
; hpcloud.swift.url = https://region-a.geo-1.objects.hpcloudsvc.com/auth/v1.0/

hpcloud.swift.container = "I♡HPCloud"

hpcloud.identity.url = https://region-a.geo-1.idenity.hpcloudsvc.com
hpcloud.identity.tenantId = 12345
hpcloud.identity.username = butcher@hp.com
hpcloud.identity.password = secret
hpcloud.identity.account =  54321
hpcloud.identity.key = 9878787
```

You will need to add all of the `hpcloud.identity` settings, and all of
this information can be found on your console.

The hpcloud.swift.account, key, and url params are no longer required
for the basic tests, but are required if you are also running the tests
in the group `deprecated`.

### Running Tests with Make

The `Makefile` included with the HPCloud library can run the tests.
Beginning from the root directory of the project, simply type the
following:

```
$ make test
```

By default, this will run ALL of the unit tests. However, you can run
a subset of the tests using the TESTS argument:

```
$ make test TESTS=test/Tests/CDNTest.php
```

The above only runs the CDN unit tests. To specify a list of tests, 
make sure you put quotes around the entire string:

```
$ make test TESTS="test/Tests/CDNTest.php test/Tests/ACLTest.php"
```

If you know which *group* of tests you want to run, you can run just
a select group of tests using the `test-group` target:

```
$ make test-group GROUP=deprecated
```

The above will run all of the unit tests in the `@group deprecated` group.
(Note: the library does not use group tests very often, so this is
unlikely to be a commonly required feature.)

### Running Tests Using `phpunit`

If for some reason the Makefile doesn't suite your needs, you have the
option of running the tests directly using `phpunit`.

Beginning from the root directory of the project (you should see `src/`
and `test/` in that directory), run this command to execute all of the
tests:

```
$ phpunit test/Tests
```

This should generate output looking something like this:

```
phpunit test/Tests
PHPUnit 3.6.3 by Sebastian Bergmann.

..................................................

Time: 01:24, Memory: 6.50Mb

OK (50 tests, 125 assertions)
```

If the tests fail, detailed information about the failure will be
displayed.

PHPUnit has a wide variety of commandline options. Other sorts of
reports and analyses can be done using those.

## Writing Tests

Tests should be written according to the PHPUnit documentation. Tests
should follow the same coding standards as all other parts of the
library, with one caveat: The namespaces for tests are still
non-standard.

The different namespacing is an historical relic resulting from two things:

* Originally, we used Atoum, which ascribes additional semantic (testing) value to
  namespaces.
* PHPUnit's namespacing support is relatively new.

Eventually, the namespaces for the unit tests will all be standardized,
too.

