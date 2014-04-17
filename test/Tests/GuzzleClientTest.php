<?php
/* ============================================================================
(c) Copyright 2014 Hewlett-Packard Development Company, L.P.

     Licensed under the Apache License, Version 2.0 (the "License");
     you may not use this file except in compliance with the License.
     You may obtain a copy of the License at

             http://www.apache.org/licenses/LICENSE-2.0

     Unless required by applicable law or agreed to in writing, software
     distributed under the License is distributed on an "AS IS" BASIS,
     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
     See the License for the specific language governing permissions and
     limitations under the License.
============================================================================ */

namespace OpenStack\Tests;

use OpenStack\Transport\GuzzleClient;

require_once 'test/TestCase.php';

class GuzzleClientTest extends \OpenStack\Tests\TestCase
{
    /**
     * Get the config from the test settings file and pass that into the client.
     */
    public function buildClient()
    {
        $options = array();
        if (isset(self::$settings['transport.proxy'])) {
            $options['proxy'] = self::$settings['transport.proxy'];
        }
        if (isset(self::$settings['transport.debug'])) {
            $options['debug'] = self::$settings['transport.debug'];
        }
        if (isset(self::$settings['transport.ssl.verify'])) {
            $options['ssl_verify'] = self::$settings['transport.ssl.verify'];
        }
        if (isset(self::$settings['transport.timeout'])) {
            $options['timeout'] = self::$settings['transport.timeout'];
        }

        return new GuzzleClient($options);
    }

    public function testDoRequest()
    {
        $url = 'http://www.openstack.org';
        $method = 'GET';

        $client = $this->buildClient();

        $this->assertInstanceOf('\OpenStack\Transport\GuzzleClient', $client);

        $response = $client->doRequest($url, $method);
        $this->assertInstanceOf('\GuzzleHttp\Message\Response', $response);

    }

    /**
     * @depends testDoRequest
     * @expectedException \OpenStack\Transport\FileNotFoundException
     */
    public function testDoRequestException()
    {
        $url = 'http://www.openstack.org/this-does-no-exist';
        $method = 'GET';

        $client = $this->buildClient();
        $client->doRequest($url, $method);
    }

}
