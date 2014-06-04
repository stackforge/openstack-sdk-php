Tutorial: Using Stream Wrappers   {#streams-tutorial}
===============================

This is an introduction to the OpenStack PHP-Client library. While the library is
large and feature-rich, this tutorial focuses on the Stream Wrapper
feature. (There is also a [tutorial about the object-oriented
library](@ref 00-tutorial).)

## TL;DR

With a few lines of setup code, you can fetch objects from OpenStack's
object storage using built-in PHP functions like this:

    <?php
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
    ?>

In fact, the vast majority of file and stream functions work with
OpenStack's `swift://` URLs.

The rest of this tutorial explains how they work.

## The Setup

The example above does not show the code necessary for initializing the
OpenStack PHP-Client stream wrapper. In this section, we will look at the necessary
setup code.

### Loading Classes

The OpenStack PHP-Client library is structured following PSR-4 recommendations.
Practically speaking, what this means is that applications that use an
PSR-4 autoloader may be able to automatically load the OpenStack PHP-Client.

However, we'll assume that that is not the case. We'll assume that the
library needs to be initialized manually.

What we will do is first load the PHP-Client Bootstrap.php file, and then
use the autoloader in that file to load the rest of the library:

    <?php
    require 'vendor/autoload.php';

    use \OpenStack\Bootstrap;

    Bootstrap::useStreamWrappers();

The first thing the example above does is require Composer's autoloader
file, which contains code necessary to autoload anything else we will need.

Next, we call Bootstrap::useStreamWrappers(), which tells OpenStack to register
its stream wrapper classes.

In a nutshell, PHP allows libraries to map a particular URL pattern to a
stream wrapper. PHP-Client registers the `swift://` URL prefix. So any
request to a URL beginning with `swift://` will be proxied through the
OpenStack PHP-Client library.

## Setting Up Authentication

When working with remote OpenStack Object Storage, you must authenticate
to the remote system. Authentication requires the following four pieces
of information:

- account: Your account ID
- key: Your account's secret key
- tenantid: The tenant ID for the services you wish to use
- endpoint: The endpoint URL for OpenStack's Identity Service. It usually
  looks something like this: `https://region-a.geo-1.identity.hpcloudsvc.com:35357`

All four of these pieces of information can be found in the **API Keys**
section of your console account.

(Note: You can use your username and password instead of account and
key, but you still must supply the tenant ID. Instead of supplying
`account` and `key`, use `username` and `password`.)

We are going to look at two ways to set authentication information. The
first is global. That means we supply it once, and all stream and file
functions automatically use that information. The second is to pass
authentication information into the stream context.

### Global Configuration

Supplying global account information has two distinct advantages:

-# It reduces the complexity of your code
-# It allows context-less functions like `file_exists` and `stat` to
  work.

But it has a disadvantage: *Only one account can be used at a time.* Since
that account's information is shared across all stream wrappers, they
all share the same account, tenant Id, and service catalog.

If you are working on an application that needs to connect to more than
one account in the same request, you may find this setup imperfect for
your needs.

That said, here's how we set up a global configuration:

    $settings = array(
      'username' => YOUR_USERNAME,
      'password' => YOUR_PASSWORD,
      'tenantid' => YOUR_TENANT_ID,
      'endpoint' => IDENTITY_SERVICES_URL,
    );
    Bootstrap::setConfiguration($settings);

Basically, what we do above is declare an associative array of
configuration parameters and then tell OpenStack::Bootstrap to set these
as the default configuration.

Once the above is done, all of those PHP stream and file functions will
just work. All you need to do is pass them `swift://` URLs, and they
will do the rest.

## The Format of Swift URLs

Early in the tutorial we saw some swift URLs like this:
`swift://Example/my_file.txt` . What is this URL referencing?

The URL above has three important parts, in the form
`swift://CONTAINER/OBJECT_NAME`.

- *swift://*: This is the schema. This part of the URL tells PHP to pass
  the request to the OpenStack stream wrapper. (Swift, by the way, is the
  [OpenStack name for object storage](http://openstack.org/projects/storage/).
- *Example*: This is the *container name*. In Object Storage parlance, a
  container is a place to store documents. One account can have lots of
  containers, and each container can have lots of objects.
- *my_file.txt*: This is the object name. An object is basically the
  same as a file.

Swift does not support directories, but it does allow slashes in object
names. So `swift://Example/this/is/my/file.png' checks the container
*Example* for the object named `this/is/my/file.png`.

(For power users, there are some fancy operations you can do to treat
Swift filename parts as if they were directories. Check out
`\OpenStack\ObjectStore\v1\Resource\Container`.)

## Using Stream Contexts for Authentication

Sometimes it is better to pass authentication information directly to
the stream or file function, instead of relying upon a global
configuration. PHP provides for this with **stream contexts**.

Stream contexts have one major downside: Not all PHP functions accept
stream contexts. Here are some notable examples:

- file_exists()
- is_readable()
- stat()

(Basically, anything that calls the underlying `stat(3)`.)

The advantage, though, is that each call can have its own authentication
data. This is good for supporting multiple accounts, and can also be
used to optimize long-term performance (e.g. by saving authentication
tokens in a database and re-using them).

Here's how a stream context is used:

    <?php
    require __DIR__ . '/../vendor/autoload.php';

    use \OpenStack\Bootstrap;

    Bootstrap::useStreamWrappers();

    $cxt = stream_context_create(array(
      'swift' => array(
        'username' => YOUR_USERNAME,
        'password' => YOUR_PASSWORD,
        'tenantid' => YOUR_TENANT_ID,
        'endpoint' => IDENTITY_SERVICES_URL,    
      ),
    ));

    print file_get_contents('swift://Example/my_file.txt', FALSE, $cxt);
    ?>

The main difference is the creation of `$cxt` using PHP's
`stream_context_create()`. To fully understand this, you may want to
take a look at the [PHP documentation](http://us3.php.net/manual/en/book.stream.php)
for streams.

## Stream Wrapper As A File System
As it was noted earlier in this tutorial, swift does not support directories.
Instead the names of a file can be path like with a separator. For example,
`swiftfs://Example/path/to/my_file.txt` has a name of `path/to/my_file.txt`.

To enable applications to use swift in a more directory like manner there is a
second stream wrapper with a prefix `swiftfs://`. swiftfs stands for swift file
system. It works in a similar manner to to the standard stream wrappers with a
few key differences:

- mkdir will return TRUE is no objects start with the directory you are trying
    to crate. Otherwise it will return FALSE.
- rmdir will return FALSE if any objects start with the directory prefix you are
    trying to remove. rmdir does not allow you to remove directories with files
    in them.
- Running stat on a directory that is a prefix for some objects (e.g., 
    `swiftfs://Example/path/to/`) will see this is a prefix for a file and treat
    it as if it were a directory.

To use this stream wrapper instead of the standard swift one simple replace the
usage of `swift://` with `swiftfs://`.

## Summary

This tutorial is focused on using stream wrappers to interact with your
OpenStack Object Storage service. We focused on configuring the
environment for transparently using PHP functions like `fopen()` and
`file_get_contents()` to work with objects in OpenStack's object storage.

This is just one way of interoperating with the OpenStack PHP-Client library. For
more detail-oriented work, you may find the Object Oriented facilities
better suited. You can read [the OO tutorial](@ref oo-tutorial) to learn
more about that.

Addidtionally, you may wish to learn more about the internals of the
stream wrapper, the main class,
`\OpenStack\ObjectStore\v1\Resource\StreamWrapper`, is well-documented.
