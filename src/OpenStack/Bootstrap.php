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

namespace OpenStack;

use OpenStack\Identity\v2\IdentityService;
use OpenStack\ObjectStore\v1\Resource\StreamWrapper;
use OpenStack\ObjectStore\v1\Resource\StreamWrapperFS;

/**
 * Bootstrapping services.
 *
 * There is no requirement that this class be used. OpenStack is
 * built to be flexible, and any individual component can be
 * used directly, with one caveat: No explicit `require` or
 * `include` calls are made.
 *
 * This class provides the following services:
 *
 * - Configuration: "global" settings are set here.
 *   See the setConfiguration() method to see how they
 *   can be set, and the config() and hasConfig() methods to see
 *   how configuration might be checked.
 * - Stream Wrappers: This class can initialize a set of stream
 *   wrappers which will make certain OpenStack services available
 *   through the core PHP stream support.
 *
 * Configuration
 *
 * Configuration directives can be merged into the existing confiuration
 * using the setConfiguration method.
 *
 *     <?php
 *     $config = array(
 *       // We use Guzzle, which defaults to CURL, for a transport layer.
 *       'transport' => 'OpenStack\Common\Transport\Guzzle\GuzzleAdapter',
 *       // Set the HTTP max wait time to 500 seconds.
 *       'transport.timeout' => 500,
 *     );
 *     Bootstrap::setConfiguration($config);
 *
 *     // Check and get params.
 *     if (Bootstrap::hasConf('transport.timeout') {
 *       $to = Bootstrap::conf('transport.timeout');
 *     }
 *
 *     // Or get a param with a default value:
 *     $val = Bootstrap::conf('someval', 'default value');
 *
 *     // $val will be set to 'default value' because there
 *     // is no 'someval' configuration param.
 *
 *     ?>
 *
 * STREAM WRAPPERS
 *
 * Stream wrappers allow you to use the built-in file manipulation
 * functions in PHP to interact with other services. Specifically,
 * the OpenStack stream wrappers allow you to use built-in file commands
 * to access Object Storage (Swift) and other OpenStack services using
 * commands like file_get_contents() and fopen().
 *
 * It's awesome. Trust me.
 */
class Bootstrap
{
    const VERSION = '0.0.1';

    public static $config = [
        // The transport implementation. By default, we use the Guzzle Client
        'transport' => 'OpenStack\Common\Transport\Guzzle\GuzzleAdapter',
    ];

    /**
     * @var \OpenStack\Identity\v2\IdentityService An identity services object
     *   created from the global settings.
     */
    public static $identity = null;

    /**
     * @var \OpenStack\Common\Transport\ClientInterface A transport client for requests.
     */
    public static $transport = null;

    /**
     * Register stream wrappers for OpenStack.
     *
     * This registers the ObjectStorage stream wrappers, which allow you to access
     * ObjectStorage through standard file access mechanisms.
     *
     *     // Enable stream wrapper.
     *     Bootstrap::useStreamWrappers();
     *
     *     // Create a context resource.
     *     $cxt = stream_context_create(array(
     *       'tenantid' => '12de21',
     *       'username' => 'foobar',
     *       'password' => 'f78saf7hhlll',
     *       'endpoint' => 'https://identity.hpcloud.com' // <-- not real URL!
     *     ));
     *
     *     // Get the contents of a Swift object.
     *     $content = file_get_contents('swift://public/notes.txt', 'r', false, $cxt);
     */
    public static function useStreamWrappers()
    {
        self::enableStreamWrapper(
            StreamWrapper::DEFAULT_SCHEME,
            'OpenStack\ObjectStore\v1\Resource\StreamWrapper'
        );
        self::enableStreamWrapper(
            StreamWrapperFS::DEFAULT_SCHEME,
            'OpenStack\ObjectStore\v1\Resource\StreamWrapperFS'
        );
    }

    /**
     * Register a stream wrapper according to its scheme and class
     *
     * @param $scheme Stream wrapper's scheme
     * @param $class  The class that contains stream wrapper functionality
     */
    private static function enableStreamWrapper($scheme, $class)
    {
        if (in_array($scheme, stream_get_wrappers())) {
            stream_wrapper_unregister($scheme);
        }

        stream_wrapper_register($scheme, $class);
    }

