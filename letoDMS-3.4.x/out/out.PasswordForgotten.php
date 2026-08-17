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

UI::htmlStartPage(getMLText("password_forgotten"), "login");
UI::globalBanner();
UI::pageNavigation(getMLText("password_forgotten"));
?>

<?php UI::contentContainerStart(); ?>
<form action="../op/op.PasswordForgotten.php" method="post" name="form1" onsubmit="return checkForm();">
<?php
if (isset($_REQUEST["referuri"]) && strlen($_REQUEST["referuri"])>0) {
	echo "<input type='hidden' name='referuri' value='".$_REQUEST["referuri"]."'/>";
}
?>
  <p><?php printMLText("password_forgotten_text"); ?></p>
	<table border="0">
		<tr>
			<td><?php printMLText("login");?></td>
			<td><input name="login" id="login"></td>
		</tr>
		<tr>
			<td><?php printMLText("email");?></td>
			<td><input name="email" id="email"></td>
		</tr>
		<tr>
			<td colspan="2"><input type="Submit" value="<?php printMLText("submit_password_forgotten") ?>"></td>
		</tr>
	</table>
</form>
<?php UI::contentContainerEnd(); ?>
<script language="JavaScript">document.form1.email.focus();</script>
<p><a href="../out/out.Login.php"><?php echo getMLText("login"); ?></a></p>
<?php
	UI::htmlEndPage();
?>
