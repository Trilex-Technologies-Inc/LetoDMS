<?php
//    MyDMS. Document Management System
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
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if ($user->isGuest()) {
	UI::exitError(getMLText("my_account"),getMLText("access_denied"));
}

if (!$settings->_enableUsersView) {
	UI::exitError(getMLText("my_account"),getMLText("access_denied"));
}

$allUsers = $dms->getAllUsers();

if (is_bool($allUsers)) {
	UI::exitError(getMLText("my_account"),getMLText("internal_error"));
}

$groups = $dms->getAllGroups();

if (is_bool($groups)) {
	UI::exitError(getMLText("admin_tools"),getMLText("internal_error"));
}

UI::htmlStartPage(getMLText("my_account"));
UI::globalNavigation();
UI::pageNavigation(getMLText("my_account"), "my_account");

UI::contentHeading(getMLText("groups"));
UI::contentContainerStart();

echo "<ul class=\"groupView\">\n";
$users = $dms->getAllUsers();

foreach ($groups as $group){

	$members = $group->getUsers();
	$managers = $group->getManagers();
	$ismanager = false; /* set to true if current user is manager */

	echo "<li>".htmlspecialchars($group->getName());
	if($group->getComment())
		echo " : ".htmlspecialchars($group->getComment());
	foreach($managers as $manager)
		if($manager->getId() == $user->getId()) {
			echo " : you are the manager of this group";
			$ismanager = true;
		}
	echo "</li>";

	echo "<ul>\n";
	$memberids = array();
	foreach ($members as $member) {
		$memberids[] = $member->getId();

		echo "<li>".htmlspecialchars($member->getFullName());
		if ($member->getEmail()!="")
			echo " (<a href=\"mailto:".htmlspecialchars($member->getEmail())."\">".htmlspecialchars($member->getEmail())."</a>)";
		foreach($managers as $manager)
			if($manager->getId() == $member->getId())
				echo ", ".getMLText("manager");
		if($ismanager) {
			echo ' <a href="../op/op.GroupView.php?action=del&groupid='.$group->getId().'&userid='.$member->getId().'"><img src="images/del.gif" width="15" height="15" border="0" align="absmiddle" alt=""> '.getMLText("rm_user").'</a>';
		}
		echo "</li>";
	}
	if($ismanager) {
		echo "<li>".getMLText("add_user_to_group").":";
		echo "<form action=\"../op/op.GroupView.php\">";
		echo "<input type=\"hidden\" name=\"action\" value=\"add\" /><input type=\"hidden\" name=\"groupid\" value=\"".$group->getId()."\" />";
		echo "<select name=\"userid\" onChange=\"javascript: submit();\">";
		echo "<option value=\"\"></option>";
		foreach($users as $u) {
			if(!$u->isAdmin() && !$u->isGuest() && !in_array($u->getId(), $memberids))
				echo "<option value=\"".$u->getId()."\">".htmlspecialchars($u->getFullName())."</option>";
		}
		echo "</select>";
		echo "</form>";
		echo "</li>";
	}
	echo "</ul>\n";
}
echo "</ul>\n";

UI::contentContainerEnd();
UI::htmlEndPage();
?>
