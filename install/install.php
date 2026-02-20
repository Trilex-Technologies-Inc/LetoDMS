<?php
//    MyDMS. Document Management System
//    Copyright (C) 2010 Matteo Lucarelli, 2011 Uwe Steinmann
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


/**
 * Check Update file
 */
if (file_exists("../inc/inc.Settings.old.php")) {
  echo "You can't install letoDMS, unless you delete " . realpath("../inc/inc.Settings.old.php") . ".";
  exit;
}


/**
 * Check file for installation
 */
if (!file_exists("create_tables-innodb.sql")) {
  echo "Can't install letoDMS, 'create_tables-innodb.sql' missing";
  exit;
}
if (!file_exists("create_tables.sql")) {
  echo "Can't install letoDMS, 'create_tables.sql' missing";
  exit;
}
if (!file_exists("settings.xml.template_install")) {
  echo "Can't install letoDMS, 'settings.xml.template_install' missing";
  exit;
}

/**
 * Functions
 */
function openDBConnection($settings) { /* {{{ */
	switch($settings->_dbDriver) {
		case 'mysql':
		case 'mysqli':
		case 'mysqlnd':
			$dsn = $settings->_dbDriver.":dbname=".$settings->_dbDatabase.";host=".$settings->_dbHostname;
			break;
		case 'sqlite':
			$dsn = $settings->_dbDriver.":".$settings->_dbDatabase;
			break;
	}
	$connTmp = new PDO($dsn, $settings->_dbUser, $settings->_dbPass);
	return $connTmp;
} /* }}} */

function printError($error) { /* {{{ */
	print "<div class=\"alert alert-danger\" role=\"alert\">";
	print "<strong>Error</strong><br />";
	print $error;
	print "</div>";
} /* }}} */

function printWarning($error) { /* {{{ */
	print "<div class=\"alert alert-warning\" role=\"alert\">";
	print "<strong>Warning</strong><br />";
	print $error;
	print "</div>";
} /* }}} */

function printCheckError($resCheck) { /* {{{ */
	$hasError = false;
	foreach($resCheck as $keyRes => $paramRes) {
		if(isset($paramRes['type']) && $paramRes['type'] == 'error')
			$hasError = true;
		$errorMes = getMLText("settings_$keyRes"). " : " . getMLText("settings_".$paramRes["status"]);

		if (isset($paramRes["currentvalue"]))
			$errorMes .= "<br/> =&gt; " . getMLText("settings_currentvalue") . " : " . $paramRes["currentvalue"];
		if (isset($paramRes["suggestionvalue"]))
			$errorMes .= "<br/> =&gt; " . getMLText("settings_suggestionvalue") . " : " . $paramRes["suggestionvalue"];
		if (isset($paramRes["suggestion"]))
			$errorMes .= "<br/> =&gt; " . getMLText("settings_".$paramRes["suggestion"]);
		if (isset($paramRes["systemerror"]))
			$errorMes .= "<br/> =&gt; " . $paramRes["systemerror"];

		if(isset($paramRes['type']) && $paramRes['type'] == 'error')
			printError($errorMes);
		else
			printWarning($errorMes);
	}

	return $hasError;
} /* }}} */

function fileExistsInIncludePath($file) { /* {{{ */
	$paths = explode(PATH_SEPARATOR, get_include_path());
	$found = false;
	foreach($paths as $p) {
		$fullname = $p.DIRECTORY_SEPARATOR.$file;
		if(is_file($fullname)) {
			$found = $fullname;
			break;
		}
	}
	return $found;
} /* }}} */

/**
 * Load default settings + set
 */
define("LETODMS_INSTALL", "on");
define("LETODMS_VERSION", "4.0.0");

require_once('../inc/inc.ClassSettings.php');

$configDir = Settings::getConfigDir();

/**
 * Check if ENABLE_INSTALL_TOOL exists in config dir
 */
if (!$configDir) {
	echo "Fatal error! I could not even find a configuration directory.";
	exit;
}

if (!file_exists($configDir."/ENABLE_INSTALL_TOOL")) {
	echo "For installation of LetoDMS, you must create the file conf/ENABLE_INSTALL_TOOL";
	exit;
}

if (!file_exists($configDir."/settings.xml")) {
	if(!copy("settings.xml.template_install", $configDir."/settings.xml")) {
		echo "Could not create initial configuration file from template. Check directory permission of conf/.";
		exit;
	}
}

// Set folders settings
$settings = new Settings();
$settings->load($configDir."/settings.xml");

