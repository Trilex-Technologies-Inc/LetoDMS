<?php
/**
 * Implementation of a password history management.
 *
 * Whenever a password is changed the old one is stored in a
 * database table. Those passwords can than be used to enforce
 * new passwords and not reusing old ones.
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * 
 * Implementation of a password history management.
 *
 * This class provides some very basic methods to manage old passwords
 * once used by users.
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_PasswordHistoryManager {
	/**
	 * @var object $db reference to database object. This must be an instance
	 *      of {@link LetoDMS_Core_DatabaseAccess}.
	 * @access protected
	 */
	protected $db;

	/**
	 * Create a new instance of the password history manager
	 *
	 * @param object $db object to access the underlying database
	 * @return object instance of LetoDMS_PasswordHistory
	 */
	function __construct($db) { /* {{{ */
		$this->db = $db;
	} /* }}} */

	function add($user, $pwd) { /* {{{ */
		$queryStr = "INSERT INTO tblUserPasswordHistory (userID, pwd, `date`) ".
		  "VALUES (".$this->db->qstr($user->getId()).", ".$this->db->qstr($pwd).", CURRENT_TIMESTAMP)";
		if (!$this->db->getResult($queryStr)) {
			return false;
		}
	} /* }}} */

	function search($user, $pwd) { /* {{{ */
		$queryStr = "SELECT * FROM tblUserPasswordHistory WHERE userID = ".$this->db->qstr($user->getId())." AND pwd=".$this->db->qstr($pwd);

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) == 0)
			return array();
		return $resArr[0];
	} /* }}} */
}
?>
