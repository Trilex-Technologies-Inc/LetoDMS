<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//    Copyright (C) 2010-2012 Uwe Steinmann
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

function filterDocumentLinks($user, $links) { /* {{{ */
	GLOBAL $settings;
	
	$tmp = array();
	foreach ($links as $link)
		if ($link->isPublic() || ($link->_userID == $user->getID()) || $user->isAdmin())
			array_push($tmp, $link);
	return $tmp;
} /* }}} */

if (!isset($_GET["documentid"]) || !is_numeric($_GET["documentid"]) || intval($_GET["documentid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$documentid = intval($_GET["documentid"]);
$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$folder = $document->getFolder();
$docPathHTML = getFolderPathHTML($folder, true). " / ".htmlspecialchars($document->getName());

if ($document->getAccessMode($user) < M_READ) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("access_denied"));
}

if ($document->verifyLastestContentExpriry()){
	header("Location:../out/out.ViewDocument.php?documentid=".$documentid);
}

/* Create object for checking access to certain operations */
$accessop = new LetoDMS_AccessOperation($document, $user, $settings);

UI::htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
UI::globalNavigation($folder);
UI::pageNavigation($docPathHTML, "view_document");
UI::contentHeading(getMLText("document_infos"));
UI::contentContainerStart();

?>
<table>
<?php
if ($document->isLocked()) {
	$lockingUser = $document->getLockingUser();
?>
<tr>
	<td class="warning" colspan=2><?php printMLText("lock_message", array("email" => $lockingUser->getEmail(), "username" => htmlspecialchars($lockingUser->getFullName())));?></td>
</tr>
<?php
}
?>
<tr>
<td><?php printMLText("owner");?>:</td>
<td>
<?php
$owner = $document->getOwner();
print "<a class=\"infos\" href=\"mailto:".$owner->getEmail()."\">".htmlspecialchars($owner->getFullName())."</a>";
?>
</td>
</tr>
<tr>
<td><?php printMLText("comment");?>:</td>
<td><?php print htmlspecialchars($document->getComment());?></td>
</tr>
<tr>
<td><?php printMLText("creation_date");?>:</td>
<td><?php print getLongReadableDate($document->getDate()); ?></td>
</tr>
<tr>
<td><?php printMLText("keywords");?>:</td>
<td><?php print htmlspecialchars($document->getKeywords());?></td>
</tr>
<tr>
<td><?php printMLText("categories");?>:</td>
<td>
<?php
	$cats = $document->getCategories();
	$ct = array();
	foreach($cats as $cat)
		$ct[] = htmlspecialchars($cat->getName());
	echo implode(', ', $ct);
?>
</td>
</tr>
<?php
$attributes = $document->getAttributes();
if($attributes) {
	foreach($attributes as $attribute) {
		$attrdef = $attribute->getAttributeDefinition();
?>
		<tr>
			<td><?php echo htmlspecialchars($attrdef->getName()); ?>:</td>
			<td><?php echo htmlspecialchars($attribute->getValue()); ?></td>
		</tr>
<?php
	}
}
?>
</table>
<?php
UI::contentContainerEnd();

$versions = $document->getContent();
if(!$latestContent = $document->getLatestContent()) {
	UI::contentHeading(getMLText("current_version"));
	UI::contentContainerStart();
	print getMLText('document_content_missing');
	UI::contentContainerEnd();
	UI::htmlEndPage();
	exit;
}

$status = $latestContent->getStatus();
$reviewStatus = $latestContent->getReviewStatus();
$approvalStatus = $latestContent->getApprovalStatus();

// verify if file exists
$file_exists=file_exists($dms->contentDir . $latestContent->getPath());

UI::contentHeading(getMLText("current_version"));
UI::contentContainerStart();
print "<table class=\"folderView\">";
print "<thead>\n<tr>\n";
print "<th width='10%'></th>\n";
print "<th width='10%'>".getMLText("version")."</th>\n";
print "<th width='20%'>".getMLText("file")."</th>\n";
print "<th width='25%'>".getMLText("comment")."</th>\n";
print "<th width='15%'>".getMLText("status")."</th>\n";
print "<th width='20%'></th>\n";
print "</tr></thead><tbody>\n";
print "<tr>\n";
print "<td><ul class=\"actions\">";

if ($file_exists){
	print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\"><img class=\"mimeicon\" src=\"images/icons/".UI::getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">".getMLText("download")."</a></li>";
	if ($settings->_viewOnlineFileTypes && in_array(strtolower($latestContent->getFileType()), $settings->_viewOnlineFileTypes))
		print "<li><a target=\"_blank\" href=\"../op/op.ViewOnline.php?documentid=".$documentid."&version=". $latestContent->getVersion()."\"><img src=\"images/view.gif\" class=\"mimeicon\">" . getMLText("view_online") . "</a></li>";
}else print "<li><img class=\"mimeicon\" src=\"images/icons/".UI::getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\"></li>";

print "</ul></td>\n";
print "<td>".$latestContent->getVersion()."</td>\n";

print "<td><ul class=\"documentDetail\">\n";
print "<li>".$latestContent->getOriginalFileName() ."</li>\n";

if ($file_exists)
	print "<li>". formatted_size(filesize($dms->contentDir . $latestContent->getPath())) ." ".htmlspecialchars($latestContent->getMimeType())."</li>";
else print "<li><span class=\"warning\">".getMLText("document_deleted")."</span></li>";

$updatingUser = $latestContent->getUser();
print "<li>".getMLText("uploaded_by")." <a href=\"mailto:".$updatingUser->getEmail()."\">".htmlspecialchars($updatingUser->getFullName())."</a></li>";
print "<li>".getLongReadableDate($latestContent->getDate())."</li>";

print "</ul>\n";
print "<ul class=\"documentDetail\">\n";
$attributes = $latestContent->getAttributes();
if($attributes) {
	foreach($attributes as $attribute) {
		$attrdef = $attribute->getAttributeDefinition();
		print "<li>".htmlspecialchars($attrdef->getName()).": ".htmlspecialchars($attribute->getValue())."</li>\n";
	}
}
print "</ul>\n";

print "<td>".htmlspecialchars($latestContent->getComment())."</td>";

print "<td width='10%'>".getOverallStatusText($status["status"]);
if ( $status["status"]==S_DRAFT_REV || $status["status"]==S_DRAFT_APP || $status["status"]==S_EXPIRED ){
	print "<br><span".($document->hasExpired()?" class=\"warning\" ":"").">".(!$document->getExpires() ? getMLText("does_not_expire") : getMLText("expires").": ".getReadableDate($document->getExpires()))."</span>";
}
print "</td>";

print "<td>";

print "<ul class=\"actions\">";
/* Only admin has the right to remove version in any case or a regular
 * user if enableVersionDeletion is on
 */
if($accessop->mayRemoveVersion()) {
	print "<li><a href=\"out.RemoveVersion.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\">".getMLText("rm_version")."</a></li>";
}
if($accessop->mayOverwriteStatus()) {
	print "<li><a href='../out/out.OverrideContentStatus.php?documentid=".$documentid."&version=".$latestContent->getVersion()."'>".getMLText("change_status")."</a></li>";
}
// Allow changing reviewers/approvals only if not reviewed
if($accessop->maySetReviewersApprovers()) {
	print "<li><a href='../out/out.SetReviewersApprovers.php?documentid=".$documentid."&version=".$latestContent->getVersion()."'>".getMLText("change_assignments")."</a></li>";
}
if($accessop->maySetExpires()) {
	print "<li><a href='../out/out.SetExpires.php?documentid=".$documentid."'>".getMLText("set_expiry")."</a></li>";
}
if($accessop->mayEditComment()) {
	print "<li><a href=\"out.EditComment.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\">".getMLText("edit_comment")."</a></li>";
}
if($accessop->mayEditAttributes()) {
	print "<li><a href=\"out.EditAttributes.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\">".getMLText("edit_attributes")."</a></li>";
}

print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&vfile=1\">".getMLText("versioning_info")."</a></li>";	

print "</ul>";
echo "</td>";
print "</tr></tbody>\n</table>\n";

print "<table class=\"folderView\">\n";

if (is_array($reviewStatus) && count($reviewStatus)>0) {

	print "<tr><td colspan=5>\n";
	UI::contentSubHeading(getMLText("reviewers"));
	print "</tr>";
	
	print "<tr>\n";
	print "<td width='20%'><b>".getMLText("name")."</b></td>\n";
	print "<td width='20%'><b>".getMLText("last_update")."</b></td>\n";
	print "<td width='25%'><b>".getMLText("comment")."</b></td>";
	print "<td width='15%'><b>".getMLText("status")."</b></td>\n";
	print "<td width='20%'></td>\n";
	print "</tr>\n";

	foreach ($reviewStatus as $r) {
		$required = null;
		$is_reviewer = false;
		switch ($r["type"]) {
			case 0: // Reviewer is an individual.
				$required = $dms->getUser($r["required"]);
				if (!is_object($required)) {
					$reqName = getMLText("unknown_user")." '".$r["required"]."'";
				}
				else {
					$reqName = htmlspecialchars($required->getFullName());
				}
				if($r["required"] == $user->getId())
					$is_reviewer = true;
				break;
			case 1: // Reviewer is a group.
				$required = $dms->getGroup($r["required"]);
				if (!is_object($required)) {
					$reqName = getMLText("unknown_group")." '".$r["required"]."'";
				}
				else {
					$reqName = "<i>".htmlspecialchars($required->getName())."</i>";
				}
				if($required->isMember($user) && ($user->getId() != $owner->getId()))
					$is_reviewer = true;
				break;
		}
		print "<tr>\n";
		print "<td>".$reqName."</td>\n";
		print "<td><ul class=\"documentDetail\"><li>".$r["date"]."</li>";
		/* $updateUser is the user who has done the review */
		$updateUser = $dms->getUser($r["userID"]);
		print "<li>".(is_object($updateUser) ? htmlspecialchars($updateUser->getFullName()) : "unknown user id '".$r["userID"]."'")."</li></ul></td>";
		print "<td>".htmlspecialchars($r["comment"])."</td>\n";
		print "<td>".getReviewStatusText($r["status"])."</td>\n";
		print "<td><ul class=\"actions\">";

		if($accessop->mayReview()) {
			if ($is_reviewer && $r["status"]==0) {
				print "<li><a href=\"../out/out.ReviewDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&reviewid=".$r['reviewID']."\">".getMLText("submit_review")."</a></li>";
			}else if (($updateUser==$user)&&(($r["status"]==1)||($r["status"]==-1))&&(!$document->hasExpired())){
				print "<li><a href=\"../out/out.ReviewDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&reviewid=".$r['reviewID']."\">".getMLText("edit")."</a></li>";
			}
		}
		
		print "</ul></td>\n";	
		print "</td>\n</tr>\n";
	}
}

if (is_array($approvalStatus) && count($approvalStatus)>0) {

	print "<tr><td colspan=5>\n";
	UI::contentSubHeading(getMLText("approvers"));
	print "</tr>";

	print "<tr>\n";
	print "<td width='20%'><b>".getMLText("name")."</b></td>\n";
	print "<td width='20%'><b>".getMLText("last_update")."</b></td>\n";	
	print "<td width='25%'><b>".getMLText("comment")."</b></td>";
	print "<td width='15%'><b>".getMLText("status")."</b></td>\n";
	print "<td width='20%'></td>\n";
	print "</tr>\n";

	foreach ($approvalStatus as $a) {
		$required = null;
		$is_approver = false;
		switch ($a["type"]) {
			case 0: // Approver is an individual.
				$required = $dms->getUser($a["required"]);
				if (!is_object($required)) {
					$reqName = getMLText("unknown_user")." '".$r["required"]."'";
				}
				else {
					$reqName = htmlspecialchars($required->getFullName());
				}
				if($a["required"] == $user->getId())
					$is_approver = true;
				break;
			case 1: // Approver is a group.
				$required = $dms->getGroup($a["required"]);
				if (!is_object($required)) {
					$reqName = getMLText("unknown_group")." '".$r["required"]."'";
				}
				else {
					$reqName = "<i>".htmlspecialchars($required->getName())."</i>";
				}
				if($required->isMember($user) && ($user->getId() != $owner->getId()))
					$is_approver = true;
				break;
		}
		print "<tr>\n";
		print "<td>".$reqName."</td>\n";
		print "<td><ul class=\"documentDetail\"><li>".$a["date"]."</li>";
		/* $updateUser is the user who has done the approval */
		$updateUser = $dms->getUser($a["userID"]);
		print "<li>".(is_object($updateUser) ? htmlspecialchars($updateUser->getFullName()) : "unknown user id '".$a["userID"]."'")."</li></ul></td>";	
		print "<td>".htmlspecialchars($a["comment"])."</td>\n";
		print "<td>".getApprovalStatusText($a["status"])."</td>\n";
		print "<td><ul class=\"actions\">";
	
		if($accessop->mayApprove()) {
			if ($is_approver && $status["status"]==S_DRAFT_APP) {
				print "<li><a href=\"../out/out.ApproveDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&approveid=".$a['approveID']."\">".getMLText("submit_approval")."</a></li>";
			}else if (($updateUser==$user)&&(($a["status"]==1)||($a["status"]==-1))&&(!$document->hasExpired())){
				print "<li><a href=\"../out/out.ApproveDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&approveid=".$a['approveID']."\">".getMLText("edit")."</a></li>";
			}
		}
		
		print "</ul></td>\n";	
		print "</td>\n</tr>\n";
	}
}

print "</table>\n";

UI::contentContainerEnd();

UI::contentHeading(getMLText("previous_versions"));
UI::contentContainerStart();

if (count($versions)>1) {

	print "<table class=\"folderView\">";
	print "<thead>\n<tr>\n";
	print "<th width='10%'></th>\n";
	print "<th width='10%'>".getMLText("version")."</th>\n";
	print "<th width='20%'>".getMLText("file")."</th>\n";
	print "<th width='25%'>".getMLText("comment")."</th>\n";
	print "<th width='15%'>".getMLText("status")."</th>\n";
	print "<th width='20%'></th>\n";
	print "</tr>\n</thead>\n<tbody>\n";

	for ($i = count($versions)-2; $i >= 0; $i--) {
		$version = $versions[$i];
		$vstat = $version->getStatus();
		
		// verify if file exists
		$file_exists=file_exists($dms->contentDir . $version->getPath());
		
		print "<tr>\n";
		print "<td><ul class=\"actions\">";
		if ($file_exists){
			print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&version=".$version->getVersion()."\"><img class=\"mimeicon\" src=\"images/icons/".UI::getMimeIcon($version->getFileType())."\" title=\"".htmlspecialchars($version->getMimeType())."\">".getMLText("download")."</a>";
			if ($settings->_viewOnlineFileTypes && in_array(strtolower($latestContent->getFileType()), $settings->_viewOnlineFileTypes))
				print "<li><a target=\"_blank\" href=\"../op/op.ViewOnline.php?documentid=".$documentid."&version=".$version->getVersion()."\"><img src=\"images/view.gif\" class=\"mimeicon\">" . getMLText("view_online") . "</a>";
		}else print "<li><img class=\"mimeicon\" src=\"images/icons/".UI::getMimeIcon($version->getFileType())."\" title=\"".htmlspecialchars($version->getMimeType())."\">";
		
		print "</ul></td>\n";
		print "<td>".$version->getVersion()."</td>\n";
		print "<td><ul class=\"documentDetail\">\n";
		print "<li>".$version->getOriginalFileName()."</li>\n";
		if ($file_exists) print "<li>". formatted_size(filesize($dms->contentDir . $version->getPath())) ." ".htmlspecialchars($version->getMimeType())."</li>";
		else print "<li><span class=\"warning\">".getMLText("document_deleted")."</span></li>";
		$updatingUser = $version->getUser();
		print "<li>".getMLText("uploaded_by")." <a href=\"mailto:".$updatingUser->getEmail()."\">".htmlspecialchars($updatingUser->getFullName())."</a></li>";
		print "<li>".getLongReadableDate($version->getDate())."</li>";
		print "</ul>\n";
		print "<ul class=\"documentDetail\">\n";
		$attributes = $version->getAttributes();
		if($attributes) {
			foreach($attributes as $attribute) {
				$attrdef = $attribute->getAttributeDefinition();
				print "<li>".htmlspecialchars($attrdef->getName()).": ".htmlspecialchars($attribute->getValue())."</li>\n";
			}
		}
		print "</ul>\n";
		print "<td>".htmlspecialchars($version->getComment())."</td>";
		print "<td>".getOverallStatusText($vstat["status"])."</td>";
		print "<td>";
		print "<ul class=\"actions\">";
		/* Only admin has the right to remove version in any case or a regular
		 * user if enableVersionDeletion is on
		 */
		if($accessop->mayRemoveVersion()) {
			print "<li><a href=\"out.RemoveVersion.php?documentid=".$documentid."&version=".$version->getVersion()."\">".getMLText("rm_version")."</a></li>";
		}
		print "<li><a href='../out/out.DocumentVersionDetail.php?documentid=".$documentid."&version=".$version->getVersion()."'>".getMLText("details")."</a></li>";
		print "</ul>";
		print "</td>\n</tr>\n";
	}
	print "</tbody>\n</table>\n";
}
else printMLText("no_previous_versions");

UI::contentContainerEnd();

UI::contentHeading(getMLText("linked_files"));
UI::contentContainerStart();

$files = $document->getDocumentFiles();

if (count($files) > 0) {

	print "<table class=\"folderView\">";
	print "<thead>\n<tr>\n";
	print "<th width='20%'></th>\n";
	print "<th width='20%'>".getMLText("file")."</th>\n";
	print "<th width='40%'>".getMLText("comment")."</th>\n";
	print "<th width='20%'></th>\n";
	print "</tr>\n</thead>\n<tbody>\n";

	foreach($files as $file) {

		$file_exists=file_exists($dms->contentDir . $file->getPath());
		
		$responsibleUser = $file->getUser();

		print "<tr>";
		print "<td><ul class=\"actions\">";
		if ($file_exists)
			print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&file=".$file->getID()."\"><img class=\"mimeicon\" src=\"images/icons/".UI::getMimeIcon($file->getFileType())."\" title=\"".htmlspecialchars($file->getMimeType())."\">".htmlspecialchars($file->getName())."</a>";
		else print "<li><img class=\"mimeicon\" src=\"images/icons/".UI::getMimeIcon($file->getFileType())."\" title=\"".htmlspecialchars($file->getMimeType())."\">";
		print "</ul></td>";
		
		print "<td><ul class=\"documentDetail\">\n";
		print "<li>".$file->getOriginalFileName() ."</li>\n";
		if ($file_exists)
			print "<li>". filesize($dms->contentDir . $file->getPath()) ." bytes ".htmlspecialchars($file->getMimeType())."</li>";
		else print "<li>".htmlspecialchars($file->getMimeType())." - <span class=\"warning\">".getMLText("document_deleted")."</span></li>";

		print "<li>".getMLText("uploaded_by")." <a href=\"mailto:".$responsibleUser->getEmail()."\">".htmlspecialchars($responsibleUser->getFullName())."</a></li>";
		print "<li>".getLongReadableDate($file->getDate())."</li>";

		print "<td>".htmlspecialchars($file->getComment())."</td>";
	
		print "<td><span class=\"actions\">";
		if (($document->getAccessMode($user) == M_ALL)||($file->getUserID()==$user->getID()))
			print "<form action=\"../out/out.RemoveDocumentFile.php\" method=\"get\"><input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"fileid\" value=\"".$file->getID()."\" /><input type=\"submit\" value=\"".getMLText("delete")."\" /></form>";
		print "</span></td>";		
		
		print "</tr>";
	}
	print "</tbody>\n</table>\n";	

}
else printMLText("no_attached_files");

if ($document->getAccessMode($user) >= M_READWRITE){
	print "<br>";
	print "<ul class=\"actions\"><li><a href=\"../out/out.AddFile.php?documentid=".$documentid."\">".getMLText("add")."</a></ul>\n";
}
UI::contentContainerEnd();


UI::contentHeading(getMLText("linked_documents"));
UI::contentContainerStart();
$links = $document->getDocumentLinks();
$links = filterDocumentLinks($user, $links);

if (count($links) > 0) {

	print "<table class=\"folderView\">";
	print "<thead>\n<tr>\n";
	print "<th width='40%'></th>\n";
	print "<th width='25%'>".getMLText("comment")."</th>\n";
	print "<th width='15%'>".getMLText("document_link_by")."</th>\n";
	print "<th width='20%'></th>\n";
	print "</tr>\n</thead>\n<tbody>\n";

	foreach($links as $link) {
		$responsibleUser = $link->getUser();
		$targetDoc = $link->getTarget();

		print "<tr>";
		print "<td><a href=\"out.ViewDocument.php?documentid=".$targetDoc->getID()."\" class=\"linklist\">".htmlspecialchars($targetDoc->getName())."</a></td>";
		print "<td>".htmlspecialchars($targetDoc->getComment())."</td>";
		print "<td>".htmlspecialchars($responsibleUser->getFullName());
		if (($user->getID() == $responsibleUser->getID()) || ($document->getAccessMode($user) == M_ALL ))
			print "<br>".getMLText("document_link_public").":".(($link->isPublic()) ? getMLText("yes") : getMLText("no"));
		print "</td>";
		print "<td><span class=\"actions\">";
		if (($user->getID() == $responsibleUser->getID()) || ($document->getAccessMode($user) == M_ALL ))
			print "<form action=\"../op/op.RemoveDocumentLink.php\" method=\"post\">".createHiddenFieldWithKey('removedocumentlink')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"linkid\" value=\"".$link->getID()."\" /><input type=\"submit\" value=\"".getMLText("delete")."\" /></form>";
		print "</span></td>";
		print "</tr>";
	}
	print "</tbody>\n</table>\n";
}
else printMLText("no_linked_files");

if (!$user->isGuest()){
?>
	<br>
	<form action="../op/op.AddDocumentLink.php" name="form1">
	<input type="Hidden" name="documentid" value="<?php print $documentid;?>">
	<table>
	<tr>
	<td><?php printMLText("add_document_link");?>:</td>
	<td><?php UI::printDocumentChooser("form1");?></td>
	</tr>
	<?php
	if ($document->getAccessMode($user) >= M_READWRITE) {
		print "<tr><td>".getMLText("document_link_public")."</td>";
		print "<td><ul class=\"actions\">";
		print "<li><input type=\"Radio\" name=\"public\" value=\"true\" checked>" . getMLText("yes")."</li>";
		print "<li><input type=\"Radio\" name=\"public\" value=\"false\">" . getMLText("no")."</li>";
		print "</ul></td></tr>";
	}
	?>
	<tr>
	<td colspan="2"><input type="Submit" value="<?php printMLText("update");?>"></td>
	</tr>
	</table>
	</form>
<?php
}
UI::contentContainerEnd();

UI::htmlEndPage();
?>
