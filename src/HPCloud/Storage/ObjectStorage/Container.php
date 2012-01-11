<?php
/**
 * @file
 *
 * Contains the class for ObjectStorage Container objects.
 */

namespace HPCloud\Storage\ObjectStorage;

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
 * Typically, containers are created using ObjectStorage::addContainer().
 * They are retrieved using ObjectStorage::container() or
 * ObjectStoarge::containers().
 *
 * @code
 * <?php
 * use \HPCloud\Storage\ObjectStorage;
 * use \HPCloud\Storage\ObjectStorage\Container;
 * use \HPCloud\Storage\ObjectStorage\Object;
 *
 * // Create a new ObjectStorage instance, logging in with older Swift
 * // credentials.
 * $store = ObjectStorage::newFromSwiftAuth('user', 'key', 'http://example.com');
 *
 * // Get the container called 'foo'.
 * $container = $store->container('foo');
 *
 * // Create an object.
 * $obj = new Object('bar.txt');
 * $obj->setContent('Example content.', 'text/plain');
 *
 * // Save the new object in the container.
 * $container->save($obj);
 *
 * ?>
 * @endcode
 *
 * Once you have a Container, you manipulate objects inside of the
 * container.
 *
 * @todo Add support for container metadata.
 */
class Container implements \Countable, \IteratorAggregate {
  /**
   * The prefix for any piece of metadata passed in HTTP headers.
   */
  const METADATA_HEADER_PREFIX = 'X-Object-Meta-';


  protected $properties = array();
  protected $name = NULL;

  protected $count = 0;
  protected $bytes = 0;

  protected $token;
  protected $url;

  /**
   * Create a new Container from JSON data.
   *
   * This is used in lieue of a standard constructor when
   * fetching containers from ObjectStorage.
   *
   * @param array $jsonArray
   *   An associative array as returned by json_decode($foo, TRUE);
   * @param string $token
   *   The auth token.
   * @param string $url
   *   The base URL. The container name is automatically appended to 
   *   this at construction time.
   */
  public static function newFromJSON($jsonArray, $token, $url) {
    $container = new Container($jsonArray['name']);

    $container->url = $url . '/' . urlencode($jsonArray['name']);
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

    return $container;
  }

  /**
   * Given an OpenStack HTTP response, build a Container.
   *
   * This factory is intended for use by low-level libraries. In most
   * cases, the standard constructor is preferred for client-size
   * Container initialization.
   *
   * @param string $name
   *   The name of the container.
   * @param \HPCloud\Transport\Response $respose
   *   The HTTP response object from the Transporter layer
   * @param string $token
   *   The auth token.
   * @param string $url
   *   The base URL. The container name is automatically appended to
   *   this at construction time.
   * @return Container
   *   The Container object, initialized and ready for use.
   */
  public static function newFromResponse($name, $response, $token, $url) {
    $container = new Container($name);
    $container->bytes = $response->header('X-Container-Bytes-Used', 0);
    $container->count = $response->header('X-Container-Object-Count', 0);
    $container->url = $url . '/' . urlencode($name);
    $container->token = $token;

    return $container;
  }

  /**
   * Construct a new Container.
   */
  public function __construct($name) {
    $this->name = $name;
  }

  /**
   * Get the name of this container.
   *
   * @return string
   *   The name of the container.
   */
  public function name() {
    return $this->name;
  }

  /**
   * Get the number of bytes in this container.
   *
   * @return int
   *   The number of bytes in this container.
   */
  public function bytes() {
    return $this->bytes;
  }

  /**
   * Get the number of items in this container.
   *
   * Since Container implements Countable, the PHP builtin
   * count() can be used on a Container instance:
   *
   * @code
   * <?php
   * count($container) === $container->count();
   * ?>
   * @endcode
   *
   * @return int
   *   The number of items in this container.
   */
  public function count() {
    return $this->count;
  }

