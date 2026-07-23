<?php
require_once __DIR__.'/inc/TaskManager.php';
if (!checkFormKey('taskmanager'))
	(new UI($GLOBALS['theme'] ?? 'bootstrap'))->exitError('Task Manager', getMLText('invalid_request_token'));
$service = new LetoDMS_TaskManager($db);
$action = isset($_POST['action']) ? $_POST['action'] : '';
$ok = false;
if ($action === 'add') {
	$title = trim(isset($_POST['title']) ? $_POST['title'] : '');
	$description = trim(isset($_POST['description']) ? $_POST['description'] : '');
	$due = trim(isset($_POST['due_date']) ? $_POST['due_date'] : '');
	if ($title !== '' && strlen($title) <= 255 && ($due === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)))
		$ok = $service->add($user->getID(), $title, $description, $due);
} elseif (($action === 'toggle' || $action === 'remove') && isset($_POST['id']) && is_numeric($_POST['id'])) {
	$ok = $action === 'toggle' ? $service->toggle($user->getID(), $_POST['id']) : $service->remove($user->getID(), $_POST['id']);
}
header('Location: ../out/out.Module.php?module=taskmanager&message='.urlencode($ok ? 'Task updated.' : 'Could not update the task.'));
exit;

