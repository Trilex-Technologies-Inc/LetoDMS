<?php
/**
 * Implementation of Login view
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
 * Class which outputs the html page for Login view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Login extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$enableguestlogin = $this->params['enableguestlogin'];
		$enablepasswordforgotten = $this->params['enablepasswordforgotten'];
		$refer = $this->params['referrer'];
		$themes = !empty($this->params['themes']) ? $this->params['themes'] : array('blue', 'bootstrap');

		$this->htmlStartPage(getMLText("sign_in"), "login");
		$this->globalBanner();
		$this->contentStart();
		$this->pageNavigation(getMLText("sign_in"));
?>
<script language="JavaScript">
function checkForm()
{
	msg = "";
	if (document.form1.login.value == "") msg += "<?php printMLText("js_no_login");?>\n";
	if (document.form1.pwd.value == "") msg += "<?php printMLText("js_no_pwd");?>\n";
	if (msg != "")
	{
		alert(msg);
		return false;
	}
	else
		return true;
}

function guestLogin()
{
	url = "../op/op.Login.php?login=guest" + 
		"&sesstheme=" + document.form1.sesstheme.options[document.form1.sesstheme.options.selectedIndex].value +
		"&lang=" + document.form1.lang.options[document.form1.lang.options.selectedIndex].value;
	if (document.form1.referuri) {
		url += "&referuri=" + escape(document.form1.referuri.value);
	}
	document.location.href = url;
}

</script>
<?php $this->contentContainerStart(); ?>
<form action="../op/op.Login.php" method="post" name="form1" onsubmit="return checkForm();" class="mx-auto" style="max-width: 520px;">
<?php
		if ($refer) {
			echo "<input type='hidden' name='referuri' value='".sanitizeString($refer)."'/>";
		}
?>
	<div class="card shadow-sm">
		<div class="card-body">
			<div class="mb-3">
				<label class="form-label" for="login"><?php printMLText("user_login");?>:</label>
				<input class="form-control" type="text" name="login" id="login">
			</div>
			<div class="mb-3">
				<label class="form-label" for="pwd"><?php printMLText("password");?>:</label>
				<input class="form-control" name="pwd" id="pwd" type="password">
			</div>
			<div class="mb-3">
				<label class="form-label" for="lang"><?php printMLText("language");?>:</label>
<?php
			print "<select class=\"form-select\" name=\"lang\" id=\"lang\">";
			print "<option value=\"\">-";
			$languages = getLanguages();
			foreach ($languages??[] as $currLang) {
				$selected = ($currLang === "English") ? " selected" : "";
				print "<option value=\"".$currLang."\"".$selected.">".$currLang;
			}
			print "</select>";
?>
			</div>
			<div class="mb-4">
				<label class="form-label" for="sesstheme"><?php printMLText("theme");?>:</label>
<?php
			print "<select class=\"form-select\" name=\"sesstheme\" id=\"sesstheme\">";
			print "<option value=\"\">-";
			foreach ($themes??[] as $currTheme) {
				$selected = ($currTheme === "bootstrap") ? " selected" : "";
				print "<option value=\"".$currTheme."\"".$selected.">".$currTheme;
			}
			print "</select>";
?>
			</div>
			<div class="d-grid">
				<input class="btn btn-primary" type="submit" value="<?php printMLText("submit_login") ?>">
			</div>
		</div>
	</div>
</form>
<?php
		$this->contentContainerEnd();
		$tmpfoot = array();
		if ($enableguestlogin)
			$tmpfoot[] = "<a href=\"javascript:guestLogin()\">" . getMLText("guest_login") . "</a>\n";
		if ($enablepasswordforgotten)
			$tmpfoot[] = "<a href=\"../out/out.PasswordForgotten.php\">" . getMLText("password_forgotten") . "</a>\n";
		if($tmpfoot) {
			print "<p>";
			print implode(' | ', $tmpfoot);
			print "</p>\n";
		}
?>
<script language="JavaScript">document.form1.login.focus();</script>
<?php
		$this->htmlEndPage();
	} /* }}} */
}
?>
