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

namespace OpenStack\ObjectStore\v1;

use OpenStack\Common\Exception;
use OpenStack\Common\Transport\ClientInterface;
use OpenStack\Common\Transport\Exception\ConflictException;
use OpenStack\Common\Transport\Exception\ResourceNotFoundException;
use OpenStack\Common\Transport\Guzzle\GuzzleAdapter;
use OpenStack\ObjectStore\v1\Exception\ContainerNotEmptyException;
use OpenStack\ObjectStore\v1\Resource\Container;
use OpenStack\ObjectStore\v1\Resource\ACL;

/**
 * Access to ObjectStorage (Swift).
 *
 * This is the primary piece of the Object Oriented representation of
 * the Object Storage service. Developers wishing to work at a low level
 * should use this API.
 *
 * There is also a stream wrapper interface that exposes ObjectStorage
 * to PHP's streams system. For common use of an object store, you may
 * prefer to use that system. (@see \OpenStack\Bootstrap).
 *
 * To authenticate, use the IdentityService authentication mechanism (@see
 * \OpenStack\Identity\v2\IdentityService).
 *
 * Common Tasks
 *
 * - Create a new container with createContainer().
 * - List containers with containers().
 * - Remove a container with deleteContainer().
 *
 * @todo ObjectStorage is not yet constrained to a particular version
 * of the API. It attempts to use whatever version is passed in to the
 * URL. This is different from IdentityService, which uses a fixed version.
 */
class ObjectStorage
{
    /**
     * The name of this service type in OpenStack.
     *
     * This is used with IdentityService::serviceCatalog().
     */
    const SERVICE_TYPE = 'object-store';

    const API_VERSION = '1';

    /**
     * The authorization token.
     */
    protected $token = null;
    /**
     * The URL to the Swift endpoint.
     */
    protected $url = null;

    /**
     * The HTTP Client
     */
    protected $client;

    /**
     * Given an IdentityService instance, create an ObjectStorage instance.
     *
     * This constructs a new ObjectStorage from an authenticated instance
     * of an \OpenStack\Identity\v2\IdentityService object.
     *
     * @param \OpenStack\Identity\v2\IdentityService $identity An identity services object that already
     *                                                         has a valid token and a service catalog.
     * @param string $region The Object Storage region
     * @param \OpenStack\Common\Transport\ClientInterface $client The HTTP client
     *
     * @return \OpenStack\ObjectStore\v1\ObjectStorage A new ObjectStorage instance.
     */
    public static function newFromIdentity($identity, $region, \OpenStack\Common\Transport\ClientInterface $client = null)
    {
        $cat = $identity->serviceCatalog();
        $tok = $identity->token();

        return self::newFromServiceCatalog($cat, $tok, $region, $client);
    }

    /**
     * Given a service catalog and a token, create an ObjectStorage instance.
     *
     * The IdentityService object contains a service catalog listing all of the
     * services to which the present user has access.
     *
     * This builder can scan the catalog and generate a new ObjectStorage
     * instance pointed to the first object storage endpoint in the catalog
     * that matches the specified parameters.
     *
     * @param array  $catalog   The service catalog from IdentityService::serviceCatalog().
     *                          This can be either the entire catalog or a catalog
     *                          filtered to just ObjectStorage::SERVICE_TYPE.
     * @param string $authToken The auth token returned by IdentityService.
     * @param string $region    The Object Storage region
     * @param \OpenStack\Common\Transport\ClientInterface $client The HTTP client
     *
     *
     * @return \OpenStack\ObjectStore\v1\ObjectStorage A new ObjectStorage instance.
     */
    public static function newFromServiceCatalog($catalog, $authToken, $region, \OpenStack\Common\Transport\ClientInterface $client = null)
    {
        $c = count($catalog);
        for ($i = 0; $i < $c; ++$i) {
            if ($catalog[$i]['type'] == self::SERVICE_TYPE) {
                foreach ($catalog[$i]['endpoints'] as $endpoint) {
                    if (isset($endpoint['publicURL']) && $endpoint['region'] == $region) {
                        return new ObjectStorage($authToken, $endpoint['publicURL'], $client);
                    }
                }
            }
        }

        return false;

    }

    /**
     * Construct a new ObjectStorage object.
     *
     * Use this if newFromServiceCatalog() does not meet your needs.
     *
     * @param string $authToken A token that will be included in subsequent
     *                          requests to validate that this client has authenticated
     *                          correctly.
     * @param string $url       The URL to the endpoint. This typically is returned
     *                          after authentication.
     * @param \OpenStack\Common\Transport\ClientInterface $client The HTTP client
     */
    public function __construct($authToken, $url, ClientInterface $client = null)
    {
        $this->token = $authToken;
        $this->url = $url;

        // Guzzle is the default client to use.
        if (is_null($client)) {
            $this->client = GuzzleAdapter::create();
        } else {
            $this->client = $client;
        }
    }

    /**
     * Get the authentication token.
     *
     * @return string The authentication token.
     */
    public function token()
    {
        return $this->token;
    }