$rootDir = realpath ("..");
$rootDir = str_replace ("\\", "/" , $rootDir) . "/";
$installPath = realpath ("install.php");
$installPath = str_replace ("\\", "/" , $installPath);
$tmpToDel = str_replace ($rootDir, "" , $installPath);
$httpRoot = str_replace ($tmpToDel, "" , $_SERVER["REQUEST_URI"]);
do {
	$httpRoot = str_replace ("//", "/" , $httpRoot, $count);
} while ($count<>0);

if(!$settings->_rootDir)
	$settings->_rootDir = $rootDir;
//$settings->_coreDir = $settings->_rootDir;
//$settings->_luceneClassDir = $settings->_rootDir;
if(!$settings->_contentDir) {
	$settings->_contentDir = $settings->_rootDir . 'data/';
	$settings->_luceneDir = $settings->_rootDir . 'data/lucene/';
	$settings->_stagingDir = $settings->_rootDir . 'data/staging/';
}
$settings->_httpRoot = $httpRoot;

if(isset($settings->_extraPath))
	ini_set('include_path', $settings->_extraPath. PATH_SEPARATOR .ini_get('include_path'));

/**
 * Include GUI + Language
 */
include("../inc/inc.Language.php");
include "../languages/English/lang.inc";
include("../inc/inc.ClassUI.php");


