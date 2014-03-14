<?php
/** @mainpage About the OpenStack PHP-Client
 *
 * This is the documentation for the OpenStack PHP-Client library.
 *
 * @section about_package Overview
 *
 * <a href="http://www.openstack.org">OpenStack</a> is open source software for
 * building public and private clouds.
 *
 * The PHP-Client library provides PHP developers with a fully tested,
 * robust, and feature-rich library for working with the OpenStack services.
 *
 * @attention
 * Making use of this library will require that you have several pieces of
 * account information for your OpenStack account:
 * - account ID and secret key: For cases where you want account-wide 
 *   authentication/authorization.
 * - username/password: Typically, this is the same username/password you use
 *   to access the console.
 * - tenant ID: This associates an account or user with a bundle of services.
 *   You can find this information in your console.
 * - endpoint: You will need the URL to the OpenStack endpoint responsible for
 *   <i>authenticating users</i>. This can be found in your console.
 *
 * @section where_to_start Where To Start
 *
 * Cruising a list of methods and classes is not exactly the best way to get
 * started with a library. It's better to know where to start. Here's
 * what we suggest:
 *
 *- There are a few tutorials inside this documentation that will help you
 *   get started. One explains [Stream Wrappers](@ref streams-tutorial) and
 *   the other [the library itself](@ref oo-tutorial).
 *- Connecting and logging in is almost inevitably going to be your first
 *   task. For that, you will want to look at IdentityServices.
 *- ObjectStorage (a.k.a. swift) is the cloud storage system. There are
 *   two ways to use it:
 *   - You can explore the object oriented API, starting with ObjectStorage.
 *   - You can use the PHP stream wrappers to access your object storage. This
 *     is explained in StreamWrapper.
 *
 * @section learn_more Learn More
 *
 * This documentation is intended to provide a detailed reference to the
 * PHP-Client library. To learn more about the APIs and OpenStack visit
 * http://api.openstack.org/ and http://docs.openstack.org/.
 *
 * @section intro_example_sw Basic Example: Stream Wrappers
 *
 * The super-simple stream API:
 *
 * @code
 * <?php
 * // This is only required if you don't have a PSR-0
 * // autoloader to do the hard work for you.
 * require 'OpenStack/Autoloader.php';
 *
 * // If you aren't using a PSR-0 autoloader,
 * // you might want to use this:
 * \OpenStack\Autoloader::useAutoloader();
 *
 * // Turn on stream wrappers.
 * \OpenStack\Bootstrap::useStreamWrappers();
 *
 * // Create a stream context. You can get this
 * // information (including tenant ID) from your
 * // OpenStack console.
 * $cxt = stream_context_create(array(
 *   'username' => 'foo@example.com',
 *   'password' => 'secret',
 *   'tenantid' => '123456',
 *   'endpoint' => 'http://url.from.hpcloud.com/',
 * ));
 *
 *
 * // Get an object from the remote object storage and read it as a string
 * // right into $myObject.
 * $myObject = file_get_contents('swift://mycontainer/foo.txt', FALSE, $cxt);
 *
 * ?>
 * @endcode
 *
 * With stream wrapper support, you can transparently read and write files to the
 * ObjectStorage service without using any fancy API at all. Use the
 * normal file methods like this:
 *
 *- fopen()/fclose()
 *- fread()/fwrite()
 *- file_get_contents(), stream_get_contents()
 *- stat()/fstat()
 *- is_readable()/is_writable()
 *- And so on (http://us3.php.net/manual/en/ref.filesystem.php).
 *
 * Learn more about this at OpenStack::Storage::ObjectStorage::StreamWrapper.
 *
 * @section intro_example_ident Basic Example: Identity Service
 *
 * Stream wrappers are nice and all, but
 * some of us love fancy APIs. So here's an example using the full API
 * to log in and then dump a list of services that are available to you:
 *
 * @code
 * <?php
 * // This is only required if you don't have a PSR-0
 * // autoloader to do the hard work for you.
 * require 'OpenStack/Autoloader.php';
 *
 * // If you aren't using a PSR-0 autoloader,
 * // you might want to use this:
 * \OpenStack\Autoloader::useAutoloader();
 *
 * use \OpenStack\Services\IdentityService;
 *
 * // Create a new identity service object, and tell it where to
 * // go to authenticate. This URL can be found in your console.
 * $identity = new IdentityService('http://get.url.from.hpcloud.com');
 *
 * // You can authenticate either with username/password (IdentityService::authenticateAsUser())
 * // or as an account/secret key (IdentityService::authenticateAsAccount()). In either
 * // case you can get the info you need from the console.
 * $account = '123456789098765';
 * $secret = 'dgasgasd';
 * $tenantId = '56545654';
 *
 * // $token will be your authorization key when you connect to other
 * // services. You can also get it from $identity->token().
 * $token = $identity->authenticateAsAccount($account, $secret, $tenantId);
 *
 * // Get a listing of all of the services you currently have configured in
 * // OpenStack.
 * $catalog = $identity->serviceCatalog();
 *
 * var_dump($catalog);
 *
 * ?>
 * @endcode
 *
 *-# Our classes use PHP namespaces to organize components. If you've never used
 *   them before, don't worry. They're easy to get the hang of.
 *-# The Bootstrap class handles setting up OpenStack services. Read about it at OpenStack::Bootstrap.
 *-# The IdentityServices class handles authenticating to OpenStack, discovering services, and providing
 *   access to your account. OpenStack::Services::IdentityService explains the details, but here are
 *   a few functions you'll want to know:
 *   - OpenStack::Services::IdentityService::__construct() tells the object where to connect.
 *   - OpenStack::Services::IdentityService::authenticateAsUser() lets you log
 *     in with username and password.
 *   - OpenStack::Services::IdentityService::authenticateAsAccount() lets you log
 *     in with account number and secret key.
 *   - OpenStack::Services::IdentityService::serviceCatalog() tells you about
 *     the services you have activated on this account.
 *
 * @section intro_example_swift Basic Example: Object Storage
 *
 * Assuming you have an object storage instance available in your service
 * catalog, we could continue on with something like this:
 *
 * @code
 * <?php
 * // The explicit way:
 * // Find out where our ObjectStorage instance lives:
 * // $storageList = $identity->serviceCatalog('object-storage');
 * // $objectStorageUrl = storageList[0]['endpoints'][0]['publicURL'];
 *
 * // Create a new ObjectStorage instance:
 * // $objectStore = new \OpenStack\Storage\ObjectStorage($token, $objectStorageUrl);
 *
 * // Or let ObjectStorage figure out which instance to use:
 * $objectStore = \OpenStack\Storage\ObjectStorage::newFromIdentity($identity);
 *
 * // List containers:
 * print_r($objectStore->containers());
 *
 * // Get a container named 'stuff':
 * $container = $objectStore->container('stuff');
 *
 * // List all of the objects in that container:
 * print_r($container->objects());
 *
 * // Get an object named 'example.txt'
 * $obj = $container->object('example.txt');
 *
 * // Print that object's contents:
 * print $obj->content();
 *
 * // Actually, since it implements __tostring, we could do this:
 * print $obj;
 * ?>
 * @endcode
 *
 * This shows you a few methods for accessing objects and containers on your
 * OpenStack::Storage::ObjectStorage account. There are many functions for
 * creating and modifying containers and objects, too.
 *
 *- OpenStack::Storage::ObjectStorage is where you will start.
 *- Container services are in OpenStack::Storage::ObjectStorage::Container
 *- There are two classes for objects:
 *     - OpenStack::Storage::ObjectStorage::Object is for creating new objects.
 *     - OpenStack::Storage::ObjectStorage::RemoteObject provides better network
 *     performance when reading objects.
 *
 */
// Note that Doxygen assumes that dot (.) is the namespace separator in
// package descriptions.
/**
 * @package OpenStack
 * The OpenStack PHP-Client library.
 */
/**
 * @namespace OpenStack.Services
 * OpenStack classes providing access to various services.
 *
 * OpenStack offers a number of services, including Compute (Nova),
 * and IdentityService.
 *
 * This package is reserved for classes that provide access to
 * services.
 */
/**
 * @package OpenStack.Storage
 * OpenStack classes for remote storage.
 *
 * Services for now and the future:
 *
 *- ObjectStorage
 *- Others coming.
 *
 */
/**
 * @package OpenStack.Storage.ObjectStorage
 * Classes specific to ObjectStorage.
 *
 * The main class is OpenStack::Storage::ObjectStorage.
 */
/**
 * @package OpenStack.Transport
 * HTTP/REST/JSON classes.
 *
 * HTTP/HTTPS is the transport protocol for OpenStack's RESTful services.
 *
 * This library provides both CURL and PHP Streams-based HTTP support,
 * and this package provides a simple REST client architecture, along
 * with the minimal JSON processing necessary.
 *
 *
 */
?>
