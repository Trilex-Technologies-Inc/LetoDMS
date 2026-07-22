<?php
require_once('class.Bootstrap.php');
class LetoDMS_View_ModuleManager extends LetoDMS_Bootstrap_Style {
	public function show() {
		$this->htmlStartPage('Modules'); $this->globalNavigation(); $this->contentStart(); $this->contentHeading('Module management');
		if (!empty($this->params['message'])) echo '<div class="alert alert-info">'.htmlspecialchars($this->params['message']).'</div>';
?>
<p class="muted">Install modules to create their required data, then enable or disable them without losing data.</p>
<table class="table table-striped table-bordered">
<thead><tr><th>Module</th><th>Version</th><th>Status</th><th style="width:300px">Actions</th></tr></thead><tbody>
<?php foreach ($this->params['modules'] as $name => $module) { ?>
<tr><td><strong><?php echo htmlspecialchars($module['title']); ?></strong><br><span class="muted"><?php echo htmlspecialchars($module['description']); ?></span></td>
<td><?php echo htmlspecialchars($module['version']); ?></td><td><?php echo !$module['installed'] ? '<span class="label">Not installed</span>' : ($module['enabled'] ? '<span class="label label-success">Enabled</span>' : '<span class="label label-warning">Disabled</span>'); ?></td>
<td>
<?php if ($module['installed'] && $module['enabled'] && !empty($module['url'])) { ?><a class="btn" href="<?php echo htmlspecialchars($module['url']); ?>">Open</a><?php } ?>
<?php if (!$module['installed']) { $this->button($name, 'install', 'Install', 'btn-primary'); } else { $this->button($name, $module['enabled'] ? 'disable' : 'enable', $module['enabled'] ? 'Disable' : 'Enable', $module['enabled'] ? '' : 'btn-success'); $this->button($name, 'uninstall', 'Uninstall', 'btn-danger', 'Uninstalling permanently deletes this module\'s data. Continue?'); } ?>
</td></tr><?php } ?>
<?php if (!$this->params['modules']) { ?><tr><td colspan="4">No valid packages were found in the modules directory.</td></tr><?php } ?>
</tbody></table>
<?php $this->contentEnd(); $this->htmlEndPage(); }
	private function button($name, $action, $label, $class='', $confirm='') { ?>
<form action="../op/op.ModuleManager.php" method="post" style="display:inline"<?php echo $confirm ? ' onsubmit="return confirm(\''.htmlspecialchars($confirm, ENT_QUOTES).'\')"' : ''; ?>>
<?php echo createHiddenFieldWithKey('modulemanager'); ?><input type="hidden" name="module" value="<?php echo htmlspecialchars($name); ?>"><input type="hidden" name="action" value="<?php echo $action; ?>"><button class="btn <?php echo $class; ?>" type="submit"><?php echo $label; ?></button></form>
<?php }
}

