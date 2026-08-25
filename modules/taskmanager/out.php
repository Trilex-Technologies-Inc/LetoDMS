<?php
require_once __DIR__.'/inc/TaskManager.php';
require_once __DIR__.'/views/TaskManager.php';
$tasks = new LetoDMS_TaskManager($db);
$view = new LetoDMS_Module_View_TaskManager(array(
	'dms'=>$dms,
	'user'=>$user,
	'tasks'=>$tasks->getTasks($user->getID()),
	'message'=>isset($_GET['message']) ? $_GET['message'] : ''
), 'bootstrap');
UI::configureView($view)->show();
exit;

