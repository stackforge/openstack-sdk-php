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

  protected $contentVerification = TRUE;

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

    $object->contentLength = (int) $data['bytes'];
    $object->etag = (string) $data['hash'];
    $object->lastModified = strtotime($data['last_modified']);

    $object->token = $token;
    $object->url = $url;

    return $object;
  }

  /**
   * Create a new RemoteObject from HTTP headers.
   *
   * This is used to create objects from GET and HEAD requests, which
   * return all of the metadata inside of the headers.
   *
   * @param string $name
   *   The name of the object.
   * @param array $headers
   *   An associative array of HTTP headers in the exact format 
   *   documented by OpenStack's API docs.
   * @param string $token
   *   The current auth token (used for issuing subsequent requests).
   * @param string $url
   *   The URL to the object in the object storage. Used for issuing
   *   subsequent requests.
   */
  public static function newFromHeaders($name, $headers, $token, $url) {
    $object = new RemoteObject($name);

    //throw new \Exception(print_r($headers, TRUE));

    $object->setContentType($headers['Content-Type']);
    $object->contentLength = (int) $headers['Content-Length'];
    $object->etag = (string) $headers['Etag']; // ETag is now Etag.
    $object->lastModified = strtotime($headers['Last-Modified']);

    // Set the metadata, too.
    $object->setMetadata(self::extractHeaderAttributes($headers));

    $object->token = $token;
    $object->url = $url;

    return $object;
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
   * @param array $headers
   *   An associative array of HTTP headers.
   * @return array
   *   An associative array of name/value attribute pairs.
   */
  public static function extractHeaderAttributes($headers) {
    $attributes = array();
    $offset = strlen(Container::METADATA_HEADER_PREFIX);
    foreach ($headers as $header => $value) {

      $index = strpos($header, Container::METADATA_HEADER_PREFIX);
      if ($index === 0) {
        $key = substr($header, $offset);
        $attributes[$key] = $value;
      }
    }
    return $attributes;
  }

  public function contentLength() {
    if (!empty($this->content)) {
      return parent::contentLength();
    }
    return $this->contentLength;
  }

  public function eTag() {

    if (!empty($this->content)) {
      return parent::eTag();
    }

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
    return $this->metadata;
  }

  /**
   * Get the content of this object.
   *
   * Since this is a proxy object, calling content() will cause the
   * object to be fetched from the remote data storage. The result will
   * be delivered as one large string.
   *
   * The file size, content type, etag, and modification date of the
   * object are all updated during this command, too. This accounts for
   * the possibility that the content was modified externally between
   * the time this object was constructed and the time this method was
   * executed.
   *
   * The RemoteObject does NOT cache content. Each call to content()
   * will initiate a new network request.
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

    // XXX: This allows local overwrites. Is this a good idea?
    if (!empty($this->content)) {
      return $this->content;
    }

    // Get the object, content included.
    $response = $this->fetchObject(TRUE);

    $content = $response->content();

    $check = md5($content);
    if ($check != $this->etag()) {
      throw new ContentVerificationException("Checksum $check does not match Etag " . $this->etag());
    }

    return $content;
  }

  /**
   * Enable or disable content verification (checksum/md5).
   *
   * The default behavior of a RemoteObject is to verify that the MD5
   * provided by the server matches the locally generated MD5 of the
   * file contents.
   *
   * If content verification is enabled, then whenever the content is
   * fetched from the remote server, its checksum is calculated and
   * tested against the ETag value. This provides a layer of assurance
   * that the payload of the HTTP request was not altered during
   * transmission.
   *
   * This featured can be turned off, which is sometimes necessary on
   * systems that do not correctly produce MD5s. Turning this off might
   * also provide a small performance improvement on large files, but at
   * the expense of security.
   *
   * @param boolean $enabled
   *   If this is TRUE, content verification is performed. The content
   *   is hashed and checked against a server-supplied MD5 hashcode. If
   *   this is FALSE, no checking is done.
   */
  public function setContentVerification($enabled) {
    $this->contentVerification = $enabled;
  }

  /**
   * Indicate whether this object verifies content (checksum).
   *
   * When content verification is on, RemoteObject attemts to perform a
   * checksum on the object, calculating the MD5 hash of the content
   * returned by the remote server, and comparing that to the server's
   * supplied ETag hash.
   *
   * @return boolean
   *   TRUE if this is verifying, FALSE otherwise.
   */
  public function isVerifyingContent() {
    return $this->contentVerification;
  }

  /**
   * Rebuild the local object from the remote.
   *
   * WARNING: This will destroy any unsaved local changes.
   *
   * @param boolean $fetchContent
   *   If this is TRUE, the content will be downloaded as well.
   */
  public function refresh($fetchContent = FALSE) {

    // Kill old content.
    unset($this->content);

    $response = $this->fetchObject($fetchContent);


    if ($fetchContent) {
      $this->setContent($response->content());
    }
  }

  /**
   * Helper function for fetching an object.
   */
  protected function fetchObject($fetchContent = FALSE) {
    $method = $fetchContent ? 'GET' : 'HEAD';

    $client = \HPCloud\Transport::instance();
    $headers = array(
      'X-Auth-Token' => $this->token,
    );

    $response = $client->doRequest($this->url, $method, $headers);

    if ($response->status() != 200) {
      throw new \HPCloud\Exception('An unknown exception occurred during transmission.');
    }

    // Reset the content length, last modified, and etag:
    $this->setContentType($response->header('Content-Type', $this->contentType()));
    $this->lastModified = strtotime($response->header('Last-Modified', 0));
    $this->etag = $response->header('Etag', $this->etag);

    return $response;
  }
}
