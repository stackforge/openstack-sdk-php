<?php
/** @mainpage About HPCloud-PHP
 *
 * This is the documentation for the HPCloud PHP library.
 *
 * @section about_package Overview
 *
 * <a href="http://hpcloud.com">HPCloud</a> provides public cloud
 * infrastructure that is business-grade, open source-based, and developer
 * focused. Built on <a href="http://openstack.org">OpenStack</a>, it provides
 * many cloud-based services that developers can take advantage of when
 * building robust and reliable websites.
 *
 * The HPCloud-PHP library provides PHP developers with a fully tested,
 * robust, and feature-rich library for working with the HPCloud services.
 *
 * @attention
 * Making use of this library will require that you have several pieces of
 * account information for your HPCloud account (or OpenStack account, if you're
 * using this library with non-HP OpenStack implentations):
 * - account ID and secret key: For cases where you want account-wide 
 *   authentication/authorization.
 * - username/password: Typically, this is the same username/password you use
 *   to access the HPCloud console.
 * - tenant ID: This associates an account or user with a bundle of services.
 *   You can find this information in your console.
 * - endpoint: You will need the URL to the HPCloud endpoint responsible for
 *   <i>authenticating users</i>. This can be found in your console.
 *
 * (If you are not sure what the "HPCloud Console" is, head over to
 * http://docs.hpcloud.com. There you will find some articles and videos
 * explaining the HPCloud structure.)
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
 *- ObjectStorage (a.k.a. swift) is our cloud storage system. There are
 *   two ways to use it:
 *   - You can explore the object oriented API, starting with ObjectStorage.
 *   - You can use the PHP stream wrappers to access your object storage. This
 *     is explained in StreamWrapper.
 *
 * @section learn_more Learn More
 *
 * This documentation is intended to provide a detailed reference to the
 * HPCloud-PHP library. But this ain't all we've got. Tutorials, videos,
 * screencasts, a knowledge base, and active community forums are
 * just a click away.
 *
 * Head over to http://docs.hpcloud.com to find these and other resources.
 *
 * Or maybe you'd just like to see a couple of examples.
 *
 * @section intro_example_sw Basic Example: Stream Wrappers
 *
 * The super-simple stream API:
 *
 * @code
 * <?php
 * // This is only required if you don't have a PSR-0
 * // autoloader to do the hard work for you.
 * require 'HPCloud/Bootstrap.php';
 *
 * // If you aren't using a PSR-0 autoloader,
 * // you might want to use this:
 * \HPCloud\Bootstrap::useAutoloader();
 *
 * // Turn on stream wrappers.
 * \HPCloud\Bootstrap::useStreamWrappers();
 *
 * // Create a stream context. You can get this
 * // information (including tenant ID) from your
 * // HPCloud console.
 * $cxt = stream_context_create(array(
 *   'username' => 'matthew.butcher@hp.com',
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
 * HPCloud ObjectStorage service without using any fancy API at all. Use the
 * normal file methods like this:
 *
 *- fopen()/fclose()
 *- fread()/fwrite()
 *- file_get_contents(), stream_get_contents()
 *- stat()/fstat()
 *- is_readable()/is_writable()
 *- And so on (http://us3.php.net/manual/en/ref.filesystem.php).
 *
 * Learn more about this at HPCloud::Storage::ObjectStorage::StreamWrapper.
 *
 * @section intro_example_ident Basic Example: Identity Services
 *
 * Stream wrappers are nice and all, but
 * some of us love fancy APIs. So here's an example using the full API
 * to log in and then dump a list of services that are available to you:
 *
 * @code
 * <?php
 * // This is only required if you don't have a PSR-0
 * // autoloader to do the hard work for you.
 * require 'HPCloud/Bootstrap.php';
 *
 * // If you aren't using a PSR-0 autoloader,
 * // you might want to use this:
 * \HPCloud\Bootstrap::useAutoloader();
 *
 * use \HPCloud\Services\IdentityServices;
 *
 * // Create a new identity service object, and tell it where to
 * // go to authenticate. This URL can be found in your HPCloud
 * // console.
 * $identity = new IdentityServices('http://get.url.from.hpcloud.com');
 *
 * // You can authenticate either with username/password (IdentityServices::authenticateAsUser())
 * // or as an account/secret key (IdentityServices::authenticateAsAccount()). In either
 * // case you can get the info you need from the console.
 * $account = '123456789098765';
 * $secret = 'dgasgasd';
 * $tenantId = '56545654';
 *
 * // $token will be your authorization key when you connect to other
 * // services. You can also get it from $identity->token().
 * $token = $identity->authenticateAsAccount($account, $secret, $tenantId);
 *
 * // Get a listing of all of the services you currently have configured on
 * // HPCloud.
 * $catalog = $identity->serviceCatalog();
 *
 * var_dump($catalog);
 *
 * ?>
 * @endcode
 *
 *-# Our classes use PHP namespaces to organize components. If you've never used
 *   them before, don't worry. They're easy to get the hang of.
 *-# The Bootstrap class handles setting up HPCloud services. Read about it at HPCloud::Bootstrap.
 *-# The IdentityServices class handles authenticating to HP, discovering services, and providing
 *   access to your account. HPCloud::Services::IdentityServices explains the details, but here are
 *   a few functions you'll want to know:
 *   - HPCloud::Services::IdentityServices::__construct() tells the object where to connect.
 *   - HPCloud::Services::IdentityServices::authenticateAsUser() lets you log
 *     in with username and password.
 *   - HPCloud::Services::IdentityServices::authenticateAsAccount() lets you log
 *     in with account number and secret key.
 *   - HPCloud::Services::IdentityServices::serviceCatalog() tells you about
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
 * // $objectStore = new \HPCloud\Storage\ObjectStorage($token, $objectStorageUrl);
 *
 * // Or let ObjectStorage figure out which instance to use:
 * $objectStore = \HPCloud\Storage\ObjectStorage::newFromIdentity($identity);
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
 * HPCloud::Storage::ObjectStorage account. There are many functions for
 * creating and modifying containers and objects, too.
 *
 *- HPCloud::Storage::ObjectStorage is where you will start.
 *- Container services are in HPCloud::Storage::ObjectStorage::Container
 *- There are two classes for objects:
 *     - HPCloud::Storage::ObjectStorage::Object is for creating new objects.
 *     - HPCloud::Storage::ObjectStorage::RemoteObject provides better network
 *     performance when reading objects.
 *
 */
// Note that Doxygen assumes that dot (.) is the namespace separator in
// package descriptions.
/**
 * @package HPCloud
 * The HPCloud PHP library.
 */
/**
 * @namespace HPCloud.Services
 * HPCloud classes providing access to various services.
 *
 * HPCloud offers a number of services, including Compute (Nova),
 * IdentityServices, and CDN.
 *
 * This package is reserved for classes that provide access to
 * services.
 */
/**
 * @package HPCloud.Storage
 * HPCloud classes for remote storage.
 *
 * Services for now and the future:
 *
 *- ObjectStorage
 *- CDN caching of storage
 *- Others coming.
 *
 */
/**
 * @package HPCloud.Storage.ObjectStorage
 * Classes specific to ObjectStorage.
 *
 * The main class is HPCloud::Storage::ObjectStorage.
 */
/*
 * @package HPCloud.Storage.CDN
 * Classes specific to CDN.
 */
/**
 * @package HPCloud.Transport
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
