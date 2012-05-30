<?php
/**
 * @file
 * Abstract operations class.
 */

namespace HPCloud\Services\DBaaS;

/**
 * Abstract class for DBaaS Operations groups.
 *
 * DBaaS operations have some structural similarities that
 * can be shared between instance and snapshot operation
 * groups. These are encapsulated here.
 */
abstract class Operations {
  protected $token;
  protected $projectId;

  /**
   * Generate the base headers needed by DBaaS requests.
   */
  protected function headers($merge = array()) {
    return $merge + array(
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
      'X-Auth-Token' => $this->token,
      'x-Auth-Project-Id' => $this->projectId,
    );
  }
}
