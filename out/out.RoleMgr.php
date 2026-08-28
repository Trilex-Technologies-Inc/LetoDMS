<?php
include("../inc/inc.Settings.php");
include("../inc/inc.Utils.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if (!$user->hasPermission('role.manage'))
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), getMLText("access_denied"));

$roles = $dms->getAllRoles();
$permissions = $dms->getAllPermissions();
$users = $dms->getAllUsers($settings->_sortUsersInList);
if ($roles === false || $permissions === false || $users === false)
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), "RBAC tables are missing. Run install/update-4.1.0/update.sql first.");
$selected = isset($_GET['roleid']) ? $dms->getRole((int)$_GET['roleid']) : null;
$view = (new UI($GLOBALS['theme'] ?? 'bootstrap'))->factory($theme, 'RoleMgr', array('dms'=>$dms, 'user'=>$user, 'roles'=>$roles, 'permissions'=>$permissions, 'users'=>$users, 'selected'=>$selected));
if ($view) { $view->show(); exit; }
?>
