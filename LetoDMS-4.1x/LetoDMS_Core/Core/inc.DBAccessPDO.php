<?php
/**
 * Implementation of database access using PDO
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to represent the database access for the document management
 * This class uses PDO for the actual database access.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DatabaseAccess {
	/**
	 * @var boolean set to true for debug mode
	 */
	public $_debug;

	/**
	 * @var string name of database driver (mysql or sqlite3)
	 */
	protected $_driver;

	/**
	 * @var string name of hostname
	 */
	protected $_hostname;

	/**
	 * @var string name of database
	 */
	protected $_database;

	/**
	 * @var string name of database user
	 */
	protected $_user;

	/**
	 * @var string password of database user
	 */
	protected $_passw;

	/**
	 * @var object internal database connection
	 */
	private $_conn;

	/**
	 * @var boolean set to true if connection to database is established
	 */
	private $_connected;

	/**
	 * @var boolean set to true if temp. table for tree view has been created
	 */
	private  $_ttreviewid;

	/**
	 * @var boolean set to true if temp. table for approvals has been created
	 */
	private $_ttapproveid;

	/**
	 * @var boolean set to true if temp. table for doc status has been created
	 */
	private $_ttstatid;

	/**
	 * @var boolean set to true if temp. table for doc content has been created
	 */
	private $_ttcontentid;

	/**
	 * @var boolean set to true if in a database transaction
	 */
	private $_intransaction;
	
	/**
	 * Return list of all database tables
	 *
	 * This function is used to retrieve a list of database tables for backup
	 *
	 * @return array list of table names
	 */
	function TableList() { /* {{{ */
		switch($this->_driver) {
			case 'mysql':
				$sql = "select TABLE_NAME as name from information_schema.tables where TABLE_SCHEMA='letodms' and TABLE_TYPE='BASE TABLE'";
				break;
			case 'sqlite':
				$sql = "select tbl_name as name from sqlite_master where type='table'";
				break;
			default:
				return false;
		}
		$arr = $this->getResultArray($sql);
		$res = array();
		foreach($arr as $tmp)
			$res[] = $tmp['name'];
		return $res;
	}	/* }}} */

	/**
	 * Constructor of LetoDMS_Core_DatabaseAccess
	 *
	 * Sets all database parameters but does not connect.
	 *
	 * @param string $driver the database type e.g. mysql, sqlite
	 * @param string $hostname host of database server
	 * @param string $user name of user having access to database
	 * @param string $passw password of user
	 * @param string $database name of database
	 */
	function LetoDMS_Core_DatabaseAccess($driver, $hostname, $user, $passw, $database = false) { /* {{{ */
		$this->_driver = $driver;
		$this->_hostname = $hostname;
		$this->_database = $database;
		$this->_user = $user;
		$this->_passw = $passw;
		$this->_connected = false;
		// $tt*****id is a hack to ensure that we do not try to create the
		// temporary table twice during a single connection. Can be fixed by
		// using Views (MySQL 5.0 onward) instead of temporary tables.
		// CREATE ... IF NOT EXISTS cannot be used because it has the
		// unpleasant side-effect of performing the insert again even if the
		// table already exists.
		//
		// See createTemporaryTable() method for implementation.
		$this->_ttreviewid = false;
		$this->_ttapproveid = false;
		$this->_ttstatid = false;
		$this->_ttcontentid = false;
		$this->_debug = false;
	} /* }}} */

	/**
	 * Connect to database
	 *
	 * @return boolean true if connection could be established, otherwise false
	 */
	function connect() { /* {{{ */
		switch($this->_driver) {
			case 'mysql':
			case 'mysqli':
			case 'mysqlnd':
				$dsn = $this->_driver.":dbname=".$this->_database.";host=".$this->_hostname;
				break;
			case 'sqlite':
				$dsn = $this->_driver.":".$this->_database;
				break;
		}
		$this->_conn = new PDO($dsn, $this->_user, $this->_passw);
		if (!$this->_conn)
			return false;

		switch($this->_driver) {
			case 'mysql':
				$this->_conn->exec('SET NAMES utf8');
				break;
		}
		$this->_connected = true;
		return true;
	} /* }}} */

	/**
	 * Make sure a database connection exisits
	 *
	 * This function checks for a database connection. If it does not exists
	 * it will reconnect.
	 *
	 * @return boolean true if connection is established, otherwise false
	 */
	function ensureConnected() { /* {{{ */
		if (!$this->_connected) return $this->connect();
		else return true;
	} /* }}} */

	/**
	 * Sanitize String used in database operations
	 *
	 * @param string text
	 * @return string sanitized string
	 */
	function qstr($text) { /* {{{ */
		return $this->_conn->quote($text);
	} /* }}} */

	/**
	 * Execute SQL query and return result
	 *
	 * Call this function only with sql query which return data records.
	 *
	 * @param string $queryStr sql query
	 * @return array/boolean data if query could be executed otherwise false
	 */
	function getResultArray($queryStr) { /* {{{ */
		$resArr = array();
		
		$res = $this->_conn->query($queryStr);
		if ($res === false) {
			if($this->_debug)
				echo "error: ".$queryStr."<br />";
			return false;
		}
		$resArr = $res->fetchAll(PDO::FETCH_ASSOC);
//		$res->Close();
		return $resArr;
	} /* }}} */

	/**
	 * Execute SQL query
	 *
	 * Call this function only with sql query which do not return data records.
	 *
	 * @param string $queryStr sql query
	 * @param boolean $silent not used anymore. This was used when this method
	 *        still issued an error message
	 * @return boolean true if query could be executed otherwise false
	 */
	function getResult($queryStr, $silent=false) { /* {{{ */
		$res = $this->_conn->exec($queryStr);
		if($res === false) {
			if($this->_debug)
				echo "error: ".$queryStr."<br />";
			return false;
		} else
			return true;
		
		return $res;
	} /* }}} */

	function startTransaction() { /* {{{ */
		if(!$this->_intransaction) {
			$this->_conn->beginTransaction();
		}
		$this->_intransaction++;
	} /* }}} */

	function rollbackTransaction() { /* {{{ */
		if($this->_intransaction == 1) {
			$this->_conn->rollBack();
		}
		$this->_intransaction--;
	} /* }}} */

	function commitTransaction() { /* {{{ */
		if($this->_intransaction == 1) {
			$this->_conn->commit();
		}
		$this->_intransaction--;
	} /* }}} */

	/**
	 * Return the id of the last instert record
	 *
	 * @return integer id used in last autoincrement
	 */
	function getInsertID() { /* {{{ */
		return $this->_conn->lastInsertId();
	} /* }}} */

	function getErrorMsg() { /* {{{ */
		$info = $this->_conn->errorInfo();
		return($info[2]);
	} /* }}} */

	function getErrorNo() { /* {{{ */
		return $this->_conn->errorCode();
	} /* }}} */

	/**
	 * Create various temporary tables to speed up and simplify sql queries
	 */
	function createTemporaryTable($tableName, $override=false) { /* {{{ */
		if (!strcasecmp($tableName, "ttreviewid")) {
			switch($this->_driver) {
				case 'sqlite':
					$queryStr = "CREATE TEMPORARY TABLE IF NOT EXISTS `ttreviewid` AS ".
						"SELECT `tblDocumentReviewLog`.`reviewID`, ".
						"MAX(`tblDocumentReviewLog`.`reviewLogID`) AS `maxLogID` ".
						"FROM `tblDocumentReviewLog` ".
						"GROUP BY `tblDocumentReviewLog`.`reviewID` ".
						"ORDER BY `tblDocumentReviewLog`.`reviewLogID`";
				break;
				default:
					$queryStr = "CREATE TEMPORARY TABLE IF NOT EXISTS `ttreviewid` (PRIMARY KEY (`reviewID`), INDEX (`maxLogID`)) ".
						"SELECT `tblDocumentReviewLog`.`reviewID`, ".
						"MAX(`tblDocumentReviewLog`.`reviewLogID`) AS `maxLogID` ".
						"FROM `tblDocumentReviewLog` ".
						"GROUP BY `tblDocumentReviewLog`.`reviewID` ".
						"ORDER BY `tblDocumentReviewLog`.`reviewLogID`";
			}
			if (!$this->_ttreviewid) {
				if (!$this->getResult($queryStr))
					return false;
				$this->_ttreviewid=true;
			}
			else {
				if (is_bool($override) && $override) {
					if (!$this->getResult("DELETE FROM `ttreviewid`"))
						return false;
					if (!$this->getResult($queryStr))
						return false;
				}
			}
			return $this->_ttreviewid;
		}
		else if (!strcasecmp($tableName, "ttapproveid")) {
			switch($this->_driver) {
				case 'sqlite':
					$queryStr = "CREATE TEMPORARY TABLE IF NOT EXISTS `ttapproveid` AS ".
						"SELECT `tblDocumentApproveLog`.`approveID`, ".
						"MAX(`tblDocumentApproveLog`.`approveLogID`) AS `maxLogID` ".
						"FROM `tblDocumentApproveLog` ".
						"GROUP BY `tblDocumentApproveLog`.`approveID` ".
						"ORDER BY `tblDocumentApproveLog`.`approveLogID`";
					break;
				default:
					$queryStr = "CREATE TEMPORARY TABLE IF NOT EXISTS `ttapproveid` (PRIMARY KEY (`approveID`), INDEX (`maxLogID`)) ".
						"SELECT `tblDocumentApproveLog`.`approveID`, ".
						"MAX(`tblDocumentApproveLog`.`approveLogID`) AS `maxLogID` ".
						"FROM `tblDocumentApproveLog` ".
						"GROUP BY `tblDocumentApproveLog`.`approveID` ".
						"ORDER BY `tblDocumentApproveLog`.`approveLogID`";
			}
			if (!$this->_ttapproveid) {
				if (!$this->getResult($queryStr))
					return false;
				$this->_ttapproveid=true;
			}
			else {
				if (is_bool($override) && $override) {
					if (!$this->getResult("DELETE FROM `ttapproveid`"))
						return false;
					if (!$this->getResult($queryStr))
						return false;
				}
			}
			return $this->_ttapproveid;
		}
		else if (!strcasecmp($tableName, "ttstatid")) {
			switch($this->_driver) {
				case 'sqlite':
					$queryStr = "CREATE TEMPORARY TABLE IF NOT EXISTS `ttstatid` AS ".
						"SELECT `tblDocumentStatusLog`.`statusID` AS `statusID`, ".
						"MAX(`tblDocumentStatusLog`.`statusLogID`) AS `maxLogID` ".
						"FROM `tblDocumentStatusLog` ".
						"GROUP BY `tblDocumentStatusLog`.`statusID` ".
						"ORDER BY `tblDocumentStatusLog`.`statusLogID`";
					break;
				default:
					$queryStr = "CREATE TEMPORARY TABLE IF NOT EXISTS `ttstatid` (PRIMARY KEY (`statusID`), INDEX (`maxLogID`)) ".
						"SELECT `tblDocumentStatusLog`.`statusID`, ".
						"MAX(`tblDocumentStatusLog`.`statusLogID`) AS `maxLogID` ".
						"FROM `tblDocumentStatusLog` ".
						"GROUP BY `tblDocumentStatusLog`.`statusID` ".
						"ORDER BY `tblDocumentStatusLog`.`statusLogID`";
			}
			if (!$this->_ttstatid) {
				if (!$this->getResult($queryStr))
					return false;
				$this->_ttstatid=true;
			}
			else {
				if (is_bool($override) && $override) {
					if (!$this->getResult("DELETE FROM `ttstatid`"))
						return false;
					if (!$this->getResult($queryStr))
						return false;
				}
			}
			return $this->_ttstatid;
		}
		else if (!strcasecmp($tableName, "ttcontentid")) {
			switch($this->_driver) {
				case 'sqlite':
					$queryStr = "CREATE TEMPORARY TABLE `ttcontentid` AS ".
						"SELECT `tblDocumentContent`.`document` AS `document`, ".
						"MAX(`tblDocumentContent`.`version`) AS `maxVersion` ".
						"FROM `tblDocumentContent` ".
						"GROUP BY `tblDocumentContent`.`document` ".
						"ORDER BY `tblDocumentContent`.`document`";
					break;
				default:
					$queryStr = "CREATE TEMPORARY TABLE `ttcontentid` (PRIMARY KEY (`document`), INDEX (`maxVersion`)) ".
						"SELECT `tblDocumentContent`.`document`, ".
						"MAX(`tblDocumentContent`.`version`) AS `maxVersion` ".
						"FROM `tblDocumentContent` ".
						"GROUP BY `tblDocumentContent`.`document` ".
						"ORDER BY `tblDocumentContent`.`document`";
			}
			if (!$this->_ttcontentid) {
				if (!$this->getResult($queryStr))
					return false;
				$this->_ttcontentid=true;
			}
			else {
				if (is_bool($override) && $override) {
					if (!$this->getResult("DELETE FROM `ttcontentid`"))
						return false;
					if (!$this->getResult($queryStr))
						return false;
				}
			}
			return $this->_ttcontentid;
		}
		return false;
	} /* }}} */
}

?>
