<?php

/*
 * (c) Copyright 2012-2014 Hewlett-Packard Development Company, L.P.
 * (c) Copyright 2014      Rackspace US, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace OpenStack\ObjectStore\v1\Resource;

use OpenStack\Common\Exception;
use OpenStack\Common\Transport\ClientInterface;
use OpenStack\Common\Transport\Exception\ResourceNotFoundException;
use OpenStack\Common\Transport\Guzzle\GuzzleAdapter;

/**
 * A container in an ObjectStorage.
 *
 * An Object Storage instance is divided into containers, where each
 * container can hold an arbitrary number of objects. This class
 * describes a container, providing access to its properties and to the
 * objects stored inside of it.
 *
 * Containers are iterable, which means you can iterate over a container
 * and access each file inside of it.
 *
 * Typically, containers are created using ObjectStorage::createContainer().
 * They are retrieved using ObjectStorage::container() or
 * ObjectStorage::containers().
 *
 *     <?php
 *     use \OpenStack\ObjectStore\v1\ObjectStorage;
 *     use \OpenStack\ObjectStore\v1\Resource\Container;
 *     use \OpenStack\ObjectStore\v1\Resource\Object;
 *
 *     // Create a new ObjectStorage instance
 *     // For more examples on authenticating and creating an ObjectStorage
 *     // instance see functions below
 *     // @see \OpenStack\ObjectStore\v1\ObjectStorage::newFromIdentity()
 *     // @see \OpenStack\ObjectStore\v1\ObjectStorage::newFromServiceCatalog()
 *     $ostore = \OpenStack\ObjectStore\v1\ObjectStorage::newFromIdentity($yourIdentity, $yourRegion, $yourTransportClient);
 *
 *     // Get the container called 'foo'.
 *     $container = $store->container('foo');
 *
 *     // Create an object.
 *     $obj = new Object('bar.txt');
 *     $obj->setContent('Example content.', 'text/plain');
 *
 *     // Save the new object in the container.
 *     $container->save($obj);
 *
 *     ?>
 *
 * Once you have a Container, you manipulate objects inside of the
 * container.
 *
 * @todo Add support for container metadata.
 */
class Container implements \Countable, \IteratorAggregate
{
    /**
     * The prefix for any piece of metadata passed in HTTP headers.
     */
    const METADATA_HEADER_PREFIX = 'X-Object-Meta-';
    const CONTAINER_METADATA_HEADER_PREFIX = 'X-Container-Meta-';

    //protected $properties = array();
    protected $name = null;

    // These were both changed from 0 to null to allow lazy loading.
    protected $count = null;
    protected $bytes = null;

    protected $token;
    protected $url;
    protected $baseUrl;
    protected $acl;
    protected $metadata;

    /**
     * The HTTP Client
     */
    protected $client;

    /**
     * Transform a metadata array into headers.
     *
     * This is used when storing an object in a container.
     *
     * @param array  $metadata An associative array of metadata. Metadata is not
     *                         escaped in any way (there is no codified spec by which to escape), so
     *                         make sure that keys are alphanumeric (dashes allowed) and values are
     *                         ASCII-armored with no newlines.
     * @param string $prefix   A prefix for the metadata headers.
     *
     * @return array An array of headers.
     *
     * @see http://docs.openstack.org/bexar/openstack-object-storage/developer/content/ch03s03.html#d5e635
     * @see http://docs.openstack.org/bexar/openstack-object-storage/developer/content/ch03s03.html#d5e700
     */
    public static function generateMetadataHeaders(array $metadata, $prefix = null)
    {
        if (empty($prefix)) {
            $prefix = Container::METADATA_HEADER_PREFIX;
        }
        $headers = [];
        foreach ($metadata as $key => $val) {
            $headers[$prefix . $key] = $val;
        }

        return $headers;
    }
    /**
     * Create an object URL.
     *
     * Given a base URL and an object name, create an object URL.
     *
     * This is useful because object names can contain certain characters
     * (namely slashes (`/`)) that are normally URLencoded when they appear
     * inside of path sequences.
     *
     * Swift does not distinguish between `%2F` and a slash character, so
     * this is not strictly necessary.
     *
     * @param string $base  The base URL. This is not altered; it is just prepended
     *                      to the returned string.
     * @param string $oname The name of the object.
     *
     * @return string The URL to the object. Characters that need escaping will be
     *                escaped, while slash characters are not. Thus, the URL will
     *                look pathy.
     */
    public static function objectUrl($base, $oname)
    {
        if (strpos($oname, '/') === false) {
            return $base . '/' . rawurlencode($oname);
        }

        $oParts = explode('/', $oname);
        $buffer = [];
        foreach ($oParts as $part) {
            $buffer[] = rawurlencode($part);
        }
        $newname = implode('/', $buffer);

        return $base . '/' . $newname;
    }