    /**
     * Set configuration directives for OpenStack.
     *
     * This merges the provided associative array into the existing
     * configuration parameters (Bootstrap::$config).
     *
     * All of the OpenStack classes share the same configuration. This
     * ensures that a stable runtime environment is maintained.
     *
     * Common configuration directives:
     *
     * - 'transport': The namespaced classname for the transport that
     *   should be used. Example: \OpenStack\Common\Transport\Guzzle\GuzzleAdapter
     * - 'transport.debug': The integer 1 for enabling debug, 0 for
     *   disabling. Enabling will turn on verbose debugging output
     *   for any transport that supports it.
     * - 'transport.timeout': An integer value indicating how long
     *   the transport layer should wait for an HTTP request. A
     *   transport MAY ignore this parameter, but the ones included
     *   with the library honor it.
     * - 'transport.ssl_verify': Set this to false to turn off SSL certificate
     *   verification. This is NOT recommended, but is sometimes necessary for
     *   certain proxy configurations.
     * - 'transport.proxy': Set the proxy as a string.
     * - 'username' and 'password'
     * - 'tenantid'
     * - 'endpoint': The full URL to identity services. This is used by stream
     *   wrappers.
     *
     * @param array $array An associative array of configuration directives.
     */
    public static function setConfiguration($array)
    {
        self::$config = $array + self::$config;
    }

    /**
     * Get a configuration option.
     *
     * Get a configuration option by name, with an optional default.
     *
     * @param string $name    The name of the configuration option to get.
     * @param mixed  $default The default value to return if the name is not found.
     *
     * @return mixed The value, if found; or the default, if set; or null.
     */
    public static function config($name = null, $default = null)
    {
        // If no name is specified, return the entire config array.
        if (empty($name)) {
            return self::$config;
        }

        // If the config value exists, return that.
        if (isset(self::$config[$name])) {
            return self::$config[$name];
        }

        // Otherwise, just return the default value.
        return $default;
    }

    /**
     * Check whether the given configuration option is set.
     *
     *     if (Bootstrap::hasConfig('transport')) {
     *       syslog(LOG_INFO, 'An alternate transport is supplied.');
     *     }
     *
     * @param string $name The name of the item to check for.
     *
     * @return boolean true if the named option is set, false otherwise. Note that
     *                 the value may be falsey (false, 0, etc.), but if the value is null, this
     *                 will return false.
     */
    public static function hasConfig($name)
    {
        return isset(self::$config[$name]);
    }

    /**
     * Get a \OpenStack\Identity\v2\IdentityService object from the bootstrap config.
     *
     * A factory helper function that uses the bootstrap configuration to create
     * a ready to use \OpenStack\Identity\v2\IdentityService object.
     *
     * @param bool $force Whether to force the generation of a new object even if
     *                    one is already cached.
     *
     * @return \OpenStack\Identity\v2\IdentityService An authenticated ready to use
     *                                                \OpenStack\Identity\v2\IdentityService object.
     * @throws \OpenStack\Common\Exception When the needed configuration to authenticate
     *                              is not available.
     */
    public static function identity($force = false)
    {
        $transport = self::transport();

        // If we already have an identity make sure the token is not expired.
        if ($force || is_null(self::$identity) || self::$identity->isExpired()) {

            // Make sure we have an endpoint to use
            if (!self::hasConfig('endpoint')) {
                throw new Exception('Unable to authenticate. No endpoint supplied.');
            }

            // User cannot be an empty string, so we need
            // to do more checking than self::hasConfig(), which returns true
            // if an item exists and is an empty string.
            $user = self::config('username', null);

            // Check if we have a username/password
            if (!empty($user) && self::hasConfig('password')) {
                $is = new IdentityService(self::config('endpoint'), $transport);
                $is->authenticateAsUser($user, self::config('password'), self::config('tenantid', null), self::config('tenantname', null));
                self::$identity = $is;
            } else {
                throw new Exception('Unable to authenticate. No user credentials supplied.');
            }
        }

        return self::$identity;
    }

    /**
     * Get a transport client.
     *
     * @param  boolean $reset Whether to recreate the transport client if one already exists.
     *
     * @return \OpenStack\Common\Transport\ClientInterface A transport client.
     */
    public static function transport($reset = false)
    {
        if (is_null(self::$transport) || $reset == true) {
            $options = [
                'ssl_verify' => self::config('ssl_verify', true),
                'timeout' => self::config('timeout', 0),          // 0 is no timeout.
                'debug' => self::config('debug', 0),
            ];
            $proxy = self::config('proxy', false);
            if ($proxy) {
                $options['proxy'] = $proxy;
            }

            $class = self::config('transport');
            self::$transport = $class::create($options);
        }

        return self::$transport;
    }
}