  /**
   * Save an Object into Object Storage.
   *
   * This takes an \HPCloud\Storage\ObjectStorage\Object
   * and stores it in the given container in the present
   * container on the remote object store.
   *
   * @param \HPCloud\Storage\ObjectStorage\Object $obj
   *   The object to store.
   * @return boolean
   *   TRUE if the object was saved.
   * @throws \HPCloud\Transport\LengthRequiredException
   *   if the Content-Length could not be determined and chunked
   *   encoding was not enabled. This should not occur for this class,
   *   which always automatically generates Content-Length headers.
   *   However, subclasses could generate this error.
   * @throws \HPCloud\Transport\UnprocessableEntityException
   *   if the checksome passed here does not match the checksum
   *   calculated remotely.
   * @throws \HPCloud\Exception when an unexpected (usually
   *   network-related) error condition arises.
   */
  public function save(Object $obj) {

    if (empty($this->token)) {
      throw new \HPCloud\Exception('Container does not have an auth token.');
    }
    if (empty($this->url)) {
      throw new \HPCloud\Exception('Container does not have a URL to send data.');
    }

    $url = $this->url . '/' . $obj->name();

    // See if we have any metadata.
    $headers = array();
    $md = $obj->metadata();
    if (!empty($md)) {
      $headers = $this->generateMetadataHeaders($md);
    }

    // Now build up the rest of the headers:
    $headers['ETag'] = $obj->eTag();

    // Set the content type.
    $headers['Content-Type'] = $obj->contentType();

    // If chunked, we set transfer encoding; else
    // we set the content length.
    if ($obj->isChunked()) {
      // How do we handle this? Does the underlying
      // stream wrapper pay any attention to this?
      $headers['Transfer-Encoding'] = 'chunked';
    }
    else {
      $headers['Content-Length'] = $obj->contentLength();
    }

    // Auth token.
    $headers['X-Auth-Token'] = $this->token;

    $client = \HPCloud\Transport::instance();

    $response = $client->doRequest($url, 'PUT', $headers, $obj->content());

    if ($response->status() != 201) {
      throw new \HPCloud\Exception('An unknown error occurred while saving: ' . $response->status());
    }
    return TRUE;
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
   * remoteObject(), which delays loading the content until one of its 
   * content methods (e.g. RemoteObject::content()) is called.
   *
   * This does not yet support the following features of Swift:
   *
   * - Byte range queries.
   * - If-Modified-Since/If-Unmodified-Since
   * - If-Match/If-None-Match
   *
   * @param string $name
   *   The name of the object to load.
   * @return \HPCloud\Storage\ObjectStorage\RemoteObject
   *   A remote object with the content already stored locally.
   */
  public function object($name) {

    $url = $this->url . '/' . urlencode($name);
    $headers = array();

    // Auth token.
    $headers['X-Auth-Token'] = $this->token;

    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, 'GET', $headers);

    if ($response->status() != 200) {
      throw new \HPCloud\Exception('An unknown error occurred while saving: ' . $response->status());
    }

    $remoteObject = RemoteObject::newFromHeaders($name, $response->headers(), $this->token, $url);
    $remoteObject->setContent($response->content());

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
   * This method can featch the relevant metadata, but delay fetching
   * the content until it is actually needed.
   *
   * Since RemoteObject extends Object, all of the calls that can be
   * made to an Object can also be made to a RemoteObject. Be aware,
   * though, that calling RemoteObject::content() will initiate another
   * network operation.
   *
   * @param string $name
   *   The name of the object to fetch.
   * @return \HPCloud\Storage\ObjectStorage\RemoteObject
   *   A remote object ready for use.
   */
  public function remoteObject($name) {
    $url = $this->url . '/' . urlencode($name);
    $headers = array(
      'X-Auth-Token' => $this->token,
    );


    $client = \HPCloud\Transport::instance();
    $response = $client->doRequest($url, 'HEAD', $headers);

    if ($response->status() != 200) {
      throw new \HPCloud\Exception('An unknown error occurred while saving: ' . $response->status());
    }

    $headers = $response->headers();

    return RemoteObject::newFromHeaders($name, $headers, $this->token, $url);
  }

  /**
   * Get a list of objects in this container.
   *
   * This will return a list of objects in the container. With no
   * parameters, it will attempt to return a listing of <i>all</i>
   * objects in the container. However, by setting contraints, you can
   * retrieve only a specific subset of objects.
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
   * @param int $limit
   *   An integer indicating the maximum number of items to return. This 
   *   cannot be greater than the Swift maximum (10k).
   * @param string $maker
   *   The name of the object to start with. The query will begin with
   *   the next object AFTER this one.
   */
  public function objects($limit = NULL, $marker = NULL) {
    $params = array();
    return $this->objectQuery($params, $limit, $marker);
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
   * markers". See objectsWithPath().)
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
   * @param string $prefix
   *   The leading prefix.
   * @param string $delimiter
   *   The character used to delimit names. By default, this is '/'.
   * @param int $limit
   *   An integer indicating the maximum number of items to return. This
   *   cannot be greater than the Swift maximum (10k).
   * @param string $marker
   *   The name of the object to start with. The query will begin with
   *   the next object AFTER this one.
   */
  public function objectsWithPrefix($prefix, $delimiter = '/', $limit = NULL, $marker = NULL) {
    $params = array(
      'prefix' => $prefix,
      'delimiter' => $delimiter,
    );
    return $this->objectQuery($params, $limit, $marker);
  }

  /**
   * Specify a path (subdirectory) to traverse.
   *
   * OpenStack Swift provides two basic ways to handle directory-like
   * structures. The first is using a prefix (see objectsByPrefix()).
   * The second is to create directory markers and use a path.
   *
   * A directory marker is just a file with a name that is
   * directory-like. You create it exactly as you create any other file.
   * Typically, it is 0 bytes long.
   *
   * @code
   * <?php
   * $dir = new Object('a/b/c', '');
   * $container->save($dir);
   * ?>
   * @endcode
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
   * @param string $path
   *   The path prefix.
   * @param string $delimiter
   *   The character used to delimit names. By default, this is '/'.
   * @param int $limit
   *   An integer indicating the maximum number of items to return. This
   *   cannot be greater than the Swift maximum (10k).
   * @param string $marker
   *   The name of the object to start with. The query will begin with
   *   the next object AFTER this one.
   */
  public function objectsByPath($path, $delimiter = '/', $limit = NULL, $marker = NULL) {
    $params = array(
      'path' => $path,
      'delimiter' => $delimiter,
    );
    return $this->objectQuery($params, $limit, $marker);
  }

  /**
   * Perform the HTTP query for a list of objects and de-serialize the
   * results.
   */
  protected function objectQuery($params = array(), $limit = NULL, $marker = NULL) {
    if (isset($limit)) {
      $params['limit'] = (int) $limit;
      if (!empty($marker)) {
        $params['marker'] = (string) $marker;
      }
    }

    // We always want JSON.
    $params['format'] = 'json';

    $query = http_build_query($params);
    $url = $this->url . '?' . $query;

    $client = \HPCloud\Transport::instance();
    $headers = array(
      'X-Auth-Token' => $this->token,
    );

    $response = $client->doRequest($url, 'GET', $headers);

    // The only codes that should be returned are 200 and the ones
    // already thrown by doRequest.
    if ($response->status() != 200) {
      throw new \HPCloud\Exception('An unknown exception occurred while processing the request.');
    }

    $responseContent = $response->content();
    $json = json_decode($responseContent, TRUE);

    // Turn the array into a list of RemoteObject instances.
    $list = array();
    foreach ($json as $item) {
      if (!empty($item['subdir'])) {
        $list[] = new Subdir($item['subdir'], $params['delimiter']);
      }
      elseif (empty($item['name'])) {
        throw new \HPCloud\Exception('Unexpected entity returned.');
      }
      else {
        $url = $this->url . '/' . urlencode($item['name']);
        $list[] = RemoteObject::newFromJSON($item, $this->token, $url);
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
   * @code
   * <?php
   * foreach ($container as $object) {
   *  print $object->name();
   * }
   * ?>
   * @endcode
   *
   * The above is equivalent to doing the following:
   * @code
   * <?php
   * $objects = $container->objects();
   * foreach ($objects as $object) {
   *  print $object->name();
   * }
   * ?>
   * @endcode
   *
   * Note that there is no way to pass any constraints into an iterator.
   * You cannot limit the number of items, set an marker, or add a
   * prefix.
   */
  public function getIterator() {
    return new \ArrayIterator($this->objects());
  }

  /**
   * Remove the named object from storage.
   *
   * @param string $name
   *   The name of the object to remove.
   * @return boolean
   *   TRUE if the file was deleted, FALSE if no such file is found.
   */
  public function delete($name) {
    $url = $this->url . '/' . urlencode($name);
    $headers = array(
      'X-Auth-Token' => $this->token,
    );

    $client = \HPCloud\Transport::instance();

    try {
      $response = $client->doRequest($url, 'DELETE', $headers);
    }
    catch (\HPCloud\Transport\FileNotFoundException $fnfe) {
      return FALSE;
    }

    if ($response->status() != 204) {
      throw new \HPCloud\Exception("An unknown exception occured while deleting $name.");
    }

    return TRUE;
  }

  /**
   * Transform a metadata array into headers.
   *
   * This is used when storing an object in a container.
   *
   * @param array $metadata
   *   An associative array of metadata. Metadata is not escaped in any
   *   way (there is no codified spec by which to escape), so make sure
   *   that keys are alphanumeric (dashes allowed) and values are
   *   ASCII-armored with no newlines.
   * @return array
   *   An array of headers.
   * @see http://docs.openstack.org/bexar/openstack-object-storage/developer/content/ch03s03.html#d5e635
   * @see http://docs.openstack.org/bexar/openstack-object-storage/developer/content/ch03s03.html#d5e700
   */
  protected function generateMetadataHeaders(array $metadata) {
    $headers = array();
    foreach ($metadata as $key => $val) {
      $headers[self::METADATA_HEADER_PREFIX . $key] = $val;
    }
    return $headers;
  }

}
