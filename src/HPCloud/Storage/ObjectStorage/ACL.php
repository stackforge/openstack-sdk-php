<?php
/**
 * @file
 *
 * Contains the class for manipulating ObjectStorage ACL strings.
 */

namespace HPCloud\Storage\ObjectStorage;

/**
 * Access control list for object storage.
 *
 * EXPERIMENTAL: This is bassed on a feature of Swift that is likely to
 * change.
 *
 * Swift access control rules are broken into two permissions: READ and
 * WRITE. Read permissions grant the user the ability to access the file
 * (using verbs like GET and HEAD), while WRITE permissions allow any
 * modification operation. WRITE does not imply READ.
 *
 * In the current implementation of Swift, access can be assigned based
 * on two different factors:
 *
 * - Accounts: Access can be granted to specific accounts, and within
 *   those accounts, can be further specified to specific users. See the
 *   addAccount() method for details on this.
 * - Referrers: Access can be granted based on host names or host name
 *   patterns. For example, only subdomains of `*.example.com` may be
 *   granted READ access to a particular object.
 *
 * ACLs are transmitted within the HTTP headers for an object or
 * container. Two headers are used: X-Container-Read for READ rules, and
 * X-Container-Write for WRITE rules. Each header may have a chain of
 * rules.
 *
 * Examples
 *
 * For most casual cases, only the static constructor functions are
 * used. For example, an ACL that does not grant any public access can
 * be created with a single call:
 *
 * @code
 * <?php
 * ACL::nonPublic();
 * ?>
 * @endcode
 *
 * Public read access is granted like this:
 *
 * @code
 * <?php
 * ACL::publicRead();
 * ?>
 * @endcode
 *
 * Sometimes you will need more sophisticated access control rules. The
 * following grants READ access to anyone coming from an `example.com`
 * domain, but grants WRITE access only to the account `admins`:
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

  // This is not yet implemented in Swift, and may not be.
  /* *
   * Declare this object public.
   *
   * This allows anybody to read or write the object. This, of course,
   * has security implications.
   *
   * This grants the following:
   *
   * - READ to any host, with container listings.
   * - WRITE to any account.
   *
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   an ACL object with the appopriate permissions set.
   *//*
  public static function publicReadWrite() {
    $acl = new ACL();
    $acl->addAccount(self::WRITE, '*');
    $acl->addReferrer(self::READ, '*');
    $acl->allowListings();

    return $acl;
  }
    */

  /**
   * Allow READ access to the public.
   *
   * This grants the following:
   *
   * - READ to any host, with container listings.
   *
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   an ACL object with the appopriate permissions set.
   */
  public static function publicRead() {
    $acl = new ACL();
    $acl->addReferrer(self::READ, '*');
    $acl->allowListings();

    return $acl;
  }

  // This is not implemented in Swift, and may not be.
  /* *
   * Allow WRITE access to the public.
   *
   * This grants the following:
   *
   * - Write access to any account.
   *
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   an ACL object with the appopriate permissions set.
   */
  /*
  public static function publicWrite() {
    $acl = new ACL();
    $acl->addAccount(self::WRITE, '*');

    return $acl;
  }
  */

  /**
   * Disallow all public access.
   *
   * Non-public is the same as private. Private, however, is a reserved
   * word in PHP.
   *
   * This does not grant any permissions. OpenStack interprets an object
   * with no permissions as a private object.
   *
   * @return \HPCloud\Storage\ObjectStorage\ACL
   *   an ACL object with the appopriate permissions set.
   */
  public static function nonPublic() {
    // Default ACL is private.
    return new ACL();
  }

  public static function newFromHeaders($headers) {

  }

  public static function parseRule($rule) {

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
   * Allow an account access.
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
   * array, an entry of the form 'account:user' will be generated in the
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
   */
  public function addAccount($perm, $account, $user = NULL) {
    $rule = array('account' => $account);

    if (!empty($user)) {
      $rule['user'] = $user;
    }

    $this->addRule($perm, $rule);
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
   * @param string $host
   *   A host specification string as described above.
   */
  public function addReferrer($perm, $host = '*') {
    $this->addRule($perm, array('host' => $host));
  }

  /**
   * Add a rule to the appropriate stack of rules.
   *
   * @param int $perm
   *   One of the predefined permission constants.
   * @param array $rule
   *   A rule array.
   */
  protected function addRule($perm, $rule) {
    $rule['mask'] = $perm;

    $this->rules[] = $rule;
  }

  /**
   * Allow hosts with READ permissions to list a container's content.
   *
   * By default, granting READ permission on a container does not grant
   * permission to list the contents of a container. Setting the
   * allowListings() permission will allow matching hosts to also list
   * the contents of a container.
   *
   * In the current Swift implementation, there is no mechanism for
   * allowing some hosts to get listings, while denying others.
   */
  public function allowListings() {

    $this->rules[] = array(
      'mask' => self::READ,
      'rlistings' => TRUE,
    );
  }

  /**
   * Get the rules array for this ACL.
   *
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

  public function __toString() {
    $headers = $this->headers();

    $buffer = array();
    foreach ($headers as $k => $v) {
      $buffer[] = $k . ': ' . $v;
    }

    return implode("\t", $buffer);
  }

}
