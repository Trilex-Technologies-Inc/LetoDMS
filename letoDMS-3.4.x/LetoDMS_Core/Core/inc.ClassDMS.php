<?php
/**
 * Implementation of the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include some files
 */
require_once("inc.AccessUtils.php");
require_once("inc.FileUtils.php");
require_once("inc.ClassAccess.php");
require_once("inc.ClassObject.php");
require_once("inc.ClassFolder.php");
require_once("inc.ClassDocument.php");
require_once("inc.ClassGroup.php");
require_once("inc.ClassUser.php");
require_once("inc.ClassKeywords.php");
require_once("inc.ClassNotification.php");
require_once("inc.ClassAttribute.php");

/**
 * Class to represent the complete document management system.
 * This class is needed to do most of the dms operations. It needs
 * an instance of {@link LetoDMS_Core_DatabaseAccess} to access the
 * underlying database. Many methods are factory functions which create
 * objects representing the entities in the dms, like folders, documents,
 * users, or groups.
 *
 * Each dms has its own database for meta data and a data store for document
 * content. Both must be specified when creating a new instance of this class.
 * All folders and documents are organized in a hierachy like
 * a regular file system starting with a {@link $rootFolderID}
 *
 * This class does not enforce any access rights on documents and folders
 * by design. It is up to the calling application to use the methods
 * {@link LetoDMS_Core_Folder::getAccessMode} and
 * {@link LetoDMS_Core_Document::getAccessMode} and interpret them as desired.
 * Though, there are two convinient functions to filter a list of
 * documents/folders for which users have access rights for. See
 * {@link LetoDMS_Core_DMS::filterAccess}
 * and {@link LetoDMS_Core_DMS::filterUsersByAccess}
 *
 * Though, this class has two methods to set the currently logged in user
 * ({@link setUser} and {@link login}), none of them need to be called, because
 * there is currently no class within the LetoDMS core which needs the logged
 * in user.
 *
 * <code>
 * <?php
 * include("inc/inc.ClassDMS.php");
 * $db = new LetoDMS_Core_DatabaseAccess($type, $hostname, $user, $passwd, $name);
 * $db->connect() or die ("Could not connect to db-server");
 * $dms = new LetoDMS_Core_DMS($db, $contentDir);
 * $dms->setRootFolderID(1);
 * ...
 * ?>
 * </code>
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DMS {
	/**
	 * @var object $db reference to database object. This must be an instance
	 *      of {@link LetoDMS_Core_DatabaseAccess}.
	 * @access protected
	 */
	protected $db;

	/**
	 * @var object $user reference to currently logged in user. This must be
	 *      an instance of {@link LetoDMS_Core_User}. This variable is currently not
	 *      used. It is set by {@link setUser}.
	 * @access private
	 */
	private $user;

	/**
	 * @var string $contentDir location in the file system where all the
	 *      document data is located. This should be an absolute path.
	 * @access public
	 */
	public $contentDir;

	/**
	 * @var integer $rootFolderID ID of root folder
	 * @access public
	 */
	public $rootFolderID;

	/**
	 * @var boolean $enableConverting set to true if conversion of content
	 *      is desired
	 * @access public
	 */
	public $enableConverting;

	/**
	 * @var array $convertFileTypes list of files types that shall be converted
	 * @access public
	 */
	public $convertFileTypes;

	/**
	 * @var array $viewOnlineFileTypes list of files types that can be viewed
	 *      online
	 * @access public
	 */
	public $viewOnlineFileTypes;

	/**
	 * @var string $version version of pear package
	 * @access public
	 */
	public $version;

	/**
	 * Filter objects out which are not accessible in a given mode by a user.
	 *
	 * @param array $objArr list of objects (either documents or folders)
	 * @param object $user user for which access is checked
	 * @param integer $minMode minimum access mode required
	 * @return array filtered list of objects
	 */
	static function filterAccess($objArr, $user, $minMode) { /* {{{ */
		if (!is_array($objArr)) {
			return array();
		}
		$newArr = array();
		foreach ($objArr as $obj) {
			if ($obj->getAccessMode($user) >= $minMode)
				array_push($newArr, $obj);
		}
		return $newArr;
	} /* }}} */

	/**
	 * Filter users out which cannot access an object in a given mode.
	 *
	 * @param object $obj object that shall be accessed
	 * @param array $users list of users which are to check for sufficient
	 *        access rights
	 * @param integer $minMode minimum access right on the object for each user
	 * @return array filtered list of users
	 */
	static function filterUsersByAccess($obj, $users, $minMode) { /* {{{ */
		$newArr = array();
		foreach ($users as $currUser) {
			if ($obj->getAccessMode($currUser) >= $minMode)
				array_push($newArr, $currUser);
		}
		return $newArr;
	} /* }}} */

	/**
	 * Create a new instance of the dms
	 *
	 * @param object $db object to access the underlying database
	 * @param string $contentDir path in filesystem containing the data store
	 *        all document contents is stored
	 * @return object instance of LetoDMS_Core_DMS
	 */
	function __construct($db, $contentDir) { /* {{{ */
		$this->db = $db;
		if(substr($contentDir, -1) == '/')
			$this->contentDir = $contentDir;
		else
			$this->contentDir = $contentDir.'/';
		$this->rootFolderID = 1;
		$this->maxDirID = 0; //31998;
		$this->enableAdminRevApp = false;
		$this->enableConverting = false;
		$this->convertFileTypes = array();
		$this->version = '@package_version@';
		if($this->version[0] == '@')
			$this->version = '3.4.2';
	} /* }}} */

	function getDB() { /* {{{ */
		return $this->db;
	} /* }}} */

	/**
	 * Return the database version
	 *
	 * @return array array with elements major, minor, subminor, date
	 */
	function getDBVersion() { /* {{{ */
		$tbllist = $this->db->TableList();
		$tbllist = explode(',',strtolower(join(',',$tbllist)));
		if(!array_search('tblversion', $tbllist))
			return false;
		$queryStr = "SELECT * FROM tblVersion order by major,minor,subminor limit 1";
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) != 1)
			return false;
		$resArr = $resArr[0];
		return $resArr;
	} /* }}} */

	/**
	 * Check if the version in the database is the same as of this package
	 * Only the major and minor version number will be checked.
	 *
	 * @return boolean returns false if versions do not match, but returns
	 *         true if version matches or table tblVersion does not exists.
	 */
	function checkVersion() { /* {{{ */
		$tbllist = $this->db->TableList();
		$tbllist = explode(',',strtolower(join(',',$tbllist)));
		if(!array_search('tblversion', $tbllist))
			return true;
		$queryStr = "SELECT * FROM tblVersion order by major,minor,subminor limit 1";
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) != 1)
			return false;
		$resArr = $resArr[0];
		$ver = explode('.', $this->version);
		if(($resArr['major'] != $ver[0]) || ($resArr['minor'] != $ver[1]))
			return false;
		return true;
	} /* }}} */

	/**
	 * Set id of root folder
	 * This function must be called right after creating an instance of
	 * LetoDMS_Core_DMS
	 *
	 * @param interger $id id of root folder
	 */
	function setRootFolderID($id) { /* {{{ */
		$this->rootFolderID = $id;
	} /* }}} */

	/**
	 * Set maximum number of subdirectories per directory
	 *
	 * The value of maxDirID is quite crucial because, all documents are
	 * associated with a directory in the filesystem. Consequently, there is
	 * maximum number of documents, because depending on the file system
	 * the maximum number of subdirectories is limited. Since version 3.3.0 of
	 * letodms an additional directory level has been introduced. All documents
	 * from 1 to maxDirID-1 will be saved in 1/<docid>, documents from maxDirID
	 * to 2*maxDirID-1 are stored in 2/<docid> and so on.
	 *
	 * This function must be called right after creating an instance of
	 * LetoDMS_Core_DMS
	 *
	 * @param interger $id id of root folder
	 */
	function setMaxDirID($id) { /* {{{ */
		$this->maxDirID = $id;
	} /* }}} */

	/**
	 * Get root folder
	 *
	 * @return object/boolean return the object of the root folder or false if
	 *        the root folder id was not set before with {@link setRootFolderID}.
	 */
	function getRootFolder() { /* {{{ */
		if(!$this->rootFolderID) return false;
		return $this->getFolder($this->rootFolderID);
	} /* }}} */

	function setEnableAdminRevApp($enable) { /* {{{ */
		$this->enableAdminRevApp = $enable;
	} /* }}} */

	function setEnableConverting($enable) { /* {{{ */
		$this->enableConverting = $enable;
	} /* }}} */

	function setConvertFileTypes($types) { /* {{{ */
		$this->convertFileTypes = $types;
	} /* }}} */

	function setViewOnlineFileTypes($types) { /* {{{ */
		$this->viewOnlineFileTypes = $types;
	} /* }}} */

	/**
	 * Login as a user
	 *
	 * Checks if the given credentials are valid and returns a user object.
	 * It also sets the property $user for later access on the currently
	 * logged in user
	 *
	 * @param string $username login name of user
	 * @param string $password password of user
	 *
	 * @return object instance of class LetoDMS_Core_User or false
	 */
	function login($username, $password) { /* {{{ */
	} /* }}} */

	/**
	 * Set the logged in user
	 *
	 * If user authentication was done externally, this function can
	 * be used to tell the dms who is currently logged in.
	 *
	 * @param object $user
	 *
	 */
	function setUser($user) { /* {{{ */
		$this->user = $user;
	} /* }}} */

	/**
	 * Return a document by its id
	 *
	 * This function retrieves a document from the database by its id.
	 *
	 * @param integer $id internal id of document
	 * @return object instance of LetoDMS_Core_Document or false
	 */
	function getDocument($id) { /* {{{ */
		if (!is_numeric($id)) return false;

		$queryStr = "SELECT * FROM tblDocuments WHERE id = " . (int) $id;
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) != 1)
			return false;
		$resArr = $resArr[0];

		// New Locking mechanism uses a separate table to track the lock.
		$queryStr = "SELECT * FROM tblDocumentLocks WHERE document = " . (int) $id;
		$lockArr = $this->db->getResultArray($queryStr);
		if ((is_bool($lockArr) && $lockArr==false) || (count($lockArr)==0)) {
			// Could not find a lock on the selected document.
			$lock = -1;
		}
		else {
			// A lock has been identified for this document.
			$lock = $lockArr[0]["userID"];
		}

		$document = new LetoDMS_Core_Document($resArr["id"], $resArr["name"], $resArr["comment"], $resArr["date"], $resArr["expires"], $resArr["owner"], $resArr["folder"], $resArr["inheritAccess"], $resArr["defaultAccess"], $lock, $resArr["keywords"], $resArr["sequence"]);
		$document->setDMS($this);
		return $document;
	} /* }}} */

	/**
	 * Returns all documents of a given user
	 *
	 * @param object $user
	 * @return array list of documents
	 */
	function getDocumentsByUser($user) { /* {{{ */
		$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser` ".
			"FROM `tblDocuments` ".
			"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
			"WHERE `tblDocuments`.`owner` = " . $user->getID() . " ORDER BY `sequence`";

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$documents = array();
		foreach ($resArr as $row) {
			$document = new LetoDMS_Core_Document($row["id"], $row["name"], $row["comment"], $row["date"], $row["expires"], $row["owner"], $row["folder"], $row["inheritAccess"], $row["defaultAccess"], $row["lockUser"], $row["keywords"], $row["sequence"]);
			$document->setDMS($this);
			$documents[] = $document;
		}
		return $documents;
	} /* }}} */

	/**
	 * Returns all documents locked by a given user
	 * FIXME: Not full implemented. Do not use, because it still requires the
	 * temporary tables!
	 *
	 * @param object $user
	 * @return array list of documents
	 */
	function getDocumentsLockedByUser($user) { /* {{{ */
		$queryStr = "SELECT `tblDocuments`.* ".
			"FROM `tblDocuments` LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id` = `tblDocumentLocks`.`document` ".
			"WHERE `tblDocumentLocks`.`userID` = '".$user->getID()."' ".
			"ORDER BY `id` DESC";

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$documents = array();
		foreach ($resArr as $row) {
			$document = new LetoDMS_Core_Document($row["id"], $row["name"], $row["comment"], $row["date"], $row["expires"], $row["owner"], $row["folder"], $row["inheritAccess"], $row["defaultAccess"], $row["lockUser"], $row["keywords"], $row["sequence"]);
			$document->setDMS($this);
			$documents[] = $document;
		}
		return $documents;
	} /* }}} */

	/**
	 * Returns a document by its name
	 *
	 * This function searches a document by its name and restricts the search
	 * to given folder if passed as the second parameter.
	 *
	 * @param string $name
	 * @param object $folder
	 * @return object/boolean found document or false
	 */
	function getDocumentByName($name, $folder=null) { /* {{{ */
		if (!$name) return false;

		$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser` ".
			"FROM `tblDocuments` ".
			"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
			"WHERE `tblDocuments`.`name` = " . $this->db->qstr($name);
		if($folder)
			$queryStr .= " AND `tblDocuments`.`folder` = ". $folder->getID();
		$queryStr .= " LIMIT 1";

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		if(!$resArr)
			return false;

		$row = $resArr[0];
		$document = new LetoDMS_Core_Document($row["id"], $row["name"], $row["comment"], $row["date"], $row["expires"], $row["owner"], $row["folder"], $row["inheritAccess"], $row["defaultAccess"], $row["lockUser"], $row["keywords"], $row["sequence"]);
		$document->setDMS($this);
		return $document;
	} /* }}} */

	function makeTimeStamp($hour, $min, $sec, $year, $month, $day) {
		$thirtyone = array (1, 3, 5, 7, 8, 10, 12);
		$thirty = array (4, 6, 9, 11);

		// Very basic check that the terms are valid. Does not fail for illegal
		// dates such as 31 Feb.
		if (!is_numeric($hour) || !is_numeric($min) || !is_numeric($sec) || !is_numeric($year) || !is_numeric($month) || !is_numeric($day) || $month<1 || $month>12 || $day<1 || $day>31 || $hour<0 || $hour>23 || $min<0 || $min>59 || $sec<0 || $sec>59) {
			return false;
		}
		$year = (int) $year;
		$month = (int) $month;
		$day = (int) $day;

		if (array_search($month, $thirtyone)) {
			$max=31;
		}
		else if (array_search($month, $thirty)) {
			$max=30;
		}
		else {
			$max=(($year % 4 == 0) && ($year % 100 != 0 || $year % 400 == 0)) ? 29 : 28;
		}

		// If the date falls out of bounds, set it to the maximum for the given
		// month. Makes assumption about the user's intention, rather than failing
		// for absolutely everything.
		if ($day>$max) {
			$day=$max;
		}

		return mktime($hour, $min, $sec, $month, $day, $year);
	}

	/*
	 * Search the database for documents
	 *
	 * Note: the creation date will be used to check againts the
	 * date saved with the document
	 * or folder. The modification date will only be used for documents. It
	 * is checked against the creation date of the document content. This
	 * meanѕ that updateѕ of a document will only result in a searchable
	 * modification if a new version is uploaded.
	 *
	 * @param query string seach query with space separated words
	 * @param limit integer number of items in result set
	 * @param offset integer index of first item in result set
	 * @param logicalmode string either AND or OR
	 * @param searchin array() list of fields to search in
	 *        1 = keywords, 2=name, 3=comment
	 * @param startFolder object search in the folder only (null for root folder)
	 * @param owner object search for documents owned by this user
	 * @param status array list of status
	 * @param creationstartdate array search for documents created after this date
	 * @param creationenddate array search for documents created before this date
	 * @param modificationstartdate array search for documents modified after this date
	 * @param modificationenddate array search for documents modified before this date
	 * @param categories array list of categories the documents must have assigned
	 * @param attributes array list of attributes
	 * @param mode int decide whether to search for documents/folders
	 *        0x1 = documents only
	 *        0x2 = folders only
	 *        0x3 = both
	 * @return array containing the elements total and docs
	 */
	function search($query, $limit=0, $offset=0, $logicalmode='AND', $searchin=array(), $startFolder=null, $owner=null, $status = array(), $creationstartdate=array(), $creationenddate=array(), $modificationstartdate=array(), $modificationenddate=array(), $categories=array(), $attributes=array(), $mode=0x3) { /* {{{ */
		// Split the search string into constituent keywords.
		$tkeys=array();
		if (strlen($query)>0) {
			$tkeys = preg_split("/[\t\r\n ,]+/", $query);
		}

		// if none is checkd search all
		if (count($searchin)==0)
			$searchin=array( 0, 1, 2, 3, 4);

		/*--------- Do it all over again for folders -------------*/
		if($mode & 0x2) {
			$searchKey = "";
			if (in_array(2, $searchin)) {
				$searchFields[] = "`tblFolders`.`name`";
			}
			if (in_array(3, $searchin)) {
				$searchFields[] = "`tblFolders`.`comment`";
			}
			if (in_array(4, $searchin)) {
				$searchFields[] = "`tblFolderAttributes`.`value`";
			}

			if (count($searchFields)>0) {
				foreach ($tkeys as $key) {
					$key = trim($key);
					if (strlen($key)>0) {
						$searchKey = (strlen($searchKey)==0 ? "" : $searchKey." ".$logicalmode." ")."(".implode(" like ".$this->db->qstr("%".$key."%")." OR ", $searchFields)." like ".$this->db->qstr("%".$key."%").")";
					}
				}
			}

			// Check to see if the search has been restricted to a particular sub-tree in
			// the folder hierarchy.
			$searchFolder = "";
			if ($startFolder) {
				$searchFolder = "`tblFolders`.`folderList` LIKE '%:".$startFolder->getID().":%'";
			}

			// Check to see if the search has been restricted to a particular
			// document owner.
			$searchOwner = "";
			if ($owner) {
				$searchOwner = "`tblFolders`.`owner` = '".$owner->getId()."'";
			}

			// Is the search restricted to documents created between two specific dates?
			$searchCreateDate = "";
			if ($creationstartdate) {
				$startdate = LetoDMS_Core_DMS::makeTimeStamp($creationstartdate['hour'], $creationstartdate['minute'], $creationstartdate['second'], $creationstartdate['year'], $creationstartdate["month"], $creationstartdate["day"]);
				if ($startdate) {
					$searchCreateDate .= "`tblFolders`.`date` >= ".$startdate;
				}
			}
			if ($creationenddate) {
				$stopdate = LetoDMS_Core_DMS::makeTimeStamp($creationenddate['hour'], $creationstartdate['minute'], $creationstartdate['second'], $creationenddate["year"], $creationenddate["month"], $creationenddate["day"]);
				if ($stopdate) {
					if($startdate)
						$searchCreateDate .= " AND ";
					$searchCreateDate .= "`tblFolders`.`date` <= ".$stopdate;
				}
			}

			$searchQuery = "FROM `tblFolders` LEFT JOIN `tblFolderAttributes` on `tblFolders`.`id`=`tblFolderAttributes`.`folder` WHERE 1=1";

			if (strlen($searchKey)>0) {
				$searchQuery .= " AND (".$searchKey.")";
			}
			if (strlen($searchFolder)>0) {
				$searchQuery .= " AND ".$searchFolder;
			}
			if (strlen($searchOwner)>0) {
				$searchQuery .= " AND (".$searchOwner.")";
			}
			if (strlen($searchCreateDate)>0) {
				$searchQuery .= " AND (".$searchCreateDate.")";
			}

			/* Do not search for folders if not at least a search for a key,
			 * an owner, or creation date is requested.
			 */
			if($searchKey || $searchOwner || $searchCreateDate) {
				// Count the number of rows that the search will produce.
				$resArr = $this->db->getResultArray("SELECT COUNT(*) AS num ".$searchQuery." GROUP BY `tblFolders`.`id`");
				$totalFolders = 0;
				if ($resArr && isset($resArr[0]) && is_numeric($resArr[0]["num"]) && $resArr[0]["num"]>0) {
					$totalFolders = (integer)$resArr[0]["num"];
				}

				// If there are no results from the count query, then there is no real need
				// to run the full query. TODO: re-structure code to by-pass additional
				// queries when no initial results are found.

				// Only search if the offset is not beyond the number of folders
				if($totalFolders > $offset) {
					// Prepare the complete search query, including the LIMIT clause.
					$searchQuery = "SELECT DISTINCT `tblFolders`.* ".$searchQuery;

					if($limit) {
						$searchQuery .= " LIMIT ".$offset.",".$limit;
					}

					// Send the complete search query to the database.
					$resArr = $this->db->getResultArray($searchQuery);
				} else {
					$resArr = array();
				}

				// ------------------- Ausgabe der Ergebnisse ----------------------------
				$numResults = count($resArr);
				if ($numResults == 0) {
					$folderresult = array('totalFolders'=>$totalFolders, 'folders'=>array());
				} else {
					foreach ($resArr as $folderArr) {
						$folders[] = $this->getFolder($folderArr['id']);
					}
					$folderresult = array('totalFolders'=>$totalFolders, 'folders'=>$folders);
				}
			} else {
				$folderresult = array('totalFolders'=>0, 'folders'=>array());
			}
		} else {
			$folderresult = array('totalFolders'=>0, 'folders'=>array());
		}

		/*--------- Do it all over again for documents -------------*/

		if($mode & 0x1) {
			$searchKey = "";
			$searchFields = array();
			if (in_array(1, $searchin)) {
				$searchFields[] = "`tblDocuments`.`keywords`";
			}
			if (in_array(2, $searchin)) {
				$searchFields[] = "`tblDocuments`.`name`";
			}
			if (in_array(3, $searchin)) {
				$searchFields[] = "`tblDocuments`.`comment`";
			}
			if (in_array(4, $searchin)) {
				$searchFields[] = "`tblDocumentAttributes`.`value`";
				$searchFields[] = "`tblDocumentContentAttributes`.`value`";
			}


			if (count($searchFields)>0) {
				foreach ($tkeys as $key) {
					$key = trim($key);
					if (strlen($key)>0) {
						$searchKey = (strlen($searchKey)==0 ? "" : $searchKey." ".$logicalmode." ")."(".implode(" like ".$this->db->qstr("%".$key."%")." OR ", $searchFields)." like ".$this->db->qstr("%".$key."%").")";
					}
				}
			}

			// Check to see if the search has been restricted to a particular sub-tree in
			// the folder hierarchy.
			$searchFolder = "";
			if ($startFolder) {
				$searchFolder = "`tblDocuments`.`folderList` LIKE '%:".$startFolder->getID().":%'";
			}

			// Check to see if the search has been restricted to a particular
			// document owner.
			$searchOwner = "";
			if ($owner) {
				$searchOwner = "`tblDocuments`.`owner` = '".$owner->getId()."'";
			}

			// Check to see if the search has been restricted to a particular
			// document category.
			$searchCategories = "";
			if ($categories) {
				$catids = array();
				foreach($categories as $category)
					$catids[] = $category->getId();
				$searchCategories = "`tblDocumentCategory`.`categoryID` in (".implode(',', $catids).")";
			}

			// Check to see if the search has been restricted to a particular
			// attribute.
			$searchAttributes = array();
			if ($attributes) {
				foreach($attributes as $attrdefid=>$attribute) {
					if($attribute) {
						$attrdef = $this->getAttributeDefinition($attrdefid);
						if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_document) {
							if($attrdef->getValueSet())
								$searchAttributes[] = "`tblDocumentAttributes`.`attrdef`=".$attrdefid." AND `tblDocumentAttributes`.`value`='".$attribute."'";
							else
								$searchAttributes[] = "`tblDocumentAttributes`.`attrdef`=".$attrdefid." AND `tblDocumentAttributes`.`value` like '%".$attribute."%'";
						} elseif($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_documentcontent) {
							if($attrdef->getValueSet())
								$searchAttributes[] = "`tblDocumentContentAttributes`.`attrdef`=".$attrdefid." AND `tblDocumentContentAttributes`.`value`='".$attribute."'";
							else
								$searchAttributes[] = "`tblDocumentContentAttributes`.`attrdef`=".$attrdefid." AND `tblDocumentContentAttributes`.`value` like '%".$attribute."%'";
						}
					}
				}
			}

			// Is the search restricted to documents created between two specific dates?
			$searchCreateDate = "";
			if ($creationstartdate) {
				$startdate = LetoDMS_Core_DMS::makeTimeStamp($creationstartdate['hour'], $creationstartdate['minute'], $creationstartdate['second'], $creationstartdate['year'], $creationstartdate["month"], $creationstartdate["day"]);
				if ($startdate) {
					$searchCreateDate .= "`tblDocuments`.`date` >= ".$startdate;
				}
			}
			if ($creationenddate) {
				$stopdate = LetoDMS_Core_DMS::makeTimeStamp($creationenddate['hour'], $creationenddate['minute'], $creationenddate['second'], $creationenddate["year"], $creationenddate["month"], $creationenddate["day"]);
				if ($stopdate) {
					if($searchCreateDate)
						$searchCreateDate .= " AND ";
					$searchCreateDate .= "`tblDocuments`.`date` <= ".$stopdate;
				}
			}
			if ($modificationstartdate) {
				$startdate = LetoDMS_Core_DMS::makeTimeStamp($modificationstartdate['hour'], $modificationstartdate['minute'], $modificationstartdate['second'], $modificationstartdate['year'], $modificationstartdate["month"], $modificationstartdate["day"]);
				if ($startdate) {
					if($searchCreateDate)
						$searchCreateDate .= " AND ";
					$searchCreateDate .= "`tblDocumentContent`.`date` >= ".$startdate;
				}
			}
			if ($modificationenddate) {
				$stopdate = LetoDMS_Core_DMS::makeTimeStamp($modificationenddate['hour'], $modificationenddate['minute'], $modificationenddate['second'], $modificationenddate["year"], $modificationenddate["month"], $modificationenddate["day"]);
				if ($stopdate) {
					if($searchCreateDate)
						$searchCreateDate .= " AND ";
					$searchCreateDate .= "`tblDocumentContent`.`date` <= ".$stopdate;
				}
			}

			// ---------------------- Suche starten ----------------------------------

			//
			// Construct the SQL query that will be used to search the database.
			//

			if (!$this->db->createTemporaryTable("ttcontentid") || !$this->db->createTemporaryTable("ttstatid")) {
				return false;
			}

			$searchQuery = "FROM `tblDocumentContent` ".
				"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
				"LEFT JOIN `tblDocumentAttributes` ON `tblDocuments`.`id` = `tblDocumentAttributes`.`document` ".
				"LEFT JOIN `tblDocumentContentAttributes` ON `tblDocumentContent`.`id` = `tblDocumentContentAttributes`.`content` ".
				"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
				"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
				"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
				"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
				"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
				"LEFT JOIN `tblDocumentCategory` ON `tblDocuments`.`id`=`tblDocumentCategory`.`documentID` ".
				"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
				"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version`";

			if (strlen($searchKey)>0) {
				$searchQuery .= " AND (".$searchKey.")";
			}
			if (strlen($searchFolder)>0) {
				$searchQuery .= " AND ".$searchFolder;
			}
			if (strlen($searchOwner)>0) {
				$searchQuery .= " AND (".$searchOwner.")";
			}
			if (strlen($searchCategories)>0) {
				$searchQuery .= " AND (".$searchCategories.")";
			}
			if (strlen($searchCreateDate)>0) {
				$searchQuery .= " AND (".$searchCreateDate.")";
			}
			if ($searchAttributes) {
				$searchQuery .= " AND (".implode(" AND ", $searchAttributes).")";
			}

			// status
			if ($status) {
				$searchQuery .= " AND `tblDocumentStatusLog`.`status` IN (".implode(',', $status).")";
			}

			// Count the number of rows that the search will produce.
			$resArr = $this->db->getResultArray("SELECT COUNT(*) AS num ".$searchQuery." GROUP BY `tblDocuments`.`id`");
			$totalDocs = 0;
			if (is_numeric($resArr[0]["num"]) && $resArr[0]["num"]>0) {
				$totalDocs = (integer)$resArr[0]["num"];
			}

			// If there are no results from the count query, then there is no real need
			// to run the full query. TODO: re-structure code to by-pass additional
			// queries when no initial results are found.

			// Prepare the complete search query, including the LIMIT clause.
			$searchQuery = "SELECT DISTINCT `tblDocuments`.*, ".
				"`tblDocumentContent`.`version`, ".
				"`tblDocumentStatusLog`.`status`, `tblDocumentLocks`.`userID` as `lockUser` ".$searchQuery;

			// calculate the remaining entrїes of the current page
			// If page is not full yet, get remaining entries
			$remain = $limit - count($folderresult['folders']);
			if($remain) {
				if($remain == $limit)
					$offset -= $totalFolders;
				else
					$offset = 0;
				if($limit)
					$searchQuery .= " LIMIT ".$offset.",".$remain;

				// Send the complete search query to the database.
				$resArr = $this->db->getResultArray($searchQuery);
			} else {
				$resArr = array();
			}

			// ------------------- Ausgabe der Ergebnisse ----------------------------
			$numResults = count($resArr);
			if ($numResults == 0) {
				$docresult = array('totalDocs'=>$totalDocs, 'docs'=>array());
			} else {
				foreach ($resArr as $docArr) {
					$docs[] = $this->getDocument($docArr['id']);
				}
				$docresult = array('totalDocs'=>$totalDocs, 'docs'=>$docs);
			}
		} else {
			$docresult = array('totalDocs'=>0, 'docs'=>array());
		}

		if($limit) {
			$totalPages = (integer)(($totalDocs+$totalFolders)/$limit);
			if ((($totalDocs+$totalFolders)%$limit) > 0) {
				$totalPages++;
			}
		} else {
			$totalPages = 1;
		}

		return array_merge($docresult, $folderresult, array('totalPages'=>$totalPages));
	} /* }}} */

	/**
	 * Return a folder by its id
	 *
	 * This function retrieves a folder from the database by its id.
	 *
	 * @param integer $id internal id of folder
	 * @return object instance of LetoDMS_Core_Folder or false
	 */
	function getFolder($id) { /* {{{ */
		if (!is_numeric($id)) return false;

		$queryStr = "SELECT * FROM tblFolders WHERE id = " . (int) $id;
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false)
			return false;
		else if (count($resArr) != 1)
			return false;

		$resArr = $resArr[0];
		$folder = new LetoDMS_Core_Folder($resArr["id"], $resArr["name"], $resArr["parent"], $resArr["comment"], $resArr["date"], $resArr["owner"], $resArr["inheritAccess"], $resArr["defaultAccess"], $resArr["sequence"]);
		$folder->setDMS($this);
		return $folder;
	} /* }}} */

	/**
	 * Return a folder by its name
	 *
	 * This function retrieves a folder from the database by its name. The
	 * search covers the whole database. If
	 * the parameter $folder is not null, it will search for the name
	 * only within this parent folder. It will not be done recursively.
	 *
	 * @param string $name name of the folder
	 * @param object $folder parent folder
	 * @return object/boolean found folder or false
	 */
	function getFolderByName($name, $folder=null) { /* {{{ */
		if (!$name) return false;

		$queryStr = "SELECT * FROM tblFolders WHERE name = " . $this->db->qstr($name);
		if($folder)
			$queryStr .= " AND `parent` = ". $folder->getID();
		$queryStr .= " LIMIT 1";
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false)
			return false;

		if(!$resArr)
			return false;

		$resArr = $resArr[0];
		$folder = new LetoDMS_Core_Folder($resArr["id"], $resArr["name"], $resArr["parent"], $resArr["comment"], $resArr["date"], $resArr["owner"], $resArr["inheritAccess"], $resArr["defaultAccess"], $resArr["sequence"]);
		$folder->setDMS($this);
		return $folder;
	} /* }}} */

	/**
	 * Return a user by its id
	 *
	 * This function retrieves a user from the database by its id.
	 *
	 * @param integer $id internal id of user
	 * @return object instance of LetoDMS_Core_User or false
	 */
	function getUser($id) { /* {{{ */
		if (!is_numeric($id))
			return false;

		$queryStr = "SELECT * FROM tblUsers WHERE id = " . (int) $id;
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false) return false;
		if (count($resArr) != 1) return false;

		$resArr = $resArr[0];

		$user = new LetoDMS_Core_User($resArr["id"], $resArr["login"], $resArr["pwd"], $resArr["fullName"], $resArr["email"], $resArr["language"], $resArr["theme"], $resArr["comment"], $resArr["role"], $resArr["hidden"], $resArr["disabled"], $resArr["pwdExpiration"], $resArr["loginfailures"]);
		$user->setDMS($this);
		return $user;
	} /* }}} */

	/**
	 * Return a user by its login
	 *
	 * This function retrieves a user from the database by its login.
	 * If the second optional parameter $email is not empty, the user must
	 * also have the given email.
	 *
	 * @param string $login internal login of user
	 * @param string $email email of user
	 * @return object instance of LetoDMS_Core_User or false
	 */
	function getUserByLogin($login, $email='') { /* {{{ */
		$queryStr = "SELECT * FROM tblUsers WHERE login = ".$this->db->qstr($login);
		if($email)
			$queryStr .= " AND email=".$this->db->qstr($email);
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false) return false;
		if (count($resArr) != 1) return false;

		$resArr = $resArr[0];

		$user = new LetoDMS_Core_User($resArr["id"], $resArr["login"], $resArr["pwd"], $resArr["fullName"], $resArr["email"], $resArr["language"], $resArr["theme"], $resArr["comment"], $resArr["role"], $resArr["hidden"], $resArr["disabled"], $resArr["pwdExpiration"], $resArr["loginfailures"]);
		$user->setDMS($this);
		return $user;
	} /* }}} */

	/**
	 * Return a user by its email
	 *
	 * This function retrieves a user from the database by its email.
	 * It is needed when the user requests a new password.
	 *
	 * @param integer $email email address of user
	 * @return object instance of LetoDMS_Core_User or false
	 */
	function getUserByEmail($email) { /* {{{ */
		$queryStr = "SELECT * FROM tblUsers WHERE email = ".$this->db->qstr($email);
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false) return false;
		if (count($resArr) != 1) return false;

		$resArr = $resArr[0];

		$user = new LetoDMS_Core_User($resArr["id"], $resArr["login"], $resArr["pwd"], $resArr["fullName"], $resArr["email"], $resArr["language"], $resArr["theme"], $resArr["comment"], $resArr["role"], $resArr["hidden"], $resArr["disabled"], $resArr["pwdExpiration"], $resArr["loginfailures"]);
		$user->setDMS($this);
		return $user;
	} /* }}} */

	/**
	 * Return list of all users
	 *
	 * @return array of instances of LetoDMS_Core_User or false
	 */
	function getAllUsers($orderby = '') { /* {{{ */
		if($orderby == 'fullname')
			$queryStr = "SELECT * FROM tblUsers ORDER BY fullname";
		else
			$queryStr = "SELECT * FROM tblUsers ORDER BY login";
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false)
			return false;

		$users = array();

		for ($i = 0; $i < count($resArr); $i++) {
			$user = new LetoDMS_Core_User($resArr[$i]["id"], $resArr[$i]["login"], $resArr[$i]["pwd"], $resArr[$i]["fullName"], $resArr[$i]["email"], (isset($resArr["language"])?$resArr["language"]:NULL), (isset($resArr["theme"])?$resArr["theme"]:NULL), $resArr[$i]["comment"], $resArr[$i]["role"], $resArr[$i]["hidden"], $resArr[$i]["disabled"], $resArr[$i]["pwdExpiration"], $resArr[$i]["loginfailures"]);
			$user->setDMS($this);
			$users[$i] = $user;
		}

		return $users;
	} /* }}} */

	/**
	 * Add a new user
	 *
	 * @param string $login login name
	 * @param string $pwd password of new user
	 * @param string $email Email of new user
	 * @param string $language language of new user
	 * @param string $comment comment of new user
	 * @param integer $role role of new user (can be 0=normal, 1=admin, 2=guest)
	 * @param integer $isHidden hide user in all lists, if this is set login
	 *        is still allowed
	 * @param integer $isDisabled disable user and prevent login
	 * @return object of LetoDMS_Core_User
	 */
	function addUser($login, $pwd, $fullName, $email, $language, $theme, $comment, $role='0', $isHidden=0, $isDisabled=0, $pwdexpiration='') { /* {{{ */
		$db = $this->db;
		if (is_object($this->getUserByLogin($login))) {
			return false;
		}
		if($role == '')
			$role = '0';
		$queryStr = "INSERT INTO tblUsers (login, pwd, fullName, email, language, theme, comment, role, hidden, disabled, pwdExpiration) VALUES (".$db->qstr($login).", ".$db->qstr($pwd).", ".$db->qstr($fullName).", ".$db->qstr($email).", '".$language."', '".$theme."', ".$db->qstr($comment).", '".intval($role)."', '".intval($isHidden)."', '".intval($isDisabled)."', ".$db->qstr($pwdexpiration).")";
		$res = $this->db->getResult($queryStr);
		if (!$res)
			return false;

		return $this->getUser($this->db->getInsertID());
	} /* }}} */

	/**
	 * Get a group by its id
	 *
	 * @param integer $id id of group
	 * @return object/boolean group or false if no group was found
	 */
	function getGroup($id) { /* {{{ */
		if (!is_numeric($id))
			return false;

		$queryStr = "SELECT * FROM tblGroups WHERE id = " . (int) $id;
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false)
			return false;
		else if (count($resArr) != 1) //wenn, dann wohl eher 0 als > 1 ;-)
			return false;

		$resArr = $resArr[0];

		$group = new LetoDMS_Core_Group($resArr["id"], $resArr["name"], $resArr["comment"]);
		$group->setDMS($this);
		return $group;
	} /* }}} */

	/**
	 * Get a group by its name
	 *
	 * @param string $name name of group
	 * @return object/boolean group or false if no group was found
	 */
	function getGroupByName($name) { /* {{{ */
		$queryStr = "SELECT `tblGroups`.* FROM `tblGroups` WHERE `tblGroups`.`name` = ".$this->db->qstr($name);
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false)
			return false;
		else if (count($resArr) != 1) //wenn, dann wohl eher 0 als > 1 ;-)
			return false;

		$resArr = $resArr[0];

		$group = new LetoDMS_Core_Group($resArr["id"], $resArr["name"], $resArr["comment"]);
		$group->setDMS($this);
		return $group;
	} /* }}} */

	/**
	 * Get a list of all groups
	 *
	 * @return array array of instances of {@link LetoDMS_Core_Group}
	 */
	function getAllGroups() { /* {{{ */
		$queryStr = "SELECT * FROM tblGroups ORDER BY name";
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false)
			return false;

		$groups = array();

		for ($i = 0; $i < count($resArr); $i++) {

			$group = new LetoDMS_Core_Group($resArr[$i]["id"], $resArr[$i]["name"], $resArr[$i]["comment"]);
			$group->setDMS($this);
			$groups[$i] = $group;
		}

		return $groups;
	} /* }}} */

	/**
	 * Create a new user group
	 *
	 * @param string $name name of group
	 * @param string $comment comment of group
	 * @return object/boolean instance of {@link LetoDMS_Core_Group} or false in
	 *         case of an error.
	 */
	function addGroup($name, $comment) { /* {{{ */
		if (is_object($this->getGroupByName($name))) {
			return false;
		}

		$queryStr = "INSERT INTO tblGroups (name, comment) VALUES (".$this->db->qstr($name).", ".$this->db->qstr($comment).")";
		if (!$this->db->getResult($queryStr))
			return false;

		return $this->getGroup($this->db->getInsertID());
	} /* }}} */

	function getKeywordCategory($id) { /* {{{ */
		if (!is_numeric($id))
			return false;

		$queryStr = "SELECT * FROM tblKeywordCategories WHERE id = " . (int) $id;
		$resArr = $this->db->getResultArray($queryStr);
		if ((is_bool($resArr) && !$resArr) || (count($resArr) != 1))
			return false;

		$resArr = $resArr[0];
		$cat = new LetoDMS_Core_Keywordcategory($resArr["id"], $resArr["owner"], $resArr["name"]);
		$cat->setDMS($this);
		return $cat;
	} /* }}} */

	function getKeywordCategoryByName($name, $userID) { /* {{{ */
		$queryStr = "SELECT * FROM tblKeywordCategories WHERE name = " . $this->db->qstr($name) . " AND owner = " . (int) $userID;
		$resArr = $this->db->getResultArray($queryStr);
		if ((is_bool($resArr) && !$resArr) || (count($resArr) != 1))
			return false;

		$resArr = $resArr[0];
		$cat = new LetoDMS_Core_Keywordcategory($resArr["id"], $resArr["owner"], $resArr["name"]);
		$cat->setDMS($this);
		return $cat;
	} /* }}} */

	function getAllKeywordCategories($userIDs = array()) { /* {{{ */
		$queryStr = "SELECT * FROM tblKeywordCategories";
		if ($userIDs)
			$queryStr .= " WHERE owner in (".implode(',', $userIDs).")";

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$categories = array();
		foreach ($resArr as $row) {
			$cat = new LetoDMS_Core_KeywordCategory($row["id"], $row["owner"], $row["name"]);
			$cat->setDMS($this);
			array_push($categories, $cat);
		}

		return $categories;
	} /* }}} */

	/**
	 * This function should be replaced by getAllKeywordCategories()
	 */
	function getAllUserKeywordCategories($userID) { /* {{{ */
		$queryStr = "SELECT * FROM tblKeywordCategories";
		if ($userID != -1)
			$queryStr .= " WHERE owner = " . (int) $userID;

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$categories = array();
		foreach ($resArr as $row) {
			$cat = new LetoDMS_Core_KeywordCategory($row["id"], $row["owner"], $row["name"]);
			$cat->setDMS($this);
			array_push($categories, $cat);
		}

		return $categories;
	} /* }}} */

	function addKeywordCategory($userID, $name) { /* {{{ */
		if (is_object($this->getKeywordCategoryByName($name, $userID))) {
			return false;
		}
		$queryStr = "INSERT INTO tblKeywordCategories (owner, name) VALUES (".(int) $userID.", ".$this->db->qstr($name).")";
		if (!$this->db->getResult($queryStr))
			return false;

		return $this->getKeywordCategory($this->db->getInsertID());
	} /* }}} */

	function getDocumentCategory($id) { /* {{{ */
		if (!is_numeric($id))
			return false;

		$queryStr = "SELECT * FROM tblCategory WHERE id = " . (int) $id;
		$resArr = $this->db->getResultArray($queryStr);
		if ((is_bool($resArr) && !$resArr) || (count($resArr) != 1))
			return false;

		$resArr = $resArr[0];
		$cat = new LetoDMS_Core_DocumentCategory($resArr["id"], $resArr["name"]);
		$cat->setDMS($this);
		return $cat;
	} /* }}} */

	function getDocumentCategories() { /* {{{ */
		$queryStr = "SELECT * FROM tblCategory";

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$categories = array();
		foreach ($resArr as $row) {
			$cat = new LetoDMS_Core_DocumentCategory($row["id"], $row["name"]);
			$cat->setDMS($this);
			array_push($categories, $cat);
		}

		return $categories;
	} /* }}} */

	/**
	 * Get a category by its name
	 *
	 * The name of a category is by default unique.
	 *
	 * @param string $name human readable name of category
	 * @return object instance of LetoDMS_Core_DocumentCategory
	 */
	function getDocumentCategoryByName($name) { /* {{{ */
		$queryStr = "SELECT * FROM tblCategory where name=".$this->db->qstr($name);

		$resArr = $this->db->getResultArray($queryStr);
		if (!$resArr)
			return false;

		$row = $resArr[0];
		$cat = new LetoDMS_Core_DocumentCategory($row["id"], $row["name"]);
		$cat->setDMS($this);

		return $cat;
	} /* }}} */

	function addDocumentCategory($name) { /* {{{ */
		if (is_object($this->getDocumentCategoryByName($name))) {
			return false;
		}
		$queryStr = "INSERT INTO tblCategory (name) VALUES (".$this->db->qstr($name).")";
		if (!$this->db->getResult($queryStr))
			return false;

		return $this->getDocumentCategory($this->db->getInsertID());
	} /* }}} */

	/**
	 * Get all notifications for a group
	 *
	 * @param object $group group for which notifications are to be retrieved
	 * @param integer $type type of item (T_DOCUMENT or T_FOLDER)
	 * @return array array of notifications
	 */
	function getNotificationsByGroup($group, $type=0) { /* {{{ */
		$queryStr = "SELECT `tblNotify`.* FROM `tblNotify` ".
		 "WHERE `tblNotify`.`groupID` = ". $group->getID();
		if($type) {
			$queryStr .= " AND `tblNotify`.`targetType` = ". (int) $type;
		}

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$notifications = array();
		foreach ($resArr as $row) {
			$not = new LetoDMS_Core_Notification($row["target"], $row["targetType"], $row["userID"], $row["groupID"]);
			$not->setDMS($this);
			array_push($notifications, $cat);
		}

		return $notifications;
	} /* }}} */

	/**
	 * Get all notifications for a user
	 *
	 * @param object $user user for which notifications are to be retrieved
	 * @param integer $type type of item (T_DOCUMENT or T_FOLDER)
	 * @return array array of notifications
	 */
	function getNotificationsByUser($user, $type=0) { /* {{{ */
		$queryStr = "SELECT `tblNotify`.* FROM `tblNotify` ".
		 "WHERE `tblNotify`.`userID` = ". $user->getID();
		if($type) {
			$queryStr .= " AND `tblNotify`.`targetType` = ". (int) $type;
		}

		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$notifications = array();
		foreach ($resArr as $row) {
			$not = new LetoDMS_Core_Notification($row["target"], $row["targetType"], $row["userID"], $row["groupID"]);
			$not->setDMS($this);
			array_push($notifications, $cat);
		}

		return $notifications;
	} /* }}} */

	/**
	 * Create a token to request a new password.
	 * This function will not delete the password but just creates an entry
	 * in tblUserRequestPassword indicating a password request.
	 *
	 * @return string hash value of false in case of an error
	 */
	function createPasswordRequest($user) { /* {{{ */
		$hash = md5(uniqid(time()));
		$queryStr = "INSERT INTO tblUserPasswordRequest (userID, hash, `date`) VALUES (" . $user->getId() . ", " . $this->db->qstr($hash) .", now())";
		$resArr = $this->db->getResult($queryStr);
		if (is_bool($resArr) && !$resArr) return false;
		return $hash;

	} /* }}} */

	/**
	 * Check if hash for a password request is valid.
	 * This function searches a previously create password request and
	 * returns the user.
	 *
	 * @param string $hash
	 */
	function checkPasswordRequest($hash) { /* {{{ */
		/* Get the password request from the database */
		$queryStr = "SELECT * FROM tblUserPasswordRequest where hash=".$this->db->qstr($hash);
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		if (count($resArr) != 1)
			return false;
		$resArr = $resArr[0];

		return $this->getUser($resArr['userID']);

	} /* }}} */

	/**
	 * Delete a password request
	 *
	 * @param string $hash
	 */
	function deletePasswordRequest($hash) { /* {{{ */
		/* Delete the request, so nobody can use it a second time */
		$queryStr = "DELETE FROM tblUserPasswordRequest WHERE hash=".$this->db->qstr($hash);
		if (!$this->db->getResult($queryStr))
			return false;
		return true;
	} /* }}} */

	/**
	 * Return a attribute definition by its id
	 *
	 * This function retrieves a attribute definitionr from the database by
	 * its id.
	 *
	 * @param integer $id internal id of attribute defintion
	 * @return object instance of LetoDMS_Core_AttributeDefinition or false
	 */
	function getAttributeDefinition($id) { /* {{{ */
		if (!is_numeric($id))
			return false;

		$queryStr = "SELECT * FROM tblAttributeDefinitions WHERE id = " . (int) $id;
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false) return false;
		if (count($resArr) != 1) return false;

		$resArr = $resArr[0];

		$attrdef = new LetoDMS_Core_AttributeDefinition($resArr["id"], $resArr["name"], $resArr["objtype"], $resArr["type"], $resArr["multiple"], $resArr["minvalues"], $resArr["maxvalues"], $resArr["valueset"]);
		$attrdef->setDMS($this);
		return $attrdef;
	} /* }}} */

	/**
	 * Return a attribute definition by its name
	 *
	 * This function retrieves an attribute def. from the database by its name.
	 *
	 * @param string $name internal name of attribute def.
	 * @return object instance of LetoDMS_Core_AttributeDefinition or false
	 */
	function getAttributeDefinitionByName($name) { /* {{{ */
		$queryStr = "SELECT * FROM tblAttributeDefinitions WHERE name = " . $this->db->qstr($name);
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false) return false;
		if (count($resArr) != 1) return false;

		$resArr = $resArr[0];

		$attrdef = new LetoDMS_Core_AttributeDefinition($resArr["id"], $resArr["name"], $resArr["objtype"], $resArr["type"], $resArr["multiple"], $resArr["minvalues"], $resArr["maxvalues"], $resArr["valueset"]);
		$attrdef->setDMS($this);
		return $attrdef;
	} /* }}} */

	/**
	 * Return list of all attributes definitions
	 *
	 * @param integer $objtype select those attributes defined for an object type
	 * @return array of instances of LetoDMS_Core_AttributeDefinition or false
	 */
	function getAllAttributeDefinitions($objtype=0) { /* {{{ */
		$queryStr = "SELECT * FROM tblAttributeDefinitions";
		if($objtype) {
			if(is_array($objtype))
				$queryStr .= ' WHERE objtype in (\''.implode("','", $objtype).'\')';
			else
				$queryStr .= ' WHERE objtype='.intval($objtype);
		}
		$queryStr .= ' ORDER BY name';
		$resArr = $this->db->getResultArray($queryStr);

		if (is_bool($resArr) && $resArr == false)
			return false;

		$attrdefs = array();

		for ($i = 0; $i < count($resArr); $i++) {
			$attrdef = new LetoDMS_Core_AttributeDefinition($resArr[$i]["id"], $resArr[$i]["name"], $resArr[$i]["objtype"], $resArr[$i]["type"], $resArr[$i]["multiple"], $resArr[$i]["minvalues"], $resArr[$i]["maxvalues"], $resArr[$i]["valueset"]);
			$attrdef->setDMS($this);
			$attrdefs[$i] = $attrdef;
		}

		return $attrdefs;
	} /* }}} */

	/**
	 * Add a new attribute definition
	 *
	 * @param string $name name of attribute
	 * @param string $type type of attribute
	 * @param boolean $multiple set to 1 if attribute has multiple attributes
	 * @param integer $minvalues minimum number of values
	 * @param integer $maxvalues maximum number of values if multiple is set
	 * @param string $valueset list of allowed values (csv format)
	 * @return object of LetoDMS_Core_User
	 */
	function addAttributeDefinition($name, $objtype, $type, $multiple=0, $minvalues=0, $maxvalues=1, $valueset='') { /* {{{ */
		if (is_object($this->getAttributeDefinitionByName($name))) {
			return false;
		}
		if(!$type)
			return false;
		$queryStr = "INSERT INTO tblAttributeDefinitions (name, objtype, type, multiple, minvalues, maxvalues, valueset) VALUES (".$this->db->qstr($name).", ".intval($objtype).", ".intval($type).", ".intval($multiple).", ".intval($minvalues).", ".intval($maxvalues).", ".$this->db->qstr($valueset).")";
		$res = $this->db->getResult($queryStr);
		if (!$res)
			return false;

		return $this->getAttributeDefinition($this->db->getInsertID());
	} /* }}} */

}
?>
