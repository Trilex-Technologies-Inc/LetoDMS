<?php
/**
 * Implementation of a document in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @license    GPL2
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Matteo Lucarelli, 2010 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * The different states a document can be in
 */
/*
 * Document is in review state. A document is in review state when
 * it needs to be reviewed by a user or group.
 */
define("S_DRAFT_REV", 0);

/*
 * Document is in approval state. A document is in approval state when
 * it needs to be approved by a user or group.
 */
define("S_DRAFT_APP", 1);

/*
 * Document is released. A document is in release state either when
 * it needs no review or approval after uploaded or has been reviewed
 * and/or approved..
 */
define("S_RELEASED",  2);

/*
 * Document is in workflow. A document is in workflow if a workflow
 * has been started and has not reached a final state.
 */
define("S_IN_WORKFLOW",  3);

/*
 * Document was rejected. A document is in rejected state when
 * the review failed or approval was not given.
 */
define("S_REJECTED", -1);

/*
 * Document is obsolete. A document can be obsoleted once it was
 * released.
 */
define("S_OBSOLETE", -2);

/*
 * Document is expired. A document expires when the expiration date
 * is reached
 */
define("S_EXPIRED",  -3);

/**
 * Class to represent a document in the document management system
 *
 * A document in LetoDMS is similar to files in a regular file system.
 * Documents may have any number of content elements
 * ({@link LetoDMS_Core_DocumentContent}). These content elements are often
 * called versions ordered in a timely manner. The most recent content element
 * is the current version.
 *
 * Documents can be linked to other documents and can have attached files.
 * The document content can be anything that can be stored in a regular
 * file.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Matteo Lucarelli, 2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Document extends LetoDMS_Core_Object { /* {{{ */
	/**
	 * @var string name of document
	 */
	protected $_name;

	/**
	 * @var string comment of document
	 */
	protected $_comment;

	/**
	 * @var integer unix timestamp of creation date
	 */
	protected $_date;

	/**
	 * @var integer id of user who is the owner
	 */
	protected $_ownerID;

	/**
	 * @var integer id of folder this document belongs to
	 */
	protected $_folderID;

	/**
	 * @var integer timestamp of expiration date
	 */
	protected $_expires;

	/**
	 * @var boolean true if access is inherited, otherwise false
	 */
	protected $_inheritAccess;

	/**
	 * @var integer default access if access rights are not inherited
	 */
	protected $_defaultAccess;

	/**
	 * @var array list of notifications for users and groups
	 */
	protected $_readAccessList;

	/**
	 * @var array list of notifications for users and groups
	 */
	public $_notifyList;

	/**
	 * @var boolean true if document is locked, otherwise false
	 */
	protected $_locked;

	/**
	 * @var string list of keywords
	 */
	protected $_keywords;

	/**
	 * @var array list of categories
	 */
	protected $_categories;

	/**
	 * @var integer position of document within the parent folder
	 */
	protected $_sequence;

	function LetoDMS_Core_Document($id, $name, $comment, $date, $expires, $ownerID, $folderID, $inheritAccess, $defaultAccess, $locked, $keywords, $sequence) { /* {{{ */
		parent::__construct($id);
		$this->_name = $name;
		$this->_comment = $comment;
		$this->_date = $date;
		$this->_expires = $expires;
		$this->_ownerID = $ownerID;
		$this->_folderID = $folderID;
		$this->_inheritAccess = $inheritAccess;
		$this->_defaultAccess = $defaultAccess;
		$this->_locked = ($locked == null || $locked == '' ? -1 : $locked);
		$this->_keywords = $keywords;
		$this->_sequence = $sequence;
		$this->_categories = array();
		$this->_notifyList = array();
	} /* }}} */

	/*
	 * Return the directory of the document in the file system relativ
	 * to the contentDir
	 *
	 * @return string directory of document
	 */
	function getDir() { /* {{{ */
		if($this->_dms->maxDirID) {
			$dirid = (int) (($this->_id-1) / $this->_dms->maxDirID) + 1; 
			return $dirid."/".$this->_id."/";
		} else {
			return $this->_id."/";
		}
	} /* }}} */

	/*
	 * Return the name of the document
	 *
	 * @return string name of document
	 */
	function getName() { return $this->_name; }

	/*
	 * Set the name of the document
	 *
	 * @param $newName string new name of document
	 */
	function setName($newName) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments SET name = ".$db->qstr($newName)." WHERE id = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	/*
	 * Return the comment of the document
	 *
	 * @return string comment of document
	 */
	function getComment() { return $this->_comment; }

	/*
	 * Set the comment of the document
	 *
	 * @param $newComment string new comment of document
	 */
	function setComment($newComment) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments SET comment = ".$db->qstr($newComment)." WHERE id = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_comment = $newComment;
		return true;
	} /* }}} */

	function getKeywords() { return $this->_keywords; }

	function setKeywords($newKeywords) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments SET keywords = ".$db->qstr($newKeywords)." WHERE id = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_keywords = $newKeywords;
		return true;
	} /* }}} */

	/**
	 * Retrieve a list of all categories this document belongs to
	 *
	 * @return array list of category objects
	 */
	function getCategories() { /* {{{ */
		$db = $this->_dms->getDB();

		if(!$this->_categories) {
			$queryStr = "SELECT * FROM tblCategory where id in (select categoryID from tblDocumentCategory where documentID = ".$this->_id.")";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;

			foreach ($resArr as $row) {
				$cat = new LetoDMS_Core_DocumentCategory($row['id'], $row['name']);
				$cat->setDMS($this->_dms);
				$this->_categories[] = $cat;
			}
		}
		return $this->_categories;
	} /* }}} */

	/**
	 * Set a list of categories for the document
	 * This function will delete currently assigned categories and sets new
	 * categories.
	 *
	 * @param array $newCategories list of category objects
	 */
	function setCategories($newCategories) { /* {{{ */
		$db = $this->_dms->getDB();

		$db->startTransaction();
		$queryStr = "DELETE from tblDocumentCategory WHERE documentID = ". $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		foreach($newCategories as $cat) {
			$queryStr = "INSERT INTO tblDocumentCategory (categoryID, documentID) VALUES (". $cat->getId() .", ". $this->_id .")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		$db->commitTransaction();
		$this->_categories = $newCategories;
		return true;
	} /* }}} */

	/**
	 * Return creation date of the document
	 *
	 * @return integer unix timestamp of creation date
	 */
	function getDate() { /* {{{ */
		return $this->_date;
	} /* }}} */

	/**
	 * Return the parent folder of the document
	 *
	 * @return object parent folder
	 */
	function getFolder() { /* {{{ */
		if (!isset($this->_folder))
			$this->_folder = $this->_dms->getFolder($this->_folderID);
		return $this->_folder;
	} /* }}} */

	/**
	 * Set folder of a document
	 *
	 * This function basically moves a document from a folder to another
	 * folder.
	 *
	 * @param object $newFolder
	 * @return boolean false in case of an error, otherwise true
	 */
	function setFolder($newFolder) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments SET folder = " . $newFolder->getID() . " WHERE id = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;
		$this->_folderID = $newFolder->getID();
		$this->_folder = $newFolder;

		// Make sure that the folder search path is also updated.
		$path = $newFolder->getPath();
		$flist = "";
		foreach ($path as $f) {
			$flist .= ":".$f->getID();
		}
		if (strlen($flist)>1) {
			$flist .= ":";
		}
		$queryStr = "UPDATE tblDocuments SET folderList = '" . $flist . "' WHERE id = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		return true;
	} /* }}} */

	/**
	 * Return owner of document
	 *
	 * @return object owner of document as an instance of {@link LetoDMS_Core_User}
	 */
	function getOwner() { /* {{{ */
		if (!isset($this->_owner))
			$this->_owner = $this->_dms->getUser($this->_ownerID);
		return $this->_owner;
	} /* }}} */

	/**
	 * Set owner of a document
	 *
	 * @param object $newOwner new owner
	 * @return boolean true if successful otherwise false
	 */
	function setOwner($newOwner) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments set owner = " . $newOwner->getID() . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_ownerID = $newOwner->getID();
		$this->_owner = $newOwner;
		return true;
	} /* }}} */

	function getDefaultAccess() { /* {{{ */
		if ($this->inheritsAccess()) {
			$res = $this->getFolder();
			if (!$res) return false;
			return $this->_folder->getDefaultAccess();
		}
		return $this->_defaultAccess;
	} /* }}} */

	function setDefaultAccess($mode) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments set defaultAccess = " . (int) $mode . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_defaultAccess = $mode;

		// If any of the notification subscribers no longer have read access,
		// remove their subscription.
		foreach ($this->_notifyList["users"] as $u) {
			if ($this->getAccessMode($u) < M_READ) {
				$this->removeNotify($u->getID(), true);
			}
		}
		foreach ($this->_notifyList["groups"] as $g) {
			if ($this->getGroupAccessMode($g) < M_READ) {
				$this->removeNotify($g->getID(), false);
			}
		}

		return true;
	} /* }}} */

	function inheritsAccess() { return $this->_inheritAccess; }

	/**
	 * Set inherited access mode
	 * Setting inherited access mode will set or unset the internal flag which
	 * controls if the access mode is inherited from the parent folder or not.
	 * It will not modify the
	 * access control list for the current object. It will remove all
	 * notifications of users which do not even have read access anymore
	 * after setting or unsetting inherited access.
	 *
	 * @param boolean $inheritAccess set to true for setting and false for
	 *        unsetting inherited access mode
	 * @return boolean true if operation was successful otherwise false
	 */
	function setInheritAccess($inheritAccess) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments SET inheritAccess = " . ($inheritAccess ? "1" : "0") . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_inheritAccess = ($inheritAccess ? "1" : "0");

		// If any of the notification subscribers no longer have read access,
		// remove their subscription.
		if(isset($this->_notifyList["users"])) {
			foreach ($this->_notifyList["users"] as $u) {
				if ($this->getAccessMode($u) < M_READ) {
					$this->removeNotify($u->getID(), true);
				}
			}
		}
		if(isset($this->_notifyList["groups"])) {
			foreach ($this->_notifyList["groups"] as $g) {
				if ($this->getGroupAccessMode($g) < M_READ) {
					$this->removeNotify($g->getID(), false);
				}
			}
		}

		return true;
	} /* }}} */

	/**
	 * Check if document expires
	 *
	 * @return boolean true if document has expiration date set, otherwise false
	 */
	function expires() { /* {{{ */
		if (intval($this->_expires) == 0)
			return false;
		else
			return true;
	} /* }}} */

	/**
	 * Get expiration time of document
	 *
	 * @return integer/boolean expiration date as unix timestamp or false
	 */
	function getExpires() { /* {{{ */
		if (intval($this->_expires) == 0)
			return false;
		else
			return $this->_expires;
	} /* }}} */

	/**
	 * Set expiration date as unix timestamp
	 *
	 * @param integer unix timestamp of expiration date
	 */
	function setExpires($expires) { /* {{{ */
		$db = $this->_dms->getDB();

		$expires = (!$expires) ? 0 : $expires;

		if ($expires == $this->_expires) {
			// No change is necessary.
			return true;
		}

		$queryStr = "UPDATE tblDocuments SET expires = " . (int) $expires . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_expires = $expires;
		return true;
	} /* }}} */

	/**
	 * Check if the document has expired
	 *
	 * @return boolean true if document has expired otherwise false
	 */
	function hasExpired() { /* {{{ */
		if (intval($this->_expires) == 0) return false;
		if (time()>$this->_expires+24*60*60) return true;
		return false;
	} /* }}} */

	/**
	 * Check if the document has expired and set the status accordingly
	 * It will also recalculate the status if the current status is
	 * set to S_EXPIRED but the document isn't actually expired.
	 * The method will update the document status log database table
	 * if needed.
	 * FIXME: Why does it not set a document to S_EXPIRED if it is
	 * currently in state S_RELEASED
	 * FIXME: some left over reviewers/approvers are in the way if
	 * no workflow is set an traditional workflow mode is on. In that
	 * case the status is set to S_DRAFT_REV or S_DRAFT_APP
	 *
	 * @return boolean true if status has changed
	 */
	function verifyLastestContentExpriry(){ /* {{{ */
		$lc=$this->getLatestContent();
		if($lc) {
			$st=$lc->getStatus();

			if (($st["status"]==S_DRAFT_REV || $st["status"]==S_DRAFT_APP || $st["status"]==S_IN_WORKFLOW) && $this->hasExpired()){
				return $lc->setStatus(S_EXPIRED,"", $this->getOwner());
			}
			elseif ($st["status"]==S_EXPIRED && !$this->hasExpired() ){
				$lc->verifyStatus(true, $this->getOwner());
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if document is locked
	 *
	 * @return boolean true if locked otherwise false
	 */
	function isLocked() { return $this->_locked != -1; }

	/**
	 * Lock or unlock document
	 *
	 * @param $falseOrUser user object for locking or false for unlocking
	 * @return boolean true if operation was successful otherwise false
	 */
	function setLocked($falseOrUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$lockUserID = -1;
		if (is_bool($falseOrUser) && !$falseOrUser) {
			$queryStr = "DELETE FROM tblDocumentLocks WHERE document = ".$this->_id;
		}
		else if (is_object($falseOrUser)) {
			$queryStr = "INSERT INTO tblDocumentLocks (document, userID) VALUES (".$this->_id.", ".$falseOrUser->getID().")";
			$lockUserID = $falseOrUser->getID();
		}
		else {
			return false;
		}
		if (!$db->getResult($queryStr)) {
			return false;
		}
		unset($this->_lockingUser);
		$this->_locked = $lockUserID;
		return true;
	} /* }}} */

	/**
	 * Get the user currently locking the document
	 *
	 * @return object user have a lock
	 */
	function getLockingUser() { /* {{{ */
		if (!$this->isLocked())
			return false;

		if (!isset($this->_lockingUser))
			$this->_lockingUser = $this->_dms->getUser($this->_locked);
		return $this->_lockingUser;
	} /* }}} */

	function getSequence() { return $this->_sequence; }

	function setSequence($seq) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblDocuments SET sequence = " . $seq . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_sequence = $seq;
		return true;
	} /* }}} */

	/**
	 * Delete all entries for this document from the access control list
	 *
	 * @return boolean true if operation was successful otherwise false
	 */
	function clearAccessList() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "DELETE FROM tblACLs WHERE targetType = " . T_DOCUMENT . " AND target = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);
		return true;
	} /* }}} */

	/**
	 * Returns a list of access privileges
	 *
	 * If the document inherits the access privileges from the parent folder
	 * those will be returned.
	 * $mode and $op can be set to restrict the list of returned access
	 * privileges. If $mode is set to M_ANY no restriction will apply
	 * regardless of the value of $op. The returned array contains a list
	 * of {@link LetoDMS_Core_UserAccess} and
	 * {@link LetoDMS_Core_GroupAccess} objects. Even if the document
	 * has no access list the returned array contains the two elements
	 * 'users' and 'groups' which are than empty. The methode returns false
	 * if the function fails.
	 * 
	 * @param integer $mode access mode (defaults to M_ANY)
	 * @param integer $op operation (defaults to O_EQ)
	 * @return array multi dimensional array
	 */
	function getAccessList($mode = M_ANY, $op = O_EQ) { /* {{{ */
		$db = $this->_dms->getDB();

		if ($this->inheritsAccess()) {
			$res = $this->getFolder();
			if (!$res) return false;
			return $this->_folder->getAccessList($mode, $op);
		}

		if (!isset($this->_accessList[$mode])) {
			if ($op!=O_GTEQ && $op!=O_LTEQ && $op!=O_EQ) {
				return false;
			}
			$modeStr = "";
			if ($mode!=M_ANY) {
				$modeStr = " AND mode".$op.(int)$mode;
			}
			$queryStr = "SELECT * FROM tblACLs WHERE targetType = ".T_DOCUMENT.
				" AND target = " . $this->_id .	$modeStr . " ORDER BY targetType";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;

			$this->_accessList[$mode] = array("groups" => array(), "users" => array());
			foreach ($resArr as $row) {
				if ($row["userID"] != -1)
					array_push($this->_accessList[$mode]["users"], new LetoDMS_Core_UserAccess($this->_dms->getUser($row["userID"]), $row["mode"]));
				else //if ($row["groupID"] != -1)
					array_push($this->_accessList[$mode]["groups"], new LetoDMS_Core_GroupAccess($this->_dms->getGroup($row["groupID"]), $row["mode"]));
			}
		}

		return $this->_accessList[$mode];
	} /* }}} */

	/**
	 * Add access right to folder
	 * This function may change in the future. Instead of passing the a flag
	 * and a user/group id a user or group object will be expected.
	 *
	 * @param integer $mode access mode
	 * @param integer $userOrGroupID id of user or group
	 * @param integer $isUser set to 1 if $userOrGroupID is the id of a
	 *        user
	 */
	function addAccess($mode, $userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "userID" : "groupID";

		$queryStr = "INSERT INTO tblACLs (target, targetType, ".$userOrGroup.", mode) VALUES
					(".$this->_id.", ".T_DOCUMENT.", " . (int) $userOrGroupID . ", " .(int) $mode. ")";
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		// Update the notify list, if necessary.
		if ($mode == M_NONE) {
			$this->removeNotify($userOrGroupID, $isUser);
		}

		return true;
	} /* }}} */

	/**
	 * Change access right of document
	 * This function may change in the future. Instead of passing the a flag
	 * and a user/group id a user or group object will be expected.
	 *
	 * @param integer $newMode access mode
	 * @param integer $userOrGroupID id of user or group
	 * @param integer $isUser set to 1 if $userOrGroupID is the id of a
	 *        user
	 */
	function changeAccess($newMode, $userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "userID" : "groupID";

		$queryStr = "UPDATE tblACLs SET mode = " . (int) $newMode . " WHERE targetType = ".T_DOCUMENT." AND target = " . $this->_id . " AND " . $userOrGroup . " = " . (int) $userOrGroupID;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		// Update the notify list, if necessary.
		if ($newMode == M_NONE) {
			$this->removeNotify($userOrGroupID, $isUser);
		}

		return true;
	} /* }}} */

	/**
	 * Remove access rights for a user or group
	 *
	 * @param integer $userOrGroupID ID of user or group
	 * @param boolean $isUser true if $userOrGroupID is a user id, false if it
	 *        is a group id.
	 * @return boolean true on success, otherwise false
	 */
	function removeAccess($userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "userID" : "groupID";

		$queryStr = "DELETE FROM tblACLs WHERE targetType = ".T_DOCUMENT." AND target = ".$this->_id." AND ".$userOrGroup." = " . (int) $userOrGroupID;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		// Update the notify list, if the user looses access rights.
		$mode = ($isUser ? $this->getAccessMode($this->_dms->getUser($userOrGroupID)) : $this->getGroupAccessMode($this->_dms->getGroup($userOrGroupID)));
		if ($mode == M_NONE) {
			$this->removeNotify($userOrGroupID, $isUser);
		}

		return true;
	} /* }}} */

	/**
	 * Returns the greatest access privilege for a given user
	 *
	 * This function returns the access mode for a given user. An administrator
	 * and the owner of the folder has unrestricted access. A guest user has
	 * read only access or no access if access rights are further limited
	 * by access control lists. All other users have access rights according
	 * to the access control lists or the default access. This function will
	 * recursive check for access rights of parent folders if access rights
	 * are inherited.
	 *
	 * The function searches the access control list for entries of
	 * user $user. If it finds more than one entry it will return the
	 * one allowing the greatest privileges, but user rights will always
	 * precede group rights. If there is no entry in the
	 * access control list, it will return the default access mode.
	 * The function takes inherited access rights into account.
	 * For a list of possible access rights see @file inc.AccessUtils.php
	 *
	 * @param $user object instance of class LetoDMS_Core_User
	 * @return integer access mode
	 */
	function getAccessMode($user) { /* {{{ */
		if(!$user)
			return M_NONE;

		/* Administrators have unrestricted access */
		if ($user->isAdmin()) return M_ALL;

		/* The owner of the document has unrestricted access */
		if ($user->getID() == $this->_ownerID) return M_ALL;

		/* The guest users do not have more than read access */
		if ($user->isGuest()) {
			$mode = $this->getDefaultAccess();
			if ($mode >= M_READ) return M_READ;
			else return M_NONE;
		}

		/* Check ACLs */
		$accessList = $this->getAccessList();
		if (!$accessList) return false;

		foreach ($accessList["users"] as $userAccess) {
			if ($userAccess->getUserID() == $user->getID()) {
				return $userAccess->getMode();
			}
		}
		/* Get the highest right defined by a group */
		$result = 0;
		foreach ($accessList["groups"] as $groupAccess) {
			if ($user->isMemberOfGroup($groupAccess->getGroup())) {
				if ($groupAccess->getMode() > $result)
					$result = $groupAccess->getMode();
//					return $groupAccess->getMode();
			}
		}
		if($result)
			return $result;
		$result = $this->getDefaultAccess();
		return $result;
	} /* }}} */

	/**
	 * Returns the greatest access privilege for a given group
	 *
	 * This function searches the access control list for entries of
	 * group $group. If it finds more than one entry it will return the
	 * one allowing the greatest privileges. If there is no entry in the
	 * access control list, it will return the default access mode.
	 * The function takes inherited access rights into account.
	 * For a list of possible access rights see @file inc.AccessUtils.php
	 *
	 * @param $group object instance of class LetoDMS_Core_Group
	 * @return integer access mode
	 */
	function getGroupAccessMode($group) { /* {{{ */
		$highestPrivileged = M_NONE;

		//ACLs durchforsten
		$foundInACL = false;
		$accessList = $this->getAccessList();
		if (!$accessList)
			return false;

		foreach ($accessList["groups"] as $groupAccess) {
			if ($groupAccess->getGroupID() == $group->getID()) {
				$foundInACL = true;
				if ($groupAccess->getMode() > $highestPrivileged)
					$highestPrivileged = $groupAccess->getMode();
				if ($highestPrivileged == M_ALL) // max access right -> skip the rest
					return $highestPrivileged;
			}
		}

		if ($foundInACL)
			return $highestPrivileged;

		//Standard-Berechtigung verwenden
		return $this->getDefaultAccess();
	} /* }}} */

	/**
	 * Returns a list of all notifications
	 *
	 * The returned list has two elements called 'users' and 'groups'. Each one
	 * is an array itself countaining objects of class LetoDMS_Core_User and
	 * LetoDMS_Core_Group.
	 *
	 * @param integer $type type of notification (not yet used)
	 * @return array list of notifications
	 */
	function getNotifyList($type=0) { /* {{{ */
		if (empty($this->_notifyList)) {
			$db = $this->_dms->getDB();

			$queryStr ="SELECT * FROM tblNotify WHERE targetType = " . T_DOCUMENT . " AND target = " . $this->_id;
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && $resArr == false)
				return false;

			$this->_notifyList = array("groups" => array(), "users" => array());
			foreach ($resArr as $row)
			{
				if ($row["userID"] != -1)
					array_push($this->_notifyList["users"], $this->_dms->getUser($row["userID"]) );
				else //if ($row["groupID"] != -1)
					array_push($this->_notifyList["groups"], $this->_dms->getGroup($row["groupID"]) );
			}
		}
		return $this->_notifyList;
	} /* }}} */

	/**
	 * Add a user/group to the notification list
	 * This function does not check if the currently logged in user
	 * is allowed to add a notification. This must be checked by the calling
	 * application.
	 *
	 * @param $userOrGroupID integer id of user or group to add
	 * @param $isUser integer 1 if $userOrGroupID is a user,
	 *                0 if $userOrGroupID is a group
	 * @return integer  0: Update successful.
	 *                 -1: Invalid User/Group ID.
	 *                 -2: Target User / Group does not have read access.
	 *                 -3: User is already subscribed.
	 *                 -4: Database / internal error.
	 */
	function addNotify($userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser ? "userID" : "groupID");

		/* Verify that user / group exists. */
		$obj = ($isUser ? $this->_dms->getUser($userOrGroupID) : $this->_dms->getGroup($userOrGroupID));
		if (!is_object($obj)) {
			return -1;
		}

		/* Verify that the requesting user has permission to add the target to
		 * the notification system.
		 */
		/*
		 * The calling application should enforce the policy on who is allowed
		 * to add someone to the notification system. If is shall remain here
		 * the currently logged in user should be passed to this function
		 *
		GLOBAL $user;
		if ($user->isGuest()) {
			return -2;
		}
		if (!$user->isAdmin()) {
			if ($isUser) {
				if ($user->getID() != $obj->getID()) {
					return -2;
				}
			}
			else {
				if (!$obj->isMember($user)) {
					return -2;
				}
			}
		}
		 */

		/* Verify that target user / group has read access to the document. */
		if ($isUser) {
			// Users are straightforward to check.
			if ($this->getAccessMode($obj) < M_READ) {
				return -2;
			}
		}
		else {
			// Groups are a little more complex.
			if ($this->getDefaultAccess() >= M_READ) {
				// If the default access is at least READ-ONLY, then just make sure
				// that the current group has not been explicitly excluded.
				$acl = $this->getAccessList(M_NONE, O_EQ);
				$found = false;
				foreach ($acl["groups"] as $group) {
					if ($group->getGroupID() == $userOrGroupID) {
						$found = true;
						break;
					}
				}
				if ($found) {
					return -2;
				}
			}
			else {
				// The default access is restricted. Make sure that the group has
				// been explicitly allocated access to the document.
				$acl = $this->getAccessList(M_READ, O_GTEQ);
				if (is_bool($acl)) {
					return -4;
				}
				$found = false;
				foreach ($acl["groups"] as $group) {
					if ($group->getGroupID() == $userOrGroupID) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					return -2;
				}
			}
		}
		/* Check to see if user/group is already on the list. */
		$queryStr = "SELECT * FROM `tblNotify` WHERE `tblNotify`.`target` = '".$this->_id."' ".
			"AND `tblNotify`.`targetType` = '".T_DOCUMENT."' ".
			"AND `tblNotify`.`".$userOrGroup."` = '".(int) $userOrGroupID."'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr)) {
			return -4;
		}
		if (count($resArr)>0) {
			return -3;
		}

		$queryStr = "INSERT INTO tblNotify (target, targetType, " . $userOrGroup . ") VALUES (" . $this->_id . ", " . T_DOCUMENT . ", " . (int) $userOrGroupID . ")";
		if (!$db->getResult($queryStr))
			return -4;

		unset($this->_notifyList);
		return 0;
	} /* }}} */

	/**
	 * Remove a user or group from the notification list
	 * This function does not check if the currently logged in user
	 * is allowed to remove a notification. This must be checked by the calling
	 * application.
	 *
	 * @param $userOrGroupID id of user or group
	 * @param $isUser boolean true if a user is passed in $userOrGroupID, false
	 *        if a group is passed in $userOrGroupID
	 * @param $type type of notification (0 will delete all) Not used yet!
	 * @return integer 0 if operation was succesful
	 *                 -1 if the userid/groupid is invalid
	 *                 -3 if the user/group is already subscribed
	 *                 -4 in case of an internal database error
	 */
	function removeNotify($userOrGroupID, $isUser, $type=0) { /* {{{ */
		$db = $this->_dms->getDB();

		/* Verify that user / group exists. */
		$obj = ($isUser ? $this->_dms->getUser($userOrGroupID) : $this->_dms->getGroup($userOrGroupID));
		if (!is_object($obj)) {
			return -1;
		}

		$userOrGroup = ($isUser) ? "userID" : "groupID";

		/* Verify that the requesting user has permission to add the target to
		 * the notification system.
		 */
		/*
		 * The calling application should enforce the policy on who is allowed
		 * to add someone to the notification system. If is shall remain here
		 * the currently logged in user should be passed to this function
		 *
		GLOBAL $user;
		if ($user->isGuest()) {
			return -2;
		}
		if (!$user->isAdmin()) {
			if ($isUser) {
				if ($user->getID() != $obj->getID()) {
					return -2;
				}
			}
			else {
				if (!$obj->isMember($user)) {
					return -2;
				}
			}
		}
		 */

		/* Check to see if the target is in the database. */
		$queryStr = "SELECT * FROM `tblNotify` WHERE `tblNotify`.`target` = '".$this->_id."' ".
			"AND `tblNotify`.`targetType` = '".T_DOCUMENT."' ".
			"AND `tblNotify`.`".$userOrGroup."` = '".(int) $userOrGroupID."'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr)) {
			return -4;
		}
		if (count($resArr)==0) {
			return -3;
		}

		$queryStr = "DELETE FROM tblNotify WHERE target = " . $this->_id . " AND targetType = " . T_DOCUMENT . " AND " . $userOrGroup . " = " . (int) $userOrGroupID;
		/* If type is given then delete only those notifications */
		if($type)
			$queryStr .= " AND `type` = ".(int) $type;
		if (!$db->getResult($queryStr))
			return -4;

		unset($this->_notifyList);
		return 0;
	} /* }}} */

	/**
	 * Add content to a document
	 *
	 * Each document may have any number of content elements attached to it.
	 * Each content element has a version number. Newer versions (greater
	 * version number) replace older versions.
	 *
	 * @param string $comment comment
	 * @param object $user user who shall be the owner of this content
	 * @param string $tmpFile file containing the actuall content
	 * @param string $orgFileName original file name
	 * @param string $mimeType MimeType of the content
	 * @param array $reviewers list of reviewers
	 * @param array $approvers list of approvers
	 * @param integer $version version number of content or 0 if next higher version shall be used.
	 * @param array $attributes list of version attributes. The element key
	 *        must be the id of the attribute definition.
	 * @return bool/array false in case of an error or a result set
	 */
	function addContent($comment, $user, $tmpFile, $orgFileName, $fileType, $mimeType, $reviewers=array(), $approvers=array(), $version=0, $attributes=array(), $workflow=null) { /* {{{ */
		$db = $this->_dms->getDB();

		// the doc path is id/version.filetype
		$dir = $this->getDir();

		$date = time();

		/* The version field in table tblDocumentContent used to be auto
		 * increment but that requires the field to be primary as well if
		 * innodb is used. That's why the version is now determined here.
		 */
		if ((int)$version<1) {
			$queryStr = "SELECT MAX(version) as m from tblDocumentContent where document = ".$this->_id;
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$res)
				return false;

			$version = $resArr[0]['m']+1;
		}

		$filesize = LetoDMS_Core_File::fileSize($tmpFile);
		$checksum = LetoDMS_Core_File::checksum($tmpFile);

		$db->startTransaction();
		$queryStr = "INSERT INTO tblDocumentContent (document, version, comment, date, createdBy, dir, orgFileName, fileType, mimeType, fileSize, checksum) VALUES ".
						"(".$this->_id.", ".(int)$version.",".$db->qstr($comment).", ".$date.", ".$user->getID().", ".$db->qstr($dir).", ".$db->qstr($orgFileName).", ".$db->qstr($fileType).", ".$db->qstr($mimeType).", ".$filesize.", ".$db->qstr($checksum).")";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$contentID = $db->getInsertID();

		// copy file
		if (!LetoDMS_Core_File::makeDir($this->_dms->contentDir . $dir)) {
			$db->rollbackTransaction();
			return false;
		}
		if (!LetoDMS_Core_File::copyFile($tmpFile, $this->_dms->contentDir . $dir . $version . $fileType)) {
			$db->rollbackTransaction();
			return false;
		}

		unset($this->_content);
		unset($this->_latestContent);
		$content = new LetoDMS_Core_DocumentContent($contentID, $this, $version, $comment, $date, $user->getID(), $dir, $orgFileName, $fileType, $mimeType, $filesize, $checksum);
		if($workflow)
			$content->setWorkflow($workflow, $user);
		$docResultSet = new LetoDMS_Core_AddContentResultSet($content);

		if($attributes) {
			foreach($attributes as $attrdefid=>$attribute) {
				if(trim($attribute))
					if(!$content->setAttributeValue($this->_dms->getAttributeDefinition($attrdefid), $attribute)) {
						$this->removeContent($content);
						$db->rollbackTransaction();
						return false;
					}
			}
		}

		// TODO - verify
		if ($this->_dms->enableConverting && in_array($docResultSet->getContent()->getFileType(), array_keys($this->_dms->convertFileTypes)))
			$docResultSet->getContent()->convert(); // Even if if fails, do not return false

		$queryStr = "INSERT INTO `tblDocumentStatus` (`documentID`, `version`) ".
			"VALUES (". $this->_id .", ". (int) $version .")";
		if (!$db->getResult($queryStr)) {
			$this->removeContent($content);
			$db->rollbackTransaction();
			return false;
		}

		$statusID = $db->getInsertID();

		// Add reviewers into the database. Reviewers must review the document
		// and submit comments, if appropriate. Reviewers can also recommend that
		// a document be rejected.
		$pendingReview=false;
		$reviewRes = array();
		foreach (array("i", "g") as $i){
			if (isset($reviewers[$i])) {
				foreach ($reviewers[$i] as $reviewerID) {
					$reviewer=($i=="i" ?$this->_dms->getUser($reviewerID) : $this->_dms->getGroup($reviewerID));
					$res = ($i=="i" ? $docResultSet->getContent()->addIndReviewer($reviewer, $user, true) : $docResultSet->getContent()->addGrpReviewer($reviewer, $user, true));
					$docResultSet->addReviewer($reviewer, $i, $res);
					// If no error is returned, or if the error is just due to email
					// failure, mark the state as "pending review".
					if ($res==0 || $res=-3 || $res=-4) {
						$pendingReview=true;
					}
				}
			}
		}
		// Add approvers to the database. Approvers must also review the document
		// and make a recommendation on its release as an approved version.
		$pendingApproval=false;
		$approveRes = array();
		foreach (array("i", "g") as $i){
			if (isset($approvers[$i])) {
				foreach ($approvers[$i] as $approverID) {
					$approver=($i=="i" ? $this->_dms->getUser($approverID) : $this->_dms->getGroup($approverID));
					$res=($i=="i" ? $docResultSet->getContent()->addIndApprover($approver, $user, !$pendingReview) : $docResultSet->getContent()->addGrpApprover($approver, $user, !$pendingReview));
					$docResultSet->addApprover($approver, $i, $res);
					if ($res==0 || $res=-3 || $res=-4) {
						$pendingApproval=true;
					}
				}
			}
		}

		// If there are no reviewers or approvers, the document is automatically
		// promoted to the released state.
		if ($pendingReview) {
			$status = S_DRAFT_REV;
			$comment = "";
		}
		elseif ($pendingApproval) {
			$status = S_DRAFT_APP;
			$comment = "";
		}
		elseif($workflow) {
			$status = S_IN_WORKFLOW;
			$comment = ", workflow: ".$workflow->getName();
		} else {
			$status = S_RELEASED;
			$comment = "";
		}
		$queryStr = "INSERT INTO `tblDocumentStatusLog` (`statusID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $statusID ."', '". $status."', 'New document content submitted". $comment ."', CURRENT_TIMESTAMP, '". $user->getID() ."')";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$docResultSet->setStatus($status,$comment,$user);

		$db->commitTransaction();
		return $docResultSet;
	} /* }}} */

	/**
	 * Return all content elements of a document
	 *
	 * This functions returns an array of content elements ordered by version
	 *
	 * @return array list of objects of class LetoDMS_Core_DocumentContent
	 */
	function getContent() { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_content)) {
			$queryStr = "SELECT * FROM tblDocumentContent WHERE document = ".$this->_id." ORDER BY version";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$res)
				return false;

			$this->_content = array();
			foreach ($resArr as $row)
				array_push($this->_content, new LetoDMS_Core_DocumentContent($row["id"], $this, $row["version"], $row["comment"], $row["date"], $row["createdBy"], $row["dir"], $row["orgFileName"], $row["fileType"], $row["mimeType"], $row['fileSize'], $row['checksum']));
		}

		return $this->_content;
	} /* }}} */

	/**
	 * Return the content element of a document with a given version number
	 *
	 * @param integer $version version number of content element
	 * @return object object of class LetoDMS_Core_DocumentContent
	 */
	function getContentByVersion($version) { /* {{{ */
		if (!is_numeric($version)) return false;

		if (isset($this->_content)) {
			foreach ($this->_content as $revision) {
				if ($revision->getVersion() == $version)
					return $revision;
			}
			return false;
		}

		$db = $this->_dms->getDB();
		$queryStr = "SELECT * FROM tblDocumentContent WHERE document = ".$this->_id." AND version = " . (int) $version;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$res)
			return false;
		if (count($resArr) != 1)
			return false;

		$resArr = $resArr[0];
		return new LetoDMS_Core_DocumentContent($resArr["id"], $this, $resArr["version"], $resArr["comment"], $resArr["date"], $resArr["createdBy"], $resArr["dir"], $resArr["orgFileName"], $resArr["fileType"], $resArr["mimeType"], $resArr['fileSize'], $resArr['checksum']);
	} /* }}} */

	function getLatestContent() { /* {{{ */
		if (!isset($this->_latestContent)) {
			$db = $this->_dms->getDB();
			$queryStr = "SELECT * FROM tblDocumentContent WHERE document = ".$this->_id." ORDER BY version DESC LIMIT 0,1";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;
			if (count($resArr) != 1)
				return false;

			$resArr = $resArr[0];
			$this->_latestContent = new LetoDMS_Core_DocumentContent($resArr["id"], $this, $resArr["version"], $resArr["comment"], $resArr["date"], $resArr["createdBy"], $resArr["dir"], $resArr["orgFileName"], $resArr["fileType"], $resArr["mimeType"], $resArr['fileSize'], $resArr['checksum']);
		}
		return $this->_latestContent;
	} /* }}} */

	function removeContent($version) { /* {{{ */
		$db = $this->_dms->getDB();

		$emailList = array();
		$emailList[] = $version->_userID;

		if (file_exists( $this->_dms->contentDir.$version->getPath() ))
			if (!LetoDMS_Core_File::removeFile( $this->_dms->contentDir.$version->getPath() ))
				return false;

		$db->startTransaction();

		$status = $version->getStatus();
		$stID = $status["statusID"];

		$queryStr = "DELETE FROM tblDocumentContent WHERE `document` = " . $this->getID() .	" AND `version` = " . $version->_version;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM tblDocumentContentAttributes WHERE content = " . $version->getId();
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblDocumentStatusLog` WHERE `statusID` = '".$stID."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblDocumentStatus` WHERE `documentID` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$status = $version->getReviewStatus();
		$stList = "";
		foreach ($status as $st) {
			$stList .= (strlen($stList)==0 ? "" : ", "). "'".$st["reviewID"]."'";
			if ($st["status"]==0 && !in_array($st["required"], $emailList)) {
				$emailList[] = $st["required"];
			}
		}

		if (strlen($stList)>0) {
			$queryStr = "DELETE FROM `tblDocumentReviewLog` WHERE `tblDocumentReviewLog`.`reviewID` IN (".$stList.")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}
		$queryStr = "DELETE FROM `tblDocumentReviewers` WHERE `documentID` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$status = $version->getApprovalStatus();
		$stList = "";
		foreach ($status as $st) {
			$stList .= (strlen($stList)==0 ? "" : ", "). "'".$st["approveID"]."'";
			if ($st["status"]==0 && !in_array($st["required"], $emailList)) {
				$emailList[] = $st["required"];
			}
		}
		if (strlen($stList)>0) {
			$queryStr = "DELETE FROM `tblDocumentApproveLog` WHERE `tblDocumentApproveLog`.`approveID` IN (".$stList.")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}
		$queryStr = "DELETE FROM `tblDocumentApprovers` WHERE `documentID` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblWorkflowDocumentContent` WHERE `document` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();
		return true;
	} /* }}} */

	function getDocumentLink($linkID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($linkID)) return false;

		$queryStr = "SELECT * FROM tblDocumentLinks WHERE document = " . $this->_id ." AND id = " . (int) $linkID;
		$resArr = $db->getResultArray($queryStr);
		if ((is_bool($resArr) && !$resArr) || count($resArr)==0)
			return false;

		$resArr = $resArr[0];
		$document = $this->_dms->getDocument($resArr["document"]);
		$target = $this->_dms->getDocument($resArr["target"]);
		return new LetoDMS_Core_DocumentLink($resArr["id"], $document, $target, $resArr["userID"], $resArr["public"]);
	} /* }}} */

	function getDocumentLinks() { /* {{{ */
		if (!isset($this->_documentLinks)) {
			$db = $this->_dms->getDB();

			$queryStr = "SELECT * FROM tblDocumentLinks WHERE document = " . $this->_id;
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;
			$this->_documentLinks = array();

			foreach ($resArr as $row) {
				$target = $this->_dms->getDocument($row["target"]);
				array_push($this->_documentLinks, new LetoDMS_Core_DocumentLink($row["id"], $this, $target, $row["userID"], $row["public"]));
			}
		}
		return $this->_documentLinks;
	} /* }}} */

	function addDocumentLink($targetID, $userID, $public) { /* {{{ */
		$db = $this->_dms->getDB();

		$public = ($public) ? "1" : "0";

		$queryStr = "INSERT INTO tblDocumentLinks(document, target, userID, public) VALUES (".$this->_id.", ".(int)$targetID.", ".(int)$userID.", ".(int)$public.")";
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_documentLinks);
		return true;
	} /* }}} */

	function removeDocumentLink($linkID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($linkID)) return false;

		$queryStr = "DELETE FROM tblDocumentLinks WHERE document = " . $this->_id ." AND id = " . (int) $linkID;
		if (!$db->getResult($queryStr)) return false;
		unset ($this->_documentLinks);
		return true;
	} /* }}} */

	function getDocumentFile($ID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($ID)) return false;

		$queryStr = "SELECT * FROM tblDocumentFiles WHERE document = " . $this->_id ." AND id = " . (int) $ID;
		$resArr = $db->getResultArray($queryStr);
		if ((is_bool($resArr) && !$resArr) || count($resArr)==0) return false;

		$resArr = $resArr[0];
		return new LetoDMS_Core_DocumentFile($resArr["id"], $this, $resArr["userID"], $resArr["comment"], $resArr["date"], $resArr["dir"], $resArr["fileType"], $resArr["mimeType"], $resArr["orgFileName"], $resArr["name"]);
	} /* }}} */

	function getDocumentFiles() { /* {{{ */
		if (!isset($this->_documentFiles)) {
			$db = $this->_dms->getDB();

			$queryStr = "SELECT * FROM tblDocumentFiles WHERE document = " . $this->_id." ORDER BY `date` DESC";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr) return false;

			$this->_documentFiles = array();

			foreach ($resArr as $row) {
				array_push($this->_documentFiles, new LetoDMS_Core_DocumentFile($row["id"], $this, $row["userID"], $row["comment"], $row["date"], $row["dir"], $row["fileType"], $row["mimeType"], $row["orgFileName"], $row["name"]));
			}
		}
		return $this->_documentFiles;
	} /* }}} */

	function addDocumentFile($name, $comment, $user, $tmpFile, $orgFileName,$fileType, $mimeType ) { /* {{{ */
		$db = $this->_dms->getDB();

		$dir = $this->getDir();

		$queryStr = "INSERT INTO tblDocumentFiles (comment, date, dir, document, fileType, mimeType, orgFileName, userID, name) VALUES ".
			"(".$db->qstr($comment).", '".time()."', ".$db->qstr($dir).", ".$this->_id.", ".$db->qstr($fileType).", ".$db->qstr($mimeType).", ".$db->qstr($orgFileName).",".$user->getID().",".$db->qstr($name).")";
		if (!$db->getResult($queryStr)) return false;

		$id = $db->getInsertID();

		$file = $this->getDocumentFile($id);
		if (is_bool($file) && !$file) return false;

		// copy file
		if (!LetoDMS_Core_File::makeDir($this->_dms->contentDir . $dir)) return false;
		if (!LetoDMS_Core_File::copyFile($tmpFile, $this->_dms->contentDir . $file->getPath() )) return false;

		return true;
	} /* }}} */

	function removeDocumentFile($ID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($ID)) return false;

		$file = $this->getDocumentFile($ID);
		if (is_bool($file) && !$file) return false;

		if (file_exists( $this->_dms->contentDir . $file->getPath() )){
			if (!LetoDMS_Core_File::removeFile( $this->_dms->contentDir . $file->getPath() ))
				return false;
		}

		$name=$file->getName();
		$comment=$file->getcomment();

		$queryStr = "DELETE FROM tblDocumentFiles WHERE document = " . $this->getID() . " AND id = " . (int) $ID;
		if (!$db->getResult($queryStr))
			return false;

		unset ($this->_documentFiles);

		return true;
	} /* }}} */

	/**
	 * Remove a document completly
	 *
	 * This methods calls the callback 'onPreRemoveDocument' before removing
	 * the document. The current document will be passed as the second
	 * parameter to the callback function. After successful deletion the
	 * 'onPostRemoveDocument' callback will be used. The current document id
	 * will be passed as the second parameter. If onPreRemoveDocument fails
	 * the whole function will fail and the document will not be deleted.
	 * The return value of 'onPostRemoveDocument' will be disregarded.
	 *
	 * @return boolean true on success, otherwise false
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		/* Check if 'onPreRemoveDocument' callback is set */
		if(isset($this->_dms->callbacks['onPreRemoveDocument'])) {
			$callback = $this->_dms->callbacks['onPreRemoveDocument'];
			if(!call_user_func($callback[0], $callback[1], $this)) {
				return false;
			}
		}

		$res = $this->getContent();
		if (is_bool($res) && !$res) return false;

		$db->startTransaction();

		// FIXME: call a new function removeContent instead
		foreach ($this->_content as $version) {
			if (!$this->removeContent($version)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		// remove document file
		$res = $this->getDocumentFiles();
		if (is_bool($res) && !$res) {
			$db->rollbackTransaction();
			return false;
		}

		foreach ($res as $documentfile)
			if(!$this->removeDocumentFile($documentfile->getId())) {
				$db->rollbackTransaction();
				return false;
			}

		// TODO: versioning file?

		if (file_exists( $this->_dms->contentDir . $this->getDir() ))
			if (!LetoDMS_Core_File::removeDir( $this->_dms->contentDir . $this->getDir() )) {
				$db->rollbackTransaction();
				return false;
			}

		$queryStr = "DELETE FROM tblDocuments WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM tblDocumentAttributes WHERE document = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM tblACLs WHERE target = " . $this->_id . " AND targetType = " . T_DOCUMENT;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM tblDocumentLinks WHERE document = " . $this->_id . " OR target = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM tblDocumentLocks WHERE document = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM tblDocumentFiles WHERE document = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM tblDocumentCategory WHERE documentID = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		// Delete the notification list.
		$queryStr = "DELETE FROM tblNotify WHERE target = " . $this->_id . " AND targetType = " . T_DOCUMENT;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();

		/* Check if 'onPostRemoveDocument' callback is set */
		if(isset($this->_dms->callbacks['onPostRemoveDocument'])) {
			$callback = $this->_dms->callbacks['onPostRemoveDocument'];
			if(!call_user_func($callback[0], $callback[1], $this->_id)) {
			}
		}

		return true;
	} /* }}} */

	/**
	 * Get List of users and groups which have read access on the document
	 *
	 * This function is deprecated. Use
	 * {@see LetoDMS_Core_Document::getReadAccessList()} instead.
	 */
	function getApproversList() { /* {{{ */
		return $this->getReadAccessList();
	} /* }}} */

	function getReadAccessList() { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_readAccessList)) {
			$this->_readAccessList = array("groups" => array(), "users" => array());
			$userIDs = "";
			$groupIDs = "";
			$defAccess  = $this->getDefaultAccess();

			if ($defAccess<M_READ) {
				// Get the list of all users and groups that are listed in the ACL as
				// having read access to the document.
				$tmpList = $this->getAccessList(M_READ, O_GTEQ);
			}
			else {
				// Get the list of all users and groups that DO NOT have read access
				// to the document.
				$tmpList = $this->getAccessList(M_NONE, O_LTEQ);
			}
			foreach ($tmpList["groups"] as $groupAccess) {
				$groupIDs .= (strlen($groupIDs)==0 ? "" : ", ") . $groupAccess->getGroupID();
			}
			foreach ($tmpList["users"] as $userAccess) {
				$user = $userAccess->getUser();
				if (!$this->_dms->enableAdminRevApp && $user->isAdmin()) continue;
				if ($user->isGuest()) continue;
				$userIDs .= (strlen($userIDs)==0 ? "" : ", ") . $userAccess->getUserID();
			}

			// Construct a query against the users table to identify those users
			// that have read access to this document, either directly through an
			// ACL entry, by virtue of ownership or by having administrative rights
			// on the database.
			$queryStr="";
			/* If default access is less then read, $userIDs and $groupIDs contains
			 * a list of user with read access
			 */
			if ($defAccess < M_READ) {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblUsers`.* FROM `tblUsers` ".
						"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`userID`=`tblUsers`.`id` ".
						"WHERE `tblGroupMembers`.`groupID` IN (". $groupIDs .") ".
						"AND `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." UNION ";
				}
				$queryStr .=
					"SELECT `tblUsers`.* FROM `tblUsers` ".
					"WHERE (`tblUsers`.`role` != ".LetoDMS_Core_User::role_guest.") ".
					"AND ((`tblUsers`.`id` = ". $this->_ownerID . ") ".
					"OR (`tblUsers`.`role` = ".LetoDMS_Core_User::role_admin.")".
					(strlen($userIDs) == 0 ? "" : " OR (`tblUsers`.`id` IN (". $userIDs ."))").
					") ORDER BY `login`";
			}
			/* If default access is equal or greate then read, $userIDs and
			 * $groupIDs contains a list of user without read access
			 */
			else {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblUsers`.* FROM `tblUsers` ".
						"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`userID`=`tblUsers`.`id` ".
						"WHERE `tblGroupMembers`.`groupID` NOT IN (". $groupIDs .")".
						"AND `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." ".
						(strlen($userIDs) == 0 ? "" : " AND (`tblUsers`.`id` NOT IN (". $userIDs ."))")." UNION ";
				}
				$queryStr .=
					"SELECT `tblUsers`.* FROM `tblUsers` ".
					"WHERE (`tblUsers`.`id` = ". $this->_ownerID . ") ".
					"OR (`tblUsers`.`role` = ".LetoDMS_Core_User::role_admin.") ".
					"UNION ".
					"SELECT `tblUsers`.* FROM `tblUsers` ".
					"WHERE `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." ".
					(strlen($userIDs) == 0 ? "" : " AND (`tblUsers`.`id` NOT IN (". $userIDs ."))").
					" ORDER BY `login`";
			}
			$resArr = $db->getResultArray($queryStr);
			if (!is_bool($resArr)) {
				foreach ($resArr as $row) {
					$user = $this->_dms->getUser($row['id']);
					if (!$this->_dms->enableAdminRevApp && $user->isAdmin()) continue;
					$this->_readAccessList["users"][] = $user;
				}
			}

			// Assemble the list of groups that have read access to the document.
			$queryStr="";
			if ($defAccess < M_READ) {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblGroups`.* FROM `tblGroups` ".
						"WHERE `tblGroups`.`id` IN (". $groupIDs .")";
				}
			}
			else {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblGroups`.* FROM `tblGroups` ".
						"WHERE `tblGroups`.`id` NOT IN (". $groupIDs .")";
				}
				else {
					$queryStr = "SELECT `tblGroups`.* FROM `tblGroups`";
				}
			}
			if (strlen($queryStr)>0) {
				$resArr = $db->getResultArray($queryStr);
				if (!is_bool($resArr)) {
					foreach ($resArr as $row) {
						$group = $this->_dms->getGroup($row["id"]);
						$this->_readAccessList["groups"][] = $group;
					}
				}
			}
		}
		return $this->_readAccessList;
	} /* }}} */

	/**
	 * Get the internally used folderList which stores the ids of folders from
	 * the root folder to the parent folder.
	 *
	 * @return string column separated list of folder ids
	 */
	function getFolderList() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT folderList FROM tblDocuments where id = ".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		return $resArr[0]['folderList'];
	} /* }}} */

	/**
	 * Checks the internal data of the document and repairs it.
	 * Currently, this function only repairs an incorrect folderList
	 *
	 * @return boolean true on success, otherwise false
	 */
	function repair() { /* {{{ */
		$db = $this->_dms->getDB();

		$curfolderlist = $this->getFolderList();

		// calculate the folderList of the folder
		$parent = $this->getFolder();
		$pathPrefix="";
		$path = $parent->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}
		if($curfolderlist != $pathPrefix) {
			$queryStr = "UPDATE tblDocuments SET folderList='".$pathPrefix."' WHERE id = ". $this->_id;
			$res = $db->getResult($queryStr);
			if (!$res)
				return false;
		}
		return true;
	} /* }}} */

	/**
	 * Calculate the disk space including all versions of the document
	 * 
	 * This is done by using the internal database field storing the
	 * filesize of a document version.
	 *
	 * @return integer total disk space in Bytes
	 */
	function getUsedDiskSpace() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT SUM(filesize) sum FROM tblDocumentContent WHERE document = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		return $resArr[0]['sum'];
	} /* }}} */

} /* }}} */


