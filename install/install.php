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
	print "<div class=\"alert alert-error\">";
	print "<strong>Error</strong><br />";
	print $error;
	print "</div>";
} /* }}} */

function printWarning($error) { /* {{{ */
	print "<div class=\"alert\">";
	print "<strong>Warning</strong><br />";
	print $error;
	print "</div>";
} /* }}} */

function installEscape($value) { /* {{{ */
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

$configDir = (new Settings())->getConfigDir();

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
if(!$settings->_contentDir) {
	$settings->_contentDir = $settings->_rootDir . 'data/';
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


(new UI($GLOBALS['theme'] ?? 'bootstrap'))->htmlStartPage("INSTALL");
(new UI($GLOBALS['theme'] ?? 'bootstrap'))->contentContainerStart();
?>
<style type="text/css">
	.install-shell { max-width: 1080px; margin: 24px auto 48px; }
	.install-hero { padding: 28px 32px; margin-bottom: 24px; color: #fff; background: #24445f; background: linear-gradient(135deg, #24445f, #3276b1); border-radius: 8px; box-shadow: 0 8px 24px rgba(36, 68, 95, .18); }
	.install-hero h1 { margin: 0 0 8px; color: #fff; font-size: 30px; line-height: 1.2; }
	.install-hero p { margin: 0; color: rgba(255, 255, 255, .85); font-size: 15px; }
	.install-panel { margin-bottom: 24px; overflow: hidden; background: #fff; border: 1px solid #dfe5ea; border-radius: 8px; box-shadow: 0 3px 12px rgba(30, 50, 70, .07); }
	.install-panel-header { padding: 18px 22px; background: #f7f9fb; border-bottom: 1px solid #dfe5ea; }
	.install-panel-header h2 { margin: 0; font-size: 20px; line-height: 1.3; }
	.install-panel-body { padding: 22px; }
	.install-form .control-group { margin-bottom: 18px; }
	.install-form label { font-weight: 600; color: #34495e; }
	.install-form .input-block-level { box-sizing: border-box; min-height: 38px; }
	.install-form .help-block { margin: 5px 0 0; color: #6f7d89; font-size: 12px; line-height: 1.4; }
	.install-form .recommended { border-color: #e3b341; background-color: #fffdf5; }
	.install-checkbox { padding: 14px 16px; background: #f7f9fb; border: 1px solid #dfe5ea; border-radius: 5px; }
	.install-checkbox label { margin: 0; }
	.install-actions { display: flex; align-items: center; justify-content: flex-end; padding: 18px 22px; background: #f7f9fb; border-top: 1px solid #dfe5ea; }
	.install-actions .btn { min-width: 150px; }
	@media (max-width: 767px) {
		.install-shell { margin: 12px; }
		.install-hero, .install-panel-body { padding: 20px; }
	}
</style>
<main class="install-shell">
	<header class="install-hero">
		<h1>letoDMS Installation</h1>
		<p>Configure version <?php echo installEscape(LETODMS_VERSION); ?> and verify the server and database settings.</p>
	</header>
<?php

/**
 * Show phpinfo
 */
if (isset($_GET['phpinfo'])) {
	echo '<a href="install.php">' . getMLText("back") . '</a>';
  phpinfo();
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->contentContainerEnd();
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->htmlEndPage();
  exit();
}

/**
 * check if ENABLE_INSTALL_TOOL shall be removed
 */
if (isset($_GET['disableinstall'])) { /* {{{ */
	if(file_exists($configDir."/ENABLE_INSTALL_TOOL")) {
		if(unlink($configDir."/ENABLE_INSTALL_TOOL")) {
			echo getMLText("settings_install_disabled");
			echo "<br/><br/>";
			echo '<a href="' . $httpRoot . '/out/out.Settings.php">' . getMLText("settings_more_settings") .'</a>';
		} else {
			echo getMLText("settings_cannot_disable");
			echo "<br/><br/>";
			echo '<a href="install.php">' . getMLText("back") . '</a>';
		}
	} else {
		echo getMLText("settings_cannot_disable");
		echo "<br/><br/>";
		echo '<a href="install.php">' . getMLText("back") . '</a>';
	}
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->contentContainerEnd();
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->htmlEndPage();
  exit();
} /* }}} */

/**
 * Check System
 */
if (printCheckError( $settings->checkSystem())) { /* {{{ */
	if (function_exists("apache_get_version")) {
  	echo "<br/>Apache version: " . apache_get_version();
	}

	echo "<br/>PHP version: " . phpversion();

	echo "<br/>PHP include path: " . ini_get('include_path');

	echo '<br/>';
	echo '<br/>';
	echo '<a href="' . $httpRoot . 'install/install.php">' . getMLText("refresh") . '</a>';
	echo ' - ';
	echo '<a href="' . $httpRoot . 'install/install.php?phpinfo">' . getMLText("version_info") . '</a>';

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
	$settings->_stagingDir = $_POST["stagingDir"];
	$settings->_extraPath = $_POST["extraPath"];
	$settings->_dbDriver = $_POST["dbDriver"];
	$settings->_dbHostname = $_POST["dbHostname"];
	$settings->_dbDatabase = $_POST["dbDatabase"];
	$settings->_dbUser = $_POST["dbUser"];
	$settings->_dbPass = $_POST["dbPass"];
	$settings->_coreDir = $_POST["coreDir"];

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
				// var_dump($query);
					$query = trim($query);
					if (!empty($query)) {
						$connTmp->exec($query);

						if ($connTmp->errorCode() != 0) {
							$errorMsg .= $connTmp->errorInfo() . "<br/>";
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
				echo $errorMsg;
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

						echo "Your current database schema has version ".$rec['major'].'.'.$rec['minor'].'.'.$rec['subminor']."<br /><br />";
						$connTmp = null;

						if($updatedirs) {
							foreach($updatedirs as $updatedir) {
								if($updatedir > $rec['major'].'.'.$rec['minor'].'.'.$rec['subminor']) {
									$needsupdate = true;
									print "<h3>Database update to version ".$updatedir." needed</h3>";
									if(file_exists('update-'.$updatedir.'/update.txt')) {
										print "<p>Please read the comments on updating this version. <a href=\"update-".$updatedir."/update.txt\" target=\"_blank\">Read now</a></p>";
									}
									if(file_exists('update-'.$updatedir.'/update.php')) {
										print "<p>Afterwards run the <a href=\"update.php?version=".$updatedir."\">update script</a>.</p>";
									}
								}
							}
						} else {
							print "<p>Your current database is up to date.</p>";
						}
					}
					if(!$needsupdate) {
						echo getMLText("settings_install_success");
						echo "<br/><br/>";
						echo getMLText("settings_delete_install_folder");
						echo "<br/><br/>";
						echo '<a href="install.php?disableinstall=1">' . getMLText("settings_disable_install") . '</a>';
						echo "<br/><br/>";

						echo '<a href="../out/out.Settings.php">' . getMLText("settings_more_settings") .'</a>';
					}
				} else {
					print "<p>You does not seem to have a valid database. The table tblVersion is missing.</p>";
				}
			}
		}
	}

	// Back link
	echo '<br/>';
	echo '<br/>';
	echo '<a href="/install/install.php">' . getMLText("back") . '</a>';

} else {

	/**
	 * Set parameters
	 */
	?>
	<form class="install-form" action="install.php" method="post">
		<input type="hidden" name="action" value="setSettings">

		<section class="install-panel">
			<div class="install-panel-header"><h2><?php printMLText("settings_Server");?></h2></div>
			<div class="install-panel-body">
				<div class="row-fluid">
					<div class="span6">
						<div class="control-group">
							<label for="rootDir"><?php printMLText("settings_rootDir");?>:</label>
							<input class="input-block-level" id="rootDir" name="rootDir" value="<?php echo installEscape($settings->_rootDir); ?>">
							<span class="help-block"><?php printMLText("settings_rootDir_desc");?></span>
						</div>
						<div class="control-group">
							<label for="httpRoot"><?php printMLText("settings_httpRoot");?>:</label>
							<input class="input-block-level" id="httpRoot" name="httpRoot" value="<?php echo installEscape($settings->_httpRoot); ?>">
							<span class="help-block"><?php printMLText("settings_httpRoot_desc");?></span>
						</div>
						<div class="control-group">
							<label for="contentDir"><?php printMLText("settings_contentDir");?>:</label>
							<input class="input-block-level recommended" id="contentDir" name="contentDir" value="<?php echo installEscape($settings->_contentDir); ?>">
							<span class="help-block"><?php printMLText("settings_contentDir_desc");?></span>
						</div>
						<div class="control-group">
							<label for="stagingDir"><?php printMLText("settings_stagingDir");?>:</label>
							<input class="input-block-level recommended" id="stagingDir" name="stagingDir" value="<?php echo installEscape($settings->_stagingDir); ?>">
							<span class="help-block"><?php printMLText("settings_stagingDir_desc");?></span>
						</div>
					</div>
					<div class="span6">
						<div class="control-group">
							<label for="coreDir"><?php printMLText("settings_coreDir");?>:</label>
							<input class="input-block-level" id="coreDir" name="coreDir" value="<?php echo installEscape($settings->_coreDir); ?>">
							<span class="help-block"><?php printMLText("settings_coreDir_desc");?></span>
						</div>
						<div class="control-group">
							<label for="extraPath"><?php printMLText("settings_extraPath");?>:</label>
							<input class="input-block-level" id="extraPath" name="extraPath" value="<?php echo installEscape($settings->_extraPath); ?>">
							<span class="help-block"><?php printMLText("settings_extraPath_desc");?></span>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="install-panel">
			<div class="install-panel-header"><h2><?php printMLText("settings_Database");?></h2></div>
			<div class="install-panel-body">
				<div class="row-fluid">
					<div class="span6">
						<div class="control-group">
							<label for="dbDriver"><?php printMLText("settings_dbDriver");?>:</label>
							<input class="input-block-level" id="dbDriver" name="dbDriver" value="<?php echo installEscape($settings->_dbDriver); ?>">
							<span class="help-block"><?php printMLText("settings_dbDriver_desc");?></span>
						</div>
						<div class="control-group">
							<label for="dbHostname"><?php printMLText("settings_dbHostname");?>:</label>
							<input class="input-block-level" id="dbHostname" name="dbHostname" value="<?php echo installEscape($settings->_dbHostname); ?>">
							<span class="help-block"><?php printMLText("settings_dbHostname_desc");?></span>
						</div>
						<div class="control-group">
							<label for="dbDatabase"><?php printMLText("settings_dbDatabase");?>:</label>
							<input class="input-block-level recommended" id="dbDatabase" name="dbDatabase" value="<?php echo installEscape($settings->_dbDatabase); ?>">
							<span class="help-block"><?php printMLText("settings_dbDatabase_desc");?></span>
						</div>
					</div>
					<div class="span6">
						<div class="control-group">
							<label for="dbUser"><?php printMLText("settings_dbUser");?>:</label>
							<input class="input-block-level recommended" id="dbUser" name="dbUser" value="<?php echo installEscape($settings->_dbUser); ?>" autocomplete="username">
							<span class="help-block"><?php printMLText("settings_dbUser_desc");?></span>
						</div>
						<div class="control-group">
							<label for="dbPass"><?php printMLText("settings_dbPass");?>:</label>
							<input class="input-block-level recommended" id="dbPass" name="dbPass" value="<?php echo installEscape($settings->_dbPass); ?>" type="password" autocomplete="new-password">
							<span class="help-block"><?php printMLText("settings_dbPass_desc");?></span>
						</div>
						<div class="install-checkbox">
							<label class="checkbox" for="createDatabase">
								<input id="createDatabase" name="createDatabase" type="checkbox">
								<?php printMLText("settings_createdatabase");?>
							</label>
						</div>
					</div>
				</div>
			</div>
			<div class="install-actions">
				<button class="btn btn-primary btn-large" type="submit"><?php printMLText("apply");?></button>
			</div>
		</section>
	</form>
	<?php

}

/*

*/

// just remove info for web page installation
$settings->_printDisclaimer = false;
$settings->_footNote = false;
// end of the page
echo '</main>';
(new UI($GLOBALS['theme'] ?? 'bootstrap'))->contentContainerEnd();
(new UI($GLOBALS['theme'] ?? 'bootstrap'))->htmlEndPage();
?>
