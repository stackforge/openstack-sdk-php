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
 */
class Container implements \Countable {
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

    if ($obj->isChunked()) {
      // How do we handle this? Does the underlying
      // stream wrapper pay any attention to this?
      $headers['Transfer-Encoding'] = 'chunked';
    }
    else {
      $headers['Content-Length'] = $obj->contentLength();
    }

    $headers['X-Auth-Token'] = $this->token;

    $client = \HPCloud\Transport::instance();

    $response = $client->doRequest($url, 'PUT', $headers, $obj->content());

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
