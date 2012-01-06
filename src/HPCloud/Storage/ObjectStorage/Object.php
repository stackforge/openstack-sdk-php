<?php
/**
 * @file
 * Contains the class Object for ObjectStorage.
 */

namespace HPCloud\Storage\ObjectStorage;

/**
 * An object for ObjectStorage.
 *
 * The HPCloud ObjectStorage system provides a method for storing
 * complete chunks of data (objects) in the cloud. This class describes
 * such a chunk of data.
 *
 * An object has the following basic components:
 *
 * - Name: A filename (which may be pathlike, subject to OpenStack's
 *   pathing rules).
 * - Content: The content of the object.
 * - Content type: The MIME type of the object. Examples:
 *   * text/plain; charset=UTF-8
 *   * image/png
 *   * application/x-my-custom-mime
 * - Metadata: File attributes that are stored along with the file on
 *   object store.
 *
 * Objects are stored and retrieved <i>by name</i>. So it is assumed
 * that, per container, no more than one file with a given name exists.
 *
 * You may create Object instance and then store them in Containers.
 * Likewise, a Container instance can retrieve Object instances from the
 * remote object store.
 */
class Object {

  const DEFAULT_CONTENT_TYPE = 'application/octet-stream';

  /**
   * The name of the object.
   *
   * This can be path-like, subject to OpenStack's definition
   * of "path-like".
   */
  protected $name;

  /**
   * The content.
   *
   * Subclasses needn't use this to store an object's content,
   * as they may prefer filesystem backing.
   */
  protected $content;
  /**
   * The content type.
   *
   * The default type is 'application/octet-stream', which marks this as
   * a generic byte stream.
   */
  protected $contentType = self::DEFAULT_CONTENT_TYPE;
  /**
   * Associative array of stored metadata.
   */
  protected $metadata = array();


  /**
   * Construct a new object for storage.
   *
   * @param string $name
   *   A name (may be pathlike) for the object.
   * @param string $content
   *   Optional content to store in this object. This is the same
   *   as calling setContent().
   * @param string $type
   *   Optional content type for this content. This is the same as
   *   calling setContentType().
   */
  public function __construct($name, $content = NULL, $type = NULL) {
    $this->name = $name;

    if (!is_null($content)) {
      $this->content = $content;
    }
    if (!empty($type)) {
      $this->contentType = $type;
    }
  }

  /**
   * Set the metadata.
   *
   * OpenStack allows you to specify metadata for a file. Metadata items
   * must follow these conventions:
   *
   * - names must contain only letters, numbers, and short dashes. Since
   *   OpenStack normalizes the name to begin with uppercase, it is
   *   suggested that you follow this convetion: Foo, not foo. Or you
   *   can do your own normalizing (such as converting all to lowercase.
   * - values must be encoded if they contain newlines or binary data.
   *   While the exact encoding is up to you, Base-64 encoding is probably
   *   your best bet.
   *
   * This library does only minimal processing of metadata, and does no
   * error checking, escaping, etc. This is up to the implementor.
   *
   * IMPORTANT: Current versions of OpenStack Swift see the names FOO,
   * Foo, foo, and fOo as the same. This is not to say that it is case
   * insensitive; only that it normalizes strings according to its own
   * rules.
   *
   * @param array $array
   *   An associative array of metadata names to values.
   */
  public function setMetadata(array $array) {
    $this->metadata = $array;
    return $this;
  }

  /**
   * Get any associated metadata.
   *
   * This returns an associative array of all metadata for this object.
   *
   * @return array
   *   An associative array of metadata. This may be empty.
   */
  public function metadata() {
    return $this->metadata;
  }

  /**
   * Override (change) the name of an object.
   *
   * Note that this changes only the <i>local copy</i> of an object. It
   * does not rename the remote copy. In fact, changing the local name
   * and then saving it will result in a new object being created in the
   * object store.
   *
   * To copy an object, see
   * \HPCloud\Storage\ObjectStorage\Container::copyObject().
   *
   * @param string $name
   *   A file or object name.
   */
  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  /**
   * Get the name.
   *
   * Returns the name of an object. If the name has been overwritten
   * using setName(), this will return the latest (overwritten) name.
   *
   * @return string
   *   The name of the object.
   */
  public function name() {
    return $this->name;
  }

