<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
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
include("../inc/inc.Authentication.php");

if ($user->isGuest()) {
	UI::exitError(getMLText("edit_default_keywords"),getMLText("access_denied"));
}

if (isset($_POST["action"])) $action=$_POST["action"];
else $action=NULL;

/* Create new category ------------------------------------------------ */
if ($action == "addcategory") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('addcategory')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	$name = $_POST["name"];
	if (is_object($dms->getKeywordCategoryByName($name, $user->getID()))) {
		UI::exitError(getMLText("admin_tools"),getMLText("keyword_exists"));
	}
	$newCategory = $dms->addKeywordCategory($user->getID(), $name);
	if (!$newCategory) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	$categoryid=$newCategory->getID();
}


/* Delete category ---------------------------------------------------- */
else if ($action == "removecategory") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('removecategory')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (!isset($_POST["categoryid"]) || !is_numeric($_POST["categoryid"]) || intval($_POST["categoryid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_keyword_category"));
	}
	$categoryid = $_POST["categoryid"];
	$category = $dms->getKeywordCategory($categoryid);
	if (!is_object($category)) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_keyword_category"));
	}

	$owner = $category->getOwner();
	if ($owner->getID() != $user->getID()) {
		UI::exitError(getMLText("personal_default_keywords"),getMLText("access_denied"));
	}
	if (!$category->remove()) {
		UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
	}
	$categoryid=-1;
}

/* Edit category: new name -------------------------------------------- */
else if ($action == "editcategory") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('editcategory')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (!isset($_POST["categoryid"]) || !is_numeric($_POST["categoryid"]) || intval($_POST["categoryid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_keyword_category"));
	}
	$categoryid = $_POST["categoryid"];
	$category = $dms->getKeywordCategory($categoryid);
	if (!is_object($category)) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_keyword_category"));
	}

	$owner = $category->getOwner();
	if ($owner->getID() != $user->getID()) {
		UI::exitError(getMLText("personal_default_keywords"),getMLText("access_denied"));
	}

	$name = $_POST["name"];
	if (!$category->setName($name)) {
		UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
	}
}

/* Edit category: new keyword list ----------------------------------- */
else if ($action == "newkeywords") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('newkeywords')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	$categoryid = (int) $_POST["categoryid"];
	$category = $dms->getKeywordCategory($categoryid);
	if (is_object($category)) {
		$owner    = $category->getOwner();
		if ($owner->getID() != $user->getID()) {
			UI::exitError(getMLText("personal_default_keywords"),getMLText("access_denied"));
		}

		if (isset($_POST["keywords"])) {
			$keywords = $_POST["keywords"];
		}
		if(trim($keywords)) {
			if (!$category->addKeywordList($keywords)) {
				UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
			}
		}
	}
	else UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
}

/* Edit category: edit keyword list ----------------------------------*/
else if ($action == "editkeywords") {

	if (!isset($_POST["categoryid"]) || !is_numeric($_POST["categoryid"]) || intval($_POST["categoryid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_keyword_category"));
	}
	
	$categoryid = $_POST["categoryid"];
	$category = $dms->getKeywordCategory($categoryid);
	if (is_object($category)) {
		$owner = $category->getOwner();
		if ($owner->getID() != $user->getID()) {
			UI::exitError(getMLText("personal_default_keywords"),getMLText("access_denied"));
		}

		if (isset($_POST["keywordsid"])) {
			$keywordsid = intval($_POST["keywordsid"]);
		}
		else {
			$keywordsid = intval($_GET["keywordsid"]);
		}
		if (!is_numeric($keywordsid)) {
			UI::exitError(getMLText("personal_default_keywords"),getMLText("unknown_keyword_category"));
		}
		
		if (!$category->editKeywordList($keywordsid, $_POST["keywords"])) {
			UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
		}
	}
	else UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
}

/* Edit category: delete keyword list -------------------------------- */
else if ($action == "removekeywords") {

	/* Check if the form data comes for a trusted request */
	if(!checkFormKey('removekeywords')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (!isset($_POST["categoryid"]) || !is_numeric($_POST["categoryid"]) || intval($_POST["categoryid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_keyword_category"));
	}
	$categoryid = $_POST["categoryid"];
	$category = $dms->getKeywordCategory($categoryid);
	if (is_object($category)) {
		$owner    = $category->getOwner();
		if ($owner->getID() != $user->getID()) {
			UI::exitError(getMLText("personal_default_keywords"),getMLText("access_denied"));
		}
		if (isset($_POST["keywordsid"])) {
			$keywordsid = intval($_POST["keywordsid"]);
		}
		else {
			$keywordsid = intval($_GET["keywordsid"]);
		}
		if (!is_numeric($keywordsid)) {
			UI::exitError(getMLText("personal_default_keywords"),getMLText("unknown_keyword_category"));
		}
		if (!$category->removeKeywordList($keywordsid)) {
			UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
		}
	}
	else UI::exitError(getMLText("personal_default_keywords"),getMLText("error_occured"));
}

header("Location:../out/out.UserDefaultKeywords.php?categoryid=".$categoryid);

?>
