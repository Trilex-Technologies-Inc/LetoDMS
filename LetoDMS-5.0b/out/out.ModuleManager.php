<?php
include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");
require_once("../inc/inc.ClassModuleManager.php");

if (!$user->isAdmin()) (new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Modules', getMLText('access_denied'));
$manager = new LetoDMS_ModuleManager($db, $settings->_rootDir.'modules', $settings->_dbDriver);
$manager->initialize();
$view = (new UI($GLOBALS['theme'] ?? 'bootstrap'))->factory($theme, 'ModuleManager', array('dms'=>$dms, 'user'=>$user, 'modules'=>$manager->all(), 'message'=>isset($_GET['message']) ? $_GET['message'] : ''));
if ($view) { $view->show(); exit; }