  /**
   * Set the content type (MIME type) for the object.
   *
   * Object storage is, to a certain degree, content-type aware. For
   * that reason, a content type is mandatory.
   *
   * The default MIME type used is `application/octet-stream`, which is
   * the generic content type for a byte stream. Where possible, you
   * should set a more accurate content type.
   *
   * All HTTP type options are allowed. So, for example, you can add a
   * charset to a text type:
   *
   * @code
   * <?php
   * $o = new Object('my.html');
   * $o->setContentType('text/html; charset=iso-8859-13');
   * ?>
   * @endcode
   *
   * Content type is not parsed or verified locally (though it is
   * remotely). It can be dangerous, too, to allow users to specify a
   * content type.
   *
   * @param string $type
   *   A valid content type.
   */
  public function setContentType($type) {
    $this->contentType = $type;
    return $this;
  }

  /**
   * Get the content type.
   *
   * This returns the currently set content type.
   *
   * @return string
   *   The content type, including any additional options.
   */
  public function contentType() {
    return $this->contentType;
  }

  /**
   * Set the content for this object.
   *
   * Place the content into the object. Typically, this is string 
   * content that will be stored remotely.
   *
   * PHP's string is backed by a robust system that can accomodate 
   * moderately sized files. However, it is best to keep strings short
   * (<2MB, for example -- test for your own system's sweet spot).
   * Larger data may be better handled with file system entries or
   * database storage.
   *
   * Note that the OpenStack will not allow files larger than 5G, and
   * PHP will likely croak well before that marker. So use discretion.
   *
   * @param string $content
   *   The content of the object.
   * @param string $type
   *   The content type (MIME type). This can be set here for
   *   convenience, or you can call setContentType() directly.
   */
  public function setContent($content, $type = NULL) {
    $this->content = $content;
    if (!empty($type)) {
      $this->contentType = $type;
    }
    return $this;
  }

  /**
   * Retrieve the content.
   *
   * Retrieve the ENTIRE content of an object.
   *
   * Note that this may be binary data (depending on what the original
   * content is). PHP strings are generally binary safe, but use this
   * with caution if you do not know what kind of data is stored in an
   * object.
   *
   * OpenStack does not do anything to validate that the content type is
   * accurate. While contentType() is intended to provide useful
   * information, poorly managed data can be written with the wrong
   * content type.
   *
   * When extending this class, you should make sure that this function
   * returns the entire contents of an object.
   *
   * @return string
   *   The content of the file.
   */
  public function content() {
    return $this->content;
  }

  /**
   * Calculate the content length.
   *
   * This returns the number of <i>bytes</i> in a piece of content (not
   * the number of characters). Among other things, it is used to let
   * the remote object store know how big of an object to expect when
   * transmitting data.
   *
   * When extending this class, you should make sure to calculate the
   * content length appropriately.
   *
   * return int
   *   The length of the content, in bytes.
   */
  public function contentLength() {
    // strlen() is binary safe (or at least it seems to be).
    return strlen($this->content);
  }

  /**
   * Generate an ETag for the ObjectStorage server.
   *
   * OpenStack uses ETag to pass validation data. This generates an ETag
   * using an MD5 hash of the content.
   *
   * When extending this class, generate an ETag by creating an MD5 of
   * the entire object's content (but not the metadata or name).
   *
   * @return string
   *   An MD5 value as a string of 32 hex digits (0-9a-f).
   */
  public function eTag() {
    return md5($this->content);
  }

  /**
   * This object should be transmitted in chunks.
   *
   * Indicates whether or not this object should be transmitted as
   * chunked data (in HTTP).
   *
   * This should be used when (a) the file size is large, or (b) the
   * exact size of the file is unknown.
   *
   * If this returns TRUE, it does not <i>guarantee</i> that the data
   * will be transmitted in chunks. But it recommends that the
   * underlying transport layer use chunked encoding.
   *
   * The contentLength() method is not called for chunked transfers. So
   * if this returns TRUE, contentLength() is ignored.
   *
   * @return boolean
   *   TRUE to recommend chunked transfer, FALSE otherwise.
   */
  public function isChunked() {
    // Currently, this value is hard-coded. The default Object
    // implementation does not get chunked.
    return FALSE;
  }
}