    /**
     * Extract object attributes from HTTP headers.
     *
     * When OpenStack sends object attributes, it sometimes embeds them in
     * HTTP headers with a prefix. This function parses the headers and
     * returns the attributes as name/value pairs.
     *
     * Note that no decoding (other than the minimum amount necessary) is
     * done to the attribute names or values. The Open Stack Swift
     * documentation does not prescribe encoding standards for name or
     * value data, so it is left up to implementors to choose their own
     * strategy.
     *
     * @param array  $headers An associative array of HTTP headers.
     * @param string $prefix  The prefix on metadata headers.
     *
     * @return array An associative array of name/value attribute pairs.
     */
    public static function extractHeaderAttributes($headers, $prefix = null)
    {
        if (empty($prefix)) {
            $prefix = Container::METADATA_HEADER_PREFIX;
        }
        $attributes = [];
        $offset = strlen($prefix);
        foreach ($headers as $header => $value) {

            $index = strpos($header, $prefix);
            if ($index === 0) {
                $key = substr($header, $offset);
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Create a new Container from JSON data.
     *
     * This is used in lieue of a standard constructor when
     * fetching containers from ObjectStorage.
     *
     * @param array  $jsonArray An associative array as returned by
     *                          json_decode($foo, true);
     * @param string $token     The auth token.
     * @param string $url       The base URL. The container name is automatically
     *                          appended to this at construction time.
     * @param \OpenStack\Common\Transport\ClientInterface $client A HTTP transport client.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\Container A new container object.
     */
    public static function newFromJSON($jsonArray, $token, $url, ClientInterface $client = null)
    {
        $container = new Container($jsonArray['name'], null, null, $client);

        $container->baseUrl = $url;

        $container->url = $url . '/' . rawurlencode($jsonArray['name']);
        $container->token = $token;

        // Access to count and bytes is basically controlled. This is is to
        // prevent a local copy of the object from getting out of sync with
        // the remote copy.
        if (!empty($jsonArray['count'])) {
            $container->count = $jsonArray['count'];
        }

        if (!empty($jsonArray['bytes'])) {
            $container->bytes = $jsonArray['bytes'];
        }

        //syslog(LOG_WARNING, print_r($jsonArray, true));
        return $container;
    }

    /**
     * Given an OpenStack HTTP response, build a Container.
     *
     * This factory is intended for use by low-level libraries. In most
     * cases, the standard constructor is preferred for client-size
     * Container initialization.
     *
     * @param string $name     The name of the container.
     * @param object $response \OpenStack\Common\Transport\Response The HTTP response object from the Transporter layer
     * @param string $token    The auth token.
     * @param string $url      The base URL. The container name is automatically
     *                         appended to this at construction time.
     * @param \OpenStack\Common\Transport\ClientInterface $client A HTTP transport client.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\Container The Container object, initialized and ready for use.
     */
    public static function newFromResponse($name, $response, $token, $url, ClientInterface $client = null)
    {
        $container = new Container($name, null, null, $client);
        $container->bytes = $response->getHeader('X-Container-Bytes-Used', 0);
        $container->count = $response->getHeader('X-Container-Object-Count', 0);
        $container->baseUrl = $url;
        $container->url = $url . '/' . rawurlencode($name);
        $container->token = $token;

        $headers = self::reformatHeaders($response->getHeaders());

        $container->acl = ACL::newFromHeaders($headers);

        $prefix = Container::CONTAINER_METADATA_HEADER_PREFIX;
        $metadata = Container::extractHeaderAttributes($headers, $prefix);
        $container->setMetadata($metadata);

        return $container;
    }

    /**
     * Construct a new Container.
     *
     * Typically a container should be created by ObjectStorage::createContainer().
     * Get existing containers with ObjectStorage::container() or
     * ObjectStorage::containers(). Using the constructor directly has some
     * side effects of which you should be aware.
     *
     * Simply creating a container does not save the container remotely.
     *
     * Also, this does no checking of the underlying container. That is, simply
     * constructing a Container in no way guarantees that such a container exists
     * on the origin object store.
     *
     * The constructor involves a selective lazy loading. If a new container is created,
     * and one of its accessors is called before the accessed values are initialized, then
     * this will make a network round-trip to get the container from the remote server.
     *
     * Containers loaded from ObjectStorage::container() or Container::newFromRemote()
     * will have all of the necessary values set, and thus will not require an extra network
     * transaction to fetch properties.
     *
     * The practical result of this:
     *
     * - If you are creating a new container, it is best to do so with
     *   ObjectStorage::createContainer().
     * - If you are manipulating an existing container, it is best to load the
     *   container with ObjectStorage::container().
     * - If you are simply using the container to fetch resources from the
     *   container, you may wish to use `new Container($name, $url, $token)`
     *   and then load objects from that container. Note, however, that
     *   manipulating the container directly will likely involve an extra HTTP
     *   transaction to load the container data.
     * - When in doubt, use the ObjectStorage methods. That is always the safer
     *   option.
     *
     * @param string $name  The name.
     * @param string $url   The full URL to the container.
     * @param string $token The auth token.
     * @param \OpenStack\Common\Transport\ClientInterface $client A HTTP transport client.
     */
    public function __construct($name , $url = null, $token = null, ClientInterface $client = null)
    {
        $this->name = $name;
        $this->url = $url;
        $this->token = $token;

        // Guzzle is the default client to use.
        if (is_null($client)) {
            $this->client = GuzzleAdapter::create();
        } else {
            $this->client = $client;
        }
    }

    /**
     * Get the name of this container.
     *
     * @return string The name of the container.
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Get the number of bytes in this container.
     *
     * @return int The number of bytes in this container.
     */
    public function bytes()
    {
        if (is_null($this->bytes)) {
            $this->loadExtraData();
        }

        return $this->bytes;
    }

    /**
     * Get the container metadata.
     *
     * Metadata (also called tags) are name/value pairs that can be
     * attached to a container.
     *
     * Names can be no longer than 128 characters, and values can be no
     * more than 256. UTF-8 or ASCII characters are allowed, though ASCII
     * seems to be preferred.
     *
     * If the container was loaded from a container listing, the metadata
     * will be fetched in a new HTTP request. This is because container
     * listings do not supply the metadata, while loading a container
     * directly does.
     *
     * @return array An array of metadata name/value pairs.
     */
    public function metadata()
    {
        // If created from JSON, metadata does not get fetched.
        if (!isset($this->metadata)) {
            $this->loadExtraData();
        }

        return $this->metadata;
    }

    /**
     * Set the tags on the container.
     *
     * Container metadata (sometimes called "tags") provides a way of
     * storing arbitrary name/value pairs on a container.
     *
     * Since saving a container is a function of the ObjectStorage
     * itself, if you change the metadta, you will need to call
     * ObjectStorage::updateContainer() to save the new container metadata
     * on the remote object storage.
     *
     * (Similarly, when it comes to objects, an object's metdata is saved
     * by the container.)
     *
     * Names can be no longer than 128 characters, and values can be no
     * more than 256. UTF-8 or ASCII characters are allowed, though ASCII
     * seems to be preferred.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\Container $this so the method can
     *                                                      be used in chaining.
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get the number of items in this container.
     *
     * Since Container implements Countable, the PHP builtin count() can be used
     * on a Container instance:
     *
     *     <?php
     *     count($container) === $container->count();
     *     ?>
     *
     * @return int The number of items in this container.
     */
    public function count()
    {
        if (is_null($this->count)) {
            $this->loadExtraData();
        }

        return $this->count;
    }

    /**
     * Save an Object into Object Storage.
     *
     * This takes an \OpenStack\ObjectStore\v1\Resource\Object
     * and stores it in the given container in the present
     * container on the remote object store.
     *
     * @param object   $obj  \OpenStack\ObjectStore\v1\Resource\Object The object to
     *                       store.
     * @param resource $file An optional file argument that, if set, will be
     *                       treated as the contents of the object.
     *
     * @return boolean true if the object was saved.
     *
     * @throws \OpenStack\Common\Transport\Exception\LengthRequiredException      if the Content-Length could not be
     *                                                                            determined and chunked encoding was
     *                                                                            not enabled. This should not occur for
     *                                                                            this class, which always automatically
     *                                                                            generates Content-Length headers.
     *                                                                            However, subclasses could generate
     *                                                                            this error.
     * @throws \OpenStack\Common\Transport\Exception\UnprocessableEntityException if the checksum passed here does not
     *                                                                            match the checksum calculated remotely.
     * @throws \OpenStack\Common\Exception                                        when an unexpected (usually
     *                                                                            network-related) error condition arises.
     */
    public function save(Object $obj, $file = null)
    {
        if (empty($this->token)) {
            throw new Exception('Container does not have an auth token.');
        }
        if (empty($this->url)) {
            throw new Exception('Container does not have a URL to send data.');
        }

        //$url = $this->url . '/' . rawurlencode($obj->name());
        $url = self::objectUrl($this->url, $obj->name());

        // See if we have any metadata.
        $headers = [];
        $md = $obj->metadata();
        if (!empty($md)) {
            $headers = self::generateMetadataHeaders($md, Container::METADATA_HEADER_PREFIX);
        }

        // Set the content type.
        $headers['Content-Type'] = $obj->contentType();


        // Add content encoding, if necessary.
        $encoding = $obj->encoding();
        if (!empty($encoding)) {
            $headers['Content-Encoding'] = rawurlencode($encoding);
        }

        // Add content disposition, if necessary.
        $disposition = $obj->disposition();
        if (!empty($disposition)) {
            $headers['Content-Disposition'] = $disposition;
        }

        // Auth token.
        $headers['X-Auth-Token'] = $this->token;

        // Add any custom headers:
        $moreHeaders = $obj->additionalHeaders();
        if (!empty($moreHeaders)) {
            $headers += $moreHeaders;
        }

        if (empty($file)) {
            // Now build up the rest of the headers:
            $headers['Etag'] = $obj->eTag();

            // If chunked, we set transfer encoding; else
            // we set the content length.
            if ($obj->isChunked()) {
                // How do we handle this? Does the underlying
                // stream wrapper pay any attention to this?
                $headers['Transfer-Encoding'] = 'chunked';
            } else {
                $headers['Content-Length'] = $obj->contentLength();
            }
            $response = $this->client->put($url, $obj->content(), ['headers' => $headers]);
        } else {
            // Rewind the file.
            rewind($file);

            // XXX: What do we do about Content-Length header?
            //$headers['Transfer-Encoding'] = 'chunked';
            $stat = fstat($file);
            $headers['Content-Length'] = $stat['size'];

            // Generate an eTag:
            $hash = hash_init('md5');
            hash_update_stream($hash, $file);
            $etag = hash_final($hash);
            $headers['Etag'] = $etag;

            // Not sure if this is necessary:
            rewind($file);

            $response = $this->client->put($url, $file, ['headers' => $headers]);
        }

        if ($response->getStatusCode() != 201) {
            throw new Exception('An unknown error occurred while saving: ' . $response->status());
        }

        return true;
    }

    /**
     * Update an object's metadata.
     *
     * This updates the metadata on an object without modifying anything
     * else. This is a convenient way to set additional metadata without
     * having to re-upload a potentially large object.
     *
     * Swift's behavior during this operation is sometimes unpredictable,
     * particularly in cases where custom headers have been set.
     * Use with caution.
     *
     * @param object $obj \OpenStack\ObjectStore\v1\Resource\Object The object to update.
     *
     * @return boolean true if the metadata was updated.
     *
     * @throws \OpenStack\Common\Transport\Exception\ResourceNotFoundException if the object does not already
     *                                                           exist on the object storage.
     */
    public function updateMetadata(Object $obj)
    {
        $url = self::objectUrl($this->url, $obj->name());
        $headers = ['X-Auth-Token' => $this->token];

        // See if we have any metadata. We post this even if there
        // is no metadata.
        $metadata = $obj->metadata();
        if (!empty($metadata)) {
            $headers += self::generateMetadataHeaders($metadata, Container::METADATA_HEADER_PREFIX);
        }

        // In spite of the documentation's claim to the contrary,
        // content type IS reset during this operation.
        $headers['Content-Type'] = $obj->contentType();

        // The POST verb is for updating headers.

        $response = $this->client->post($url, $obj->content(), ['headers' => $headers]);

        if ($response->getStatusCode() != 202) {
            throw new Exception(sprintf(
                "An unknown error occurred while saving: %d", $response->status()
            ));
        }

        return true;
    }

    /**
     * Copy an object to another place in object storage.
     *
     * An object can be copied within a container. Essentially, this will
     * give you duplicates of the file, each with a new name.
     *
     * An object can be copied to another container if the name of the
     * other container is specified, and if that container already exists.
     *
     * Note that there is no MOVE operation. You must copy and then DELETE
     * in order to achieve that.
     *
     * @param object $obj       \OpenStack\ObjectStore\v1\Resource\Object The object to
     *                          copy. This object MUST already be saved on the remote server. The body of
     *                          the object is not sent. Instead, the copy operation is performed on the
     *                          remote server. You can, and probably should, use a RemoteObject here.
     * @param string $newName   The new name of this object. If you are copying a
     *                          cross containers, the name can be the same. If you are copying within
     *                          the same container, though, you will need to supply a new name.
     * @param string $container The name of the alternate container. If this is
     *                          set, the object will be saved into this container. If this is not sent,
     *                          the copy will be performed inside of the original container.
     */
    public function copy(Object $obj, $newName, $container = null)
    {
        $sourceUrl = self::objectUrl($this->url, $obj->name());

        if (empty($newName)) {
            throw new Exception("An object name is required to copy the object.");
        }

        // Figure out what container we store in.
        if (empty($container)) {
            $container = $this->name;
        }
        $container = rawurlencode($container);
        $destUrl = self::objectUrl('/' . $container, $newName);

        $headers = [
            'X-Auth-Token' => $this->token,
            'Destination'  => $destUrl,
            'Content-Type' => $obj->contentType(),
        ];

        $response = $this->client->send(
            $this->client->createRequest('COPY', $sourceUrl, null, ['headers' => $headers])
        );

        if ($response->getStatusCode() != 201) {
            throw new Exception("An unknown condition occurred during copy. " . $response->getStatusCode());
        }

        return true;
    }

    /**
     * Get the object with the given name.
     *
     * This fetches a single object with the given name. It downloads the
     * entire object at once. This is useful if the object is small (under
     * a few megabytes) and the content of the object will be used. For
     * example, this is the right operation for accessing a text file
     * whose contents will be processed.
     *
     * For larger files or files whose content may never be accessed, use
     * proxyObject(), which delays loading the content until one of its
     * content methods (e.g. RemoteObject::content()) is called.
     *
     * This does not yet support the following features of Swift:
     *
     * - Byte range queries.
     * - If-Modified-Since/If-Unmodified-Since
     * - If-Match/If-None-Match
     *
     * @param string $name The name of the object to load.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject A remote object with the content already stored locally.
     */
    public function object($name)
    {
        $url = self::objectUrl($this->url, $name);
        $headers = ['X-Auth-Token' => $this->token];

        $response = $this->client->get($url, ['headers' => $headers]);

        if ($response->getStatusCode() != 200) {
            throw new Exception('An unknown error occurred while saving: ' . $response->status());
        }

        $remoteObject = RemoteObject::newFromHeaders($name, self::reformatHeaders($response->getHeaders()), $this->token, $url, $this->client);
        $remoteObject->setContent($response->getBody());

        return $remoteObject;
    }

    /**
     * Fetch an object, but delay fetching its contents.
     *
     * This retrieves all of the information about an object except for
     * its contents. Size, hash, metadata, and modification date
     * information are all retrieved and wrapped.
     *
     * The data comes back as a RemoteObject, which can be used to
     * transparently fetch the object's content, too.
     *
     * Why Use This?
     *
     * The regular object() call will fetch an entire object, including
     * its content. This may not be desireable for cases where the object
     * is large.
     *
     * This method can fetch the relevant metadata, but delay fetching
     * the content until it is actually needed.
     *
     * Since RemoteObject extends Object, all of the calls that can be
     * made to an Object can also be made to a RemoteObject. Be aware,
     * though, that calling RemoteObject::content() will initiate another
     * network operation.
     *
     * @param string $name The name of the object to fetch.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject A remote object ready for use.
     */
    public function proxyObject($name)
    {
        $url = self::objectUrl($this->url, $name);
        $headers = ['X-Auth-Token' => $this->token];

        $response = $this->client->head($url, ['headers' => $headers]);

        if ($response->getStatusCode() != 200) {
            throw new Exception('An unknown error occurred while saving: ' . $response->status());
        }

        $headers = self::reformatHeaders($response->getHeaders());

        return RemoteObject::newFromHeaders($name, $headers, $this->token, $url, $this->client);
    }

    /**
     * Get a list of objects in this container.
     *
     * This will return a list of objects in the container. With no parameters, it
     * will attempt to return a listing of all objects in the container. However,
     * by setting contraints, you can retrieve only a specific subset of objects.
     *
     * Note that OpenStacks Swift will return no more than 10,000 objects
     * per request. When dealing with large datasets, you are encouraged
     * to use paging.
     *
     * Paging
     *
     * Paging is done with a combination of a limit and a marker. The
     * limit is an integer indicating the maximum number of items to
     * return. The marker is the string name of an object. Typically, this
     * is the last object in the previously returned set. The next batch
     * will begin with the next item after the marker (assuming the marker
     * is found.)
     *
     * @param int    $limit  An integer indicating the maximum number of items to
     *                       return. This cannot be greater than the Swift maximum (10k).
     * @param string $marker The name of the object to start with. The query will
     *                       begin with the next object AFTER this one.
     *
     * @return array List of RemoteObject or Subdir instances.
     */
    public function objects($limit = null, $marker = null)
    {
        return $this->objectQuery([], $limit, $marker);
    }

    /**
     * Retrieve a list of Objects with the given prefix.
     *
     * Object Storage containers support directory-like organization. To
     * get a list of items inside of a particular "subdirectory", provide
     * the directory name as a "prefix". This will return only objects
     * that begin with that prefix.
     *
     * (Directory-like behavior is also supported by using "directory
     * markers". See objectsByPath().)
     *
     * Prefixes
     *
     * Prefixes are basically substring patterns that are matched against
     * files on the remote object storage.
     *
     * When a prefix is used, object storage will begin to return not just
     * Object instsances, but also Subdir instances. A Subdir is simply a
     * container for a "path name".
     *
     * Delimiters
     *
     * Object Storage (OpenStack Swift) does not have a native concept of
     * files and directories when it comes to paths. Instead, it merely
     * represents them and simulates their behavior under specific
     * circumstances.
     *
     * The default behavior (when prefixes are used) is to treat the '/'
     * character as a delimiter. Thus, when it encounters a name like
     * this: `foo/bar/baz.txt` and the prefix is `foo/`, it will
     * parse return a Subdir called `foo/bar`.
     *
     * Similarly, if you store a file called `foo:bar:baz.txt` and then
     * set the delimiter to `:` and the prefix to `foo:`, it will return
     * the Subdir `foo:bar`. However, merely setting the delimiter back to
     * `/` will not allow you to query `foo/bar` and get the contents of
     * `foo:bar`.
     *
     * Setting $delimiter will tell the Object Storage server which
     * character to parse the filenames on. This means that if you use
     * delimiters other than '/', you need to be very consistent with your
     * usage or else you may get surprising results.
     *
     * @param string $prefix    The leading prefix.
     * @param string $delimiter The character used to delimit names. By default,
     *                          this is '/'.
     * @param int    $limit     An integer indicating the maximum number of items to
     *                          return. This cannot be greater than the Swift maximum (10k).
     * @param string $marker    The name of the object to start with. The query will
     *                          begin with the next object AFTER this one.
     *
     * @return array List of RemoteObject or Subdir instances.
     */
    public function objectsWithPrefix($prefix, $delimiter = '/', $limit = null, $marker = null)
    {
        $params = [
            'prefix'    => $prefix,
            'delimiter' => $delimiter
        ];

        return $this->objectQuery($params, $limit, $marker);
    }

    /**
     * Specify a path (subdirectory) to traverse.
     *
     * OpenStack Swift provides two basic ways to handle directory-like
     * structures. The first is using a prefix (see objectsWithPrefix()).
     * The second is to create directory markers and use a path.
     *
     * A directory marker is just a file with a name that is
     * directory-like. You create it exactly as you create any other file.
     * Typically, it is 0 bytes long.
     *
     *     <?php
     *     $dir = new Object('a/b/c', '');
     *     $container->save($dir);
     *     ?>
     *
     * Using objectsByPath() with directory markers will return a list of
     * Object instances, some of which are regular files, and some of
     * which are just empty directory marker files. When creating
     * directory markers, you may wish to set metadata or content-type
     * information indicating that they are directory markers.
     *
     * At one point, the OpenStack documentation suggested that the path
     * method was legacy. More recent versions of the documentation no
     * longer indicate this.
     *
     * @param string $path      The path prefix.
     * @param string $delimiter The character used to delimit names. By default,
     *                          this is '/'.
     * @param int    $limit     An integer indicating the maximum number of items to
     *                          return. This cannot be greater than the Swift maximum (10k).
     * @param string $marker    The name of the object to start with. The query will
     *                          begin with the next object AFTER this one.
     */
    public function objectsByPath($path, $delimiter = '/', $limit = null, $marker = null)
    {
        $params = [
            'path'      => $path,
            'delimiter' => $delimiter,
        ];

        return $this->objectQuery($params, $limit, $marker);
    }

    /**
     * Get the URL to this container.
     *
     * Any container that has been created will have a valid URL. If the
     * Container was set to be public (See
     * ObjectStorage::createContainer()) will be accessible by this URL.
     *
     * @return string The URL.
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * Get the ACL.
     *
     * Currently, if the ACL wasn't added during object construction,
     * calling acl() will trigger a request to the remote server to fetch
     * the ACL. Since only some Swift calls return ACL data, this is an
     * unavoidable artifact.
     *
     * Calling this on a Container that has not been stored on the remote
     * ObjectStorage will produce an error. However, this should not be an
     * issue, since containers should always come from one of the
     * ObjectStorage methods.
     *
     * @todo Determine how to get the ACL from JSON data.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\ACL An ACL, or null if the ACL could not be retrieved.
     */
    public function acl()
    {
        if (!isset($this->acl)) {
            $this->loadExtraData();
        }

        return $this->acl;
    }

    /**
     * Get missing fields.
     *
     * Not all containers come fully instantiated. This method is sometimes
     * called to "fill in" missing fields.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\Container
     */
    protected function loadExtraData()
    {
        // If URL and token are empty, we are dealing with a local item that
        // has not been saved, and was not created with Container::createContainer().
        // We treat this as an error condition.
        if (empty($this->url) || empty($this->token)) {
            throw new Exception('Remote data cannot be fetched. A Token and endpoint URL are required.');
        }

        // Do a GET on $url to fetch headers.
        $headers  = ['X-Auth-Token' => $this->token];
        $response = $this->client->get($this->url, ['headers' => $headers]);

        $headers = self::reformatHeaders($response->getHeaders());
        // Get ACL.
        $this->acl = ACL::newFromHeaders($headers);

        // Update size and count.
        $this->bytes = $response->getHeader('X-Container-Bytes-Used', 0);
        $this->count = $response->getHeader('X-Container-Object-Count', 0);

        // Get metadata.
        $prefix = Container::CONTAINER_METADATA_HEADER_PREFIX;
        $this->setMetadata(Container::extractHeaderAttributes($headers, $prefix));

        return $this;
    }

    /**
     * Perform the HTTP query for a list of objects and de-serialize the
     * results.
     */
    protected function objectQuery($params = [], $limit = null, $marker = null)
    {
        if (isset($limit)) {
            $params['limit'] = (int) $limit;
            if (!empty($marker)) {
                $params['marker'] = (string) $marker;
            }
        }

        // We always want JSON.
        $params['format'] = 'json';

        $query = http_build_query($params);
        $query = str_replace('%2F', '/', $query);
        $url = $this->url . '?' . $query;

        $headers = ['X-Auth-Token' => $this->token];

        $response = $this->client->get($url, ['headers' => $headers]);

        // The only codes that should be returned are 200 and the ones
        // already thrown by GET.
        if ($response->getStatusCode() != 200) {
            throw new Exception('An unknown exception occurred while processing the request.');
        }

        $json = $response->json();

        // Turn the array into a list of RemoteObject instances.
        $list = [];
        foreach ($json as $item) {
            if (!empty($item['subdir'])) {
                $list[] = new Subdir($item['subdir'], $params['delimiter']);
            } elseif (empty($item['name'])) {
                throw new Exception('Unexpected entity returned.');
            } else {
                //$url = $this->url . '/' . rawurlencode($item['name']);
                $url = self::objectUrl($this->url, $item['name']);
                $list[] = RemoteObject::newFromJSON($item, $this->token, $url, $this->client);
            }
        }

        return $list;
    }

    /**
     * Return the iterator of contents.
     *
     * A Container is Iterable. This means that you can use a container in
     * a `foreach` loop directly:
     *
     *     <?php
     *     foreach ($container as $object) {
     *      print $object->name();
     *     }
     *     ?>
     *
     * The above is equivalent to doing the following:
     *
     *     <?php
     *     $objects = $container->objects();
     *     foreach ($objects as $object) {
     *      print $object->name();
     *     }
     *     ?>
     *
     * Note that there is no way to pass any constraints into an iterator.
     * You cannot limit the number of items, set an marker, or add a
     * prefix.
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->objects());
    }

    /**
     * Remove the named object from storage.
     *
     * @param string $name The name of the object to remove.
     *
     * @return boolean true if the file was deleted, false if no such file is
     *                 found.
     */
    public function delete($name)
    {
        $url = self::objectUrl($this->url, $name);
        $headers = [
            'X-Auth-Token' => $this->token,
        ];

        try {
            $response = $this->client->delete($url, ['headers' => $headers]);
        } catch (ResourceNotFoundException $e) {
            return false;
        }

        if ($response->getStatusCode() != 204) {
            throw new Exception(sprintf(
                "An unknown exception occured while deleting %s", $name
            ));
        }

        return true;
    }

    /**
     * Reformat the headers array to remove a nested array.
     *
     * For example, headers coming in could be in the format:
     *
     *     $headers = [
     *         'Content-Type' => [
     *             [0] => 'Foo',
     *         ],
     *     ];
     *
     * This method would reformat the array into:
     *
     *     $headers = [
     *         'Content-Type' => 'Foo',
     *     ];
     *
     * Note, for cases where multiple values for a header are needed this method
     * should not be used.
     *
     * @param array $headers A headers array from the response.
     *
     * @return array A new shallower array.
     */
    public static function reformatHeaders(array $headers)
    {
        $newHeaders = [];

        foreach ($headers as $name => $header) {
            $newHeaders[$name] = $header[0];
        }

        return $newHeaders;
    }
}
