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
 * This file contains the HPCloud::DBaaS::SnapshotDetails class.
 */

namespace HPCloud\Services\DBaaS;

/**
 * Details about a DBaaS snapshot.
 * Instances of this class are returned from DBaaS during creation
 * and listing operations.
 */
class SnapshotDetails {
  protected $id;
  protected $instanceId;
  protected $created;
  protected $status;
  protected $links;

  public static function newFromJSON($json) {
    $o = new SnapshotDetails($json['id'], $json['instanceId']);
    $o->created = $json['created'];
    $o->links = $json['links'];

    return $o;
  }
  public function __construct($id, $instanceId) {
    $this->id = $id;
    $this->instanceId = $instanceId;
  }
  /**
   * The ID of the snapshot.
   *
   * @retval string
   * @return string
   *   The ID.
   */
  public function id() {
    return $this->id;
  }
  /**
   * The ID of the database instance.
   *
   * This returns the ID of the database instance of which this
   * is a snapshot.
   *
   * @retval string
   * @return string
   *   The database instance ID.
   */
  public function instanceId() {
    return $this->instanceId;
  }
  /**
   * The data upon which this snapshot was created.
   *
   * @retval string
   * @return string
   *   An ISO data string representing the date and time
   *   that this snapshot was created.
   */
  public function createdOn() {
    return $this->created;
  }
  /**
   * The links for this snapshot.
   *
   * See HPCloud::Services::DBaaS::InstanceDetails::links().
   *
   * @attention
   *   The data returned from this may be in flux during the beta release
   *   of this product.
   * @retval array
   * @return array
   *   An array of links. Typically, at least an URL to the snapshot should
   *   be provided.
   */
  public function links() {
    return $this->links;
  }
}
