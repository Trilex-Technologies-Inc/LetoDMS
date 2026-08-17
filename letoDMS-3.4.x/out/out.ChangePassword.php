<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010-2011 Uwe Steinmann
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
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");

if (!isset($_REQUEST["hash"])) {
	header("Location: ../out/out.Login.php");
	exit;
}

UI::htmlStartPage(getMLText("change_password"), "login");
UI::globalBanner();
UI::pageNavigation(getMLText("change_password"));
?>

<?php UI::contentContainerStart(); ?>
<form action="../op/op.ChangePassword.php" method="post" name="form1" onsubmit="return checkForm();">
<?php
if (isset($_REQUEST["referuri"]) && strlen($_REQUEST["referuri"])>0) {
	echo "<input type='hidden' name='referuri' value='".$_REQUEST["referuri"]."'/>";
}
if (isset($_REQUEST["hash"]) && strlen($_REQUEST["hash"])>0) {
	echo "<input type='hidden' name='hash' value='".$_REQUEST["hash"]."'/>";
}
?>
	<table border="0">
		<tr>
			<td><?php printMLText("password");?></td>
			<td><input id="pwd" type="password" name="newpassword"> <div id="outerstrength" style="min-width: 100px; height: 14px; display: inline-block; border: 1px solid black; padding: 1px;"><div id="innerstrength" style="width: 0px; height: 14px; display: inline-block; border: 0px; padding: 0px; background-color: red;">&nbsp;</div> <div id="strength" style="display: inline-block;"></div></div></td>
		</tr>
		<tr>
			<td><?php printMLText("password_repeat");?></td>
			<td><input type="password" name="newpasswordrepeat" id="passwordrepeat"></td>
<script type="text/javascript" src='../js/jquery.passwordstrength.js'></script>
<script>
	$(document).ready( function() {
		$("#pwd").passStrength({
			url: "../op/op.Ajax.php",
			minscore: <?php echo (int) $settings->_passwordStrength; ?>
		});
	});
</script>
		</tr>
		<tr>
			<td colspan="2"><input type="Submit" value="<?php printMLText("submit_password") ?>"></td>
		</tr>
	</table>
</form>
<?php UI::contentContainerEnd(); ?>
<script language="JavaScript">document.form1.newpassword.focus();</script>
<p><a href="../out/out.Login.php"><?php echo getMLText("login"); ?></a></p>
<?php
	UI::htmlEndPage();
?>

