<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//
//    This program is free software; you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation; either version 2 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program; if not, write to the Free Software
//    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

require_once("../inc/inc.Utils.php");
require_once('../inc/inc.ClassSettings.php');

$configDir = Settings::getConfigDir();
$settings = new Settings();
$settings->load($configDir."/settings.xml");

/**
 * Check if ENABLE_INSTALL_TOOL exists in config dir
 */
if (!file_exists($configDir."/ENABLE_INSTALL_TOOL")) {
	echo "For installation of LetoDMS, you must create the file conf/ENABLE_INSTALL_TOOL";
	exit;
}

require_once("../inc/inc.Language.php");
require_once("../inc/inc.ClassUI.php");

UI::htmlStartPage('Database update');
UI::contentHeading("letoDMS Installation for version ".$_GET['version']);
UI::contentContainerStart();

require_once($settings->_ADOdbPath."adodb/adodb.inc.php");
$db = ADONewConnection($settings->_dbDriver);
if ($db) {
	$db->Connect($settings->_dbHostname, $settings->_dbUser, $settings->_dbPass, $settings->_dbDatabase);
	if (!$db->IsConnected()) {
		die;
	}
}

$errorMsg = '';
$res = $db->Execute('select * from tblVersion');
if($rec = $res->FetchRow()) {
	if($_GET['version'] > $rec['major'].'.'.$rec['minor'].'.'.$rec['subminor']) {

//		$queries = file_get_contents('update-'.$_GET['version'].'/update.sql');
//		$queries = explode(";", $queries);
		$queries = file('update-'.$_GET['version'].'/update.sql');

		// execute queries
		if($queries) {
			echo "<h3>Updating database schema</h3>";
			$sqlquery = "";
			foreach($queries as $query) {
				$query = trim($query);
				/* check if comment or empty line */
				if(substr($query, 0, 2) == "--" || $query == "") {
					if($sqlquery) {
						echo $sqlquery."<br />";
						$db->Execute($sqlquery);

						if ($db->ErrorNo()<>0) {
							$errorMsg .= $db->ErrorMsg() . "<br/>";
						}
						$sqlquery = "";
					}
				} else {
					$sqlquery .= " ".$query;
				}
			}
			/* run the last query */
			if($sqlquery) {
				echo $sqlquery."<br />";
				$db->Execute($sqlquery);

				if ($db->ErrorNo()<>0) {
					$errorMsg .= $db->ErrorMsg() . "<br/>";
				}
				$sqlquery = "";
			}
		}
	} else {
		echo "<p>Database schema already up to date.</p>";
	}


	if(!$errorMsg) {
		echo "<h3>Running update script</h3>";
		include('update-'.$_GET['version'].'/update.php');
	} else {
		echo $errorMsg;
	}
	echo "<p><a href=\"install.php\">Go back to installation.</a></p>";
} else {
	echo "<p>Could not determine database schema version.</p>";
}

UI::contentContainerEnd();
UI::htmlEndPage();
?>
