<?php
//    MyDMS. Document Management System
//    Copyright (C) 2012 Uwe Steinmann
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
include("../inc/inc.LogInit.php");
include("../inc/inc.DBInit.php");

if (!isset($_COOKIE["mydms_session"])) {
	exit;
}

require_once("../inc/inc.Utils.php");
require_once("../inc/inc.ClassSession.php");
include("../inc/inc.ClassPasswordStrength.php");
include("../inc/inc.ClassPasswordHistoryManager.php");

/* Load session */
$dms_session = $_COOKIE["mydms_session"];
$session = new LetoDMS_Session($db);
if(!$resArr = $session->load($dms_session)) {
	echo json_encode(array('error'=>1));
	exit;
}

/* Load user data */
$user = $dms->getUser($resArr["userID"]);
if (!is_object($user)) {
	echo json_encode(array('error'=>1));
	exit;
}
$dms->setUser($user);
include $settings->_rootDir . "languages/" . $resArr["language"] . "/lang.inc";

$command = $_GET["command"];

switch($command) {
	case 'checkpwstrength':
		$ps = new Password_Strength();
		$ps->set_password($_GET["pwd"]);
		if($settings->_passwordStrengthAlgorithm == 'simple')
			$ps->simple_calculate();
		else
			$ps->calculate();
		$score = $ps->get_score();
		if($settings->_passwordStrength) {
			if($score > $settings->_passwordStrength) {
				echo json_encode(array('error'=>0, 'strength'=>$score, 'ok'=>1));
			} else {
				echo json_encode(array('error'=>0, 'strength'=>$score, 'ok'=>0));
			}
		} else {
			echo json_encode(array('error'=>0, 'strength'=>$score));
		}
		break;

}
?>
