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

  protected $username;
  protected $password;

  public function newFromJSON($json) {

    //fwrite(STDOUT, json_encode($json));

    $o = new InstanceDetails($json['name'], $json['id']);
    $o->links = $json['links'];
    $o->created = $json['created'];
    $o->status = $json['status'];
    if (!empty($json['hostname'])) {
      $o->hostname = $json['hostname'];
    }

    if (!empty($json['credential']['username'])) {
      $o->username = $json['credential']['username'];
    }
    if (!empty($json['credential']['password'])) {
      $o->password = $json['credential']['password'];
    }

    if (!empty($json['name'])) {
      $o->name = $json['name'];
    }

    return $o;
  }

  public function __construct($name, $id) {
    $this->name = $name;
    $this->id = $id;
  }

  /**
   * Get the name of this instance.
   *
   * @retval string
   * @return string
   *   The name of the instance.
   */
  public function name() {
    return $this->name;
  }

  /**
   * Get the ID of the instance.
   *
   * @retval string
   * @return string
   *   The ID.
   */
  public function id() {
    return $this->id;
  }

  /**
   * Get a string expressing the creation time.
   *
   * This may only be set during CREATE or DESCRIBE results.
   *
   * @retval string
   * @return string
   *   A string indicating the creation time.
   *   Format is in ISO date format.
   */
  public function createdOn() {
    return $this->created;
  }

  /**
   * Get the status of this instance.
   *
   * This indicates whether or not the service is available, along with other
   * details.
   *
   * Known status messages:
   *- running: Instance is fully operational.
   *- building: Instance is being created.
   *- restarting: Instance has been restarted, and is still coming online.
   *
   * @retval string
   * @return string
   *   A short status message.
   */
  public function status() {
    return $this->status;
  }

  /**
   * Check whether the present instance is running.
   *
   * This is a convenience function for determining whether a remote
   * instance reports itself to be running. It is equivalent to
   * checking that status() returns 'running'.
   *
   * @retval boolean
   * @return boolean
   *   TRUE if this is running, FALSE otherwise.
   */
  public function isRunning() {
    return strcasecmp($this->status(), 'running') == 0;
  }

  /**
   * Get the hostname.
   *
   * Note that the port is always 3306, the MySQL default. Only the hostname
   * is returned.
   *
   * @attention
   * In version 1.0 of the DBaaS protocol, this is ONLY available after the
   * DB instance has been brought all the way up.
   *
   * This returns the DNS name of the host (or possibly an IP address).
   *
   * @retval string
   * @return string
   *   The FQDN or IP address of the MySQL server.
   */
  public function hostname() {
    return $this->hostname;
  }

  /**
   * Set the hostname.
   *
   * @param string $hostname
   *   The hostname for this server.
   *
   * @retval HPCloud::Services::DBaaS::InstanceDetails
   * @return \HPCloud\Services\DBaaS\InstanceDetails
   *   $this so the method can be used in chaining.
   */
  public function setHostname($hostname) {
    $this->hostname = $hostname;

    return $this;
  }

  /**
   * The username field, if available.
   *
   * @attention
   * Typically this is only available at creation time!
   *
   * @retval string
   * @return string
   *   The username for the MySQL instance.
   */
  public function username() {
    return $this->username;
  }

  /**
   * Set the username.
   *
   * @param string $username
   *   The username for this server.
   *
   * @retval HPCloud::Services::DBaaS::InstanceDetails
   * @return \HPCloud\Services\DBaaS\InstanceDetails
   *   $this so the method can be used in chaining.
   */
  public function setUsername($username) {
    $this->username = $username;

    return $this;
  }

  /**
   * The password field, if available.
   *
   * This is the password for this instance's MySQL database.
   *
   * @attention
   *   This is only returned when a database is first created.
   *
   * @retval string
   * @return string
   *   A password string.
   */
  public function password() {
    return $this->password;
  }

  /**
   * Set the password.
   *
   * @param string $password
   *   The password for this server.
   *
   * @retval HPCloud::Services::DBaaS::InstanceDetails
   * @return \HPCloud\Services\DBaaS\InstanceDetails
   *   $this so the method can be used in chaining.
   */
  public function setPassword($password) {
    $this->password = $password;

    return $this;
  }

  /**
   * An array of links about this database.
   *
   * Format:
   * @code
   * <?php
   * array(
   *   0 => array(
   *     "rel" => "self",
   *     "url" => "https://some.long/url",
   *   ),
   * );
   * ?>
   * @endcode
   *
   * At the time of this writing, there is no definition of what URLs may
   * appear here. However, the `self` URL us a URL to the present instance's
   * definition.
   *
   * @retval array
   * @return array
   *   An array of related links to DBaaS URLs.
   */
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
   * @return string
   *   The DSN, including driver, host, port, and database name.
   * @todo
   *   At this time, 'mysql' is hard-coded as the driver name. Does this
   *   need to change?
   */
  public function dsn($dbName = NULL, $charset = NULL) {
    $dsn = sprintf('mysql:host=%s;port=3306', $this->hostname());
    if (!empty($dbName)) {
      $dsn .= ';dbname=' . $dbName;
    }
    if (!empty($charset)) {
      $dsn .= ';charset=' . $charset;
    }

    return $dsn;

  }

}
