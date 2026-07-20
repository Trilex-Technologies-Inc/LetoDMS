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
		$siteName = strlen($this->params['sitename']) > 0 ? $this->params['sitename'] : "LetoDMS";

		$this->htmlStartPage(getMLText("sign_in"), "login");
		$this->globalBanner();
		$this->contentStart();
?>
<script type="text/javascript">
function checkForm()
{
	var msg = "";
	if (document.form1.login.value == "") msg += "<?php printMLText("js_no_login");?>\n";
	if (document.form1.pwd.value == "") msg += "<?php printMLText("js_no_pwd");?>\n";
	if (msg != "") {
		alert(msg);
		return false;
	}
	return true;
}

function guestLogin()
{
	var url = "../op/op.Login.php?login=guest" +
		"&sesstheme=" + encodeURIComponent(document.form1.sesstheme.value) +
		"&lang=" + encodeURIComponent(document.form1.lang.value);
	if (document.form1.referuri) {
		url += "&referuri=" + encodeURIComponent(document.form1.referuri.value);
	}
	document.location.href = url;
}
</script>

<main class="login-shell">
	<section class="login-intro" aria-label="<?php printMLText("sign_in");?>">
		<div class="login-mark" aria-hidden="true">
			<svg viewBox="0 0 24 24" focusable="false"><path d="M7 3.5h7l4 4V20.5H7z"/><path d="M14 3.5v4h4M10 12h5M10 15.5h5"/></svg>
		</div>
		<p class="login-eyebrow"><?php echo htmlspecialchars($siteName); ?></p>
		<h1><?php printMLText("sign_in");?></h1>
		<p class="login-lead">Secure access to your documents, workflows, and shared knowledge.</p>
		<ul class="login-benefits" aria-hidden="true">
			<li><span>✓</span> Organize documents in one place</li>
			<li><span>✓</span> Keep reviews and approvals moving</li>
			<li><span>✓</span> Work securely with your team</li>
		</ul>
	</section>

	<section class="login-panel">
		<div class="login-panel-heading">
			<span class="login-mobile-mark" aria-hidden="true">L</span>
			<div>
				<h2><?php printMLText("sign_in");?></h2>
				<p>Enter your account details to continue.</p>
			</div>
		</div>

		<form action="../op/op.Login.php" method="post" name="form1" onsubmit="return checkForm();" class="login-form">
<?php
		if ($refer) {
			echo "<input type='hidden' name='referuri' value='".sanitizeString($refer)."'/>";
		}
?>
			<div class="login-field">
				<label for="login"><?php printMLText("user_login");?></label>
				<input type="text" name="login" id="login" autocomplete="username" required>
			</div>
			<div class="login-field">
				<label for="pwd"><?php printMLText("password");?></label>
				<input name="pwd" id="pwd" type="password" autocomplete="current-password" required>
			</div>
			<div class="login-options">
				<div class="login-field">
					<label for="lang"><?php printMLText("language");?></label>
<?php
			print "<select name=\"lang\" id=\"lang\">";
			print "<option value=\"\">—</option>";
			$languages = getLanguages();
			foreach ($languages??[] as $currLang) {
				$selected = ($currLang === "English") ? " selected" : "";
				print "<option value=\"".htmlspecialchars($currLang)."\"".$selected.">".htmlspecialchars($currLang)."</option>";
			}
			print "</select>";
?>
				</div>
				<div class="login-field">
					<label for="sesstheme"><?php printMLText("theme");?></label>
<?php
			print "<select name=\"sesstheme\" id=\"sesstheme\">";
			print "<option value=\"\">—</option>";
			foreach ($themes??[] as $currTheme) {
				$selected = ($currTheme === "bootstrap") ? " selected" : "";
				print "<option value=\"".htmlspecialchars($currTheme)."\"".$selected.">".htmlspecialchars(ucfirst($currTheme))."</option>";
			}
			print "</select>";
?>
				</div>
			</div>
			<button class="login-submit" type="submit">
				<span><?php printMLText("submit_login") ?></span>
				<span aria-hidden="true">→</span>
			</button>
		</form>
<?php
		$tmpfoot = array();
		if ($enableguestlogin)
			$tmpfoot[] = "<a href=\"javascript:guestLogin()\">" . getMLText("guest_login") . "</a>\n";
		if ($enablepasswordforgotten)
			$tmpfoot[] = "<a href=\"../out/out.PasswordForgotten.php\">" . getMLText("password_forgotten") . "</a>\n";
		if ($tmpfoot) {
			print "<nav class=\"login-links\" aria-label=\"Account help\">";
			print implode('<span aria-hidden="true"></span>', $tmpfoot);
			print "</nav>\n";
		}
?>
	</section>
</main>
<?php $this->contentEnd(); ?>
<script type="text/javascript">document.form1.login.focus();</script>
<?php
		$this->htmlEndPage();
	} /* }}} */
}
?>
