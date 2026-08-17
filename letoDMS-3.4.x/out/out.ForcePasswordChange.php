<?php
/**
 * Do authentication of users and session management
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

UI::htmlStartPage(getMLText("sign_in"), "login");
UI::globalBanner();
echo "<h3>".getMLText('password_expiration')."</h3>";
echo "<p>".getMLText('password_expiration_text')."</p>";
UI::contentContainerStart();
?>
<form action="../op/op.EditUserData.php" method="post" name="form1" onsubmit="return checkForm();">
<table>
	<tr>
		<td><?php printMLText("current_password");?>:</td>
		<td><input id="currentpwd" type="Password" name="currentpwd" size="30"></td>
	</tr>
	<tr>
		<td><?php printMLText("password");?>:</td>
		<td><input id="pwd" type="Password" name="pwd" size="30"> <div id="outerstrength" style="min-width: 100px; height: 14px; display: inline-block; border: 1px solid black; padding: 1px;"><div id="innerstrength" style="width: 0px; height: 14px; display: inline-block; border: 0px; padding: 0px; background-color: red;">&nbsp;</div> <div id="strength" style="display: inline-block;"></div></div></td>
	</tr>
	<tr>
		<td><?php printMLText("confirm_pwd");?>:</td>
		<td><input id="pwdconf" type="Password" name="pwdconf" size="30"></td>
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
		<td colspan="2"><input type="Submit" value="<?php printMLText("submit_userinfo") ?>"></td>
	</tr>
</table>
<input type="hidden" name="fullname" value="<?php print htmlspecialchars($user->getFullName());?>" />
<input type="hidden" name="email" value="<?php print htmlspecialchars($user->getEmail());?>" />
<input type="hidden" name="comment" value="<?php print htmlspecialchars($user->getComment());?>" />
</form>

<?php
UI::contentContainerEnd();
	$tmpfoot = array();
	$tmpfoot[] = "<a href=\"../op/op.Logout.php\">" . getMLText("logout") . "</a>\n";
		print "<p>";
		print implode(' | ', $tmpfoot);
		print "</p>\n";
UI::htmlEndPage();
?>
