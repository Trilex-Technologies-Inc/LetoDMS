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
include("../inc/inc.LogInit.php");
include("../inc/inc.Utils.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassSession.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassEmail.php");

function _printMessage($heading, $message) {

	UI::htmlStartPage($heading, "password");
	UI::globalBanner();
	UI::pageNavigation($heading);
	UI::contentContainer($message."<p><a href=\"../out/out.Login.php\">" . getMLText("login") . "</a></p>\n");
			UI::htmlEndPage();
	return;
}

if (isset($_POST["email"])) {
	$email = $_POST["email"];
}
if (isset($_POST["login"])) {
	$login = $_POST["login"];
}

if (empty($email) || empty($login)) {
	_printMessage(getMLText("email_error_title"),	"<p>".getMLText("email_not_given")."</p>\n".
		"<p><a href='".$settings->_httpRoot."out/out.PasswordForgotten.php'>".getMLText("back")."</a></p>\n");
	exit;
}

$user = $dms->getUserByLogin($login, $email);
if($user) {
	$hash = $dms->createPasswordRequest($user);
	$emailobj = new LetoDMS_Email();
	$subject = "###SITENAME###: ".getMLText("password_forgotten_email_subject");
	$message = str_replace('###HASH###', $hash, getMLText("password_forgotten_email_body"));
	
	$emailobj->sendPassword('', $user, $subject, $message);
}

_printMessage(getMLText("password_forgotten_title"), "<p>".getMLText("password_forgotten_send_hash")."</p>");
exit;
?>
