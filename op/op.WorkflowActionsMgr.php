<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//    Copyright (C) 2010-2013 Uwe Steinmann
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

include("../inc/inc.Settings.php");
include("../inc/inc.LogInit.php");
include("../inc/inc.Utils.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");
include("../inc/inc.ClassPasswordStrength.php");

if (!$user->isAdmin()) {
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

if (isset($_POST["action"])) $action=$_POST["action"];
else $action=NULL;

// add new workflow ---------------------------------------------------------
if ($action == "addworkflowaction") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('addworkflowaction')) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	$name    = $_POST["name"];
	$docstatus = $_POST["docstatus"];

	if (is_object($dms->getWorkflowActionByName($name))) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("workflow_action_exists"));
	}

	$newWorkflowaction = $dms->addWorkflowAction($name, $docstatus);
	if (!$newWorkflowaction) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}

	$workflowactionid = $newWorkflowaction->getID();
	add_log_line(".php&action=addworkflowaction&name=".$name);
}

// delete user ------------------------------------------------------------
else if ($action == "removeworkflowaction") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('removeworkflowaction')) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (isset($_POST["workflowactionid"])) {
		$workflowactionid = $_POST["workflowactionid"];
	}

	if (!isset($workflowactionid) || !is_numeric($workflowactionid) || intval($workflowactionid)<1) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_workflow_id"));
	}

	$workflowActionToRemove = $dms->getWorkflowAction($workflowactionid);
	if (!is_object($workflowActionToRemove)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_workflow_id"));
	}

	if (!$workflowActionToRemove->remove()) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}

	add_log_line(".php&action=removeworkflowaction&workflowactionid=".$workflowactionid);

	$workflowactionid=-1;
}

// modify workflow ---------------------------------------------------------
else if ($action == "editworkflowaction") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('editworkflowaction')) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (!isset($_POST["workflowactionid"]) || !is_numeric($_POST["workflowactionid"]) || intval($_POST["workflowactionid"])<1) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_workflow_id"));
	}

	$workflowactionid=$_POST["workflowactionid"];
	$editedWorkflowAction = $dms->getWorkflowAction($workflowactionid);

	if (!is_object($editedWorkflowAction)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_workflow_id"));
	}

	$name = $_POST["name"];
	$docstatus = $_POST["docstatus"];

	if ($editedWorkflowAction->getName() != $name)
		$editedWorkflowAction->setName($name);

	add_log_line(".php&action=editworkflowaction&workflowactionid=".$workflow);

}
else (new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("unknown_command"));

header("Location:../out/out.WorkflowActionsMgr.php?workflowactionid=".$workflowactionid);

?>