/**
 * Class to represent content of a document
 *
 * Each document has content attached to it, often called a 'version' of the
 * document. The document content represents a file on the disk with some
 * meta data stored in the database. A document content has a version number
 * which is incremented with each replacement of the old content. Old versions
 * are kept unless they are explicitly deleted by
 * {@link LetoDMS_Core_Document::removeContent()}.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DocumentContent extends LetoDMS_Core_Object { /* {{{ */

	/**
	 * Recalculate the status of a document
	 * The methods checks the review and approval status and sets the
	 * status of the document accordingly.
	 * If status is S_RELEASED and version has workflow set status
	 * to S_IN_WORKFLOW
	 * If status is S_RELEASED and there are reviewers set status S_DRAFT_REV
	 * If status is S_RELEASED or S_DRAFT_REV and there are approvers set
	 * status S_DRAFT_APP
	 * If status is draft and there are no approver and no reviewers set
	 * status to S_RELEASED
	 * The status of a document with the current status S_OBSOLETE, S_REJECTED,
	 * or S_EXPIRED will not be changed unless the parameter
	 * $ignorecurrentstatus is set to true.
	 *
	 * @param boolean $ignorecurrentstatus ignore the current status and
	 *        recalculate a new status in any case
	 * @param object $user the user initiating this method
	 */
	function verifyStatus($ignorecurrentstatus=false, $user=null) { /* {{{ */

		unset($this->_status);
		$st=$this->getStatus();

		if (!$ignorecurrentstatus && ($st["status"]==S_OBSOLETE || $st["status"]==S_REJECTED || $st["status"]==S_EXPIRED )) return;

		$pendingReview=false;
		unset($this->_reviewStatus);  // force to be reloaded from DB
		$reviewStatus=$this->getReviewStatus();
		if (is_array($reviewStatus) && count($reviewStatus)>0) {
			foreach ($reviewStatus as $r){
				if ($r["status"]==0){
					$pendingReview=true;
					break;
				}
			}
		}
		$pendingApproval=false;
		unset($this->_approvalStatus);  // force to be reloaded from DB
		$approvalStatus=$this->getApprovalStatus();
		if (is_array($approvalStatus) && count($approvalStatus)>0) {
			foreach ($approvalStatus as $a){
				if ($a["status"]==0){
					$pendingApproval=true;
					break;
				}
			}
		}

		unset($this->_workflow); // force to be reloaded from DB
		if ($this->getWorkflow()) $this->setStatus(S_IN_WORKFLOW,"",$user);
		elseif ($pendingReview) $this->setStatus(S_DRAFT_REV,"",$user);
		elseif ($pendingApproval) $this->setStatus(S_DRAFT_APP,"",$user);
		else $this->setStatus(S_RELEASED,"",$user);
	} /* }}} */

	function LetoDMS_Core_DocumentContent($id, $document, $version, $comment, $date, $userID, $dir, $orgFileName, $fileType, $mimeType, $fileSize=0, $checksum='') { /* {{{ */
		parent::__construct($id);
		$this->_document = $document;
		$this->_version = (int) $version;
		$this->_comment = $comment;
		$this->_date = $date;
		$this->_userID = (int) $userID;
		$this->_dir = $dir;
		$this->_orgFileName = $orgFileName;
		$this->_fileType = $fileType;
		$this->_mimeType = $mimeType;
		$this->_dms = $document->_dms;
		if(!$fileSize) {
			$this->_fileSize = LetoDMS_Core_File::fileSize($this->_dms->contentDir . $this->getPath());
		} else {
			$this->_fileSize = $fileSize;
		}
		$this->_checksum = $checksum;
		$this->_workflow = null;
		$this->_workflowState = null;
	} /* }}} */

	function getVersion() { return $this->_version; }
	function getComment() { return $this->_comment; }
	function getDate() { return $this->_date; }
	function getOriginalFileName() { return $this->_orgFileName; }
	function getFileType() { return $this->_fileType; }
	function getFileName(){ return $this->_version . $this->_fileType; }
	function getDir() { return $this->_dir; }
	function getMimeType() { return $this->_mimeType; }
	function getDocument() { return $this->_document; }

	function getUser() { /* {{{ */
		if (!isset($this->_user))
			$this->_user = $this->_document->_dms->getUser($this->_userID);
		return $this->_user;
	} /* }}} */

	function getPath() { return $this->_document->getDir() . $this->_version . $this->_fileType; }

	function setDate($date = false) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$date)
			$date = time();

		$queryStr = "UPDATE tblDocumentContent SET date = ".(int) $date." WHERE `document` = " . $this->_document->getID() .	" AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;

		$this->_date = $date;

		return true;
	} /* }}} */

	function getFileSize() { /* {{{ */
		return $this->_fileSize;
	} /* }}} */

	/**
	 * Set file size by reading the file
	 */
	function setFileSize() { /* {{{ */
		$filesize = LetoDMS_Core_File::fileSize($this->_dms->contentDir . $this->_document->getDir() . $this->getFileName());
		if($filesize === false)
			return false;

		$db = $this->_document->_dms->getDB();
		$queryStr = "UPDATE tblDocumentContent SET fileSize = ".$filesize." where `document` = " . $this->_document->getID() .  " AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;
		$this->_fileSize = $filesize;

		return true;
	} /* }}} */

	function getChecksum() { /* {{{ */
		return $this->_checksum;
	} /* }}} */

	/**
	 * Set checksum by reading the file
	 */
	function setChecksum() { /* {{{ */
		$checksum = LetoDMS_Core_File::checksum($this->_dms->contentDir . $this->_document->getDir() . $this->getFileName());
		if($checksum === false)
			return false;

		$db = $this->_document->_dms->getDB();
		$queryStr = "UPDATE tblDocumentContent SET checksum = ".$db->qstr($checksum)." where `document` = " . $this->_document->getID() .  " AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;
		$this->_checksum = $checksum;

		return true;
	} /* }}} */

	function setComment($newComment) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr = "UPDATE tblDocumentContent SET comment = ".$db->qstr($newComment)." WHERE `document` = " . $this->_document->getID() .	" AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;

		$this->_comment = $newComment;

		return true;
	} /* }}} */

	/**
	 * This function is deprecated
	 */
	function convert() { /* {{{ */
		if (file_exists($this->_document->_dms->contentDir . $this->_document->getID() .'/' . "index.html"))
			return true;

		if (!in_array($this->_fileType, array_keys($this->_document->_dms->convertFileTypes)))
			return false;

		$source = $this->_document->_dms->contentDir . $this->_document->getID() .'/' . $this->getFileName();
		$target = $this->_document->_dms->contentDir . $this->_document->getID() .'/' . "index.html";
	//	$source = str_replace("/", "\\", $source);
	//	$target = str_replace("/", "\\", $target);

		$command = $this->_document->_dms->convertFileTypes[$this->_fileType];
		$command = str_replace("{SOURCE}", "\"$source\"", $command);
		$command = str_replace("{TARGET}", "\"$target\"", $command);

		$output = array();
		$res = 0;
		exec($command, $output, $res);

		if ($res != 0) {
			print (implode("\n", $output));
			return false;
		}
		return true;
	} /* }}} */

	/* FIXME: this function should not be part of the DMS. It lies in the duty
	 * of the application whether a file can be viewed online or not.
	 */
	function viewOnline() { /* {{{ */
		if (!isset($this->_document->_dms->_viewOnlineFileTypes) || !is_array($this->_document->_dms->_viewOnlineFileTypes)) {
			return false;
		}

		if (in_array(strtolower($this->_fileType), $this->_document->_dms->_viewOnlineFileTypes))
			return true;

		if ($this->_document->_dms->enableConverting && in_array($this->_fileType, array_keys($this->_document->_dms->convertFileTypes)))
			if ($this->wasConverted()) return true;

		return false;
	} /* }}} */

	function wasConverted() { /* {{{ */
		return file_exists($this->_document->_dms->contentDir . $this->_document->getID() .'/' . "index.html");
	} /* }}} */

	/**
	 * This function is deprecated
	 */
	function getURL() { /* {{{ */
		if (!$this->viewOnline())return false;

		if (in_array(strtolower($this->_fileType), $this->_document->_dms->_viewOnlineFileTypes))
			return "/" . $this->_document->getID() . "/" . $this->_version . "/" . $this->getOriginalFileName();
		else
			return "/" . $this->_document->getID() . "/" . $this->_version . "/index.html";
	} /* }}} */

	/**
	 * Get the latest status of the content
	 *
	 * The status of the content reflects its current review, approval or workflow
	 * state. A status can be a negative or positive number or 0. A negative
	 * numbers indicate a missing approval, review or an obsolete content.
	 * Positive numbers indicate some kind of approval or workflow being
	 * active, but not necessarily a release.
	 * S_DRAFT_REV, 0
	 * S_DRAFT_APP, 1
	 * S_RELEASED, 2
	 * S_IN_WORKFLOW, 3
	 * S_REJECTED, -1
	 * S_OBSOLETE, -2
	 * S_EXPIRED, -3
	 * When a content is inserted and does not need approval nor review,
	 * then its status is set to S_RELEASED immediately. Any change of
	 * the status is monitored in the table tblDocumentStatusLog. This
	 * function will always return the latest entry for the content.
	 */
	function getStatus($limit=1) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		// Retrieve the current overall status of the content represented by
		// this object.
		if (!isset($this->_status)) {
		/*
			if (!$db->createTemporaryTable("ttstatid", $forceTemporaryTable)) {
				return false;
			}
			$queryStr="SELECT `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
				"`tblDocumentStatusLog`.`comment`, `tblDocumentStatusLog`.`date`, ".
				"`tblDocumentStatusLog`.`userID` ".
				"FROM `tblDocumentStatus` ".
				"LEFT JOIN `tblDocumentStatusLog` USING (`statusID`) ".
				"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
				"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
				"AND `tblDocumentStatus`.`documentID` = '". $this->_document->getID() ."' ".
				"AND `tblDocumentStatus`.`version` = '". $this->_version ."' ";
		*/
			$queryStr=
				"SELECT `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
				"`tblDocumentStatusLog`.`comment`, `tblDocumentStatusLog`.`date`, ".
				"`tblDocumentStatusLog`.`userID` ".
				"FROM `tblDocumentStatus` ".
				"LEFT JOIN `tblDocumentStatusLog` USING (`statusID`) ".
				"WHERE `tblDocumentStatus`.`documentID` = '". $this->_document->getID() ."' ".
				"AND `tblDocumentStatus`.`version` = '". $this->_version ."' ".
				"ORDER BY `tblDocumentStatusLog`.`statusLogID` DESC LIMIT ".(int) $limit;

			$res = $db->getResultArray($queryStr);
			if (is_bool($res) && !$res)
				return false;
			if (count($res)!=1)
				return false;
			$this->_status = $res[0];
		}
		return $this->_status;
	} /* }}} */

	/**
	 * Get current and former states of the document content
	 *
	 * @param integer $limit if not set all log entries will be returned
	 * @return array list of status changes
	 */
	function getStatusLog($limit=0) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		$queryStr=
			"SELECT `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
			"`tblDocumentStatusLog`.`comment`, `tblDocumentStatusLog`.`date`, ".
			"`tblDocumentStatusLog`.`userID` ".
			"FROM `tblDocumentStatus` ".
			"LEFT JOIN `tblDocumentStatusLog` USING (`statusID`) ".
			"WHERE `tblDocumentStatus`.`documentID` = '". $this->_document->getID() ."' ".
			"AND `tblDocumentStatus`.`version` = '". $this->_version ."' ".
			"ORDER BY `tblDocumentStatusLog`.`statusLogID` DESC ";
		if($limit)
			$queryStr .= "LIMIT ".(int) $limit;

		$res = $db->getResultArray($queryStr);
		if (is_bool($res) && !$res)
			return false;

		return $res;
	} /* }}} */

	/**
	 * Set the status of the content
	 * Setting the status means to add another entry into the table
	 * tblDocumentStatusLog. The method returns also false if the status
	 * is already set on the value passed to the method.
	 *
	 * @param integer $status new status of content
	 * @param string $comment comment for this status change
	 * @param object $updateUser user initiating the status change
	 * @return boolean true on success, otherwise false
	 */
	function setStatus($status, $comment, $updateUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($status)) return false;

		/* return an error if $updateuser is not set */
		if(!$updateUser)
			return false;

		// If the supplied value lies outside of the accepted range, return an
		// error.
		if ($status < -3 || $status > 3) {
			return false;
		}

		// Retrieve the current overall status of the content represented by
		// this object, if it hasn't been done already.
		if (!isset($this->_status)) {
			$this->getStatus();
		}
		if ($this->_status["status"]==$status) {
			return false;
		}
		$queryStr = "INSERT INTO `tblDocumentStatusLog` (`statusID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $this->_status["statusID"] ."', '". (int) $status ."', ".$db->qstr($comment).", CURRENT_TIMESTAMP, '". $updateUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return false;

		return true;
	} /* }}} */

	/**
	 * Returns the access mode similar to a document
	 * There is no real access mode for document content, so this is more
	 * like a virtual access mode, derived from the status or workflow
	 * of the document content. The idea is to return an access mode
	 * M_NONE if the user is still in a workflow or under review/approval.
	 * In such a case only those user involved in the workflow/review/approval
	 * process should be allowed to see the document. This method could
	 * be called by any function that returns the content e.g. getLatestContent() 
	 * It may as well be used by LetoDMS_Core_Document::getAccessMode() to
	 * prevent access on the whole document if there is just one version.
	 * The return value is planed to be either M_NONE or M_READ.
	 *
	 * @param object $user
	 * @return integer mode
	 */
	function getAccessMode($u) { /* {{{ */
		if(!$this->_workflow)
			$this->getWorkflow();

		if($this->_workflow) {
			if (!$this->_workflowState)
				$this->getWorkflowState();
			$transitions = $this->_workflow->getNextTransitions($this->_workflowState);
			foreach($transitions as $transition) {
				if($this->triggerWorkflowTransitionIsAllowed($u, $transition))
					return M_READ;
			}
			return M_NONE;
		}

		return M_READ;
	} /* }}} */

	/**
	 * Get the current review status of the document content
	 * The review status is a list of reviewers and its current status
	 *
	 * @param integer $limit the number of recent status changes per reviewer
	 * @return array list of review status
	 */
	function getReviewStatus($limit=1) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		// Retrieve the current status of each assigned reviewer for the content
		// represented by this object.
		if (!isset($this->_reviewStatus)) {
			/* First get a list of all reviews for this document content */
			$queryStr=
				"SELECT reviewID FROM tblDocumentReviewers WHERE `version`='".$this->_version
				."' AND `documentID` = '". $this->_document->getID() ."' ";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			$this->_reviewStatus = array();
			if($recs) {
				foreach($recs as $rec) {
					$queryStr=
						"SELECT `tblDocumentReviewers`.*, `tblDocumentReviewLog`.`reviewLogID`, `tblDocumentReviewLog`.`status`, ".
						"`tblDocumentReviewLog`.`comment`, `tblDocumentReviewLog`.`date`, ".
						"`tblDocumentReviewLog`.`userID`, `tblUsers`.`fullName`, `tblGroups`.`name` AS `groupName` ".
						"FROM `tblDocumentReviewers` ".
						"LEFT JOIN `tblDocumentReviewLog` USING (`reviewID`) ".
						"LEFT JOIN `tblUsers` on `tblUsers`.`id` = `tblDocumentReviewers`.`required`".
						"LEFT JOIN `tblGroups` on `tblGroups`.`id` = `tblDocumentReviewers`.`required`".
						"WHERE `tblDocumentReviewers`.`reviewID` = '". $rec['reviewID'] ."' ".
						"ORDER BY `tblDocumentReviewLog`.`reviewLogID` DESC LIMIT ".(int) $limit;

					$res = $db->getResultArray($queryStr);
					if (is_bool($res) && !$res) {
						unset($this->_reviewStatus);
						return false;
					}
					$this->_reviewStatus = array_merge($this->_reviewStatus, $res);
				}
			}
		}
		return $this->_reviewStatus;
	} /* }}} */

	function getApprovalStatus($limit=1) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		// Retrieve the current status of each assigned approver for the content
		// represented by this object.
		if (!isset($this->_approvalStatus)) {
			/* First get a list of all approvals for this document content */
			$queryStr=
				"SELECT approveId FROM tblDocumentApprovers WHERE `version`='".$this->_version
				."' AND `documentID` = '". $this->_document->getID() ."' ";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			$this->_approvalStatus = array();
			if($recs) {
				foreach($recs as $rec) {
					$queryStr=
						"SELECT `tblDocumentApprovers`.*, `tblDocumentApproveLog`.`status`, ".
						"`tblDocumentApproveLog`.`comment`, `tblDocumentApproveLog`.`date`, ".
						"`tblDocumentApproveLog`.`userID`, `tblUsers`.`fullName`, `tblGroups`.`name` AS `groupName` ".
						"FROM `tblDocumentApprovers` ".
						"LEFT JOIN `tblDocumentApproveLog` USING (`approveID`) ".
						"LEFT JOIN `tblUsers` on `tblUsers`.`id` = `tblDocumentApprovers`.`required` ".
						"LEFT JOIN `tblGroups` on `tblGroups`.`id` = `tblDocumentApprovers`.`required`".
						"WHERE `tblDocumentApprovers`.`approveId` = '". $rec['approveId'] ."' ".
						"ORDER BY `tblDocumentApproveLog`.`approveLogId` DESC LIMIT ".(int) $limit;

					$res = $db->getResultArray($queryStr);
					if (is_bool($res) && !$res) {
						unset($this->_approvalStatus);
						return false;
					}
					$this->_approvalStatus = array_merge($this->_approvalStatus, $res);
				}
			}
		}
		return $this->_approvalStatus;
	} /* }}} */

	function addIndReviewer($user, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$userID = $user->getID();

		// Get the list of users and groups with read access to this document.
		if (!isset($this->_readAccessList)) {
			$this->_readAccessList = $this->_document->getReadAccessList();
		}
		$approved = false;
		foreach ($this->_readAccessList["users"] as $appUser) {
			if ($userID == $appUser->getID()) {
				$approved = true;
				break;
			}
		}
		if (!$approved) {
			return -2;
		}

		// Check to see if the user has already been added to the review list.
		$reviewStatus = $user->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		$indstatus = false;
		if (count($reviewStatus["indstatus"]) > 0) {
			$indstatus = array_pop($reviewStatus["indstatus"]);
			if($indstatus["status"]!=-2) {
				// User is already on the list of reviewers; return an error.
				return -3;
			}
		}

		// Add the user into the review database.
		if (!$indstatus || ($indstatus && $indstatus["status"]!=-2)) {
			$queryStr = "INSERT INTO `tblDocumentReviewers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '0', '". $userID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$reviewID = $db->getInsertID();
		}
		else {
			$reviewID = isset($indstatus["reviewID"]) ? $ $indstatus["reviewID"] : NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $reviewID ."', '0', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		// Add reviewer to event notification table.
		//$this->_document->addNotify($userID, true);

		return 0;
	} /* }}} */

	function addGrpReviewer($group, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Get the list of users and groups with read access to this document.
		if (!isset($this->_readAccessList)) {
			// TODO: error checking.
			$this->_readAccessList = $this->_document->getReadAccessList();
		}
		$approved = false;
		foreach ($this->_readAccessList["groups"] as $appGroup) {
			if ($groupID == $appGroup->getID()) {
				$approved = true;
				break;
			}
		}
		if (!$approved) {
			return -2;
		}

		// Check to see if the group has already been added to the review list.
		$reviewStatus = $group->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus) > 0 && $reviewStatus[0]["status"]!=-2) {
			// Group is already on the list of reviewers; return an error.
			return -3;
		}

		// Add the group into the review database.
		if (!isset($reviewStatus[0]["status"]) || (isset($reviewStatus[0]["status"]) && $reviewStatus[0]["status"]!=-2)) {
			$queryStr = "INSERT INTO `tblDocumentReviewers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '1', '". $groupID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$reviewID = $db->getInsertID();
		}
		else {
			$reviewID = isset($reviewStatus[0]["reviewID"])?$reviewStatus[0]["reviewID"]:NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $reviewID ."', '0', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		// Add reviewer to event notification table.
		//$this->_document->addNotify($groupID, false);

		return 0;
	} /* }}} */

	/**
	 * Add a review to the document content
	 *
	 * This method will add an entry to the table tblDocumentReviewLog.
	 * It will first check if the user is ment to review the document version.
	 * It not the return value is -3.
	 * Next it will check if the users has been removed from the list of
	 * reviewers. In that case -4 will be returned.
	 * If the given review status has been set by the user before, it cannot
	 * be set again and 0 will be returned. Іf the review could be succesfully
	 * added the review log id will be returned.
	 *
	 * @see LetoDMS_Core_DocumentContent::setApprovalByInd()
	 * @param object $user user doing the review
	 * @param object $requestUser user asking for the review, this is mostly
	 * the user currently logged in.
	 * @param integer $status status of review
	 * @param string $comment comment for review
	 * @return integer new review log id
	 */
	function setReviewByInd($user, $requestUser, $status, $comment) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $user->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus["indstatus"])==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -3;
		}
		$indstatus = array_pop($reviewStatus["indstatus"]);
		if ($indstatus["status"]==-2) {
			// User has been deleted from reviewers
			return -4;
		}
		// Check if the status is really different from the current status
		if ($indstatus["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`,
  	  `comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["reviewID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", CURRENT_TIMESTAMP, '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;
		else {
			$reviewLogID = $db->getInsertID();
			return $reviewLogID;
		}
 } /* }}} */

	/**
	 * Add a review to the document content
	 *
	 * This method is similar to
	 * {@see LetoDMS_Core_DocumentContent::setReviewByInd()} but adds a review
	 * for a group instead of a user.
	 *
	 * @param object $group group doing the review
	 * @param object $requestUser user asking for the review, this is mostly
	 * the user currently logged in.
	 * @param integer $status status of review
	 * @param string $comment comment for review
	 * @return integer new review log id
	 */
	function setReviewByGrp($group, $requestUser, $status, $comment) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $group->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus)==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -3;
		}
		if ($reviewStatus[0]["status"]==-2) {
			// Group has been deleted from reviewers
			return -4;
		}

		// Check if the status is really different from the current status
		if ($reviewStatus[0]["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`,
  	  `comment`, `date`, `userID`) ".
			"VALUES ('". $reviewStatus[0]["reviewID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", CURRENT_TIMESTAMP, '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;
		else {
			$reviewLogID = $db->getInsertID();
			return $reviewLogID;
		}
 } /* }}} */

	function addIndApprover($user, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$userID = $user->getID();

		// Get the list of users and groups with read access to this document.
		if (!isset($this->_readAccessList)) {
			// TODO: error checking.
			$this->_readAccessList = $this->_document->getReadAccessList();
		}
		$approved = false;
		foreach ($this->_readAccessList["users"] as $appUser) {
			if ($userID == $appUser->getID()) {
				$approved = true;
				break;
			}
		}
		if (!$approved) {
			return -2;
		}

		// Check to see if the user has already been added to the approvers list.
		$approvalStatus = $user->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		$indstatus = false;
		if (count($approvalStatus["indstatus"]) > 0) {
			$indstatus = array_pop($approvalStatus["indstatus"]);
			if($indstatus["status"]!=-2) {
				// User is already on the list of approverss; return an error.
				return -3;
			}
		}

		if ( $indstatus || (isset($indstatus["status"]) && $indstatus["status"]!=-2)) {
			// Add the user into the approvers database.
			$queryStr = "INSERT INTO `tblDocumentApprovers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '0', '". $userID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$approveID = $db->getInsertID();
		}
		else {
			$approveID = isset($indstatus["approveID"]) ? $indstatus["approveID"] : NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $approveID ."', '0', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		$approveLogID = $db->getInsertID();
		return $approveLogID;
	} /* }}} */

	function addGrpApprover($group, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Get the list of users and groups with read access to this document.
		if (!isset($this->_readAccessList)) {
			// TODO: error checking.
			$this->_readAccessList = $this->_document->getReadAccessList();
		}
		$approved = false;
		foreach ($this->_readAccessList["groups"] as $appGroup) {
			if ($groupID == $appGroup->getID()) {
				$approved = true;
				break;
			}
		}
		if (!$approved) {
			return -2;
		}

		// Check to see if the group has already been added to the approver list.
		$approvalStatus = $group->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus) > 0 && $approvalStatus[0]["status"]!=-2) {
			// Group is already on the list of approvers; return an error.
			return -3;
		}

		// Add the group into the approver database.
		if (!isset($approvalStatus[0]["status"]) || (isset($approvalStatus[0]["status"]) && $approvalStatus[0]["status"]!=-2)) {
			$queryStr = "INSERT INTO `tblDocumentApprovers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '1', '". $groupID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$approveID = $db->getInsertID();
		}
		else {
			$approveID = isset($approvalStatus[0]["approveID"])?$approvalStatus[0]["approveID"]:NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $approveID ."', '0', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		// Add approver to event notification table.
		//$this->_document->addNotify($groupID, false);

		$approveLogID = $db->getInsertID();
		return $approveLogID;
	} /* }}} */

	/**
	 * Sets approval status of a document content for a user
	 * This function can be used to approve or reject a document content, or
	 * to reset its approval state. The user initiating the approval may
	 * not be the user filled in as an approver of the document content.
	 * In most cases this will be but an admin may set the approval for
	 * somebody else.
	 * It is first checked if the user is in the list of approvers at all.
	 * Then it is check if the approval status is already -2. In both cases
	 * the function returns with an error.
	 *
	 * @see LetoDMS_Core_DocumentContent::setReviewByInd()
	 * @param object $user user in charge for doing the approval
	 * @param object $requestUser user actually calling this function
	 * @param integer $status the status of the approval, possible values are
	 *        0=unprocessed (maybe used to reset a status)
	 *        1=approved,
	 *       -1=rejected,
	 *       -2=user is deleted (use {link
	 *       LetoDMS_Core_DocumentContent::delIndApprover} instead)
	 * @param string $comment approval comment
	 * @return integer 0 on success, < 0 in case of an error
	 */
	function setApprovalByInd($user, $requestUser, $status, $comment) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the approval list.
		$approvalStatus = $user->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus["indstatus"])==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -3;
		}
		$indstatus = array_pop($approvalStatus["indstatus"]);
		if ($indstatus["status"]==-2) {
			// User has been deleted from approvers
			return -4;
		}
		// Check if the status is really different from the current status
		if ($indstatus["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`,
  	  `comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["approveID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", CURRENT_TIMESTAMP, '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;
		else
			return 0;
 } /* }}} */

	/**
	 * Sets approval status of a document content for a group
	 * The functions behaves like
	 * {link LetoDMS_Core_DocumentContent::setApprovalByInd} but does it for
	 * group instead of a user
	 */
	function setApprovalByGrp($group, $requestUser, $status, $comment) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the approval list.
		$approvalStatus = $group->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus)==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -3;
		}
		if ($approvalStatus[0]["status"]==-2) {
			// Group has been deleted from approvers
			return -4;
		}

		// Check if the status is really different from the current status
		if ($approvalStatus[0]["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`,
  	  `comment`, `date`, `userID`) ".
			"VALUES ('". $approvalStatus[0]["approveID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", CURRENT_TIMESTAMP, '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;
		else
			return 0;
 } /* }}} */

	function delIndReviewer($user, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$userID = $user->getID();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $user->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus["indstatus"])==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -3;
		}
		$indstatus = array_pop($reviewStatus["indstatus"]);
		if ($indstatus["status"]!=0) {
			// User has already submitted a review or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["reviewID"] ."', '-2', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	function delGrpReviewer($group, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $group->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus)==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -3;
		}
		if ($reviewStatus[0]["status"]!=0) {
			// User has already submitted a review or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $reviewStatus[0]["reviewID"] ."', '-2', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	function delIndApprover($user, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$userID = $user->getID();

		// Check to see if the user can be removed from the approval list.
		$approvalStatus = $user->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus["indstatus"])==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -3;
		}
		$indstatus = array_pop($approvalStatus["indstatus"]);
		if ($indstatus["status"]!=0) {
			// User has already submitted an approval or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["approveID"] ."', '-2', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	function delGrpApprover($group, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Check to see if the user can be removed from the approver list.
		$approvalStatus = $group->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus)==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -3;
		}
		if ($approvalStatus[0]["status"]!=0) {
			// User has already submitted an approval or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $approvalStatus[0]["approveID"] ."', '-2', '', CURRENT_TIMESTAMP, '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	/**
	 * Set state of workflow assigned to the document content
	 *
	 * @param object $state
	 */
	function setWorkflowState($state) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if($this->_workflow) {
			$queryStr = "UPDATE tblWorkflowDocumentContent set state=". $state->getID() ." WHERE workflow=". intval($this->_workflow->getID()). " AND document=". intval($this->_document->getID()) ." AND version=". intval($this->_version) ."";
			if (!$db->getResult($queryStr)) {
				return false;
			}
			$this->_workflowState = $state;
			return true;
		}
		return false;
	} /* }}} */

	/**
	 * Get state of workflow assigned to the document content
	 *
	 * @return object/boolean an object of class LetoDMS_Core_Workflow_State
	 *         or false in case of error, e.g. the version has not a workflow
	 */
	function getWorkflowState() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if (!$this->_workflowState) {
			$queryStr=
				"SELECT b.* FROM tblWorkflowDocumentContent a LEFT JOIN tblWorkflowStates b ON a.state = b.id WHERE workflow=". intval($this->_workflow->getID())
				." AND a.version='".$this->_version
				."' AND a.document = '". $this->_document->getID() ."' ";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			$this->_workflowState = new LetoDMS_Core_Workflow_State($recs[0]['id'], $recs[0]['name'], $recs[0]['maxtime'], $recs[0]['precondfunc'], $recs[0]['documentstatus']); 
			$this->_workflowState->setDMS($this->_document->_dms);
		}
		return $this->_workflowState;
	} /* }}} */

	/**
	 * Assign a workflow to a document
	 *
	 * @param object $workflow
	 */
	function setWorkflow($workflow, $user) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$this->getWorkflow();
		if($workflow && is_object($workflow)) {
			$db->startTransaction();
			$initstate = $workflow->getInitState();
			$queryStr = "INSERT INTO tblWorkflowDocumentContent (workflow, document, version, state, date) VALUES (". $workflow->getID(). ", ". $this->_document->getID() .", ". $this->_version .", ".$initstate->getID().", CURRENT_TIMESTAMP)";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
			$this->_workflow = $workflow;	
			if(!$this->setStatus(S_IN_WORKFLOW, "Added workflow '".$workflow->getName()."'", $user)) {
				$db->rollbackTransaction();
				return false;
			}
			$db->commitTransaction();
			return true;
		}
		return true;
	} /* }}} */

	/**
	 * Get workflow assigned to the document content
	 *
	 * The method returns the last sub workflow if one was assigned.
	 *
	 * @return object/boolean an object of class LetoDMS_Core_Workflow
	 *         or false in case of error, e.g. the version has not a workflow
	 */
	function getWorkflow() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!isset($this->_workflow)) {
			$queryStr=
				"SELECT b.* FROM tblWorkflowDocumentContent a LEFT JOIN tblWorkflows b ON a.workflow = b.id WHERE a.`version`='".$this->_version
				."' AND a.`document` = '". $this->_document->getID() ."' "
				." LIMIT 1";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			if(!$recs)
				return false;
			$this->_workflow = new LetoDMS_Core_Workflow($recs[0]['id'], $recs[0]['name'], $this->_document->_dms->getWorkflowState($recs[0]['initstate'])); 
			$this->_workflow->setDMS($this->_document->_dms);
		}
		return $this->_workflow;
	} /* }}} */

	/**
	 * Restart workflow from its initial state
	 *
	 * @return boolean true if workflow could be restarted
	 *         or false in case of error
	 */
	function rewindWorkflow() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$this->getWorkflow();

		if (!isset($this->_workflow)) {
			return true;
		}

		$db->startTransaction();
		$queryStr = "DELETE from tblWorkflowLog WHERE `document` = ". $this->_document->getID() ." AND `version` = ".$this->_version." AND `workflow` = ".$this->_workflow->getID();
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$this->setWorkflowState($this->_workflow->getInitState());
		$db->commitTransaction();

		return true;
	} /* }}} */

	/**
	 * Remove workflow
	 *
	 * Fully removing a workflow including entries in the workflow log is
	 * only allowed if the workflow is still its initial state.
	 * At a later point of time only unlinking the document from the
	 * workflow is allowed. It will keep any log entries.
	 * A workflow is unlinked from a document when enterNextState()
	 * succeeds.
	 *
	 * @param object $user user doing initiating the removal
	 * @param boolean $unlink if true, just unlink the workflow from the
	 *        document but do not remove the workflow log. The $unlink
	 *        flag has been added to detach the workflow from the document
	 *        when it has reached a valid end state
	          (see LetoDMS_Core_DocumentContent::enterNextState())
	 * @return boolean true if workflow could be removed
	 *         or false in case of error
	 */
	function removeWorkflow($user, $unlink=false) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$this->getWorkflow();

		if (!isset($this->_workflow)) {
			return true;
		}

		if(LetoDMS_Core_DMS::checkIfEqual($this->_workflow->getInitState(), $this->getWorkflowState()) || $unlink == true) {
			$db->startTransaction();
			$queryStr=
				"DELETE FROM tblWorkflowDocumentContent WHERE "
				."`version`='".$this->_version."' "
				." AND `document` = '". $this->_document->getID() ."' "
				." AND `workflow` = '". $this->_workflow->getID() ."' ";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
			if(!$unlink) {
				$queryStr=
					"DELETE FROM tblWorkflowLog WHERE "
					."`version`='".$this->_version."' "
					." AND `document` = '". $this->_document->getID() ."' "
					." AND `workflow` = '". $this->_workflow->getID() ."' ";
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}
			}
			$this->_workflow = null;
			$this->_workflowState = null;
			$this->verifyStatus(false, $user);
			$db->commitTransaction();
		}

		return true;
	} /* }}} */

	/**
	 * Run a sub workflow
	 *
	 * @param object $subworkflow
	 */
	function getParentWorkflow() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		/* document content must be in a workflow */
		$this->getWorkflow();
		if(!$this->_workflow)
			return false;

		$queryStr=
			"SELECT * FROM tblWorkflowDocumentContent WHERE "
			."`version`='".$this->_version."' "
			." AND `document` = '". $this->_document->getID() ."' "
			." AND `workflow` = '". $this->_workflow->getID() ."' ";
		$recs = $db->getResultArray($queryStr);
		if (is_bool($recs) && !$recs)
			return false;
		if(!$recs)
			return false;

		if($recs[0]['parentworkflow'])
			return $this->_document->_dms->getWorkflow($recs[0]['parentworkflow']);
		
		return false;
	} /* }}} */

	/**
	 * Run a sub workflow
	 *
	 * @param object $subworkflow
	 */
	function runSubWorkflow($subworkflow) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		/* document content must be in a workflow */
		$this->getWorkflow();
		if(!$this->_workflow)
			return false;

		/* The current workflow state must match the sub workflows initial state */
		if($subworkflow->getInitState()->getID() != $this->_workflowState->getID())
			return false;

		if($subworkflow) {
			$initstate = $subworkflow->getInitState();
			$queryStr = "INSERT INTO tblWorkflowDocumentContent (parentworkflow, workflow, document, version, state) VALUES (". $this->_workflow->getID(). ", ". $subworkflow->getID(). ", ". $this->_document->getID() .", ". $this->_version .", ".$initstate->getID().")";
			if (!$db->getResult($queryStr)) {
				return false;
			}
			$this->_workflow = $subworkflow;	
			return true;
		}
		return true;
	} /* }}} */

	/**
	 * Return from sub workflow to parent workflow.
	 * The method will trigger the given transition
	 *
	 * FIXME: Needs much better checking if this is allowed
	 *
	 * @param object $user intiating the return
	 * @param object $transtion to trigger
	 * @param string comment for the transition trigger
	 */
	function returnFromSubWorkflow($user, $transition=null, $comment='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		/* document content must be in a workflow */
		$this->getWorkflow();
		if(!$this->_workflow)
			return false;

		if (isset($this->_workflow)) {
			$db->startTransaction();

			$queryStr=
				"SELECT * FROM tblWorkflowDocumentContent WHERE workflow=". intval($this->_workflow->getID())
				. " AND `version`='".$this->_version
				."' AND `document` = '". $this->_document->getID() ."' ";
				echo $queryStr;
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs) {
				$db->rollbackTransaction();
				return false;
			}
			if(!$recs) {
				$db->rollbackTransaction();
				return false;
			}

			$queryStr = "DELETE FROM `tblWorkflowDocumentContent` WHERE `workflow` =". intval($this->_workflow->getID())." AND `document` = '". $this->_document->getID() ."' AND `version` = '" . $this->_version."'";
				echo $queryStr;
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}

			$this->_workflow = $this->_document->_dms->getWorkflow($recs[0]['parentworkflow']); 
			$this->_workflow->setDMS($this->_document->_dms);

			if($transition) {
			echo "Trigger transition";
				if(false === $this->triggerWorkflowTransition($user, $transition, $comment)) {
					$db->rollbackTransaction();
					return false;
				}
			}

			$db->commitTransaction();
		}
		return $this->_workflow;
	} /* }}} */

	/**
	 * Check if the user is allowed to trigger the transition
	 * A user is allowed if either the user itself or
	 * a group of which the user is a member of is registered for
	 * triggering a transition. This method does not change the workflow
	 * state of the document content.
	 *
	 * @param object $user
	 * @return boolean true if user may trigger transaction
	 */
	function triggerWorkflowTransitionIsAllowed($user, $transition) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if(!$this->_workflowState)
			$this->getWorkflowState();

		/* Check if the user has already triggered the transition */
		$queryStr=
			"SELECT * FROM tblWorkflowLog WHERE `version`='".$this->_version ."' AND `document` = '". $this->_document->getID() ."' AND `workflow` = ". $this->_workflow->getID(). " AND userid = ".$user->getID();
		$queryStr .= " AND `transition` = ".$transition->getID();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		if(count($resArr))
			return false;

		/* Get all transition users allowed to trigger the transition */
		$transusers = $transition->getUsers();
		if($transusers) {
			foreach($transusers as $transuser) {
				if($user->getID() == $transuser->getUser()->getID())
					return true;
			}
		}

		/* Get all transition groups whose members are allowed to trigger
		 * the transition */
		$transgroups = $transition->getGroups();
		if($transgroups) {
			foreach($transgroups as $transgroup) {
				$group = $transgroup->getGroup();
				if($group->isMember($user))
					return true;
			}
		}

		return false;
	} /* }}} */

	/**
	 * Check if all conditions are met to change the workflow state
	 * of a document content (run the transition).
	 * The conditions are met if all explicitly set users and a sufficient
	 * number of users of the groups have acknowledged the content.
	 *
	 * @return boolean true if transaction maybe executed
	 */
	function executeWorkflowTransitionIsAllowed($transition) { /* {{{ */
		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if(!$this->_workflowState)
			$this->getWorkflowState();

		/* Get the Log of transition triggers */
		$entries = $this->getWorkflowLog($transition);
		if(!$entries)
			return false;

		/* Get all transition users allowed to trigger the transition
		 * $allowedusers is a list of all users allowed to trigger the
		 * transition
		 */
		$transusers = $transition->getUsers();
		$allowedusers = array();
		foreach($transusers as $transuser) {
			$a = $transuser->getUser();
			$allowedusers[$a->getID()] = $a;
		}

		/* Get all transition groups whose members are allowed to trigger
		 * the transition */
		$transgroups = $transition->getGroups();
		foreach($entries as $entry) {
			$loguser = $entry->getUser();
			/* Unset each allowed user if it was found in the log */
			if(isset($allowedusers[$loguser->getID()]))
				unset($allowedusers[$loguser->getID()]);
			/* Also check groups if required. Count the group membership of
			 * each user in the log in the array $gg
			 */
			if($transgroups) {
				$loggroups = $loguser->getGroups();
				foreach($loggroups as $loggroup) {
					if(!isset($gg[$loggroup->getID()]))
						$gg[$loggroup->getID()] = 1;
					else
						$gg[$loggroup->getID()]++;
				}
			}
		}
		/* If there are allowed users left, then there some users still
		 * need to trigger the transition.
		 */
		if($allowedusers)
			return false;

		if($transgroups) {
			foreach($transgroups as $transgroup) {
				$group = $transgroup->getGroup();
				$minusers = $transgroup->getNumOfUsers();
				if(!isset($gg[$group->getID()]))
					return false;
				if($gg[$group->getID()] < $minusers)
					return false;
			}
		}
		return true;
	} /* }}} */

	/**
	 * Trigger transition
	 *
	 * This method will be deprecated
	 *
	 * The method will first check if the user is allowed to trigger the
	 * transition. If the user is allowed, an entry in the workflow log
	 * will be added, which is later used to check if the transition
	 * can actually be processed. The method will finally call
	 * executeWorkflowTransitionIsAllowed() which checks all log entries
	 * and does the transitions post function if all users and groups have
	 * triggered the transition. Finally enterNextState() is called which
	 * will try to enter the next state.
	 *
	 * @param object $user
	 * @param object $transition
	 * @param string $comment user comment
	 * @return boolean/object next state if transition could be triggered and
	 *         then next state could be entered,
	 *         true if the transition could just be triggered or
	 *         false in case of an error
	 */
	function triggerWorkflowTransition($user, $transition, $comment='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if(!$this->_workflowState)
			$this->getWorkflowState();

		if(!$this->_workflowState)
			return false;

		/* Check if the user is allowed to trigger the transition.
		 */
		if(!$this->triggerWorkflowTransitionIsAllowed($user, $transition))
			return false;

		$state = $this->_workflowState;
		$queryStr = "INSERT INTO tblWorkflowLog (document, version, workflow, userid, transition, date, comment) VALUES (".$this->_document->getID().", ".$this->_version.", " . (int) $this->_workflow->getID() . ", " .(int) $user->getID(). ", ".(int) $transition->getID().", CURRENT_TIMESTAMP, ".$db->qstr($comment).")";
		if (!$db->getResult($queryStr))
			return false;

		/* Check if this transition is processed. Run the post function in
		 * that case. A transition is processed when all users and groups
		 * have triggered it.
		 */
		if($this->executeWorkflowTransitionIsAllowed($transition)) {
			/* run post function of transition */
//			echo "run post function of transition ".$transition->getID()."<br />";
		}

		/* Go into the next state. This will only succeed if the pre condition
		 * function of that states succeeds.
		 */
		$nextstate = $transition->getNextState();
		if($this->enterNextState($user, $nextstate)) {
			return $nextstate;
		}
		return true;
		
	} /* }}} */

	/**
	 * Enter next state of workflow if possible
	 *
	 * The method will check if one of the following states in the workflow
	 * can be reached.
	 * It does it by running
	 * the precondition function of that state. The precondition function
	 * gets a list of all transitions leading to the state. It will
	 * determine, whether the transitions has been triggered and if that
	 * is sufficient to enter the next state. If no pre condition function
	 * is set, then 1 of n transtions are enough to enter the next state.
	 *
	 * If moving in the next state is possible and this state has a
	 * corresponding document state, then the document state will be
	 * updated and the workflow will be detached from the document.
	 *
	 * @param object $user
	 * @param object $nextstate
	 * @return boolean true if the state could be reached
	 *         false if not
	 */
	function enterNextState($user, $nextstate) { /* {{{ */

			/* run the pre condition of the next state. If it is not set
			 * the next state will be reached if one of the transitions
			 * leading to the given state can be processed.
			 */
			if($nextstate->getPreCondFunc() == '') {
				$transitions = $this->_workflow->getPreviousTransitions($nextstate);
				foreach($transitions as $transition) {
//				echo "transition ".$transition->getID()." led to state ".$nextstate->getName()."<br />";
					if($this->executeWorkflowTransitionIsAllowed($transition)) {
//					echo "stepping into next state<br />";
						$this->setWorkflowState($nextstate);

						/* Check if the new workflow state has a mapping into a
						 * document state. If yes, set the document state will
						 * be updated and the workflow will be removed from the
						 * document.
						 */
						$docstate = $nextstate->getDocumentStatus();
						if($docstate == S_RELEASED || $docstate == S_REJECTED) {
							$this->setStatus($docstate, "Workflow has ended", $user);
							/* Detach the workflow from the document, but keep the
							 * workflow log
							 */
							$this->removeWorkflow($user, true);
							return true ;
						}

						/* make sure the users and groups allowed to trigger the next
						 * transitions are also allowed to read the document
						 */
						$transitions = $this->_workflow->getNextTransitions($nextstate);
						foreach($transitions as $tran) {
//							echo "checking access for users/groups allowed to trigger transition ".$tran->getID()."<br />";
							$transusers = $tran->getUsers();
							foreach($transusers as $transuser) {
								$u = $transuser->getUser();
//								echo $u->getFullName()."<br />";
								if($this->_document->getAccessMode($u) < M_READ) {
									$this->_document->addAccess(M_READ, $u->getID(), 1);
//									echo "granted read access<br />";
								} else {
//									echo "has already access<br />";
								}
							}
							$transgroups = $tran->getGroups();
							foreach($transgroups as $transgroup) {
								$g = $transgroup->getGroup();
//								echo $g->getName()."<br />";
								if ($this->_document->getGroupAccessMode($g) < M_READ) {
									$this->_document->addAccess(M_READ, $g->getID(), 0);
//									echo "granted read access<br />";
								} else {
//									echo "has already access<br />";
								}
							}
						}
						return(true);
					} else {
//						echo "transition not ready for process now<br />";
					}
				}
				return false;
			} else {
			}

	} /* }}} */

	/**
	 * Get the so far logged operations on the document content within the
	 * workflow
	 *
	 * @return array list of operations
	 */
	function getWorkflowLog($transition = null) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		$queryStr=
			"SELECT * FROM tblWorkflowLog WHERE `version`='".$this->_version ."' AND `document` = '". $this->_document->getID() ."' AND `workflow` = ". $this->_workflow->getID();
		if($transition)
			$queryStr .= " AND `transition` = ".$transition->getID();
		$queryStr .= " ORDER BY `date`";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$workflowlogs = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$workflowlog = new LetoDMS_Core_Workflow_Log($resArr[$i]["id"], $this->_document->_dms->getDocument($resArr[$i]["document"]), $resArr[$i]["version"], $this->_workflow, $this->_document->_dms->getUser($resArr[$i]["userid"]), $this->_workflow->getTransition($resArr[$i]["transition"]), $resArr[$i]["date"], $resArr[$i]["comment"]);
			$workflowlog->setDMS($this);
			$workflowlogs[$i] = $workflowlog;
		}

		return $workflowlogs;
	} /* }}} */

	/**
	 * Get the latest logged transition for the document content within the
	 * workflow
	 *
	 * @return array list of operations
	 */
	function getLastWorkflowTransition() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		$queryStr=
			"SELECT * FROM tblWorkflowLog WHERE `version`='".$this->_version ."' AND `document` = '". $this->_document->getID() ."' AND `workflow` = ". $this->_workflow->getID();
		$queryStr .= " ORDER BY `id` DESC LIMIT 1";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$workflowlogs = array();
		$i = 0;
		$workflowlog = new LetoDMS_Core_Workflow_Log($resArr[$i]["id"], $this->_document->_dms->getDocument($resArr[$i]["document"]), $resArr[$i]["version"], $this->_workflow, $this->_document->_dms->getUser($resArr[$i]["userid"]), $this->_workflow->getTransition($resArr[$i]["transition"]), $resArr[$i]["date"], $resArr[$i]["comment"]);
		$workflowlog->setDMS($this);

		return $workflowlog;
	} /* }}} */

} /* }}} */


