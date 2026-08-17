<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2009-2012 Uwe Steinmann
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
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassEmail.php");
include("../inc/inc.Authentication.php");

if (!$user->isAdmin()) {
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

if (isset($_POST["action"])) $action=$_POST["action"];
else $action=NULL;

// add new attribute definition ---------------------------------------------
if ($action == "addattrdef") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('addattrdef')) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	$name = trim($_POST["name"]);
	$type = intval($_POST["type"]);
	$objtype = intval($_POST["objtype"]);
	if(isset($_POST["multiple"]))
		$multiple = trim($_POST["multiple"]);
	else
		$multiple = 0;
	$minvalues = intval($_POST["minvalues"]);
	$maxvalues = intval($_POST["maxvalues"]);
	$valueset = trim($_POST["valueset"]);

	if($name == '') {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("attrdef_noname"));
	}
	if (is_object($dms->getAttributeDefinitionByName($name))) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("attrdef_exists"));
	}
	$newAttrdef = $dms->addAttributeDefinition($name, $objtype, $type, $multiple, $minvalues, $maxvalues, $valueset);
	if (!$newAttrdef) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	$attrdefid=$newAttrdef->getID();
}

// delet attribute definition -----------------------------------------------
else if ($action == "removeattrdef") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('removeattrdef')) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (!isset($_POST["attrdefid"]) || !is_numeric($_POST["attrdefid"]) || intval($_POST["attrdefid"])<1) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}
	$attrdefid = $_POST["attrdefid"];
	$attrdef = $dms->getAttributeDefinition($attrdefid);
	if (!is_object($attrdef)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}

	if (!$attrdef->remove()) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	$attrdefid=-1;
}

// edit attribute definition -----------------------------------------------
else if ($action == "editattrdef") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('editattrdef')) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (!isset($_POST["attrdefid"]) || !is_numeric($_POST["attrdefid"]) || intval($_POST["attrdefid"])<1) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}
	$attrdefid = $_POST["attrdefid"];
	$attrdef = $dms->getAttributeDefinition($attrdefid);
	if (!is_object($attrdef)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}

	$name = $_POST["name"];
	$type = intval($_POST["type"]);
	$objtype = intval($_POST["objtype"]);
	if(isset($_POST["multiple"]))
		$multiple = trim($_POST["multiple"]);
	else
		$multiple = 0;
	$minvalues = intval($_POST["minvalues"]);
	$maxvalues = intval($_POST["maxvalues"]);
	$valueset = trim($_POST["valueset"]);
	if (!$attrdef->setName($name)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	if (!$attrdef->setType($type)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	if (!$attrdef->setObjType($objtype)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	if (!$attrdef->setMultipleValues($multiple)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	if (!$attrdef->setMinValues($minvalues)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	if (!$attrdef->setMaxValues($maxvalues)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	if (!$attrdef->setValueSet($valueset)) {
		(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
}

else {
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("unknown_command"));
}

header("Location:../out/out.AttributeMgr.php?attrdefid=".$attrdefid);

?>

