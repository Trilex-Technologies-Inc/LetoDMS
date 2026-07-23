<?php
include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");
require_once("../inc/inc.ClassModuleManager.php");

if (!$user->isAdmin()) (new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Modules', getMLText('access_denied'));
if (!checkFormKey('modulemanager')) (new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Modules', getMLText('invalid_request_token'));
$name = isset($_POST['module']) ? $_POST['module'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $name)) (new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Modules', 'Invalid module name.');
$manager = new LetoDMS_ModuleManager($db, $settings->_rootDir.'modules', $settings->_dbDriver);
$ok = false;
switch ($action) {
	case 'install': $ok = $manager->install($name); break;
	case 'uninstall': $ok = $manager->uninstall($name); break;
	case 'enable': $ok = $manager->setEnabled($name, true); break;
	case 'disable': $ok = $manager->setEnabled($name, false); break;
}
$message = $ok ? ucfirst($action).' completed.' : 'The requested module action failed.';
header('Location: ../out/out.ModuleManager.php?message='.urlencode($message));

