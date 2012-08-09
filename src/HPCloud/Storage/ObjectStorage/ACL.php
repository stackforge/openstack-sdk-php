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
 * Contains the class for manipulating ObjectStorage ACL strings.
 */

namespace HPCloud\Storage\ObjectStorage;

/**
 * Access control list for object storage.
 *
 * @b EXPERIMENTAL: This is bassed on a feature of Swift that is likely to
 * change. Most of this is based on undocmented features of the API
 * discovered both in the Python docs and in discussions by various
 * members of the OpenStack community.
 *
 * Swift access control rules are broken into two permissions: READ and
 * WRITE. Read permissions grant the user the ability to access the file
 * (using verbs like GET and HEAD), while WRITE permissions allow any
 * modification operation. WRITE does not imply READ.
 *
 * In the current implementation of Swift, access can be assigned based
 * on two different factors:
 *
 * - @b Accounts: Access can be granted to specific accounts, and within
 *   those accounts, can be further specified to specific users. See the
 *   addAccount() method for details on this.
 * - @b Referrers: Access can be granted based on host names or host name
 *   patterns. For example, only subdomains of <tt>*.example.com</tt> may be
 *   granted READ access to a particular object.
 *
 * ACLs are transmitted within the HTTP headers for an object or
 * container. Two headers are used: @c X-Container-Read for READ rules, and
 * @c X-Container-Write for WRITE rules. Each header may have a chain of
 * rules.
 *
 * @b Examples
 *
 * For most casual cases, only the static constructor functions are
 * used. For example, an ACL that does not grant any public access can
 * be created with a single call:
 *
 * @code
 * <?php
 * $acl = ACL::makeNonPublic();
 * ?>
 * @endcode
 *
 * Public read access is granted like this:
 *
 * @code
 * <?php
 * $acl = ACL::makePublic();
 * ?>
 * @endcode
 *
 * (Note that in both cases, what is returned is an instance of an ACL with
 * all of the necessary configuration done.)
 *
 * Sometimes you will need more sophisticated access control rules. The
 * following grants READ access to anyone coming from an @c example.com
 * domain, but grants WRITE access only to the account @c admins:
 *
 * @code
 * <?php
 * $acl = new ACL();
 *
 * // Grant READ to example.com users.
 * $acl->addReferrer(ACL::READ, '*.example.com');
 *
 * // Allow only people in the account 'admins' access to
 * // write.
 * $acl->addAccount(ACL::WRITE, 'admins');
 *
 * // Allow example.com users to view the container
 * // listings:
 * $acl->allowListings();
 *
 * ?>
 * @endcode
 *
 *
 * Notes
 *
 * - The current implementation does not do any validation of rules.
 *   This will likely change in the future.
 * - There is discussion in OpenStack about providing a different or
 *   drastically improved ACL mechanism. This class would then be
 *   replaced by a new mechanism.
 *
 * For a detailed description of the rules for ACL creation,
 * see http://swift.openstack.org/misc.html#acls
 */
class ACL {

  /**
   * Read flag.
   *
   * This is for an ACL of the READ type.
   */
  const READ = 1;
  /**
   * Write flag.
   *
   * This is for an ACL of the WRITE type.
   */
  const WRITE = 2;
  /**
   * Flag for READ and WRITE.
   *
   * This is equivalent to <tt>ACL::READ | ACL::WRITE</tt>
   */
  const READ_WRITE = 3; // self::READ | self::WRITE;

  /**
   * Header string for a read flag.
   */
  const HEADER_READ = 'X-Container-Read';
  /**
   * Header string for a write flag.
   */
  const HEADER_WRITE = 'X-Container-Write';

  protected $rules = array();

  /**
   * Allow READ access to the public.
   *
   * This grants the following:
   *
   * - READ to any host, with container listings.
   *
   * @retval HPCloud::Storage::ObjectStorage::ACL
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   an ACL object with the appopriate permissions set.
   */
  public static function makePublic() {
    $acl = new ACL();
    $acl->addReferrer(self::READ, '*');
    $acl->allowListings();

    return $acl;
  }

  /**
   * Disallow all public access.
   *
   * Non-public is the same as private. Private, however, is a reserved
   * word in PHP.
   *
   * This does not grant any permissions. OpenStack interprets an object
   * with no permissions as a private object.
   *
   * @retval HPCloud::Storage::ObjectStorage::ACL
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   an ACL object with the appopriate permissions set.
   */
  public static function makeNonPublic() {
    // Default ACL is private.
    return new ACL();
  }

  /**
   * Alias of ACL::makeNonPublic().
   */
  public static function makePrivate() {
    return self::makeNonPublic();
  }

