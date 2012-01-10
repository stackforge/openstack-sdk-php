# Running Tests for the HPCloud-PHP bindings

This file explains how to configured your environment for running the
HPCloud automated testing.

The HPCloud bindings offer a few stand-alone tests for testing basic
connectivity to the HPCloud services, but most tests are of the
automated variety.

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
* URL: The Endpoint URL.

All three pieces of information can be found by logging into [the
management console](https://manage.hpcloud.com) and going to the section
called *Storage*. There should be a link on that page that says *Get
Storage API Keys*. That page displays all three pieces of required
information.

From there, you can execute a command like this:

```
$ php test/AuthTest.php 123made-up-key  456made-up-secret https://region-a.geo-1.objects.hpcloudsvc.com/auth/v1.0/

```

If successfull, it should return something like this:

```
Success! The authentication token is AUTH_tk0a12345678987654321b922d29101478.
```

## Unit Tests

Unit and behavioral tests are built using [PHPUnit](http://www.phpunit.de/). Before you can
test this package, you will need to [install that tool](http://www.phpunit.de/manual/3.6/en/installation.html).

Next, you need to create your own `settings.ini` file to contain your HP
Cloud credentials, along with your preferred testing parameters.

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
hpcloud.swift.account = 12345678:87654321
hpcloud.swift.key = abcdef123456
hpcloud.swift.url = https://region-a.geo-1.objects.hpcloudsvc.com/auth/v1.0/
hpcloud.swift.container = "I♡HPCloud"
```

* hpcloud.swift.account: Your account ID
* hpcloud.swift.key: Your secret key
* hpcloud.swift.url: The endpoint URL

All three of these pieces of information can be ascertained by following
the instructions in the AuthTest section above.

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

