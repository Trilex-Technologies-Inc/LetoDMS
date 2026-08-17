<?php
/**
 * Implementation of the user object in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to represent a user in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_User {
	/**
	 * @var integer id of user
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var string login name of user
	 *
	 * @access protected
	 */
	var $_login;

	/**
	 * @var string password of user as saved in database (md5)
	 *
	 * @access protected
	 */
	var $_pwd;

	/**
	 * @var string full human readable name of user
	 *
	 * @access protected
	 */
	var $_fullName;

	/**
	 * @var string email address of user
	 *
	 * @access protected
	 */
	var $_email;

	/**
	 * @var string prefered language of user
	 *      possible values are 'English', 'German', 'Chinese_ZH_TW', 'Czech'
	 *      'Francais', 'Hungarian', 'Italian', 'Portuguese_BR', 'Slovak', 
	 *      'Spanish'
	 *
	 * @access protected
	 */
	var $_language;

	/**
	 * @var string preselected theme of user
	 *
	 * @access protected
	 */
	var $_theme;

	/**
	 * @var string comment of user
	 *
	 * @access protected
	 */
	var $_comment;

	/**
	 * @var string role of user. Can be one of LetoDMS_Core_User::role_user,
	 *      LetoDMS_Core_User::role_admin, LetoDMS_Core_User::role_guest
	 *
	 * @access protected
	 */
	var $_role;

	/**
	 * @var boolean true if user shall be hidden
	 *
	 * @access protected
	 */
	var $_isHidden;

	/**
	 * @var object reference to the dms instance this user belongs to
	 *
	 * @access protected
	 */
	var $_dms;

	const role_user = '0';
	const role_admin = '1';
	const role_guest = '2';

	function LetoDMS_Core_User($id, $login, $pwd, $fullName, $email, $language, $theme, $comment, $role, $isHidden=0) {
		$this->_id = $id;
		$this->_login = $login;
		$this->_pwd = $pwd;
		$this->_fullName = $fullName;
		$this->_email = $email;
		$this->_language = $language;
		$this->_theme = $theme;
		$this->_comment = $comment;
		$this->_role = $role;
		$this->_isHidden = $isHidden;
		$this->_dms = null;
	}

	function setDMS($dms) {
		$this->_dms = $dms;
	}

	function getID() { return $this->_id; }

	function getLogin() { return $this->_login; }

	function setLogin($newLogin) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET login =".$db->qstr($newLogin)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_login = $newLogin;
		return true;
	} /* }}} */

	function getFullName() { return $this->_fullName; }

	function setFullName($newFullName) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET fullname = ".$db->qstr($newFullName)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_fullName = $newFullName;
		return true;
	} /* }}} */

	function getPwd() { return $this->_pwd; }

	function setPwd($newPwd) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET pwd =".$db->qstr($newPwd)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_pwd = $newPwd;
		return true;
	} /* }}} */

	function getEmail() { return $this->_email; }

	function setEmail($newEmail) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET email =".$db->qstr($newEmail)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_email = $newEmail;
		return true;
	} /* }}} */

	function getLanguage() { return $this->_language; }

	function setLanguage($newLanguage) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET language =".$db->qstr($newLanguage)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_language = $newLanguage;
		return true;
	} /* }}} */

	function getTheme() { return $this->_theme; }

	function setTheme($newTheme) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET theme =".$db->qstr($newTheme)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_theme = $newTheme;
		return true;
	} /* }}} */

	function getComment() { return $this->_comment; }

	function setComment($newComment) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET comment =".$db->qstr($newComment)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_comment = $newComment;
		return true;
	} /* }}} */

	function getRole() { return $this->_role; }

	function setRole($newrole) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET role = " . $newrole . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_role = $newrole;
		return true;
	} /* }}} */

	function isAdmin() { return ($this->_role == LetoDMS_Core_User::role_admin); }

	function setAdmin($isAdmin) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET role = " . LetoDMS_Core_User::role_admin . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_role = LetoDMS_Core_User::role_admin;
		return true;
	} /* }}} */

	function isGuest() { return ($this->_role == LetoDMS_Core_User::role_guest); }

	function setGuest($isGuest) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblUsers SET role = " . LetoDMS_Core_User::role_guest . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_role = LetoDMS_Core_User::role_guest;
		return true;
	} /* }}} */

	function isHidden() { return $this->_isHidden; }

	function setHidden($isHidden) { /* {{{ */
		$db = $this->_dms->getDB();

		$isHidden = ($isHidden) ? "1" : "0";
		$queryStr = "UPDATE tblUsers SET hidden = " . $isHidden . " WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_isHidden = $isHidden;
		return true;
	}	 /* }}} */

	/**
	 * Remove the user and also remove all its keywords, notifies, etc.
	 * Do not remove folders and documents of the user, but assign them
	 * to a different user.
	 *
	 * @param object $user the user doing the removal (needed for entry in
	 *        review and approve log).
	 * @param object $assignToUser the user who is new owner of folders and
	 *        documents which previously were owned by the delete user.
	 * @return boolean true on success or false in case of an error
	 */
	function remove($user, $assignToUser=null) { /* {{{ */
		$db = $this->_dms->getDB();

		/* Records like folders and documents that formely have belonged to
		 * the user will assign to another user. If no such user is set,
		 * the function now returns false and will not use the admin user
		 * anymore.
		 */
		if(!$assignToUser)
			return;
		$assignTo = $assignToUser->getID();

		// delete private keyword lists
		$queryStr = "SELECT tblKeywords.id FROM tblKeywords, tblKeywordCategories WHERE tblKeywords.category = tblKeywordCategories.id AND tblKeywordCategories.owner = " . $this->_id;
		$resultArr = $db->getResultArray($queryStr);
		if (count($resultArr) > 0) {
			$queryStr = "DELETE FROM tblKeywords WHERE ";
			for ($i = 0; $i < count($resultArr); $i++) {
				$queryStr .= "id = " . $resultArr[$i]["id"];
				if ($i + 1 < count($resultArr))
					$queryStr .= " OR ";
			}
			if (!$db->getResult($queryStr))	return false;
		}

		$queryStr = "DELETE FROM tblKeywordCategories WHERE owner = " . $this->_id;
		if (!$db->getResult($queryStr))	return false;

		//Benachrichtigungen entfernen
		$queryStr = "DELETE FROM tblNotify WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		/* Assign documents of the removed user to the given user */
		$queryStr = "UPDATE tblFolders SET owner = " . $assignTo . " WHERE owner = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		$queryStr = "UPDATE tblDocuments SET owner = " . $assignTo . " WHERE owner = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		$queryStr = "UPDATE tblDocumentContent SET createdBy = " . $assignTo . " WHERE createdBy = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// Remove private links on documents ...
		$queryStr = "DELETE FROM tblDocumentLinks WHERE userID = " . $this->_id . " AND public = 0";
		if (!$db->getResult($queryStr)) return false;

		// ... but keep public links
		$queryStr = "UPDATE tblDocumentLinks SET userID = " . $assignTo . " WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// set administrator for deleted user's attachments
		$queryStr = "UPDATE tblDocumentFiles SET userID = " . $assignTo . " WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		//Evtl. von diesem Benutzer gelockte Dokumente werden freigegeben
		$queryStr = "DELETE FROM tblDocumentLocks WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// Delete user from all groups
		$queryStr = "DELETE FROM tblGroupMembers WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// User aus allen ACLs streichen
		$queryStr = "DELETE FROM tblACLs WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// Delete image of user
		$queryStr = "DELETE FROM tblUserImages WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// Delete user itself
		$queryStr = "DELETE FROM tblUsers WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// mandatory review/approve
		$queryStr = "DELETE FROM tblMandatoryReviewers WHERE reviewerUserID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		$queryStr = "DELETE FROM tblMandatoryApprovers WHERE approverUserID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		$queryStr = "DELETE FROM tblMandatoryReviewers WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		$queryStr = "DELETE FROM tblMandatoryApprovers WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;

		// set administrator for deleted user's events
		$queryStr = "UPDATE tblEvents SET userID = " . $assignTo . " WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;


		// TODO : update document status if reviewer/approver has been deleted
		// "DELETE FROM tblDocumentApproveLog WHERE userID = " . $this->_id;
		// "DELETE FROM tblDocumentReviewLog WHERE userID = " . $this->_id;


		$reviewStatus = $this->getReviewStatus();
		foreach ($reviewStatus["indstatus"] as $ri) {
			$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
				"VALUES ('". $ri["reviewID"] ."', '-2', 'Reviewer removed from process', NOW(), '". $user->getID() ."')";
			$res=$db->getResult($queryStr);
		}

		$approvalStatus = $this->getApprovalStatus();
		foreach ($approvalStatus["indstatus"] as $ai) {
			$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
				"VALUES ('". $ai["approveID"] ."', '-2', 'Approver removed from process', NOW(), '". $user->getID() ."')";
			$res=$db->getResult($queryStr);
		}

//		unset($this);
		return true;
	} /* }}} */

	/**
	 * Make the user a member of a group
	 * This function uses {@link LetoDMS_Group::addUser} but checks before if
	 * the user is already a member of the group.
	 *
	 * @param object $group group to be the member of
	 * @return boolean true on success or false in case of an error or the user
	 *        is already a member of the group
	 */
	function joinGroup($group) { /* {{{ */
		if ($group->isMember($this))
			return false;

		if (!$group->addUser($this))
			return false;

		unset($this->_groups);
		return true;
	} /* }}} */

	/**
	 * Removes the user from a group
	 * This function uses {@link LetoDMS_Group::removeUser} but checks before if
	 * the user is a member of the group at all.
	 *
	 * @param object $group group to leave
	 * @return boolean true on success or false in case of an error or the user
	 *        is not a member of the group
	 */
	function leaveGroup($group) { /* {{{ */
		if (!$group->isMember($this))
			return false;

		if (!$group->removeUser($this))
			return false;

		unset($this->_groups);
		return true;
	} /* }}} */

	/**
	 * Get all groups the user is a member of
	 *
	 * @return array list of groups
	 */
	function getGroups() { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_groups))
		{
			$queryStr = "SELECT `tblGroups`.*, `tblGroupMembers`.`userID` FROM `tblGroups` ".
				"LEFT JOIN `tblGroupMembers` ON `tblGroups`.`id` = `tblGroupMembers`.`groupID` ".
				"WHERE `tblGroupMembers`.`userID`='". $this->_id ."'";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && $resArr == false)
				return false;

			$this->_groups = array();
			foreach ($resArr as $row) {
				$group = new LetoDMS_Core_Group($row["id"], $row["name"], $row["comment"]);
				$group->setDMS($this->_dms);
				array_push($this->_groups, $group);
			}
		}
		return $this->_groups;
	} /* }}} */

	/**
	 * Checks if user is member of a given group
	 *
	 * @param object $group
	 * @return boolean true if user is member of the given group otherwise false
	 */
	function isMemberOfGroup($group) { /* {{{ */
		return $group->isMember($this);
	} /* }}} */

	/**
	 * Check if user has an image in its profile
	 *
	 * @return boolean true if user has a picture of itself
	 */
	function hasImage() { /* {{{ */
		if (!isset($this->_hasImage)) {
			$db = $this->_dms->getDB();

			$queryStr = "SELECT COUNT(*) AS num FROM tblUserImages WHERE userID = " . $this->_id;
			$resArr = $db->getResultArray($queryStr);
			if ($resArr === false)
				return false;

			if ($resArr[0]["num"] == 0)	$this->_hasImage = false;
			else $this->_hasImage = true;
		}

		return $this->_hasImage;
	} /* }}} */

	/**
	 * Get the image from the users profile
	 *
	 * @return array image data
	 */
	function getImage() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM tblUserImages WHERE userID = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if ($resArr === false)
			return false;

		if($resArr)
			$resArr = $resArr[0];
		return $resArr;
	} /* }}} */

	function setImage($tmpfile, $mimeType) { /* {{{ */
		$db = $this->_dms->getDB();

		$fp = fopen($tmpfile, "rb");
		if (!$fp) return false;
		$content = fread($fp, filesize($tmpfile));
		fclose($fp);

		if ($this->hasImage())
			$queryStr = "UPDATE tblUserImages SET image = '".base64_encode($content)."', mimeType = ".$db->qstr($mimeType)." WHERE userID = " . $this->_id;
		else
			$queryStr = "INSERT INTO tblUserImages (userID, image, mimeType) VALUES (" . $this->_id . ", '".base64_encode($content)."', ".$db->qstr($mimeType).")";
		if (!$db->getResult($queryStr))
			return false;

		$this->_hasImage = true;
		return true;
	} /* }}} */

	/**
	 * Get a list of reviews
	 * This function returns a list of all reviews seperated by individual
	 * and group reviews. If the document id
	 * is passed, then only this document will be checked for approvals. The
	 * same is true for the version of a document which limits the list
	 * further.
	 *
	 * For a detaile description of the result array see
	 * {link LetoDMS_Core_User::getApprovalStatus}
	 *
	 * @param int $documentID optional document id for which to retrieve the
	 *        reviews
	 * @param int $version optional version of the document
	 * @return array list of all reviews
	 */
	function getReviewStatus($documentID=null, $version=null) { /* {{{ */
		$db = $this->_dms->getDB();

/*
		if (!$db->createTemporaryTable("ttreviewid")) {
			return false;
		}
*/
		$status = array("indstatus"=>array(), "grpstatus"=>array());

		// See if the user is assigned as an individual reviewer.
		$queryStr = "SELECT `tblDocumentReviewers`.*, `tblDocumentReviewLog`.`status`, ".
			"`tblDocumentReviewLog`.`comment`, `tblDocumentReviewLog`.`date`, ".
			"`tblDocumentReviewLog`.`userID` ".
			"FROM `tblDocumentReviewers` ".
			"LEFT JOIN `tblDocumentReviewLog` USING (`reviewID`) ".
			"WHERE `tblDocumentReviewers`.`type`='0' ".
			($documentID==null ? "" : "AND `tblDocumentReviewers`.`documentID` = '". (int) $documentID ."' ").
			($version==null ? "" : "AND `tblDocumentReviewers`.`version` = '". (int) $version ."' ").
			"AND `tblDocumentReviewers`.`required`='". $this->_id ."' ".
			"ORDER BY `tblDocumentReviewLog`.`reviewLogID` DESC";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr)>0) {
			foreach ($resArr as $res) {
				if(isset($status["indstatus"][$res['documentID']])) {
					if($status["indstatus"][$res['documentID']]['date'] < $res['date']) {
						$status["indstatus"][$res['documentID']] = $res;
					}
				} else {
					$status["indstatus"][$res['documentID']] = $res;
				}
			}
		}

		// See if the user is the member of a group that has been assigned to
		// review the document version.
		$queryStr = "SELECT `tblDocumentReviewers`.*, `tblDocumentReviewLog`.`status`, ".
			"`tblDocumentReviewLog`.`comment`, `tblDocumentReviewLog`.`date`, ".
			"`tblDocumentReviewLog`.`userID` ".
			"FROM `tblDocumentReviewers` ".
			"LEFT JOIN `tblDocumentReviewLog` USING (`reviewID`) ".
			"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`groupID` = `tblDocumentReviewers`.`required` ".
			"WHERE `tblDocumentReviewers`.`type`='1' ".
			($documentID==null ? "" : "AND `tblDocumentReviewers`.`documentID` = '". (int) $documentID ."' ").
			($version==null ? "" : "AND `tblDocumentReviewers`.`version` = '". (int) $version ."' ").
			"AND `tblGroupMembers`.`userID`='". $this->_id ."' ".
			"ORDER BY `tblDocumentReviewLog`.`reviewLogID` DESC";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr)>0) {
			foreach ($resArr as $res) {
				if(isset($status["grpstatus"][$res['documentID']])) {
					if($status["grpstatus"][$res['documentID']]['date'] < $res['date']) {
						$status["grpstatus"][$res['documentID']] = $res;
					}
				} else {
					$status["grpstatus"][$res['documentID']] = $res;
				}
			}
		}
		return $status;
	} /* }}} */

	/**
	 * Get a list of approvals
	 * This function returns a list of all approvals seperated by individual
	 * and group approvals. If the document id
	 * is passed, then only this document will be checked for approvals. The
	 * same is true for the version of a document which limits the list
	 * further.
	 *
	 * The result array has two elements:
	 * - indstatus: which contains the approvals by individuals (users)
	 * - grpstatus: which contains the approvals by groups
	 *
	 * Each element is itself an array of approvals with the following elements:
	 * - approveID: unique id of approval
	 * - documentID: id of document, that needs to be approved
	 * - version: version of document, that needs to be approved
	 * - type: 0 for individual approval, 1 for group approval
	 * - required: id of user who is required to do the approval
	 * - status: 0 not approved, ....
	 * - comment: comment given during approval
	 * - date: date of approval
	 * - userID: id of user who has done the approval
	 *
	 * @param int $documentID optional document id for which to retrieve the
	 *        approvals
	 * @param int $version optional version of the document
	 * @return array list of all approvals
	 */
	function getApprovalStatus($documentID=null, $version=null) { /* {{{ */
		$db = $this->_dms->getDB();

/*
		if (!$db->createTemporaryTable("ttapproveid")) {
			return false;
		}
*/
		$status = array("indstatus"=>array(), "grpstatus"=>array());

		// See if the user is assigned as an individual approver.
		/*
		$queryStr = "SELECT `tblDocumentApprovers`.*, `tblDocumentApproveLog`.`status`, ".
			"`tblDocumentApproveLog`.`comment`, `tblDocumentApproveLog`.`date`, ".
			"`tblDocumentApproveLog`.`userID` ".
			"FROM `tblDocumentApprovers` ".
			"LEFT JOIN `tblDocumentApproveLog` USING (`approveID`) ".
			"LEFT JOIN `ttapproveid` on `ttapproveid`.`maxLogID` = `tblDocumentApproveLog`.`approveLogID` ".
			"WHERE `ttapproveid`.`maxLogID`=`tblDocumentApproveLog`.`approveLogID` ".
			($documentID==null ? "" : "AND `tblDocumentApprovers`.`documentID` = '". $documentID ."' ").
			($version==null ? "" : "AND `tblDocumentApprovers`.`version` = '". $version ."' ").
			"AND `tblDocumentApprovers`.`type`='0' ".
			"AND `tblDocumentApprovers`.`required`='". $this->_id ."' ";
*/
		$queryStr =
   "SELECT `tblDocumentApprovers`.*, `tblDocumentApproveLog`.`status`, ".
			"`tblDocumentApproveLog`.`comment`, `tblDocumentApproveLog`.`date`, ".
			"`tblDocumentApproveLog`.`userID` ".
			"FROM `tblDocumentApprovers` ".
			"LEFT JOIN `tblDocumentApproveLog` USING (`approveID`) ".
			"WHERE `tblDocumentApprovers`.`type`='0' ".
			($documentID==null ? "" : "AND `tblDocumentApprovers`.`documentID` = '". (int) $documentID ."' ").
			($version==null ? "" : "AND `tblDocumentApprovers`.`version` = '". (int) $version ."' ").
			"AND `tblDocumentApprovers`.`required`='". $this->_id ."' ".
			"ORDER BY `tblDocumentApproveLog`.`approveLogID` DESC";

		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr)>0) {
			foreach ($resArr as $res) {
				if(isset($status["indstatus"][$res['documentID']])) {
					if($status["indstatus"][$res['documentID']]['date'] < $res['date']) {
						$status["indstatus"][$res['documentID']] = $res;
					}
				} else {
					$status["indstatus"][$res['documentID']] = $res;
				}
			}
		}

		// See if the user is the member of a group that has been assigned to
		// approve the document version.
		/*
		$queryStr = "SELECT `tblDocumentApprovers`.*, `tblDocumentApproveLog`.`status`, ".
			"`tblDocumentApproveLog`.`comment`, `tblDocumentApproveLog`.`date`, ".
			"`tblDocumentApproveLog`.`userID` ".
			"FROM `tblDocumentApprovers` ".
			"LEFT JOIN `tblDocumentApproveLog` USING (`approveID`) ".
			"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`groupID` = `tblDocumentApprovers`.`required` ".
			"LEFT JOIN `ttapproveid` on `ttapproveid`.`maxLogID` = `tblDocumentApproveLog`.`approveLogID` ".
			"WHERE `ttapproveid`.`maxLogID`=`tblDocumentApproveLog`.`approveLogID` ".
			($documentID==null ? "" : "AND `tblDocumentApprovers`.`documentID` = '". $documentID ."' ").
			($version==null ? "" : "AND `tblDocumentApprovers`.`version` = '". $version ."' ").
			"AND `tblDocumentApprovers`.`type`='1' ".
			"AND `tblGroupMembers`.`userID`='". $this->_id ."'";
			*/
		$queryStr =
			"SELECT `tblDocumentApprovers`.*, `tblDocumentApproveLog`.`status`, ".
			"`tblDocumentApproveLog`.`comment`, `tblDocumentApproveLog`.`date`, ".
			"`tblDocumentApproveLog`.`userID` ".
			"FROM `tblDocumentApprovers` ".
			"LEFT JOIN `tblDocumentApproveLog` USING (`approveID`) ".
			"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`groupID` = `tblDocumentApprovers`.`required` ".
			"WHERE `tblDocumentApprovers`.`type`='1' ".
			($documentID==null ? "" : "AND `tblDocumentApprovers`.`documentID` = '". (int) $documentID ."' ").
			($version==null ? "" : "AND `tblDocumentApprovers`.`version` = '". (int) $version ."' ").
			"AND `tblGroupMembers`.`userID`='". $this->_id ."' ".
			"ORDER BY `tblDocumentApproveLog`.`approveLogID` DESC";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr)>0) {
			foreach ($resArr as $res) {
				if(isset($status["grpstatus"][$res['documentID']])) {
					if($status["grpstatus"][$res['documentID']]['date'] < $res['date']) {
						$status["grpstatus"][$res['documentID']] = $res;
					}
				} else {
					$status["grpstatus"][$res['documentID']] = $res;
				}
			}
		}
		return $status;
	} /* }}} */

	/**
	 * Get a list of mandatory reviewers
	 * A user which isn't trusted completely may have assigned mandatory
	 * reviewers (both users and groups).
	 * Whenever the user inserts a new document the mandatory reviewers are
	 * filled in as reviewers.
	 *
	 * @return array list of arrays with two elements containing the user id
	 *         (reviewerUserID) and group id (reviewerGroupID) of the reviewer.
	 */
	function getMandatoryReviewers() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM tblMandatoryReviewers WHERE userID = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);

		return $resArr;
	} /* }}} */

	/**
	 * Get a list of mandatory approvers
	 * See {link LetoDMS_Core_User::getMandatoryReviewers}
	 *
	 * @return array list of arrays with two elements containing the user id
	 *         (approverUserID) and group id (approverGroupID) of the approver.
	 */
	function getMandatoryApprovers() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM tblMandatoryApprovers WHERE userID = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);

		return $resArr;
	} /* }}} */

	/**
	 * Set a mandatory reviewer
	 * This function sets a mandatory reviewer if it isn't already set.
	 *
	 * @param integer $id id of reviewer
	 * @param boolean $isgroup true if $id is a group
	 * @return boolean true on success, otherwise false
	 */
	function setMandatoryReviewer($id, $isgroup=false) { /* {{{ */
		$db = $this->_dms->getDB();

		if ($isgroup){

			$queryStr = "SELECT * FROM tblMandatoryReviewers WHERE userID = " . $this->_id . " AND reviewerGroupID = " . $id;
			$resArr = $db->getResultArray($queryStr);
			if (count($resArr)!=0) return true;

			$queryStr = "INSERT INTO tblMandatoryReviewers (userID, reviewerGroupID) VALUES (" . $this->_id . ", " . $id .")";
			$resArr = $db->getResult($queryStr);
			if (is_bool($resArr) && !$resArr) return false;

		}else{

			$queryStr = "SELECT * FROM tblMandatoryReviewers WHERE userID = " . $this->_id . " AND reviewerUserID = " . $id;
			$resArr = $db->getResultArray($queryStr);
			if (count($resArr)!=0) return true;

			$queryStr = "INSERT INTO tblMandatoryReviewers (userID, reviewerUserID) VALUES (" . $this->_id . ", " . $id .")";
			$resArr = $db->getResult($queryStr);
			if (is_bool($resArr) && !$resArr) return false;
		}

	} /* }}} */

	/**
	 * Set a mandatory approver
	 * This function sets a mandatory approver if it isn't already set.
	 *
	 * @param integer $id id of approver
	 * @param boolean $isgroup true if $id is a group
	 * @return boolean true on success, otherwise false
	 */
	function setMandatoryApprover($id, $isgroup=false) { /* {{{ */
		$db = $this->_dms->getDB();

		if ($isgroup){

			$queryStr = "SELECT * FROM tblMandatoryApprovers WHERE userID = " . $this->_id . " AND approverGroupID = " . (int) $id;
			$resArr = $db->getResultArray($queryStr);
			if (count($resArr)!=0) return;

			$queryStr = "INSERT INTO tblMandatoryApprovers (userID, approverGroupID) VALUES (" . $this->_id . ", " . $id .")";
			$resArr = $db->getResult($queryStr);
			if (is_bool($resArr) && !$resArr) return false;

		}else{

			$queryStr = "SELECT * FROM tblMandatoryApprovers WHERE userID = " . $this->_id . " AND approverUserID = " . (int) $id;
			$resArr = $db->getResultArray($queryStr);
			if (count($resArr)!=0) return;

			$queryStr = "INSERT INTO tblMandatoryApprovers (userID, approverUserID) VALUES (" . $this->_id . ", " . $id .")";
			$resArr = $db->getResult($queryStr);
			if (is_bool($resArr) && !$resArr) return false;
		}
	} /* }}} */

	/**
	 * Deletes all mandatory reviewers
	 *
	 * @return boolean true on success, otherwise false
	 */
	function delMandatoryReviewers() { /* {{{ */
		$db = $this->_dms->getDB();
		$queryStr = "DELETE FROM tblMandatoryReviewers WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;
		return true;
	} /* }}} */

	/**
	 * Deletes all mandatory approvers
	 *
	 * @return boolean true on success, otherwise false
	 */
	function delMandatoryApprovers() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "DELETE FROM tblMandatoryApprovers WHERE userID = " . $this->_id;
		if (!$db->getResult($queryStr)) return false;
		return true;
	} /* }}} */

}
?>