  /**
   * Given a list of headers, get the ACL info.
   *
   * This is a utility for processing headers and discovering any ACLs embedded
   * inside the headers.
   *
   * @param array $headers
   *   An associative array of headers.
   * @retval HPCloud::Storage::ObjectStorage::ACL
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   A new ACL.
   */
  public static function newFromHeaders($headers) {
    $acl = new ACL();

    // READ rules.
    $rules = array();
    if (!empty($headers[self::HEADER_READ])) {
      $read = $headers[self::HEADER_READ];
      $rules = explode(',', $read);
      foreach ($rules as $rule) {
        $ruleArray = self::parseRule(self::READ, $rule);
        if (!empty($ruleArray)) {
          $acl->rules[] = $ruleArray;
        }
      }
    }

    // WRITE rules.
    $rules = array();
    if (!empty($headers[self::HEADER_WRITE])) {
      $write = $headers[self::HEADER_WRITE];
      $rules = explode(',', $write);
      foreach ($rules as $rule) {
        $ruleArray = self::parseRule(self::WRITE, $rule);
        if (!empty($ruleArray)) {
          $acl->rules[] = $ruleArray;
        }
      }
    }

    //throw new \Exception(print_r($acl->rules(), TRUE));

    return $acl;
  }

  /**
   * Parse a rule.
   *
   * This attempts to parse an ACL rule. It is not particularly
   * fault-tolerant.
   *
   * @param int $perm
   *   The permission (ACL::READ, ACL::WRITE).
   * @param string $rule
   *   The string rule to parse.
   * @retval array
   * @return array
   *   The rule as an array.
   */
  public static function parseRule($perm, $rule) {
    // This regular expression generates the following:
    //
    // array(
    //   0 => ENTIRE RULE
    //   1 => WHOLE EXPRESSION, no whitespace
    //   2 => domain compontent
    //   3 => 'rlistings', set if .rincludes is the directive
    //   4 => account name
    //   5 => :username
    //   6 => username
    // );
    $exp = '/^\s*(.r:([a-zA-Z0-9\*\-\.]+)|\.(rlistings)|([a-zA-Z0-9]+)(\:([a-zA-Z0-9]+))?)\s*$/';

    $matches = array();
    preg_match($exp, $rule, $matches);

    $entry = array('mask' => $perm);
    if (!empty($matches[2])) {
      $entry['host'] = $matches[2];
    }
    elseif (!empty($matches[3])) {
      $entry['rlistings'] = TRUE;
    }
    elseif (!empty($matches[4])) {
      $entry['account'] = $matches[4];
      if (!empty($matches[6])) {
        $entry['user'] = $matches[6];
      }
    }

    return $entry;
  }

  /**
   * Create a new ACL.
   *
   * This creates an empty ACL with no permissions granted. When no
   * permissions are granted, the file is effectively private
   * (nonPublic()).
   *
   * Use add* methods to add permissions.
   */
  public function __construct() {}

  /**
   * Grant ACL access to an account.
   *
   * Optionally, a user may be given to further limit access.
   *
   * This is used to restrict access to a particular account and, if so
   * specified, a specific user on that account.
   *
   * If just an account is given, any user on that account will be
   * automatically granted access.
   *
   * If an account and a user is given, only that user of the account is
   * granted access.
   *
   * If $user is an array, every user in the array will be granted
   * access under the provided account. That is, for each user in the
   * array, an entry of the form \c account:user will be generated in the
   * final ACL.
   *
   * At this time there does not seem to be a way to grant global write
   * access to an object.
   *
   * @param int $perm
   *   ACL::READ, ACL::WRITE or ACL::READ_WRITE (which is the same as
   *   ACL::READ|ACL::WRITE).
   * @param string $account
   *   The name of the account.
   * @param mixed $user
   *   The name of the user, or optionally an indexed array of user
   *   names.
   *
   * @retval HPCloud::Storage::ObjectStorage::ACL
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   $this for current object so the method can be used in chaining.
   */
  public function addAccount($perm, $account, $user = NULL) {
    $rule = array('account' => $account);

    if (!empty($user)) {
      $rule['user'] = $user;
    }

    $this->addRule($perm, $rule);

    return $this;
  }

  /**
   * Allow (or deny) a hostname or host pattern.
   *
   * In current Swift implementations, only READ rules can have host
   * patterns. WRITE permissions cannot be granted to hostnames.
   *
   * Formats:
   * - Allow any host: '*'
   * - Allow exact host: 'www.example.com'
   * - Allow hosts in domain: '.example.com'
   * - Disallow exact host: '-www.example.com'
   * - Disallow hosts in domain: '-.example.com'
   *
   * Note that a simple minus sign ('-') is illegal, though it seems it
   * should be "disallow all hosts."
   *
   * @param string $perm
   *   The permission being granted. One of ACL:READ, ACL::WRITE, or ACL::READ_WRITE.
   * @param string $host
   *   A host specification string as described above.
   *
   * @retval HPCloud::Storage::ObjectStorage::ACL
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   $this for current object so the method can be used in chaining.
   */
  public function addReferrer($perm, $host = '*') {
    $this->addRule($perm, array('host' => $host));

    return $this;
  }

