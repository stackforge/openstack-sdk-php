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

/**
 * Manage snapshots.
 *
 * A snapshot is an image (a backup) of a particular database taken at a
 * particular point in time. They can be used by HP Cloud Services to restore
 * a database instance to a particular point in time.
 *
 * Snapshotscan be created and deleted. Information about the snapshots can
 * be retrieved either as lists of snapshots or as individual snapshot details.
 *
 * Generally, Snapshot objects should be created through the
 * HPCloud::Services::DBaaS::snapshot() factory, and not created directly.
 *
 * Any operation that goes to the remote server may throw one of the
 * HPCloud::Exception exceptions.
 */
class Snapshot extends Operations {

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

  /**
   * Get a list of snapshot details.
   *
   * @param string $instanceId
   *   An optional database instance ID. If set, only snapshots for
   *   the given instance will be returned.
   * @retval array
   * @return array
   *   An array of HPCloud::Services::DBaaS::SnapshotDetails
   *   instances.
   */
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

  /**
   * Create a new snapshot of a given instance.
   *
   * Given the ID of a database instance and a
   * mnemonic name for the snapshot, take a snapshot of
   * the given database.
   *
   * Note that subsequent references to this snapshot must
   * be made by snapshot ID, not by `$name`.
   *
   * @param string $instanceId
   *   The instance ID for the database to snapshot.
   * @param string $name
   *   A human-readable name for the snapshot. Internally,
   *   a snapshot ID will be used to reference this
   *   snapshot.
   * @retval HPCloud::Services::DBaaS::SnapshotDetails
   * @return \HPCloud\Services\DBaaS\SnapshotDetails
   *   A snapshot details object containing information about
   *   the snapshot.
   */
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
   * @return boolean
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
   * @retval HPCloud::Services::DBaaS::SnapshotDetails
   * @return \HPCloud\Services\DBaaS\SnapshotDetails
   *   The details object.
   */
  public function describe($snapshotId) {
    $url = sprintf('%s/snapshots/%s', $this->url, $snapshotId);
    $res = $this->client->doRequest($url, 'GET', $this->headers());

    $json = json_decode($res->content(), TRUE);

    return SnapshotDetails::newFromJSON($json['snapshot']);
  }


}
