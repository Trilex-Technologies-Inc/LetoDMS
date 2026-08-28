<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2011 Uwe Steinmann
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
include("../inc/inc.Utils.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassAccessOperation.php");
include("../inc/inc.Authentication.php");

/**
 * Include class to preview documents
 */
if(!empty($settings->_previewClassDir))
	require_once($settings->_previewClassDir.'/Preview.php');
else
	require_once('../LetoDMS_Preview/Preview.php');

// Redirect to the search page if the navigation search button has been
// selected without supplying any search terms.
if (isset($_GET["navBar"])) {
	if (!isset($_GET["folderid"]) || !is_numeric($_GET["folderid"]) || intval($_GET["folderid"])<1) {
		$folderid=$settings->_rootFolderID;
	}
	else {
		$folderid = $_GET["folderid"];
	}
	if(strlen($_GET["query"])==0) {
		header("Location: ../out/out.SearchForm.php?folderid=".$folderid);
	} else {
		if(isset($_GET["fullsearch"]) && $_GET["fullsearch"]) {
			header("Location: ../op/op.SearchFulltext.php?folderid=".$folderid."&query=".$_GET["query"]);
		}
	}
}


function getTime() {
	if (function_exists('microtime')) {
		$tm = microtime();
		$tm = explode(' ', $tm);
		return (float) sprintf('%f', $tm[1] + $tm[0]);
	}
	return time();
}

//
// Parse all of the parameters for the search
//

// Create the keyword search string. This search spans up to three columns
// in the database: keywords, name and comment.

if (isset($_GET["query"]) && is_string($_GET["query"])) {
	$query = $_GET["query"];
}
else {
	$query = "";
}

$mode = "AND";
if (isset($_GET["mode"]) && is_numeric($_GET["mode"]) && $_GET["mode"]==0) {
		$mode = "OR";
}

$searchin = array();
if (isset($_GET['searchin']) && is_array($_GET["searchin"])) {
	foreach ($_GET["searchin"] as $si) {
		if (isset($si) && is_numeric($si)) {
			switch ($si) {
				case 1: // keywords
				case 2: // name
				case 3: // comment
				case 4: // attributes
					$searchin[$si] = $si;
					break;
			}
		}
	}
}

// if none is checkd search all
if (count($searchin)==0) $searchin=array(1, 2, 3, 4);

// Check to see if the search has been restricted to a particular sub-tree in
// the folder hierarchy.
if (isset($_GET["targetidform1"]) && is_numeric($_GET["targetidform1"]) && $_GET["targetidform1"]>0) {
	$targetid = $_GET["targetidform1"];
	$startFolder = $dms->getFolder($targetid);
}
else {
	$targetid = $settings->_rootFolderID;
	$startFolder = $dms->getFolder($targetid);
}
if (!is_object($startFolder)) {
	UI::exitError(getMLText("search_results"),getMLText("invalid_folder_id"));
}

// Check to see if the search has been restricted to a particular
// document owner.
$owner = null;
if (isset($_GET["ownerid"]) && is_numeric($_GET["ownerid"]) && $_GET["ownerid"]!=-1) {
	$owner = $dms->getUser($_GET["ownerid"]);
	if (!is_object($owner)) {
		UI::htmlStartPage(getMLText("search_results"));
		UI::contentContainer(getMLText("unknown_owner"));
		UI::htmlEndPage();
		exit;
	}
}

// Is the search restricted to documents created between two specific dates?
$startdate = array();
$stopdate = array();
if (isset($_GET["creationdate"]) && $_GET["creationdate"]!=null) {
	if(isset($_GET["createstart"])) {
		$tmp = explode("-", $_GET["createstart"]);
		$startdate = array('year'=>(int)$tmp[2], 'month'=>(int)$tmp[1], 'day'=>(int)$tmp[0], 'hour'=>0, 'minute'=>0, 'second'=>0);
	} else {
		$startdate = array('year'=>$_GET["createstartyear"], 'month'=>$_GET["createstartmonth"], 'day'=>$_GET["createstartday"], 'hour'=>0, 'minute'=>0, 'second'=>0);
	}
	if (!checkdate($startdate['month'], $startdate['day'], $startdate['year'])) {
		UI::htmlStartPage(getMLText("search_results"));
		UI::contentContainer(getMLText("invalid_create_date_start"));
		UI::htmlEndPage();
		exit;
	}
	if(isset($_GET["createend"])) {
		$tmp = explode("-", $_GET["createend"]);
		$stopdate = array('year'=>(int)$tmp[2], 'month'=>(int)$tmp[1], 'day'=>(int)$tmp[0], 'hour'=>0, 'minute'=>0, 'second'=>0);
	} else {
		$stopdate = array('year'=>$_GET["createendyear"], 'month'=>$_GET["createendmonth"], 'day'=>$_GET["createendday"], 'hour'=>23, 'minute'=>59, 'second'=>59);
	}
	if (!checkdate($stopdate['month'], $stopdate['day'], $stopdate['year'])) {
		UI::htmlStartPage(getMLText("search_results"));
		UI::contentContainer(getMLText("invalid_create_date_end"));
		UI::htmlEndPage();
		exit;
	}
}