/**
 * Class to represent a link between two document
 *
 * Document links are to establish a reference from one document to
 * another document. The owner of the document link may not be the same
 * as the owner of one of the documents.
 * Use {@link LetoDMS_Core_Document::addDocumentLink()} to add a reference
 * to another document.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DocumentLink { /* {{{ */
	/**
	 * @var integer internal id of document link
	 */
	protected $_id;

	/**
	 * @var object reference to document this link belongs to
	 */
	protected $_document;

	/**
	 * @var object reference to target document this link points to
	 */
	protected $_target;

	/**
	 * @var integer id of user who is the owner of this link
	 */
	protected $_userID;

	/**
	 * @var integer 1 if this link is public, or 0 if is only visible to the owner
	 */
	protected $_public;

	function LetoDMS_Core_DocumentLink($id, $document, $target, $userID, $public) {
		$this->_id = $id;
		$this->_document = $document;
		$this->_target = $target;
		$this->_userID = $userID;
		$this->_public = $public;
	}

	function getID() { return $this->_id; }

	function getDocument() {
		return $this->_document;
	}

	function getTarget() {
		return $this->_target;
	}

	function getUser() {
		if (!isset($this->_user))
			$this->_user = $this->_document->_dms->getUser($this->_userID);
		return $this->_user;
	}

	function isPublic() { return $this->_public; }

} /* }}} */

