<?php
/* ============================================================================
(c) Copyright 2012 Hewlett-Packard Development Company, L.P.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights to
use, copy, modify, merge,publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE  LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
============================================================================ */
/**
 * @file
 *
 * This file contains the DBaaS Snapshot class.
 */

namespace HPCloud\Services\DBaaS;

use \HPCloud\Services\DBaaS\SnapshotDetails;

class Snapshot {

  protected $token;
  protected $projectId;
  protected $url;
  protected $client;

  public function __construct($token, $projectId, $endpoint) {
    $this->token = $token;
    $this->projectId = $projectId;
    $this->url = $endpoint;
    $this->client = \HPCloud\Transport::instance();
  }

  public function listSnapshots($instanceId = NULL) {
    $url = $this->url . '/snapshots';
    if (!empty($instanceId)) {
      $url .= '?instanceId=' . rawurlencode($instanceId);
    }
    $headers = $this->headers();
    $retval = $resp = $this->client->doRequest($url, 'GET', $headers);

    $json = json_decode($retval, TRUE);
    $list = array();
    foreach ($json['snapshots'] as $item) {
      $list[] = SnapshotDetails::newFromJSON($item);
    }

    return $list;
  }

  public function create($instanceId, $name) {
    $url = $this->url . '/snapshots';
    $create = array(
      'snapshot' => array(
        'instanceId' => $instanceId,
        'name' => $name,
      )
    );

    $json = json_encode($create);
    $resp = $this->client->doRequest($url, 'POST', $this->headers(), $json);

    $data = json_decode($resp, TRUE);

    return SnapshotDetails::newFromJSON($data['snapshot']);
  }

  /**
   * Given a snapshot ID, delete the snapshot.
   *
   * @param string $snapshotId
   *   The snapshot ID for the snapshot that should
   *   be deleted.
   * @retval boolean
   *   Returns boolean TRUE on success. Throws one of the 
   *   HPCloud::Exception instances on failure.
   * @throws HPCloud::Exception
   *   One of the Transport class of exceptions.
   */
  public function delete($snapshotId) {
    $url = sprintf('%s/snapshots/%s', $this->url, $snapshotId);
    $this->client->doRequest($url, 'DELETE', $this->headers());

    return TRUE;
  }

  /**
   * Get the details for a particular snapshot.
   *
   * @param string $snapshotId
   *   The snapshot ID.
   *
   * @retval object HPCloud::Services::DBaaS::SnapshotDetails
   *   The details object.
   */
  public function describe($snapshotId) {
    $url = sprintf('%s/snapshots/%s', $this->url, $snapshotId);
    $res = $this->client->doRequest($url, 'GET', $this->headers());

    $json = json_decode($res->content(), TRUE);

    return SnapshotDetails::newFromJSON($json['snapshot']);
  }

  protected function headers($merge = array()) {
    return $merge + array(
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
      'X-Auth-Token' => $this->token,
      'x-Auth-Project-Id' => $this->projectId,
    );
  }

}
