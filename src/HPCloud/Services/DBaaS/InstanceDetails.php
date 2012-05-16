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
 * This file contains the HPCloud::DBaaS::InstanceDetails class.
 */

namespace HPCloud\Services\DBaaS;

class InstanceDetails {

  protected $name;
  protected $id;
  protected $links;
  protected $created;
  protected $status;
  protected $hostname;
  protected $port;

  protected $username;
  protected $password;

  public function newFromJSON($json) {

    $o = new InstanceDetails($json['name'], $json['id']);
    $o->links = $json['links'];
    $o->created = $json['created'];
    $o->status = $json['status'];
    $o->hostname = $json['hostname'];
    $o->port= !empty($json['port']) ? $json['port'] : '3306';

    if (!empty($json['credential']['username'])) {
      $o->username = $json['credential']['username'];
    }
    if (!empty($json['credential']['password'])) {
      $o->username = $json['credential']['pasword'];
    }




  }

  public function __construct($name, $id) {
  }

  public function createdOn() {
    return $this->created;
  }

  public function status() {
    return $this->status;
  }

  public function hostname() {
    return $this->hostname;
  }

  public function port() {
    return $this->port;
  }

  public function username() {
    return $this->username;
  }
  public function password() {
    return $this->password;
  }
  public function links() {
    return $this->links;
  }

  /**
   * Get the DSN to connect to the database instance.
   *
   * A convenience function for PDO.
   *
   * @see http://us3.php.net/manual/en/ref.pdo-mysql.connection.php
   *
   * @param string $dbName
   *   The name of the database to connect to. If none is specified,
   *   this will be left off of the DSN.
   * @param string $charset
   *   This will attempt to set the character set. Not all versions
   *   of PHP use this.
   *
   * @retval string
   *   The DSN, including driver, host, port, and database name.
   * @todo
   *   At this time, 'mysql' is hard-coded as the driver name. Does this
   *   need to change?
   */
  public function dsn($dbName = NULL, $charset = NULL) {
    $dsn = sprintf('mysql:host=%s;port=%s', $this->hostname(), $this->port());
    if (!empty($dbName)) {
      $dsn .= ';dbname=' . $dbName;
    }
    if (!empty($charset)) {
      $dsn .= ';charset=' . $charset;
    }

    return $dsn;

  }


}