UI::htmlStartPage("INSTALL");
?>
<style>
    .bg-custom-primary { background-color: #4a6fa5 !important; }
    .bg-custom-secondary { background-color: #6c757d !important; }
    .bg-opacity-25 { opacity: 0.25; }
</style>
<div class="container mt-4">
<?php
UI::contentHeading("letoDMS Installation for version ".LETODMS_VERSION);
UI::contentContainerStart();


/**
 * Show phpinfo
 */
if (isset($_GET['phpinfo'])) {
	echo '<a href="install.php" class="btn btn-secondary mb-3">' . getMLText("back") . '</a>';
  phpinfo();
	UI::contentContainerEnd();
	UI::htmlEndPage();
  exit();
}

/**
 * check if ENABLE_INSTALL_TOOL shall be removed
 */
if (isset($_GET['disableinstall'])) { /* {{{ */
	if(file_exists($configDir."/ENABLE_INSTALL_TOOL")) {
		if(unlink($configDir."/ENABLE_INSTALL_TOOL")) {
			echo '<div class="alert alert-success">' . getMLText("settings_install_disabled") . '</div>';
			echo '<br/>';
			echo '<a href="' . $httpRoot . '/out/out.Settings.php" class="btn btn-primary">' . getMLText("settings_more_settings") .'</a>';
		} else {
			echo '<div class="alert alert-danger">' . getMLText("settings_cannot_disable") . '</div>';
			echo '<br/>';
			echo '<a href="install.php" class="btn btn-secondary">' . getMLText("back") . '</a>';
		}
	} else {
		echo '<div class="alert alert-danger">' . getMLText("settings_cannot_disable") . '</div>';
		echo '<br/>';
		echo '<a href="install.php" class="btn btn-secondary">' . getMLText("back") . '</a>';
	}
	UI::contentContainerEnd();
	UI::htmlEndPage();
  exit();
} /* }}} */

/**
 * Check System
 */
if (printCheckError( $settings->checkSystem())) { /* {{{ */
	if (function_exists("apache_get_version")) {
  	echo "<div class='mb-2'>Apache version: " . apache_get_version() . "</div>";
	}

	echo "<div class='mb-2'>PHP version: " . phpversion() . "</div>";

	echo "<div class='mb-3'>PHP include path: " . ini_get('include_path') . "</div>";

	echo '<a href="' . $httpRoot . 'install/install.php" class="btn btn-primary me-2">' . getMLText("refresh") . '</a>';
	echo '<a href="' . $httpRoot . 'install/install.php?phpinfo" class="btn btn-info">' . getMLText("version_info") . '</a>';

	exit;
} /* }}} */


if (isset($_POST["action"])) $action=$_POST["action"];
else if (isset($_GET["action"])) $action=$_GET["action"];
else $action=NULL;

if ($action=="setSettings") {
	/**
	 * Get Parameters
	 */
	$settings->_rootDir = $_POST["rootDir"];
	$settings->_httpRoot = $_POST["httpRoot"];
	$settings->_contentDir = $_POST["contentDir"];
	$settings->_luceneDir = $_POST["luceneDir"];
	$settings->_stagingDir = $_POST["stagingDir"];
	$settings->_extraPath = $_POST["extraPath"];
	$settings->_dbDriver = $_POST["dbDriver"];
	$settings->_dbHostname = $_POST["dbHostname"];
	$settings->_dbDatabase = $_POST["dbDatabase"];
	$settings->_dbUser = $_POST["dbUser"];
	$settings->_dbPass = $_POST["dbPass"];
	$settings->_coreDir = $_POST["coreDir"];
	$settings->_luceneClassDir = $_POST["luceneClassDir"];

	/**
	 * Check Parameters, require version 3.3.x
	 */
	$hasError = printCheckError( $settings->check(substr(str_replace('.', '', LETODMS_VERSION), 0,2)));

	if (!$hasError) {
		if(isset($settings->_extraPath))
			ini_set('include_path', $settings->_extraPath. PATH_SEPARATOR .ini_get('include_path'));

		// Create database
		if (isset($_POST["createDatabase"])) {
			$createOK = false;
			$errorMsg = "";

			$connTmp =openDBConnection($settings);
			if ($connTmp) {
				// read SQL file
				if ($settings->_dbDriver=="mysql")
					$queries = file_get_contents("create_tables-innodb.sql");
				elseif($settings->_dbDriver=="sqlite")
					$queries = file_get_contents("create_tables-sqlite3.sql");
				else
					die();

				// generate SQL query
				$queries = explode(";", $queries);

				// execute queries
foreach($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        $connTmp->exec($query);

        if ($connTmp->errorCode() != 0) {
            $errorInfo = $connTmp->errorInfo();
            $errorMsg .= "SQL Error (Code: " . $errorInfo[0] . "): " . $errorInfo[2] . "<br/>";
        }
    }
}
			}

			// error ?
			if (empty($errorMsg))
				$createOK = true;

			$connTmp = null;

			// Show error
			if (!$createOK) {
				echo '<div class="alert alert-danger">' . $errorMsg . '</div>';
				$hasError = true;
			}
		} // create database

		if (!$hasError) {

			// Save settings
			$settings->save();

			$needsupdate = false;
			$connTmp =openDBConnection($settings);
			if ($connTmp) {
				$res = $connTmp->query('select * from tblVersion');
				if($res) {
					if($rec = $res->fetch(PDO::FETCH_ASSOC)) {
						$updatedirs = array();
						$d = dir(".");
						while (false !== ($entry = $d->read())) {
							if(preg_match('/update-([0-9.]*)/', $entry, $matches)) {
								$updatedirs[] = $matches[1];
							}
						}
						$d->close();

						echo "<div class='alert alert-info'>Your current database schema has version ".$rec['major'].'.'.$rec['minor'].'.'.$rec['subminor']."</div>";
						$connTmp = null;

						if($updatedirs) {
							foreach($updatedirs as $updatedir) {
								if($updatedir > $rec['major'].'.'.$rec['minor'].'.'.$rec['subminor']) {
									$needsupdate = true;
									print "<h3 class='mt-3'>Database update to version ".$updatedir." needed</h3>";
									if(file_exists('update-'.$updatedir.'/update.txt')) {
										print "<p>Please read the comments on updating this version. <a href=\"update-".$updatedir."/update.txt\" target=\"_blank\" class='btn btn-sm btn-info'>Read now</a></p>";
									}
									if(file_exists('update-'.$updatedir.'/update.php')) {
										print "<p>Afterwards run the <a href=\"update.php?version=".$updatedir."\" class='btn btn-sm btn-warning'>update script</a>.</p>";
									}
								}
							}
						} else {
							print "<p>Your current database is up to date.</p>";
						}
					}
					if(!$needsupdate) {
						echo '<div class="alert alert-success">' . getMLText("settings_install_success") . '</div>';
						echo '<div class="alert alert-warning">' . getMLText("settings_delete_install_folder") . '</div>';
						echo '<a href="install.php?disableinstall=1" class="btn btn-danger me-2">' . getMLText("settings_disable_install") . '</a>';
						echo '<a href="' . $httpRoot . '/out/out.Settings.php" class="btn btn-primary">' . getMLText("settings_more_settings") .'</a>';
					}
				} else {
					print "<div class='alert alert-danger'>You does not seem to have a valid database. The table tblVersion is missing.</div>";
				}
			}
		}
	}

	// Back link
	echo '<br/>';
	echo '<br/>';
	echo '<a href="' . $httpRoot . '/install/install.php" class="btn btn-secondary">' . getMLText("back") . '</a>';

} else {

	/**
	 * Set parameters
	 */
	?>
	<form action="install.php" method="post" enctype="multipart/form-data">
	<input type="Hidden" name="action" value="setSettings">
	
	<!-- SETTINGS - SYSTEM - SERVER -->
	<div class="card mb-4">
		<div class="card-header bg-primary text-white">
			<h5 class="mb-0"><?php printMLText("settings_Server");?></h5>
		</div>
		<div class="card-body">
			<div class="mb-3" title="<?php printMLText("settings_rootDir_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_rootDir");?>:</label>
				<input type="text" class="form-control" name="rootDir" value="<?php echo $settings->_rootDir ?>" />
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_httpRoot_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_httpRoot");?>:</label>
				<input type="text" class="form-control" name="httpRoot" value="<?php echo $settings->_httpRoot ?>" />
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_contentDir_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_contentDir");?>:</label>
				<input type="text" class="form-control border-info bg-light" name="contentDir" value="<?php echo $settings->_contentDir ?>" />
				<small class="text-info">Important directory - needs write permissions</small>
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_luceneDir_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_luceneDir");?>:</label>
				<input type="text" class="form-control border-info bg-light" name="luceneDir" value="<?php echo $settings->_luceneDir ?>" />
				<small class="text-info">Search index directory</small>
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_stagingDir_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_stagingDir");?>:</label>
				<input type="text" class="form-control border-info bg-light" name="stagingDir" value="<?php echo $settings->_stagingDir ?>" />
				<small class="text-info">Temporary upload directory</small>
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_coreDir_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_coreDir");?>:</label>
				<input type="text" class="form-control" name="coreDir" value="<?php echo $settings->_coreDir ?>" />
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_luceneClassDir_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_luceneClassDir");?>:</label>
				<input type="text" class="form-control" name="luceneClassDir" value="<?php echo $settings->_luceneClassDir ?>" />
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_extraPath_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_extraPath");?>:</label>
				<input type="text" class="form-control" name="extraPath" value="<?php echo $settings->_extraPath ?>" />
			</div>
		</div>
	</div>

	<!-- SETTINGS - SYSTEM - DATABASE -->
	<div class="card mb-4">
		<div class="card-header bg-success text-white">
			<h5 class="mb-0"><?php printMLText("settings_Database");?></h5>
		</div>
		<div class="card-body">
			<div class="mb-3" title="<?php printMLText("settings_dbDriver_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_dbDriver");?>:</label>
				<input type="text" class="form-control" name="dbDriver" value="<?php echo $settings->_dbDriver ?>" />
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_dbHostname_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_dbHostname");?>:</label>
				<input type="text" class="form-control" name="dbHostname" value="<?php echo $settings->_dbHostname ?>" />
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_dbDatabase_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_dbDatabase");?>:</label>
				<input type="text" class="form-control border-success bg-light" name="dbDatabase" value="<?php echo $settings->_dbDatabase ?>" />
				<small class="text-success">Database name</small>
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_dbUser_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_dbUser");?>:</label>
				<input type="text" class="form-control border-success bg-light" name="dbUser" value="<?php echo $settings->_dbUser ?>" />
				<small class="text-success">Database username</small>
			</div>
			
			<div class="mb-3" title="<?php printMLText("settings_dbPass_desc");?>">
				<label class="form-label fw-bold"><?php printMLText("settings_dbPass");?>:</label>
				<input type="password" class="form-control border-success bg-light" name="dbPass" value="<?php echo $settings->_dbPass ?>" />
				<small class="text-success">Database password</small>
			</div>
			
			<div class="mb-4 form-check">
				<input type="checkbox" class="form-check-input" name="createDatabase" id="createDatabase" 
				       style="width: 1.2em; height: 1.2em; cursor: pointer; accent-color: #28a745;" />
				<label class="form-check-label fw-bold" for="createDatabase" style="cursor: pointer;">
					<?php printMLText("settings_createdatabase");?>
				</label>
				<small class="text-muted d-block mt-1">
					Check this to automatically create database tables
				</small>
			</div>
		</div>
	</div>

	<div class="d-grid gap-2">
		<button type="submit" class="btn btn-primary btn-lg">
			<?php printMLText("apply");?>
		</button>
	</div>
</form>

<!-- Add Bootstrap Icons for better visual (optional) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php

}

/*

*/

// just remove info for web page installation
$settings->_printDisclaimer = false;
$settings->_footNote = false;
// end of the page
UI::contentContainerEnd();
?>
</div>
<?php
UI::htmlEndPage();
?>