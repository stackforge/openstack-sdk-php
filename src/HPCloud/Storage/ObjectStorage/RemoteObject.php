<?php
/**
 * @file
 *
 * Contains the RemoteObject class.
 */

namespace HPCloud\Storage\ObjectStorage;

class RemoteObject extends Object {

  protected $contentLength = 0;
  protected $etag = '';
  protected $lastModified = 0;

  /**
   * Create a new RemoteObject from JSON data.
   *
   * @param array $data
   *   The JSON data as an array.
   * @param string $token
   *   The authentication token.
   * @param $url
   *   The URL to the object on the remote server
   */
  public static function newFromJSON($data, $token, $url) {

    $object = new RemoteObject($data['name']);
    $object->setContentType($data['content_type']);

    $this->contentLength = (int) $data['bytes'];
    $this->etag = (string) $data['hash'];
    $this->lastModified = strtotime($data['last_modified']);

  }

  public function contentLength() {
    return $this->contentLength;
  }

  public function eTag() {
    return $this->etag;
  }

  /**
   * Get the modification time, as reported by the server.
   *
   * This returns an integer timestamp indicating when the server's
   * copy of this file was last modified.
   */
  public function lastModified() {
    return $this->lastModified;
  }

  public function metadata() {
    // How do we get this?
    return array();
  }

  /**
   * Get the content of this object.
   *
   * Since this is a proxy object, calling content() will cause the 
   * object to be fetched from the remote data storage. The result will 
   * be delivered as one large string.
   *
   * Be wary of using this method with large files.
   *
   * @return string
   *   The contents of the file as a string.
   * @throws \HPCloud\Transport\FileNotFoundException
   *   when the requested content cannot be located on the remote
   *   server.
   * @throws \HPCloud\Exception
   *   when an unknown exception (usually an abnormal network condition)
   *   occurs.
   */
  public function content() {
    $client = \HPCloud\Transport::instance();
    $headers = array(
      'X-Auth-Token' => $this->token,
    );

    $response = $client->doRequest($this->url, 'GET', $headers);

    if ($response->status() != 200) {
      throw new \HPCloud\Exception('An unknown exception occurred during transmission.');
    }
    return $response->content();
  }

  public function setContent($content, $type = NULL) {
    throw new ReadOnlyObjectException(__CLASS__ . ' is read-only.');
  }
  public function setContentType($type) {
    throw new ReadOnlyObjectException(__CLASS__ . ' is read-only.');
  }
  public function setMetadata(array $array) {
    throw new ReadOnlyObjectException(__CLASS__ . ' is read-only.');
  }

}
