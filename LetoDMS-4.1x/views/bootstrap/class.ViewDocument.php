<?php
/**
 * Implementation of ViewDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for ViewDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ViewDocument extends LetoDMS_Bootstrap_Style {

	function getAccessModeText($defMode) { /* {{{ */
		switch($defMode) {
			case M_NONE:
				return getMLText("access_mode_none");
				break;
			case M_READ:
				return getMLText("access_mode_read");
				break;
			case M_READWRITE:
				return getMLText("access_mode_readwrite");
				break;
			case M_ALL:
				return getMLText("access_mode_all");
				break;
		}
	} /* }}} */

	function printAccessList($obj) { /* {{{ */
		$accessList = $obj->getAccessList();
		if (count($accessList["users"]) == 0 && count($accessList["groups"]) == 0)
			return;

		for ($i = 0; $i < count($accessList["groups"]); $i++)
		{
			$group = $accessList["groups"][$i]->getGroup();
			$accesstext = $this->getAccessModeText($accessList["groups"][$i]->getMode());
			print $accesstext.": ".htmlspecialchars($group->getName());
			if ($i+1 < count($accessList["groups"]) || count($accessList["users"]) > 0)
				print "<br />";
		}
		for ($i = 0; $i < count($accessList["users"]); $i++)
		{
			$user = $accessList["users"][$i]->getUser();
			$accesstext = $this->getAccessModeText($accessList["users"][$i]->getMode());
			print $accesstext.": ".htmlspecialchars($user->getFullName());
			if ($i+1 < count($accessList["users"]))
				print "<br />";
		}
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$accessop = $this->params['accessobject'];
		$viewonlinefiletypes = $this->params['viewonlinefiletypes'];
		$workflowmode = $this->params['workflowmode'];
		$cachedir = $this->params['cachedir'];
		$documentid = $document->getId();

		$versions = $document->getContent();

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document");

		if ($document->isLocked()) {
			$lockingUser = $document->getLockingUser();
?>
		<div class="alert alert-warning">
			<?php printMLText("lock_message", array("email" => $lockingUser->getEmail(), "username" => htmlspecialchars($lockingUser->getFullName())));?>
		</div>
<?php
		}
?>
    <ul class="nav nav-tabs" id="docinfotab">
		  <li class="active"><a data-target="#docinfo" data-toggle="tab"><?php printMLText('document_infos'); ?> / <?php printMLText('current_version'); ?></a></li>
			<?php if (count($versions)>1) { ?>
		  <li><a data-target="#previous" data-toggle="tab"><?php printMLText('previous_versions'); ?></a></li>
<?php
			}
			if($workflowmode == 'traditional') {
?>
		  <li><a data-target="#revapp" data-toggle="tab"><?php echo getMLText('reviewers')."/".getMLText('approvers'); ?></a></li>
<?php
			} else {
				if($document->getLatestContent()->getWorkflow()) {
?>
		  <li><a data-target="#workflow" data-toggle="tab"><?php echo getMLText('workflow'); ?></a></li>
<?php
				}
			}
?>
		  <li><a data-target="#attachments" data-toggle="tab"><?php printMLText('linked_files'); ?></a></li>
		  <li><a data-target="#links" data-toggle="tab"><?php printMLText('linked_documents'); ?></a></li>
		</ul>
		<div class="tab-content">
		  <div class="tab-pane active" id="docinfo">

<div class="row-fluid">
<div class="span3">
<?php
		$this->contentHeading(getMLText("document_infos"));
		$this->contentContainerStart();
?>
		<table class="table-condensed">
<?php
		if($user->isAdmin()) {
			echo "<tr>";
			echo "<td>".getMLText("id").":</td>\n";
			echo "<td>".htmlspecialchars($document->getID())."</td>\n";
			echo "</tr>";
		}
?>
		<tr>
		<td><?php printMLText("name");?>:</td>
		<td><?php print htmlspecialchars($document->getName());?></td>
		</tr>
		<tr>
		<td><?php printMLText("owner");?>:</td>
		<td>
<?php
		$owner = $document->getOwner();
		print "<a class=\"infos\" href=\"mailto:".$owner->getEmail()."\">".htmlspecialchars($owner->getFullName())."</a>";
?>
		</td>
		</tr>
<?php
		if($document->getComment()) {
?>
		<tr>
		<td><?php printMLText("comment");?>:</td>
		<td><?php print htmlspecialchars($document->getComment());?></td>
		</tr>
<?php
		}
		if($user->isAdmin()) {
			if($document->inheritsAccess()) {
				echo "<tr>";
				echo "<td>".getMLText("access_mode").":</td>\n";
				echo "<td>";
				echo getMLText("inherited");
				echo "</tr>";
			} else {
				echo "<tr>";
				echo "<td>".getMLText('default_access')."</td>";
				echo "<td>".$this->getAccessModeText($document->getDefaultAccess())."</td>";
				echo "</tr>";
				echo "<tr>";
				echo "<td>".getMLText('access_mode')."</td>";
				echo "<td>";
				$this->printAccessList($document);
				echo "</td>";
				echo "</tr>";
			}
		}
?>
		<tr>
		<td><?php printMLText("used_discspace");?>:</td>
		<td><?php print LetoDMS_Core_File::format_filesize($document->getUsedDiskSpace());?></td>
		</tr>
		<tr>
		<td><?php printMLText("creation_date");?>:</td>
		<td><?php print getLongReadableDate($document->getDate()); ?></td>
		</tr>
<?php
		if($document->getKeywords()) {
?>
		<tr>
		<td><?php printMLText("keywords");?>:</td>
		<td><?php print htmlspecialchars($document->getKeywords());?></td>
		</tr>
<?php
		}
		if($cats = $document->getCategories()) {
?>
		<tr>
		<td><?php printMLText("categories");?>:</td>
		<td>
		<?php
			$ct = array();
			foreach($cats as $cat)
				$ct[] = htmlspecialchars($cat->getName());
			echo implode(', ', $ct);
		?>
		</td>
		</tr>
<?php
		}
?>
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
		$this->contentContainerEnd();
?>
</div>
<div class="span9">
<?php
		if(!$latestContent = $document->getLatestContent()) {
			$this->contentContainerStart();
			print getMLText('document_content_missing');
			$this->contentContainerEnd();
			$this->htmlEndPage();
			exit;
		}

		$status = $latestContent->getStatus();
		$reviewStatus = $latestContent->getReviewStatus();
		$approvalStatus = $latestContent->getApprovalStatus();

		// verify if file exists
		$file_exists=file_exists($dms->contentDir . $latestContent->getPath());

		$this->contentHeading(getMLText("current_version"));
		$this->contentContainerStart();
		print "<table class=\"table\">";
		print "<thead>\n<tr>\n";
		print "<th width='10%'></th>\n";
		print "<th width='10%'>".getMLText("version")."</th>\n";
		print "<th width='20%'>".getMLText("file")."</th>\n";
		print "<th width='25%'>".getMLText("comment")."</th>\n";
		print "<th width='15%'>".getMLText("status")."</th>\n";
		print "<th width='20%'></th>\n";
		print "</tr></thead><tbody>\n";
		print "<tr>\n";
		print "<td><ul class=\"unstyled\">";

		if ($file_exists){
			print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\"><i class=\"icon-download\"></i> ".getMLText("download")."</a></li>";
			if ($viewonlinefiletypes && in_array(strtolower($latestContent->getFileType()), $viewonlinefiletypes))
				print "<li><a target=\"_blank\" href=\"../op/op.ViewOnline.php?documentid=".$documentid."&version=". $latestContent->getVersion()."\"><i class=\"icon-star\"></i> " . getMLText("view_online") . "</a></li>";
		}else print "<li><img class=\"mimeicon\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\"></li>";

		print "</ul>";
		$previewer = new LetoDMS_Preview_Previewer($cachedir, 100);
		$previewer->createPreview($latestContent);
		if($previewer->hasPreview($latestContent)) {
			print("<img class=\"mimeicon\" width=\"100\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=100\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">");
		}
		print "</td>\n";
		print "<td>".$latestContent->getVersion()."</td>\n";

		print "<td><ul class=\"unstyled\">\n";
		print "<li>".$latestContent->getOriginalFileName() ."</li>\n";

		if ($file_exists)
			print "<li>". LetoDMS_Core_File::format_filesize($latestContent->getFileSize()) ." ".htmlspecialchars($latestContent->getMimeType())."</li>";
		else print "<li><span class=\"warning\">".getMLText("document_deleted")."</span></li>";

		$updatingUser = $latestContent->getUser();
		print "<li>".getMLText("uploaded_by")." <a href=\"mailto:".$updatingUser->getEmail()."\">".htmlspecialchars($updatingUser->getFullName())."</a></li>";
		print "<li>".getLongReadableDate($latestContent->getDate())."</li>";

		print "</ul>\n";
		print "<ul class=\"unstyled\">\n";
		$attributes = $latestContent->getAttributes();
		if($attributes) {
			foreach($attributes as $attribute) {
				$attrdef = $attribute->getAttributeDefinition();
				print "<li>".htmlspecialchars($attrdef->getName()).": ".htmlspecialchars($attribute->getValue())."</li>\n";
			}
		}
		print "</ul>\n";

		print "<td>".htmlspecialchars($latestContent->getComment())."</td>";

		print "<td width='10%'>";
		print getOverallStatusText($status["status"]);
		if ( $status["status"]==S_DRAFT_REV || $status["status"]==S_DRAFT_APP || $status["status"]==S_IN_WORKFLOW || $status["status"]==S_EXPIRED ){
			print "<br><span".($document->hasExpired()?" class=\"warning\" ":"").">".(!$document->getExpires() ? getMLText("does_not_expire") : getMLText("expires").": ".getReadableDate($document->getExpires()))."</span>";
		}
		print "</td>";

		print "<td>";

		print "<ul class=\"unstyled\">";
		/* Only admin has the right to remove version in any case or a regular
		 * user if enableVersionDeletion is on
		 */
		if($accessop->mayRemoveVersion()) {
			print "<li><a href=\"out.RemoveVersion.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\"><i class=\"icon-remove\"></i> ".getMLText("rm_version")."</a></li>";
		}
		if($accessop->mayOverwriteStatus()) {
			print "<li><a href='../out/out.OverrideContentStatus.php?documentid=".$documentid."&version=".$latestContent->getVersion()."'><i class=\"icon-align-justify\"></i> ".getMLText("change_status")."</a></li>";
		}
		if($workflowmode == 'traditional') {
			// Allow changing reviewers/approvals only if not reviewed
			if($accessop->maySetReviewersApprovers()) {
				print "<li><a href='../out/out.SetReviewersApprovers.php?documentid=".$documentid."&version=".$latestContent->getVersion()."'>".getMLText("change_assignments")."</a></li>";
			}
		} else {
			if($accessop->maySetWorkflow()) {
				$workflow = $latestContent->getWorkflow();
				if(!$workflow) {
					print "<li><a href='../out/out.SetWorkflow.php?documentid=".$documentid."&version=".$latestContent->getVersion()."'><i class=\"icon-random\"></i> ".getMLText("set_workflow")."</a></li>";
				}
			}
		}
		if($accessop->maySetExpires()) {
			print "<li><a href='../out/out.SetExpires.php?documentid=".$documentid."'><i class=\"icon-time\"></i> ".getMLText("set_expiry")."</a></li>";
		}
		if($accessop->mayEditComment()) {
			print "<li><a href=\"out.EditComment.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\"><i class=\"icon-comment\"></i> ".getMLText("edit_comment")."</a></li>";
		}
		if($accessop->mayEditAttributes()) {
			print "<li><a href=\"out.EditAttributes.php?documentid=".$documentid."&version=".$latestContent->getVersion()."\"><i class=\"icon-edit\"></i> ".getMLText("edit_attributes")."</a></li>";
		}

		print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&vfile=1\"><i class=\"icon-download\"></i> ".getMLText("versioning_info")."</a></li>";	

		print "</ul>";
		echo "</td>";
		print "</tr></tbody>\n</table>\n";
		$this->contentContainerEnd();

		if($user->isAdmin()) {
			$this->contentHeading(getMLText("status"));
			$this->contentContainerStart();
			$status = $latestContent->getStatusLog();
			echo "<table class=\"table table-condensed\"><thead>";
			echo "<th>".getMLText('date')."</th><th>".getMLText('status')."</th><th>".getMLText('user')."</th><th>".getMLText('comment')."</th></tr>\n";
			echo "</thead><tbody>";
			foreach($status as $entry) {
				$suser = $dms->getUser($entry['userID']);
				echo "<tr><td>".$entry['date']."</td><td>".getOverallStatusText($entry['status'])."</td><td>".$suser->getFullName()."</td><td>".$entry['comment']."</td></tr>\n";
			}
			print "</tbody>\n</table>\n";
			$this->contentContainerEnd();
		}
?>
		  </div>
		</div>
		</div>
<?php
		if($workflowmode == 'traditional') {
?>
		  <div class="tab-pane" id="revapp">
<?php
		$this->contentContainerstart();
		print "<table class=\"table-condensed\">\n";

		if (is_array($reviewStatus) && count($reviewStatus)>0) {

			print "<tr><td colspan=5>\n";
			$this->contentSubHeading(getMLText("reviewers"));
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
				print "<td><ul class=\"unstyled\"><li>".$r["date"]."</li>";
				/* $updateUser is the user who has done the review */
				$updateUser = $dms->getUser($r["userID"]);
				print "<li>".(is_object($updateUser) ? htmlspecialchars($updateUser->getFullName()) : "unknown user id '".$r["userID"]."'")."</li></ul></td>";
				print "<td>".htmlspecialchars($r["comment"])."</td>\n";
				print "<td>".getReviewStatusText($r["status"])."</td>\n";
				print "<td><ul class=\"unstyled\">";

				if($accessop->mayReview()) {
					if ($is_reviewer && $r["status"]==0) {
						print "<li><a href=\"../out/out.ReviewDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&reviewid=".$r['reviewID']."\" class=\"btn btn-mini\">".getMLText("submit_review")."</a></li>";
					}else if (($updateUser==$user)&&(($r["status"]==1)||($r["status"]==-1))&&(!$document->hasExpired())){
						print "<li><a href=\"../out/out.ReviewDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&reviewid=".$r['reviewID']."\" class=\"btn btn-mini\">".getMLText("edit")."</a></li>";
					}
				}
				
				print "</ul></td>\n";	
				print "</td>\n</tr>\n";
			}
		}

		if (is_array($approvalStatus) && count($approvalStatus)>0) {

			print "<tr><td colspan=5>\n";
			$this->contentSubHeading(getMLText("approvers"));
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
				print "<td><ul class=\"unstyled\"><li>".$a["date"]."</li>";
				/* $updateUser is the user who has done the approval */
				$updateUser = $dms->getUser($a["userID"]);
				print "<li>".(is_object($updateUser) ? htmlspecialchars($updateUser->getFullName()) : "unknown user id '".$a["userID"]."'")."</li></ul></td>";	
				print "<td>".htmlspecialchars($a["comment"])."</td>\n";
				print "<td>".getApprovalStatusText($a["status"])."</td>\n";
				print "<td><ul class=\"unstyled\">";
			
				if($accessop->mayApprove()) {
					if ($is_approver && $status["status"]==S_DRAFT_APP) {
						print "<li><a class=\"btn btn-mini\" href=\"../out/out.ApproveDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&approveid=".$a['approveID']."\">".getMLText("submit_approval")."</a></li>";
					}else if (($updateUser==$user)&&(($a["status"]==1)||($a["status"]==-1))&&(!$document->hasExpired())){
						print "<li><a class=\"btn btn-mini\" href=\"../out/out.ApproveDocument.php?documentid=".$documentid."&version=".$latestContent->getVersion()."&approveid=".$a['approveID']."\">".getMLText("edit")."</a></li>";
					}
				}
				
				print "</ul>";
				print "</td>\n";	
				print "</td>\n</tr>\n";
			}
		}

		print "</table>\n";
		$this->contentContainerEnd();
?>
		  </div>
<?php
		} else {
			$workflow = $latestContent->getWorkflow();
			if($workflow) {
?>
		  <div class="tab-pane" id="workflow">
<?php
			$this->contentContainerStart();
			if($user->isAdmin()) {
				if(LetoDMS_Core_DMS::checkIfEqual($workflow->getInitState(), $latestContent->getWorkflowState())) {
					print "<form action=\"../out/out.RemoveWorkflowFromDocument.php\" method=\"post\">".createHiddenFieldWithKey('removeworkflowfromdocument')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"version\" value=\"".$latestContent->getVersion()."\" /><button type=\"submit\" class=\"btn\"><i class=\"icon-remove\"></i> ".getMLText('rm_workflow')."</button></form>";
				} else {
					print "<form action=\"../out/out.RewindWorkflow.php\" method=\"post\">".createHiddenFieldWithKey('rewindworkflow')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"version\" value=\"".$latestContent->getVersion()."\" /><button type=\"submit\" class=\"btn\"><i class=\"icon-refresh\"></i> ".getMLText('rewind_workflow')."</button></form>";
				}
			}

			$workflowstate = $latestContent->getWorkflowState();
			$transitions = $workflow->getNextTransitions($workflowstate);
			echo "<h4>".$workflow->getName()."</h4>";
			if($parentworkflow = $latestContent->getParentWorkflow()) {
				echo "<p>Sub workflow of '".$parentworkflow->getName()."'</p>";
			}
			echo "<div class=\"row-fluid\">";
			echo "<div class=\"span8\">";
			echo "<h5>Current State: ".$workflowstate->getName()."</h5>";
			echo "<table class=\"table table-condensed\">\n";
			echo "<tr>";
			echo "<td>Next state:</td>";
			foreach($transitions as $transition) {
				$nextstate = $transition->getNextState();
				echo "<td>".$nextstate->getName()."</td>";
			}
			echo "</tr>";
			echo "<tr>";
			echo "<td>Action:</td>";
			foreach($transitions as $transition) {
				$action = $transition->getAction();
				echo "<td>".getMLText('action_'.$action->getName())."</td>";
			}
			echo "</tr>";
			echo "<tr>";
			echo "<td>Users:</td>";
			foreach($transitions as $transition) {
				$transusers = $transition->getUsers();
				echo "<td>";
				foreach($transusers as $transuser) {
					$u = $transuser->getUser();
					echo $u->getFullName();
					if($document->getAccessMode($u) < M_READ) {
						echo " (no access)";
					}
					echo "<br />";
				}
				echo "</td>";
			}
			echo "</tr>";
			echo "<tr>";
			echo "<td>Groups:</td>";
			foreach($transitions as $transition) {
				$transgroups = $transition->getGroups();
				echo "<td>";
				foreach($transgroups as $transgroup) {
					$g = $transgroup->getGroup();
					echo "At least ".$transgroup->getNumOfUsers()." users of ".$g->getName();
					if ($document->getGroupAccessMode($g) < M_READ) {
						echo " (no access)";
					}
					echo "<br />";
				}
				echo "</td>";
			}
			echo "</tr>";
			echo "<tr class=\"success\">";
			echo "<td>User done work:</td>";
			foreach($transitions as $transition) {
				echo "<td>";
				if($latestContent->executeWorkflowTransitionIsAllowed($transition)) {
					echo "Done";
				}
				$wkflogs = $latestContent->getWorkflowLog($transition);
				foreach($wkflogs as $wkflog) {
					$loguser = $wkflog->getUser();
					echo $loguser->getFullName()." (";
					$names = array();
					foreach($loguser->getGroups() as $loggroup) {
						$names[] =  $loggroup->getName();
					}
					echo implode(", ", $names);
					echo ") - ";
					echo $wkflog->getDate();
				}
				echo "</td>";
			}
			echo "</tr>";
			echo "<tr>";
			echo "<td></td>";
			foreach($transitions as $transition) {
				echo "<td>";
				if($latestContent->triggerWorkflowTransitionIsAllowed($user, $transition)) {
					$action = $transition->getAction();
					print "<form action=\"../out/out.TriggerWorkflow.php\" method=\"post\">".createHiddenFieldWithKey('triggerworkflow')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"version\" value=\"".$latestContent->getVersion()."\" /><input type=\"hidden\" name=\"transition\" value=\"".$transition->getID()."\" /><input type=\"submit\" class=\"btn\" value=\"".getMLText('action_'.$action->getName())."\" /></form>";
				}
				echo "</td>";
			}
			echo "</tr>";
			echo "</table>";

			$workflows = $dms->getAllWorkflows();
			if($workflows) {
				$subworkflows = array();
				foreach($workflows as $wkf) {
					if($wkf->getInitState()->getID() == $workflowstate->getID()) {
						if($workflow->getID() != $wkf->getID()) {
							$subworkflows[] = $wkf;
						}
					}
				}
				if($subworkflows) {
					echo "<form action=\"../out/out.RunSubWorkflow.php\" method=\"post\">".createHiddenFieldWithKey('runsubworkflow')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"version\" value=\"".$latestContent->getVersion()."\" />";
					echo "<select name=\"subworkflow\">";
					foreach($subworkflows as $subworkflow) {
						echo "<option value=\"".$subworkflow->getID()."\">".$subworkflow->getName()."</option>";
					}
					echo "</select>";
					echo "<label class=\"inline\">";
					echo "<input type=\"submit\" class=\"btn\" value=\"".getMLText('run_subworkflow')."\" />";
					echo "</lable>";
					echo "</form>";
				}
			}
			/* If in a sub workflow, the check if return the parent workflow
			 * is possible.
			 */
			if($parentworkflow = $latestContent->getParentWorkflow()) {
				$states = $parentworkflow->getStates();
				foreach($states as $state) {
					/* Check if the current workflow state is also a state in the
					 * parent workflow
					 */
					if($latestContent->getWorkflowState()->getID() == $state->getID()) {
						echo "Switch from sub workflow '".$workflow->getName()."' into state ".$state->getName()." of parent workflow '".$parentworkflow->getName()."' is possible<br />";
						/* Check if the transition from the state where the sub workflow
						 * starts into the current state is also allowed in the parent
						 * workflow. Checking at this point is actually too late, because
						 * the sub workflow shouldn't be entered in the first place,
						 * but that is difficult to check.
						 */
						/* If the init state has not been left, return is always possible */
						if($workflow->getInitState()->getID() == $latestContent->getWorkflowState()->getID()) {
							echo "Initial state of sub workflow has not been left. Return to parent workflow is possible<br />";
							echo "<form action=\"../out/out.ReturnFromSubWorkflow.php\" method=\"post\">".createHiddenFieldWithKey('returnfromsubworkflow')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"version\" value=\"".$latestContent->getVersion()."\" />";
							echo "<input type=\"submit\" class=\"btn\" value=\"".getMLText('return_from_subworkflow')."\" />";
							echo "</form>";
						} else {
							/* Get a transition from the last state in the parent workflow
							 * (which is the initial state of the sub workflow) into
							 * current state.
							 */
							echo "Check for transition from ".$workflow->getInitState()->getName()." into ".$latestContent->getWorkflowState()->getName()." is possible in parentworkflow ".$parentworkflow->getID()."<br />";
							$transitions = $parentworkflow->getTransitionsByStates($workflow->getInitState(), $latestContent->getWorkflowState());
							if($transitions) {
								echo "Found transitions in workflow ".$parentworkflow->getID()."<br />";
								foreach($transitions as $transition) {
									if($latestContent->triggerWorkflowTransitionIsAllowed($user, $transition)) {
										echo "Triggering transition is allowed<br />";
										echo "<form action=\"../out/out.ReturnFromSubWorkflow.php\" method=\"post\">".createHiddenFieldWithKey('returnfromsubworkflow')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"version\" value=\"".$latestContent->getVersion()."\" /><input type=\"hidden\" name=\"transition\" value=\"".$transition->getID()."\" />";
										echo "<input type=\"submit\" class=\"btn\" value=\"".getMLText('return_from_subworkflow')."\" />";
										echo "</form>";

									}
								}
							}
						}
					}
				}
			}
			echo "</div>";
			echo "</div>";
			$this->contentContainerEnd();
?>
		  </div>
<?php
			}
		}
		if (count($versions)>1) {
?>
		  <div class="tab-pane" id="previous">
<?php
			$this->contentContainerStart();

			print "<table class=\"table\">";
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
				$workflow = $version->getWorkflow();
				$workflowstate = $version->getWorkflowState();
				
				// verify if file exists
				$file_exists=file_exists($dms->contentDir . $version->getPath());
				
				print "<tr>\n";
				print "<td nowrap><ul class=\"unstyled\">";
				if ($file_exists){
					print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&version=".$version->getVersion()."\"><i class=\"icon-download\"></i> ".getMLText("download")."</a>";
					if ($viewonlinefiletypes && in_array(strtolower($latestContent->getFileType()), $viewonlinefiletypes))
						print "<li><a target=\"_blank\" href=\"../op/op.ViewOnline.php?documentid=".$documentid."&version=".$version->getVersion()."\"><i class=\"icon-star\"></i> " . getMLText("view_online") . "</a>";
				}else print "<li><img class=\"mimeicon\" src=\"".$this->getMimeIcon($version->getFileType())."\" title=\"".htmlspecialchars($version->getMimeType())."\">";
				
				print "</ul>";
				$previewer->createPreview($version);
				if($previewer->hasPreview($version)) {
					print("<img class=\"mimeicon\" width=\"100\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$version->getVersion()."&width=100\" title=\"".htmlspecialchars($version->getMimeType())."\">");
				}
				print "</td>\n";
				print "<td>".$version->getVersion()."</td>\n";
				print "<td><ul class=\"unstyled\">\n";
				print "<li>".$version->getOriginalFileName()."</li>\n";
				if ($file_exists) print "<li>". LetoDMS_Core_File::format_filesize($version->getFileSize()) ." ".htmlspecialchars($version->getMimeType())."</li>";
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
				print "<ul class=\"unstyled\">";
				/* Only admin has the right to remove version in any case or a regular
				 * user if enableVersionDeletion is on
				 */
				if($accessop->mayRemoveVersion()) {
					print "<li><a href=\"out.RemoveVersion.php?documentid=".$documentid."&version=".$version->getVersion()."\"><i class=\"icon-remove\"></i> ".getMLText("rm_version")."</a></li>";
				}
				if($accessop->mayEditComment()) {
					print "<li><a href=\"out.EditComment.php?documentid=".$document->getID()."&version=".$version->getVersion()."\"><i class=\"icon-edit\"></i> ".getMLText("edit_comment")."</a></li>";
				}
				if($accessop->mayEditAttributes()) {
					print "<li><a href=\"out.EditAttributes.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."\"><i class=\"icon-edit\"></i> ".getMLText("edit_attributes")."</a></li>";
				}
				//print "<li><a href='../out/out.DocumentVersionDetail.php?documentid=".$documentid."&version=".$version->getVersion()."'><i class=\"icon-info-sign\"></i> ".getMLText("details")."</a></li>";
				print "</ul>";
				print "</td>\n</tr>\n";
			}
			print "</tbody>\n</table>\n";
			$this->contentContainerEnd();
