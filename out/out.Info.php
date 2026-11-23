<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
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

include("../inc/inc.Version.php");
include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if (!$user->isAdmin()) {
	http_response_code(403);
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError(getMLText("admin_tools"),getMLText("access_denied"));
	exit;
}

$v = new LetoDMS_Version;
require_once '../inc/inc.ClassUpdateChecker.php';
$updateCache = !empty($settings->_cacheDir) ? rtrim($settings->_cacheDir,'/\\').'/update-checks' : sys_get_temp_dir().'/letodms-updates-'.substr(hash('sha256',$settings->_rootDir),0,20);
$updateChecker = new LetoDMS_UpdateChecker($v->_number,$updateCache);
$includePrereleases = isset($_GET['prereleases']) ? $_GET['prereleases']==='1' : LetoDMS_UpdateChecker::isPrerelease($v->_number);
$updateError = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $expected = createFormKey('update-check');
    if (!$expected || !isset($_POST['formtoken']) || !is_string($_POST['formtoken']) || !hash_equals($expected,$_POST['formtoken']) || ($_POST['action']??'')!=='check_updates') {
        http_response_code(400);$updateError='The update-check form expired. Refresh the page and try again.';
    } else {
        $includePrereleases=!empty($_POST['include_prereleases']);
        try {
            $updateChecker->check();
            header('Location: out.Info.php?prereleases='.($includePrereleases?'1':'0'),true,303);exit;
        } catch (RuntimeException $e) {$updateError=$e->getMessage();}
        catch (Throwable $e) {$updateError='The update check could not be completed. Check the server log and try again.';error_log('LetoDMS update check failed.');}
    }
}
header('Cache-Control: private, no-store');
$updateReport=$updateChecker->report($updateChecker->cached(),$includePrereleases);

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$view = (new UI($GLOBALS['theme'] ?? 'bootstrap'))->factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user, 'version'=>$v,'updates'=>$updateReport,'updateerror'=>$updateError));
if($view) {
	$view->show();
	exit;
}

?>