/**
 * Class to represent a file attached to a document
 *
 * Beside the regular document content arbitrary files can be attached
 * to a document. This is a similar concept as attaching files to emails.
 * The owner of the attached file and the document may not be the same.
 * Use {@link LetoDMS_Core_Document::addDocumentFile()} to attach a file.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DocumentFile { /* {{{ */
	/**
	 * @var integer internal id of document file
	 */
	protected $_id;

	/**
	 * @var object reference to document this file belongs to
	 */
	protected $_document;

	/**
	 * @var integer id of user who is the owner of this link
	 */
	protected $_userID;

	/**
	 * @var string comment for the attached file
	 */
	protected $_comment;

	/**
	 * @var string date when the file was attached
	 */
	protected $_date;

	/**
	 * @var string directory where the file is stored. This is the
	 * document id with a proceding '/'.
	 * FIXME: looks like this isn't used anymore. The file path is
	 * constructed by getPath()
	 */
	protected $_dir;

	/**
	 * @var string extension of the original file name with a leading '.'
	 */
	protected $_fileType;

	/**
	 * @var string mime type of the file
	 */
	protected $_mimeType;

	/**
	 * @var string name of the file that was originally uploaded
	 */
	protected $_orgFileName;

	/**
	 * @var string name of the file as given by the user
	 */
	protected $_name;

	function LetoDMS_Core_DocumentFile($id, $document, $userID, $comment, $date, $dir, $fileType, $mimeType, $orgFileName,$name) {
		$this->_id = $id;
		$this->_document = $document;
		$this->_userID = $userID;
		$this->_comment = $comment;
		$this->_date = $date;
		$this->_dir = $dir;
		$this->_fileType = $fileType;
		$this->_mimeType = $mimeType;
		$this->_orgFileName = $orgFileName;
		$this->_name = $name;
	}

	function getID() { return $this->_id; }
	function getDocument() { return $this->_document; }
	function getUserID() { return $this->_userID; }
	function getComment() { return $this->_comment; }
	function getDate() { return $this->_date; }
	function getDir() { return $this->_dir; }
	function getFileType() { return $this->_fileType; }
	function getMimeType() { return $this->_mimeType; }
	function getOriginalFileName() { return $this->_orgFileName; }
	function getName() { return $this->_name; }

	function getUser() {
		if (!isset($this->_user))
			$this->_user = $this->_document->_dms->getUser($this->_userID);
		return $this->_user;
	}

	function getPath() {
		return $this->_document->getDir() . "f" .$this->_id . $this->_fileType;
	}

} /* }}} */