$expstartdate = array();
$expstopdate = array();
if (isset($_GET["expirationdate"]) && $_GET["expirationdate"]!=null) {
	if(isset($_GET["expirationstart"]) && $_GET["expirationstart"]) {
		$tmp = explode("-", $_GET["expirationstart"]);
		$expstartdate = array('year'=>(int)$tmp[2], 'month'=>(int)$tmp[1], 'day'=>(int)$tmp[0], 'hour'=>0, 'minute'=>0, 'second'=>0);
		if (!checkdate($expstartdate['month'], $expstartdate['day'], $expstartdate['year'])) {
			UI::exitError(getMLText("search"),getMLText("invalid_expiration_date_start"));
		}
	} else {
		$expstartdate = array('year'=>$_GET["expirationstartyear"], 'month'=>$_GET["expirationstartmonth"], 'day'=>$_GET["expirationstartday"], 'hour'=>0, 'minute'=>0, 'second'=>0);
		$expstartdate = array();
	}
	if(isset($_GET["expirationend"]) && $_GET["expirationend"]) {
		$tmp = explode("-", $_GET["expirationend"]);
		$expstopdate = array('year'=>(int)$tmp[2], 'month'=>(int)$tmp[1], 'day'=>(int)$tmp[0], 'hour'=>0, 'minute'=>0, 'second'=>0);
		if (!checkdate($expstopdate['month'], $expstopdate['day'], $expstopdate['year'])) {
			UI::exitError(getMLText("search"),getMLText("invalid_expiration_date_end"));
		}
	} else {
		$expstopdate = array('year'=>$_GET["expirationendyear"], 'month'=>$_GET["expirationendmonth"], 'day'=>$_GET["expirationendday"], 'hour'=>23, 'minute'=>59, 'second'=>59);
		$expstopdate = array();
	}
}

// status
$status = array();
if (isset($_GET["pendingReview"])){
	$status[] = S_DRAFT_REV;
}
if (isset($_GET["pendingApproval"])){
	$status[] = S_DRAFT_APP;
}
if (isset($_GET["inWorkflow"])){
	$status[] = S_IN_WORKFLOW;
}
if (isset($_GET["released"])){
	$status[] = S_RELEASED;
}
if (isset($_GET["rejected"])){
	$status[] = S_REJECTED;
}
if (isset($_GET["obsolete"])){
	$status[] = S_OBSOLETE;
}
if (isset($_GET["expired"])){
	$status[] = S_EXPIRED;
}

// category
$categories = array();
if(isset($_GET['categoryids']) && $_GET['categoryids']) {
	foreach($_GET['categoryids'] as $catid) {
		if($catid > 0)
			$categories[] = $dms->getDocumentCategory($catid);
	}
}

if (isset($_GET["attributes"]))
	$attributes = $_GET["attributes"];
else
	$attributes = array();

//
// Get the page number to display. If the result set contains more than
// 25 entries, it is displayed across multiple pages.
//
// This requires that a page number variable be used to track which page the
// user is interested in, and an extra clause on the select statement.
//
// Default page to display is always one.
$pageNumber=1;
$limit = 15;
if (isset($_GET["pg"])) {
	if (is_numeric($_GET["pg"]) && $_GET["pg"]>0) {
		$pageNumber = (int) $_GET["pg"];
	}
	elseif (!strcasecmp($_GET["pg"], "all")) {
		$limit = 0;
	}
}


// ---------------- Start searching -----------------------------------------
$startTime = getTime();
$resArr = $dms->search($query, $limit, ($pageNumber-1)*$limit, $mode, $searchin, $startFolder, $owner, $status, $startdate, $stopdate, array(), array(), $categories, $attributes, 0x03, $expstartdate, $expstopdate);
$searchTime = getTime() - $startTime;
$searchTime = round($searchTime, 2);

$entries = array();
if($resArr['folders']) {
	foreach ($resArr['folders'] as $entry) {
		if ($entry->getAccessMode($user) >= M_READ) {
			$entries[] = $entry;
		}
	}
}
if($resArr['docs']) {
	foreach ($resArr['docs'] as $entry) {
		if ($entry->getAccessMode($user) >= M_READ) {
			$entries[] = $entry;
		}
	}
}
// -------------- Output results --------------------------------------------

if(count($entries) == 1) {
	$entry = $entries[0];
	if(get_class($entry) == 'LetoDMS_Core_Document') {
		header('Location: ../out/out.ViewDocument.php?documentid='.$entry->getID());
		exit;
	} elseif(get_class($entry) == 'LetoDMS_Core_Folder') {
		header('Location: ../out/out.ViewFolder.php?folderid='.$entry->getID());
		exit;
	}
} else {
	$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
	$view = UI::factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user, 'folder'=>$startFolder, 'query'=>$query, 'searchhits'=>$entries, 'totalpages'=>$resArr['totalPages'], 'pagenumber'=>$pageNumber, 'searchtime'=>$searchTime, 'urlparams'=>$_GET, 'searchin'=>$searchin, 'cachedir'=>$settings->_cacheDir));
	if($view) {
		$view->show();
		exit;
	}
}
?>
