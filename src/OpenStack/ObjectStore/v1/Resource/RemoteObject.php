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

use OpenStack\Common\Transport\ClientInterface;
use OpenStack\Common\Transport\Guzzle\GuzzleAdapter;
use OpenStack\ObjectStore\v1\Exception;

/**
 * A representation of an object stored in remote Object Storage.
 *
 * A remote object is one whose canonical copy is stored in a remote
 * object storage. It represents a local (and possibly partial) copy of
 * an object. (Contrast this with \OpenStack\ObjectStore\v1\Resource\Object)
 *
 * Depending on how the object was constructed, it may or may not have a
 * local copy of the entire contents of the file. It may only have the
 * object's "metadata" (information such as name, type, modification
 * date, and length of the object). Or it may have all of that in
 * addition to the entire content of the file.
 *
 * Remote objects can be modified locally. Simply modifying an object
 * will not result in those modifications being stored on the remote
 * server. The object must be saved (see
 * \OpenStack\ObjectStore\v1\Resource\Container::save()). When an
 * object is modified so that its local contents differ from the remote
 * stored copy, it is marked dirty (see isDirty()).
 */
class RemoteObject extends Object
{
    protected $contentLength = 0;
    protected $etag = '';
    protected $lastModified = 0;

    protected $contentVerification = true;
    protected $caching = false;

    /**
     * All headers received from a remote are stored in this array.
     * Implementing subclasses can access this array for complete access
     * to the HTTP headers.
     *
     * This will be empty if the object was constructed from JSON, and may
     * serve as a good indicator that the object does not have all
     * attributes set.
     */
    protected $allHeaders = [];

    /**
     * The HTTP Client
     */
    protected $client;

    /**
     * Create a new RemoteObject from JSON data.
     *
     * @param array  $data  The JSON data as an array.
     * @param string $token The authentication token.
     * @param $url The URL to the object on the remote server
     * @param \OpenStack\Common\Transport\ClientInterface $client A HTTP transport client.
     */
    public static function newFromJSON($data, $token, $url, ClientInterface $client = null)
    {
        $object = new RemoteObject($data['name']);
        $object->setContentType($data['content_type']);

        $object->contentLength = (int) $data['bytes'];
        $object->etag = (string) $data['hash'];
        $object->lastModified = strtotime($data['last_modified']);

        $object->token = $token;
        $object->url = $url;

        // FIXME: What do we do about HTTP header data that doesn't come
        // back in JSON?

        if (is_null($client)) {
            $client = GuzzleAdapter::create();
        }
        $object->setClient($client);

        return $object;
    }

    /**
     * Create a new RemoteObject from HTTP headers.
     *
     * This is used to create objects from GET and HEAD requests, which
     * return all of the metadata inside of the headers.
     *
     * @param string $name    The name of the object.
     * @param array  $headers An associative array of HTTP headers in the exact
     *                        format documented by OpenStack's API docs.
     * @param string $token   The current auth token (used for issuing subsequent
     *                        requests).
     * @param string $url     The URL to the object in the object storage. Used for
     *                        issuing subsequent requests.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject A new RemoteObject.
     */
    public static function newFromHeaders($name, $headers, $token, $url, ClientInterface $client = null)
    {
        $object = new RemoteObject($name);

        //$object->allHeaders = $headers;
        $object->setHeaders($headers);

        //throw new \Exception(print_r($headers, true));

        // Fix inconsistant header.
        if (isset($headers['ETag'])) {
            $headers['Etag'] = $headers['ETag'];
        }

        $object->setContentType($headers['Content-Type']);
        $object->contentLength = empty($headers['Content-Length']) ? 0 : (int) $headers['Content-Length'];
        $object->etag = (string) $headers['Etag']; // ETag is now Etag.
        $object->lastModified = strtotime($headers['Last-Modified']);

        // Set the metadata, too.
        $object->setMetadata(Container::extractHeaderAttributes($headers));


        // If content encoding and disposition exist, set them on the
        // object.
        if (!empty($headers['Content-Disposition'])) {
            $object->setDisposition($headers['Content-Disposition']);

        }
        if (!empty($headers['Content-Encoding'])) {
            $object->setEncoding($headers['Content-Encoding']);
        }

        $object->token = $token;
        $object->url = $url;

        if (is_null($client)) {
            $client = GuzzleAdapter::create();
        }
        $object->setClient($client);

        return $object;
    }

