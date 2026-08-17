<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
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
include("../inc/inc.Authentication.php");

if (!isset($_GET["documentid"]) || !is_numeric($_GET["documentid"]) || intval($_GET["documentid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$documentid = intval($_GET["documentid"]);
$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$folder = $document->getFolder();
$docPathHTML = getFolderPathHTML($folder, true). " / <a href=\"../out/out.ViewDocument.php?documentid=".$documentid."\">".htmlspecialchars($document->getName())."</a>";

if ($document->getAccessMode($user) < M_READ) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("access_denied"));
}

if (!isset($_GET["version"]) || !is_numeric($_GET["version"]) || intval($_GET["version"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("invalid_version"));
}
$version = $_GET["version"];
$content = $document->getContentByVersion($version);
if (!is_object($content)) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("invalid_version"));
}

// operation is admitted only for last deocument version
$latestContent = $document->getLatestContent();
if ($latestContent->getVersion()!=$version) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("invalid_version"));
}
// verify if document has expired
if ($document->hasExpired()){
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("access_denied"));
}

$reviews = $latestContent->getReviewStatus();
if(!$reviews) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("no_action"));
}

foreach($reviews as $review) {
	if($review['reviewID'] == $_GET['reviewid']) {
		$reviewStatus = $review;
		break;
	}
}

UI::htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
UI::globalNavigation($folder);
UI::pageNavigation($docPathHTML, "view_document");
UI::contentHeading(getMLText("submit_review"));

?>
<script language="JavaScript">
function checkIndForm()
{
	msg = "";
	if (document.form1.reviewStatus.value == "") msg += "<?php printMLText("js_no_review_status");?>\n";
	if (document.form1.comment.value == "") msg += "<?php printMLText("js_no_comment");?>\n";
	if (msg != "")
	{
		alert(msg);
		return false;
	}
	else
		return true;
}
function checkGrpForm()
{
	msg = "";
	if (document.form1.reviewGroup.value == "") msg += "<?php printMLText("js_no_review_group");?>\n";
	if (document.form1.reviewStatus.value == "") msg += "<?php printMLText("js_no_review_status");?>\n";
	if (document.form1.comment.value == "") msg += "<?php printMLText("js_no_comment");?>\n";
	if (msg != "")
	{
		alert(msg);
		return false;
	}
	else
		return true;
}
</script>

<?php

UI::contentContainerStart();

// Display the Review form.
if ($reviewStatus['type'] == 0) {
	if($reviewStatus["status"]!=0) {

		print "<table class=\"folderView\"><thead><tr>";
		print "<th>".getMLText("status")."</th>";
		print "<th>".getMLText("comment")."</th>";
		print "<th>".getMLText("last_update")."</th>";
		print "</tr></thead><tbody><tr>";
		print "<td>";
		printReviewStatusText($reviewStatus["status"]);
		print "</td>";
		print "<td>".htmlspecialchars($reviewStatus["comment"])."</td>";
		$indUser = $dms->getUser($reviewStatus["userID"]);
		print "<td>".$reviewStatus["date"]." - ". htmlspecialchars($indUser->getFullname()) ."</td>";
		print "</tr></tbody></table><br>";
	}
?>
	<form method="post" action="../op/op.ReviewDocument.php" name="form1" onsubmit="return checkIndForm();">
	<?php echo createHiddenFieldWithKey('reviewdocument'); ?>
	<table>
	<tr><td class='infos' valign='top'><?php printMLText("comment")?>:</td>
	<td class='infos' valign='top'><textarea name="comment" cols="80" rows="4"></textarea>
	</td></tr>
	<tr><td><?php printMLText("review_status")?></td>
	<td><select name="reviewStatus">
	<option value=''></option>
	<option value='1'><?php printMLText("status_reviewed")?></option>
	<option value='-1'><?php printMLText("rejected")?></option>
	</select>
	</td></tr><tr><td></td><td>
	<input type='hidden' name='reviewType' value='ind'/>
	<input type='hidden' name='documentid' value='<?php echo $documentid ?>'/>
	<input type='hidden' name='version' value='<?php echo $version ?>'/>
	<input type='submit' name='indReview' value='<?php printMLText("submit_review")?>'/>
	</td></tr></table>
	</form>
<?php
}
else if ($reviewStatus['type'] == 1) {

	if($reviewStatus["status"]!=0) {

		print "<table class=\"folderView\"><thead><tr>";
		print "<th>".getMLText("status")."</th>";
		print "<th>".getMLText("comment")."</th>";
		print "<th>".getMLText("last_update")."</th>";
		print "</tr></thead><tbody><tr>";
		print "<td>";
		printReviewStatusText($reviewStatus["status"]);
		print "</td>";
		print "<td>".htmlspecialchars($reviewStatus["comment"])."</td>";
		$indUser = $dms->getUser($reviewStatus["userID"]);
		print "<td>".$reviewStatus["date"]." - ". htmlspecialchars($indUser->getFullname()) ."</td>";
		print "</tr></tbody></table><br>\n";
	}

?>
	<form method="POST" action="../op/op.ReviewDocument.php" name="form1" onsubmit="return checkGrpForm();">
	<?php echo createHiddenFieldWithKey('reviewdocument'); ?>
	<table>
	<tr><td><?php printMLText("comment")?>:</td>
	<td><textarea name="comment" cols="80" rows="4"></textarea>
	</td></tr>
	<tr><td><?php printMLText("review_status")?>:</td>
	<td>
	<select name="reviewStatus">
	<option value=''></option>
	<option value='1'><?php printMLText("status_reviewed")?></option>
	<option value='-1'><?php printMLText("rejected")?></option>
	</select>
	</td></tr>
	<tr><td></td><td>
	<input type='hidden' name='reviewType' value='grp'/>
	<input type='hidden' name='reviewGroup' value='<?php echo $reviewStatus['required']; ?>'/>
	<input type='hidden' name='documentid' value='<?php echo $documentid ?>'/>
	<input type='hidden' name='version' value='<?php echo $version ?>'/>
	<input type='submit' name='groupReview' value='<?php printMLText("submit_review")?>'/></td></tr>
	</table>
	</form>
<?php
}

UI::contentContainerEnd();
UI::htmlEndPage();
?>