  /**
   * Add a rule to the appropriate stack of rules.
   *
   * @param int $perm
   *   One of the predefined permission constants.
   * @param array $rule
   *   A rule array.
   * 
   * @retval HPCloud::Storage::ObjectStorage::ACL
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   $this for current object so the method can be used in chaining.
   */
  protected function addRule($perm, $rule) {
    $rule['mask'] = $perm;

    $this->rules[] = $rule;

    return $this;
  }

  /**
   * Allow hosts with READ permissions to list a container's content.
   *
   * By default, granting READ permission on a container does not grant
   * permission to list the contents of a container. Setting the
   * ACL::allowListings() permission will allow matching hosts to also list
   * the contents of a container.
   *
   * In the current Swift implementation, there is no mechanism for
   * allowing some hosts to get listings, while denying others.
   *
   * @retval HPCloud::Storage::ObjectStorage::ACL
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   $this for current object so the method can be used in chaining.
   */
  public function allowListings() {

    $this->rules[] = array(
      'mask' => self::READ,
      'rlistings' => TRUE,
    );

    return $this;
  }

  /**
   * Get the rules array for this ACL.
   *
   * @retval array
   * @return array
   *   An array of associative arrays of rules.
   */
  public function rules() {
    return $this->rules;
  }

  /**
   * Generate HTTP headers for this ACL.
   *
   * If this is called on an empty object, an empty set of headers is
   * returned.
   */
  public function headers() {
    $headers = array();
    $readers = array();
    $writers = array();

    // Create the rule strings. We need two copies, one for READ and
    // one for WRITE.
    foreach ($this->rules as $rule) {
      // We generate read and write rules separately so that the
      // generation logic has a chance to respond to the differences
      // allowances for READ and WRITE ACLs.
      if (self::READ & $rule['mask']) {
        $ruleStr = $this->ruleToString(self::READ, $rule);
        if (!empty($ruleStr)) {
          $readers[] = $ruleStr;
        }
      }
      if (self::WRITE & $rule['mask']) {
        $ruleStr = $this->ruleToString(self::WRITE, $rule);
        if (!empty($ruleStr)) {
          $writers[] = $ruleStr;
        }
      }
    }

    // Create the HTTP headers.
    if (!empty($readers)) {
      $headers[self::HEADER_READ] = implode(',', $readers);
    }
    if (!empty($writers)) {
      $headers[self::HEADER_WRITE] = implode(',', $writers);
    }

    return $headers;
  }

  /**
   * Convert a rule to a string.
   *
   * @param int $perm
   *   The permission for which to generate the rule.
   * @param array $rule
   *   A rule array.
   */
  protected function ruleToString($perm, $rule) {

    // Some rules only apply to READ.
    if (self::READ & $perm) {

      // Host rule.
      if (!empty($rule['host'])) {
        return '.r:' . $rule['host'];
      }

      // Listing rule.
      if (!empty($rule['rlistings'])) {
        return '.rlistings';
      }
    }

    // READ and WRITE both allow account/user rules.
    if (!empty($rule['account'])) {

      // Just an account name.
      if (empty($rule['user'])) {
        return $rule['account'];
      }

      // Account + multiple users.
      elseif (is_array($rule['user'])) {
        $buffer = array();
        foreach ($rule['user'] as $user) {
          $buffer[] = $rule['account'] . ':' . $user;
        }
        return implode(',', $buffer);

      }

      // Account + one user.
      else {
        return $rule['account'] . ':' . $rule['user'];
      }
    }
  }

  /**
   * Check if the ACL marks this private.
   *
   * This returns TRUE only if this ACL does not grant any permissions
   * at all.
   *
   * @retval boolean
   * @return boolean
   *   TRUE if this is private (non-public), FALSE if
   *   any permissions are granted via this ACL.
   */
  public function isNonPublic() {
    return empty($this->rules);
  }

  /**
   * Alias of isNonPublic().
   */
  public function isPrivate() {
    return $this->isNonPublic();
  }

  /**
   * Check whether this object allows public reading.
   *
   * This will return TRUE the ACL allows (a) any host to access
   * the item, and (b) it allows container listings.
   *
   * This checks whether the object allows public reading,
   * not whether it is ONLY allowing public reads.
   *
   * See ACL::makePublic().
   */
  public function isPublic() {
    $allowsAllHosts = FALSE;
    $allowsRListings = FALSE;
    foreach ($this->rules as $rule) {
      if (self::READ & $rule['mask']) {
        if (!empty($rule['rlistings'])) {
          $allowsRListings = TRUE;
        }
        elseif(!empty($rule['host']) && trim($rule['host']) == '*') {
          $allowsAllHosts = TRUE;
        }
      }
    }
    return $allowsAllHosts && $allowsRListings;
  }

  /**
   * Implements the magic __toString() PHP function.
   *
   * This allows you to <tt>print $acl</tt> and get back
   * a pretty string.
   *
   * @retval string
   * @return string
   *   The ACL represented as a string.
   */
  public function __toString() {
    $headers = $this->headers();

    $buffer = array();
    foreach ($headers as $k => $v) {
      $buffer[] = $k . ': ' . $v;
    }

    return implode("\t", $buffer);
  }

}
