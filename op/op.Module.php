<?php
include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");
require_once("../inc/inc.ClassModuleManager.php");

$moduleName = isset($_GET['module']) ? $_GET['module'] : '';
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $moduleName))
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Modules', 'Invalid module name.');
$moduleManager = new LetoDMS_ModuleManager($db, $settings->_rootDir.'modules', $settings->_dbDriver);
$module = $moduleManager->get($moduleName);
if (!$module || !$module['installed'] || !$module['enabled'])
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Modules', 'This module is disabled.');
if (empty($module['op_controller']) || !is_file($module['path'].'/'.$module['op_controller']))
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Modules', 'This module does not provide an operation controller.');
require $module['path'].'/'.$module['op_controller'];