?>
		  </div>
<?php
		}
?>
		  <div class="tab-pane" id="attachments">
<?php

		$this->contentContainerStart();

		$files = $document->getDocumentFiles();

		if (count($files) > 0) {

			print "<table class=\"table\">";
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
				print "<td><ul class=\"unstyled\">";
				if ($file_exists) {
					print "<li><a href=\"../op/op.Download.php?documentid=".$documentid."&file=".$file->getID()."\"><i class=\"icon-download\"></i> ".htmlspecialchars($file->getName())."</a>";
					if ($viewonlinefiletypes && in_array(strtolower($latestContent->getFileType()), $viewonlinefiletypes))
						print "<li><a target=\"_blank\" href=\"../op/op.ViewOnline.php?documentid=".$documentid."&file=". $file->getID()."\"><i class=\"icon-star\"></i> " . getMLText("view_online") . "</a></li>";
				} else print "<li><img class=\"mimeicon\" src=\"images/icons/".$this->getMimeIcon($file->getFileType())."\" title=\"".htmlspecialchars($file->getMimeType())."\">";
				print "</ul></td>";
				
				print "<td><ul class=\"unstyled\">\n";
				print "<li>".$file->getOriginalFileName() ."</li>\n";
				if ($file_exists)
					print "<li>". filesize($dms->contentDir . $file->getPath()) ." bytes ".htmlspecialchars($file->getMimeType())."</li>";
				else print "<li>".htmlspecialchars($file->getMimeType())." - <span class=\"warning\">".getMLText("document_deleted")."</span></li>";

				print "<li>".getMLText("uploaded_by")." <a href=\"mailto:".$responsibleUser->getEmail()."\">".htmlspecialchars($responsibleUser->getFullName())."</a></li>";
				print "<li>".getLongReadableDate($file->getDate())."</li>";

				print "<td>".htmlspecialchars($file->getComment())."</td>";
			
				print "<td><span class=\"actions\">";
				if (($document->getAccessMode($user) == M_ALL)||($file->getUserID()==$user->getID()))
					print "<form action=\"../out/out.RemoveDocumentFile.php\" method=\"get\"><input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"fileid\" value=\"".$file->getID()."\" /><button type=\"submit\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("delete")."</button></form>";
				print "</span></td>";		
				
				print "</tr>";
			}
			print "</tbody>\n</table>\n";	

		}
		else printMLText("no_attached_files");

		if ($document->getAccessMode($user) >= M_READWRITE){
			print "<ul class=\"unstyled\"><li><a href=\"../out/out.AddFile.php?documentid=".$documentid."\" class=\"btn\">".getMLText("add")."</a></ul>\n";
		}
		$this->contentContainerEnd();
