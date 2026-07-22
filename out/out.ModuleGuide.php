<?php
include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if (!$user->isAdmin())
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Module Development Guide', getMLText('access_denied'));

$view = (new UI($GLOBALS['theme'] ?? 'bootstrap'))->factory($theme, 'ModuleGuide', array(
	'dms' => $dms,
	'user' => $user
));
if ($view) {
	$view->show();
	exit;
}