//
// Perhaps not the cleanest object ever devised, it exists to encapsulate all
// of the data generated during the addition of new content to the database.
// The object stores a copy of the new DocumentContent object, the newly assigned
// reviewers and approvers and the status.
//
/**
 * Class to represent a list of document contents
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_AddContentResultSet { /* {{{ */

	protected $_indReviewers;
	protected $_grpReviewers;
	protected $_indApprovers;
	protected $_grpApprovers;
	protected $_content;
	protected $_status;

	function LetoDMS_Core_AddContentResultSet($content) { /* {{{ */
		$this->_content = $content;
		$this->_indReviewers = null;
		$this->_grpReviewers = null;
		$this->_indApprovers = null;
		$this->_grpApprovers = null;
		$this->_status = null;
	} /* }}} */

	function addReviewer($reviewer, $type, $status) { /* {{{ */

		if (!is_object($reviewer) || (strcasecmp($type, "i") && strcasecmp($type, "g")) && !is_integer($status)){
			return false;
		}
		if (!strcasecmp($type, "i")) {
			if (strcasecmp(get_class($reviewer), "LetoDMS_Core_User")) {
				return false;
			}
			if ($this->_indReviewers == null) {
				$this->_indReviewers = array();
			}
			$this->_indReviewers[$status][] = $reviewer;
		}
		if (!strcasecmp($type, "g")) {
			if (strcasecmp(get_class($reviewer), "LetoDMS_Core_Group")) {
				return false;
			}
			if ($this->_grpReviewers == null) {
				$this->_grpReviewers = array();
			}
			$this->_grpReviewers[$status][] = $reviewer;
		}
		return true;
	} /* }}} */

	function addApprover($approver, $type, $status) { /* {{{ */

		if (!is_object($approver) || (strcasecmp($type, "i") && strcasecmp($type, "g")) && !is_integer($status)){
			return false;
		}
		if (!strcasecmp($type, "i")) {
			if (strcasecmp(get_class($approver), "LetoDMS_Core_User")) {
				return false;
			}
			if ($this->_indApprovers == null) {
				$this->_indApprovers = array();
			}
			$this->_indApprovers[$status][] = $approver;
		}
		if (!strcasecmp($type, "g")) {
			if (strcasecmp(get_class($approver), "LetoDMS_Core_Group")) {
				return false;
			}
			if ($this->_grpApprovers == null) {
				$this->_grpApprovers = array();
			}
			$this->_grpApprovers[$status][] = $approver;
		}
		return true;
	} /* }}} */

	function setStatus($status) { /* {{{ */
		if (!is_integer($status)) {
			return false;
		}
		if ($status<-3 || $status>2) {
			return false;
		}
		$this->_status = $status;
		return true;
	} /* }}} */

	function getStatus() { /* {{{ */
		return $this->_status;
	} /* }}} */

	function getContent() { /* {{{ */
		return $this->_content;
	} /* }}} */

	function getReviewers($type) { /* {{{ */
		if (strcasecmp($type, "i") && strcasecmp($type, "g")) {
			return false;
		}
		if (!strcasecmp($type, "i")) {
			return ($this->_indReviewers == null ? array() : $this->_indReviewers);
		}
		else {
			return ($this->_grpReviewers == null ? array() : $this->_grpReviewers);
		}
	} /* }}} */

	function getApprovers($type) { /* {{{ */
		if (strcasecmp($type, "i") && strcasecmp($type, "g")) {
			return false;
		}
		if (!strcasecmp($type, "i")) {
			return ($this->_indApprovers == null ? array() : $this->_indApprovers);
		}
		else {
			return ($this->_grpApprovers == null ? array() : $this->_grpApprovers);
		}
	} /* }}} */
} /* }}} */
?>