?>
		  </div>
		  <div class="tab-pane" id="links">
<?php
		$this->contentContainerStart();
		$links = $document->getDocumentLinks();
		$links = filterDocumentLinks($user, $links);

		if (count($links) > 0) {

			print "<table class=\"table table-condensed\">";
			print "<thead>\n<tr>\n";
			print "<th></th>\n";
			print "<th></th>\n";
			print "<th>".getMLText("comment")."</th>\n";
			print "<th>".getMLText("document_link_by")."</th>\n";
			print "<th></th>\n";
			print "</tr>\n</thead>\n<tbody>\n";

			foreach($links as $link) {
				$responsibleUser = $link->getUser();
				$targetDoc = $link->getTarget();
				$targetlc = $targetDoc->getLatestContent();

				print "<tr>";
				print "<td><a href=\"../op/op.Download.php?documentid=".$targetDoc->getID()."&version=".$targetlc->getVersion()."\">";
				if($previewer->hasPreview($targetlc)) {
					print "<img class=\"mimeicon\" width=\"40\"src=\"../op/op.Preview.php?documentid=".$targetDoc->getID()."&version=".$targetlc->getVersion()."&width=40\" title=\"".htmlspecialchars($targetlc->getMimeType())."\">";
				} else {
					print "<img class=\"mimeicon\" src=\"".$this->getMimeIcon($targetlc->getFileType())."\" title=\"".htmlspecialchars($targetlc->getMimeType())."\">";
				}
				print "</td>";
				print "<td><a href=\"out.ViewDocument.php?documentid=".$targetDoc->getID()."\" class=\"linklist\">".htmlspecialchars($targetDoc->getName())."</a></td>";
				print "<td>".htmlspecialchars($targetDoc->getComment())."</td>";
				print "<td>".htmlspecialchars($responsibleUser->getFullName());
				if (($user->getID() == $responsibleUser->getID()) || ($document->getAccessMode($user) == M_ALL ))
					print ", ".getMLText("document_link_public").": ".(($link->isPublic()) ? getMLText("yes") : getMLText("no"));
				print "</td>";
				print "<td><span class=\"actions\">";
				if (($user->getID() == $responsibleUser->getID()) || ($document->getAccessMode($user) == M_ALL ))
					print "<form action=\"../op/op.RemoveDocumentLink.php\" method=\"post\">".createHiddenFieldWithKey('removedocumentlink')."<input type=\"hidden\" name=\"documentid\" value=\"".$documentid."\" /><input type=\"hidden\" name=\"linkid\" value=\"".$link->getID()."\" /><button type=\"submit\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("delete")."</button></form>";
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
			<input type="hidden" name="documentid" value="<?php print $documentid;?>">
			<table class="table-condensed">
			<tr>
			<td><?php printMLText("add_document_link");?>:</td>
			<td><?php $this->printDocumentChooser("form1");?></td>
			</tr>
			<?php
			if ($document->getAccessMode($user) >= M_READWRITE) {
				print "<tr><td>".getMLText("document_link_public")."</td>";
				print "<td>";
				print "<input type=\"checkbox\" name=\"public\" value=\"true\" checked />";
				print "</td></tr>";
			}
?>
			<tr>
			<td></td>
			<td><input type="Submit" class="btn" value="<?php printMLText("save");?>"></td>
			</tr>
			</table>
			</form>
<?php
		}
		$this->contentContainerEnd();
?>
		  </div>
		</div>
<?php
		$this->htmlEndPage();

	} /* }}} */
}
?>
