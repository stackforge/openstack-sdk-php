<?php
/**
 * @file
 *
 * Contains the class for ObjectStorage Container objects.
 */

namespace HPCloud\Storage\ObjectStorage;

class Container implements \Countable {

  protected $properties = array();
  protected $name = NULL;

  protected $count = 0;
  protected $bytes = 0;

  /**
   * Create a new Container from JSON data.
   *
   * This is used in lieue of a standard constructor when
   * fetching containers from ObjectStorage.
   *
   * @param array $jsonArray
   *   An associative array as returned by json_decode($foo, TRUE);
   */
  public static function newFromJSON($jsonArray) {
    $container = new Container($jsonArray['name']);

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
   *   The HTTP response object from the Transporter layer.
   * @return Container
   *   The Container object, initialized and ready for use.
   */
  public static function newFromResponse($name, $response) {
    $container = new Container($name);
    $container->bytes = $response->header('X-Container-Bytes-Used', 0);
    $container->count = $response->header('X-Container-Object-Count', 0);

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

}
