<?php
require_once __DIR__.'/../../../views/bootstrap/class.Bootstrap.php';
class LetoDMS_Module_View_TaskManager extends LetoDMS_Bootstrap_Style {
	public function show() {
		$this->htmlStartPage('Task Manager'); $this->globalNavigation(); $this->contentStart(); $this->contentHeading('Task Manager');
		if (!empty($this->params['message'])) echo '<div class="alert alert-info">'.htmlspecialchars($this->params['message']).'</div>';
?>
<div class="row-fluid">
<div class="span4 well">
<h3>Add a task</h3>
<form action="../op/op.Module.php?module=taskmanager" method="post">
<?php echo createHiddenFieldWithKey('taskmanager'); ?><input type="hidden" name="action" value="add">
<label for="task-title">Title</label><input class="input-block-level" id="task-title" name="title" maxlength="255" required>
<label for="task-description">Description</label><textarea class="input-block-level" id="task-description" name="description" rows="4"></textarea>
<label for="task-due">Due date</label><input class="input-block-level" id="task-due" name="due_date" type="date">
<button class="btn btn-primary" type="submit">Add task</button>
</form></div>
<div class="span8"><h3>My tasks</h3>
<?php if (!$this->params['tasks']) { ?><div class="alert">You have no tasks yet.</div><?php } ?>
<?php foreach ($this->params['tasks'] as $task) { ?>
<div class="well" style="opacity:<?php echo $task['completed'] ? '0.65' : '1'; ?>">
<div class="pull-right">
<?php $this->action($task['id'], 'toggle', $task['completed'] ? 'Reopen' : 'Complete', $task['completed'] ? '' : 'btn-success'); ?>
<?php $this->action($task['id'], 'remove', 'Delete', 'btn-danger', true); ?>
</div>
<h4 style="<?php echo $task['completed'] ? 'text-decoration:line-through' : ''; ?>"><?php echo htmlspecialchars($task['title']); ?></h4>
<?php if ($task['description'] !== '') { ?><p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p><?php } ?>
<?php if ($task['due_date'] !== '') { ?><span class="label <?php echo !$task['completed'] && $task['due_date'] < date('Y-m-d') ? 'label-important' : 'label-info'; ?>">Due <?php echo htmlspecialchars($task['due_date']); ?></span><?php } ?>
</div><?php } ?></div></div>
<?php $this->contentEnd(); $this->htmlEndPage(); }
	private function action($id, $action, $label, $class, $confirm=false) { ?>
<form action="../op/op.Module.php?module=taskmanager" method="post" style="display:inline"<?php echo $confirm ? ' onsubmit="return confirm(\'Delete this task?\')"' : ''; ?>><?php echo createHiddenFieldWithKey('taskmanager'); ?><input type="hidden" name="action" value="<?php echo $action; ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><button class="btn btn-small <?php echo $class; ?>" type="submit"><?php echo $label; ?></button></form>
<?php }
}

