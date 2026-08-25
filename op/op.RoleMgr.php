<?php
include("../inc/inc.Settings.php");
include("../inc/inc.LogInit.php");
include("../inc/inc.Utils.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if (!$user->hasPermission('role.manage'))
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), getMLText("access_denied"));
if (!checkFormKey('rolemgr'))
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), getMLText("invalid_request_token"));

$action = isset($_POST['action']) ? $_POST['action'] : '';
$roleid = isset($_POST['roleid']) ? (int)$_POST['roleid'] : 0;
if ($action === 'add') {
	$role = $dms->addRole(isset($_POST['name']) ? $_POST['name'] : '', isset($_POST['description']) ? $_POST['description'] : '');
	if (!$role) (new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), "Could not create role. The name may already exist.");
	$roleid = $role->getID();
} elseif ($action === 'save' && $dms->getRole($roleid)) {
	if (!$dms->updateRole($roleid, $_POST['name'], isset($_POST['description']) ? $_POST['description'] : '') ||
		!$dms->setRolePermissions($roleid, isset($_POST['permissions']) ? $_POST['permissions'] : array()) ||
		!$dms->setRoleUsers($roleid, isset($_POST['users']) ? $_POST['users'] : array()))
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), getMLText("error_occured"));
} elseif ($action === 'delete' && $dms->getRole($roleid)) {
	if (!$dms->removeRole($roleid)) (new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), getMLText("error_occured"));
	$roleid = 0;
} else {
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"), getMLText("unknown_command"));
}
add_log_line(".php&action=".$action."&roleid=".$roleid);
header("Location:../out/out.RoleMgr.php".($roleid ? "?roleid=".$roleid : ""));
?>
