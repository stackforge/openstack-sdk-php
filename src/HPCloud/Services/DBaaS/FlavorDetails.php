<?php
/* ============================================================================
(c) Copyright 2013 Hewlett-Packard Development Company, L.P.
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
 * This file contains the Database FlavorDetails class.
 */

namespace HPCloud\Services\DBaaS;

/**
 * Class for working with Database Flavors Details.
 */
class FlavorDetails {

	protected $name;
	protected $id;
	protected $url;
	protected $links;
	protected $ram;
	protected $vcpu;

	public static function newFromArray(array $array) {

		$o = new FlavorDetails($array['name'], $array['id']);
		$o->links = $array['links'];
		$o->ram = $array['ram'];
		$o->vcpu = $array['vcpu'];

		if (isset($array['links'][0]) && $array['links'][0]['rel'] == 'self') {
			$o->url = $array['links'][0]['href'];
		}

		return $o;
	}

	public function __construct($name, $id) {
    $this->name = $name;
    $this->id = $id;
  }

  /**
   * Get the name of a flavor (e.g., small).
   *
   * @return string
   *   The name of a flavor.
   */
  public function name() {
  	return $this->name;
  }

  /**
   * Get the id of a flavor.
   *
   * @return int
   *   The id of a flavor.
   */
  public function id() {
  	return $this->id;
  }

  /**
   * Get the links for a flavor.
   *
   * @retval array
   * @return array
   *   Get an array of links for the flavor.
   */
  public function links() {
  	return $this->links;
  }

  /**
   * Get the callback url for the flavor.
   *
   * @retval string
   * @return string
   *   The callback url for the flavor. This is in the form 
   *   [DaaSBaseURI]/{tenant_id}/flavors/{flavorId}
   */
  public function url() {
  	return $this->url;
  }

  /**
   * Get the amount of ram available to this flavor.
   *
   * @retval int
   * @return int
   *   The amount of ram available to the flavor.
   */
  public function ram() {
  	return $this->ram;
  }

  /**
   * Get the number of virtual CPUs available to this flavor.
   *
   * @retval int
   * @return int
   *   The number of virtual CPUs available to the flavor.
   */
  public function vcpu() {
  	return $this->vcpu;
  }
}