    /**
     * Get the URL endpoint.
     *
     * @return string The URL that is the endpoint for this service.
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * Fetch a list of containers for this user.
     *
     * By default, this fetches the entire list of containers for the
     * given user. If you have more than 10,000 containers (who
     * wouldn't?), you will need to use $marker for paging.
     *
     * If you want more controlled paging, you can use $limit to indicate
     * the number of containers returned per page, and $marker to indicate
     * the last container retrieved.
     *
     * Containers are ordered. That is, they will always come back in the
     * same order. For that reason, the pager takes $marker (the name of
     * the last container) as a paging parameter, rather than an offset
     * number.
     *
     * @todo For some reason, ACL information does not seem to be returned
     *   in the JSON data. Need to determine how to get that. As a
     *   stop-gap, when a container object returned from here has its ACL
     *   requested, it makes an additional round-trip to the server to
     *   fetch that data.
     *
     * @param int    $limit  The maximum number to return at a time. The default is
     *                       -- brace yourself -- 10,000 (as determined by OpenStack. Implementations
     *                       may vary).
     * @param string $marker The name of the last object seen. Used when paging.
     *
     * @return array An associative array of containers, where the key is the
     *               container's name and the value is an \OpenStack\ObjectStore\v1\ObjectStorage\Container
     *               object. Results are ordered in server order (the order that the remote
     *               host puts them in).
     */
    public function containers($limit = 0, $marker = null)
    {
        $url = $this->url() . '?format=json';

        if ($limit > 0) {
            $url .= sprintf('&limit=%d', $limit);
        }
        if (!empty($marker)) {
            $url .= sprintf('&marker=%d', $marker);
        }

        $headers = ['X-Auth-Token' => $this->token];
        $response = $this->client->get($url, ['headers' => $headers]);
        $containers = $response->json();

        $containerList = [];
        foreach ($containers as $container) {
            $cname = $container['name'];
            $containerList[$cname] = Container::newFromJSON($container, $this->token(), $this->url(), $this->client);
        }

        return $containerList;
    }

    /**
     * Get a single specific container.
     *
     * This loads only the named container from the remote server.
     *
     * @param string $name The name of the container to load.
     *
     * @return \OpenStack\ObjectStore\v1\Resource\Container A container.
     *
     * @throws \OpenStack\Common\Transport\Exception\ResourceNotFoundException if the named container is not
     *                                                                     found on the remote server.
     */
    public function container($name)
    {
        $url = $this->url() . '/' . rawurlencode($name);

        $headers = ['X-Auth-Token' => $this->token()];
        $response = $this->client->head($url, ['headers' => $headers]);

        $status = $response->getStatusCode();

        if ($status == 204) {
            return Container::newFromResponse($name, $response, $this->token(), $this->url());
        }

        // If we get here, it's not a 404 and it's not a 204.
        throw new Exception(sprintf("Unknown status: %d", $status));
    }