    /**
     * Set the HTTP Client to use.
     *
     * @param OpenStackTransportClientInterface $client The HTTP Client
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Get the URL to this object.
     *
     * If this object has been stored remotely, it will have
     * a valid URL.
     *
     * @return string A URL to the object. The following considerations apply:
     *                - If the container is public, this URL can be loaded without
     *                authentication. You can, for example, pass the URL to a browser
     *                user agent.
     *                - If this object has never been saved remotely, then there will be
     *                no URL, and this will return null.
     */
    public function url()
    {
        return $this->url;
    }


    public function contentLength()
    {
        if (!empty($this->content)) {
            return parent::contentLength();
        }

        return $this->contentLength;
    }

    public function eTag()
    {
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
    public function lastModified()
    {
        return $this->lastModified;
    }

    public function metadata()
    {
        // How do we get this?
        return $this->metadata;
    }

    /**
     * Set the headers
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject $this for the current object so it can be used in chaining
     *                                                       methods.
     */
    public function setHeaders($headers)
    {
        $this->allHeaders = [];

        foreach ($headers as $name => $value) {
            if (strpos($name, Container::METADATA_HEADER_PREFIX) !== 0) {
                $this->allHeaders[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * Get the HTTP headers sent by the server.
     *
     * EXPERT.
     *
     * This returns the array of minimally processed HTTP headers that
     * were sent from the server.
     *
     * @return array An associative array of header names and values.
     */
    public function headers()
    {
        return $this->allHeaders;
    }

    public function additionalHeaders($mergeAll = false)
    {
        // Any additional headers will be set. Note that $this->headers will contain
        // some headers that are NOT additional. But we do not know which headers are
        // additional and which are from Swift because Swift does not commit to using
        // a specific set of headers.
        if ($mergeAll) {
            $additionalHeaders = parent::additionalHeaders() + $this->allHeaders;
            $this->filterHeaders($additionalHeaders);
        } else {
            $additionalHeaders = parent::additionalHeaders();
        }

        return $additionalHeaders;
    }

    protected $reservedHeaders = [
        'etag' => true, 'content-length' => true,
        'x-auth-token' => true,
        'transfer-encoding' => true,
        'x-trans-id' => true,
    ];

    /**
     * Filter the headers.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject $this for the current object so it can be used in chaining
     *                                                       methods.
     */
    public function filterHeaders(&$headers)
    {
        $unset = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            if (isset($this->reservedHeaders[$lower])) {
                $unset[] = $name;
            }
        }
        foreach ($unset as $u) {
            unset($headers[$u]);
        }

        return $this;
    }

    /**
     * Given an array of header names.
     *
     * This will remove the given headers from the existing headers.
     * Both additional headers and the original headers from the
     * server are affected here.
     *
     * Note that you cannot remove metadata through this mechanism,
     * as it is managed using the metadata() methods.
     *
     * Many headers are generated automatically, such as
     * Content-Type and Content-Length. Removing these
     * will simply result in their being regenerated.
     *
     * @param array $keys The header names to be removed.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject $this for the current object so it can be used in chaining
     *                                                       methods.
     */
    public function removeHeaders($keys)
    {
        foreach ($keys as $key) {
            unset($this->allHeaders[$key]);
            unset($this->additionalHeaders[$key]);
        }

        return $this;
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
     * Be wary of using this method with large files.
     *
     * @return string The contents of the file as a string.
     *
     * @throws \OpenStack\Common\Transport\Exception\ResourceNotFoundException when the requested content cannot be
     *                                                                     located on the remote server.
     * @throws \OpenStack\Common\Exception                                 when an unknown exception (usually an
     *                                                                     abnormal network condition) occurs.
     */
    public function content()
    {
        // XXX: This allows local overwrites. Is this a good idea?
        if (!empty($this->content)) {
            return $this->content;
        }

        // Get the object, content included.
        $response = $this->fetchObject(true);

        $content = $response->getBody();

        // Checksum the content.
        // XXX: Right now the md5 is done even if checking is turned off.
        // Should fix that.
        $check = md5($content);
        if ($this->isVerifyingContent() && $check != $this->etag()) {
            throw new Exception\ContentVerificationException("Checksum $check does not match Etag " . $this->etag());
        }

        // If we are caching, set the content locally when we retrieve
        // remotely.
        if ($this->isCaching()) {
            $this->setContent($content);
        }

        return $content;
    }

    /**
     * Get the content of this object as a file stream.
     *
     * This is useful for large objects. Such objects should not be read
     * into memory all at once (as content() does), but should instead be
     * made available as an input stream.
     *
     * PHP offers low-level stream support in the form of PHP stream
     * wrappers, and this mechanism is used internally whenever available.
     *
     * If there is a local copy of the content, the stream will be read
     * out of the content as if it were a temp-file backed in-memory
     * resource. To ignore the local version, pass in true for the
     * $refresh parameter.
     *
     * If the content is coming from a remote copy, the stream will be
     * read directly from the underlying IO stream.
     *
     * Each time stream() is called, a new stream is created. In most
     * cases, this results in a new HTTP transaction (unless $refresh is
     * false and the content is already stored locally).
     *
     * The stream is read-only.
     *
     * @param boolean $refresh If this is set to true, any existing local
     *                         modifications will be ignored and the content will
     *                         be refreshed from the server. Any local changes to
     *                         the object will be discarded.
     *
     * @return resource A handle to the stream, which is already opened and
     *                  positioned at the beginning of the stream.
     */
    public function stream($refresh = false)
    {
        // If we're working on local content, return that content wrapped in
        // a fake IO stream.
        if (!$refresh && isset($this->content)) {
            return $this->localFileStream();
        }

        // Otherwise, we fetch a fresh version from the remote server and
        // return its stream handle.
        $response = $this->fetchObject(true);

        // Write to in-mem handle backed by a temp file.
        $out = fopen('php://temp', 'rb+');
        fwrite($out, $response->getBody());
        rewind($out);

        return $out;
    }

    /**
     * Transform a local copy of content into a file stream.
     *
     * This buffers the content into a stream resource and then returns
     * the stream resource. The resource is not used internally, and its
     * data is never written back to the remote object storage.
     */
    protected function localFileStream()
    {
        $tmp = fopen('php://temp', 'rw');
        fwrite($tmp, $this->content(), $this->contentLength());
        rewind($tmp);

        return $tmp;
    }

    /**
     * Enable or disable content caching.
     *
     * If a RemoteObject is set to cache then the first time content() is
     * called, its results will be cached locally. This is very useful for
     * small files whose content is accessed repeatedly, but can be a
     * cause of memory consumption for larger files.
     *
     * If caching settings are changed after content is retrieved, the
     * already retrieved content will not be affected, though any
     * subsequent requests will use the new caching settings. That is,
     * existing cached content will not be removed if caching is turned
     * off.
     *
     * @param boolean $enabled If this is true, caching will be enabled. If this
     *                         is false, caching will be disabled.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject $this so the method can be used in chaining.
     */
    public function setCaching($enabled)
    {
        $this->caching = $enabled;

        return $this;
    }

    /**
     * Indicates whether this object caches content.
     *
     * Importantly, this indicates whether the object will cache
     * its contents, not whether anything is actually cached.
     *
     * @return boolean true if caching is enabled, false otherwise.
     */
    public function isCaching()
    {
        return $this->caching;
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
     * @param boolean $enabled If this is true, content verification is performed.
     *                         The content is hashed and checked against a
     *                         server-supplied MD5 hashcode. If this is false,
     *                         no checking is done.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject $this so the method can be used in chaining.
     */
    public function setContentVerification($enabled)
    {
        $this->contentVerification = $enabled;

        return $this;
    }

    /**
     * Indicate whether this object verifies content (checksum).
     *
     * When content verification is on, RemoteObject attemts to perform a
     * checksum on the object, calculating the MD5 hash of the content
     * returned by the remote server, and comparing that to the server's
     * supplied ETag hash.
     *
     * @return boolean true if this is verifying, false otherwise.
     */
    public function isVerifyingContent()
    {
        return $this->contentVerification;
    }

    /**
     * Check whether there are unsaved changes.
     *
     * An object is marked "dirty" if it has been altered
     * locally in such a way that it no longer matches the
     * remote version.
     *
     * The practical definition of dirtiness, for us, is this: An object
     * is dirty if and only if (a) it has locally buffered content AND (b)
     * the checksum of the local content does not match the checksom of
     * the remote content.
     *
     * Not that minor differences, such as altered character encoding, may
     * change the checksum value, and thus (correctly) mark the object as
     * dirty.
     *
     * The RemoteObject implementation does not internally check dirty
     * markers. It is left to implementors to ensure that dirty content is
     * written to the remote server when desired.
     *
     * To replace dirty content with a clean copy, see refresh().
     *
     * @return boolean Whether or not there are unsaved changes.
     */
    public function isDirty()
    {
        // If there is no content, the object can't be dirty.
        if (!isset($this->content)) {
            return false;
        }

        // Content is dirty iff content is set, and it is
        // different from the original content. Note that
        // we are using the etag from the original headers.
        if ($this->etag != md5($this->content)) {
            return true;
        }

        return false;
    }

    /**
     * Rebuild the local object from the remote.
     *
     * This refetches the object from the object store and then
     * reconstructs the present object based on the refreshed data.
     *
     * WARNING: This will destroy any unsaved local changes. You can use
     * isDirty() to determine whether or not a local change has been made.
     *
     * @param boolean $fetchContent If this is true, the content will be
     *                              downloaded as well.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject $this for the current object so it
     *                                                         can be used in chaining methods.
     */
    public function refresh($fetchContent = false)
    {
        // Kill old content.
        unset($this->content);

        $response = $this->fetchObject($fetchContent);


        if ($fetchContent) {
            $this->setContent($response->getBody());
        }

        return $this;
    }

    /**
     * Helper function for fetching an object.
     *
     * @param boolean $fetchContent If this is set to true, a GET request will be
     *                              issued, which will cause the remote host to
     *                              return the object in the response body. The
     *                              response body is not handled, though. If this
     *                              is set to false, a HEAD request is sent, and
     *                              no body is returned.
     *
     * @return \OpenStack\Common\Transport\Response containing the object metadata and (depending
     *                                              on the $fetchContent flag) optionally the data.
     */
    protected function fetchObject($fetchContent = false)
    {
        $method = $fetchContent ? 'GET' : 'HEAD';

        $headers = ['X-Auth-Token' => $this->token];

        $response = $this->client->send(
            $this->client->createRequest($method, $this->url, null, ['headers' => $headers])
        );

        if ($response->getStatusCode() != 200) {
            throw new \OpenStack\Common\Exception('An unknown exception occurred during transmission.');
        }

        $this->extractFromHeaders($response);

        return $response;
    }

    /**
     * Extract information from HTTP headers.
     *
     * This is used internally to set object properties from headers.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\RemoteObject $this for the current object so it
     *                                                         can be used in chaining methods.
     */
    protected function extractFromHeaders($response)
    {
        $this->setContentType($response->getHeader('Content-Type') ? $response->getHeader('Content-Type') : $this->contentType());
        $this->lastModified = strtotime($response->getHeader('Last-Modified') ? $response->getHeader('Last-Modified') : 0);
        $this->etag = $response->getHeader('Etag') ? $response->getHeader('Etag') : $this->etag;
        $this->contentLength = (int) ($response->getHeader('Content-Length') ? $response->getHeader('Content-Length') : 0);

        $this->setDisposition($response->getHeader('Content-Disposition', null));
        $this->setEncoding($response->getHeader('Content-Encoding', null));

        // Reset the metadata, too:
        $headers = [];
        foreach ($response->getHeaders() as $name => $header) {
            $headers[$name] = $header[0];
        }
        $this->setMetadata(Container::extractHeaderAttributes($headers));

        return $this;
    }
}