    /**
     * Check to see if this container name exists.
     *
     * This method directly checks the remote server. Calling container()
     * or containers() might be more efficient if you plan to work with
     * the resulting container.
     *
     * @param string $name The name of the container to test.
     *
     * @return boolean true if the container exists, false if it does not.
     *
     * @throws \OpenStack\Common\Exception If an unexpected network error occurs.
     */
    public function hasContainer($name)
    {
        try {
            $container = $this->container($name);
        } catch (ResourceNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * Create a container with the given name.
     *
     * This creates a new container on the ObjectStorage
     * server with the name provided in $name.
     *
     * A boolean is returned when the operation did not generate an error
     * condition.
     *
     * - true means that the container was created.
     * - false means that the container was not created because it already
     * exists.
     *
     * Any actual error will cause an exception to be thrown. These will
     * be the HTTP-level exceptions.
     *
     * ACLs
     *
     * Swift supports an ACL stream that allows for specifying (with
     * certain caveats) various levels of read and write access. However,
     * there are two standard settings that cover the vast majority of
     * cases.
     *
     * - Make the resource private: This grants read and write access to
     *   ONLY the creating user tenant. This is the default; it can also be
     *   specified with ACL::makeNonPublic().
     * - Make the resource public: This grants READ permission to any
     *   requesting host, yet only allows the creator to WRITE to the
     *   object. This level can be granted by ACL::makePublic().
     *
     * Note that ACLs operate at a container level. Thus, marking a
     * container public will allow access to ALL objects inside of the
     * container.
     *
     * To find out whether an existing container is public, you can
     * write something like this:
     *
     *     <?php
     *     // Get the container.
     *     $container = $objectStorage->container('my_container');
     *
     *     //Check the permission on the ACL:
     *     $boolean = $container->acl()->isPublic();
     *     ?>
     *
     * For details on ACLs, see \OpenStack\ObjectStore\v1\Resource\ACL.
     *
     * @param string $name     The name of the container.
     * @param object $acl      \OpenStack\ObjectStore\v1\Resource\ACL An access control
     *                         list object. By default, a container is non-public
     *                         (private). To change this behavior, you can add a
     *                         custom ACL. To make the container publically
     *                         readable, you can use this: \OpenStack\ObjectStore\v1\Resource\ACL::makePublic().
     * @param array  $metadata An associative array of metadata to attach to the
     *                         container.
     *
     * @return boolean true if the container was created, false if the container
     *                 was not created because it already exists.
     */
    public function createContainer($name, ACL $acl = null, $metadata = [])
    {
        $url = $this->url() . '/' . rawurlencode($name);
        $headers = ['X-Auth-Token' => $this->token()];

        if (!empty($metadata)) {
            $prefix = Container::CONTAINER_METADATA_HEADER_PREFIX;
            $headers += Container::generateMetadataHeaders($metadata, $prefix);
        }

        // Add ACLs to header.
        if (!empty($acl)) {
            $headers += $acl->headers();
        }

        $data = $this->client->put($url, null, ['headers' => $headers]);

        $status = $data->getStatusCode();

        if ($status == 201) {
            return true;
        } elseif ($status == 202) {
            return false;
        } else {
            // According to the OpenStack docs, there are no other return codes.
            throw new Exception('Server returned unexpected code: ' . $status);
        }
    }

    /**
     * Alias of createContainer().
     *
     * At present, there is no distinction in the Swift REST API between
     * creating an updating a container. In the future this may change, so
     * you are encouraged to use this alias in cases where you clearly intend
     * to update an existing container.
     */
    public function updateContainer($name, ACL $acl = null, $metadata = [])
    {
        return $this->createContainer($name, $acl, $metadata);
    }

    /**
     * Change the container's ACL.
     *
     * This will attempt to change the ACL on a container. If the
     * container does not already exist, it will be created first, and
     * then the ACL will be set. (This is a relic of the OpenStack Swift
     * implementation, which uses the same HTTP verb to create a container
     * and to set the ACL.)
     *
     * @param string $name The name of the container.
     * @param object $acl  \OpenStack\ObjectStore\v1\Resource\ACL An ACL. To make the
     *                     container publically readable, use ACL::makePublic().
     *
     * @return boolean true if the cointainer was created, false otherwise.
     */
    public function changeContainerACL($name, ACL $acl)
    {
        // Oddly, the way to change an ACL is to issue the
        // same request as is used to create a container.
        return $this->createContainer($name, $acl);
    }

    /**
     * Delete an empty container.
     *
     * Given a container name, this attempts to delete the container in
     * the object storage.
     *
     * The container MUST be empty before it can be deleted. If it is not,
     * an \OpenStack\ObjectStore\v1\Exception\ContainerNotEmptyException will
     * be thrown.
     *
     * @param string $name The name of the container.
     *
     * @return boolean true if the container was deleted, false if the container
     *                 was not found (and hence, was not deleted).
     *
     * @throws \OpenStack\ObjectStore\v1\Exception\ContainerNotEmptyException if the container is not empty.
     *
     * @throws \OpenStack\Common\Exception if an unexpected response code is returned. While this should never happen on
     *                              OpenStack servers, forks of OpenStack may choose to extend object storage in a way
     *                              that results in a non-standard code.
     */
    public function deleteContainer($name)
    {
        $url = $this->url() . '/' . rawurlencode($name);

        try {
            $headers = ['X-Auth-Token' => $this->token()];
            $data = $this->client->delete($url, ['headers' => $headers]);
        } catch (ResourceNotFoundException $e) {
            return false;
        } catch (ConflictException $e) {
            // XXX: I'm not terribly sure about this. Why not just throw the
            // ConflictException?
            throw new ContainerNotEmptyException(
                "Non-empty container cannot be deleted",
                $e->getRequest(),
                $e->getResponse()
            );
        }

        $status = $data->getStatusCode();

        // 204 indicates that the container has been deleted.
        if ($status == 204) {
            return true;
        } else {
            // OpenStacks documentation doesn't suggest any other return codes.
            throw new Exception('Server returned unexpected code: ' . $status);
        }
    }

    /**
     * Retrieve account info.
     *
     * This returns information about:
     *
     * - The total bytes used by this Object Storage instance (`bytes`).
     * - The number of containers (`count`).
     *
     * @return array An associative array of account info. Typical keys are:
     *               - bytes: Bytes consumed by existing content.
     *               - containers: Number of containers.
     *               - objects: Number of objects.
     *
     * @throws \OpenStack\Common\Transport\Exception\AuthorizationException if the user credentials
     *                                                                      are invalid or have expired.
     */
    public function accountInfo()
    {
        $headers = ['X-Auth-Token' => $this->token()];
        $response = $this->client->head($this->url(), ['headers' => $headers]);

        return [
            'bytes'      => $response->getHeader('X-Account-Bytes-Used', 0),
            'containers' => $response->getHeader('X-Account-Container-Count', 0),
            'objects'    => $response->getHeader('X-Account-Container-Count', 0)
        ];
    }
}
