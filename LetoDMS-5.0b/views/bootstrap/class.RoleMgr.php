<?php
require_once("class.Bootstrap.php");
class LetoDMS_View_RoleMgr extends LetoDMS_Bootstrap_Style {
	function show() {
		$roles=$this->params['roles']; $permissions=$this->params['permissions']; $users=$this->params['users']; $selected=$this->params['selected'];
		$selectedPermissions=array(); $selectedUsers=array();
		if ($selected) { foreach ((array)$selected->getPermissions() as $permission) $selectedPermissions[]=(int)$permission['id']; $selectedUsers=(array)$this->params['dms']->getRoleUserIDs($selected->getID()); }
		$this->htmlStartPage('Role and permission management'); $this->globalNavigation(); $this->contentStart(); $this->contentHeading('Role and permission management');
?>
<div class="row-fluid"><div class="span3 well"><h4>Roles</h4><ul class="nav nav-list">
<?php foreach ($roles as $role) { ?><li<?php if ($selected && $selected->getID()==$role->getID()) echo ' class="active"'; ?>><a href="?roleid=<?php echo $role->getID(); ?>"><?php echo htmlspecialchars($role->getName()); ?></a></li><?php } ?>
</ul><hr><form method="post" action="../op/op.RoleMgr.php"><?php echo createHiddenFieldWithKey('rolemgr'); ?><input type="hidden" name="action" value="add"><label>New role name</label><input class="input-block-level" required name="name" maxlength="80"><label>Description</label><textarea class="input-block-level" name="description"></textarea><button class="btn btn-primary" type="submit">Create role</button></form></div>
<div class="span9"><?php if ($selected) { ?><form method="post" action="../op/op.RoleMgr.php"><?php echo createHiddenFieldWithKey('rolemgr'); ?><input type="hidden" name="action" value="save"><input type="hidden" name="roleid" value="<?php echo $selected->getID(); ?>"><label>Role name</label><input class="input-block-level" required maxlength="80" name="name" value="<?php echo htmlspecialchars($selected->getName()); ?>"><label>Description</label><textarea class="input-block-level" name="description"><?php echo htmlspecialchars($selected->getDescription()); ?></textarea><h4>System permissions</h4>
<?php foreach ($permissions as $permission) { ?><label class="checkbox"><input type="checkbox" name="permissions[]" value="<?php echo (int)$permission['id']; ?>"<?php if (in_array((int)$permission['id'],$selectedPermissions)) echo ' checked'; ?>> <strong><?php echo htmlspecialchars($permission['name']); ?></strong> — <?php echo htmlspecialchars($permission['description']); ?></label><?php } ?>
<h4>Assigned users</h4><div class="well" style="max-height:240px;overflow:auto"><?php foreach ($users as $assignedUser) { ?><label class="checkbox"><input type="checkbox" name="users[]" value="<?php echo $assignedUser->getID(); ?>"<?php if (in_array((int)$assignedUser->getID(),$selectedUsers)) echo ' checked'; ?>> <?php echo htmlspecialchars($assignedUser->getFullName().' ('.$assignedUser->getLogin().')'); ?></label><?php } ?></div><button class="btn btn-primary" type="submit">Save role</button></form>
<form method="post" action="../op/op.RoleMgr.php" style="margin-top:15px" onsubmit="return confirm('Delete this role?');"><?php echo createHiddenFieldWithKey('rolemgr'); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="roleid" value="<?php echo $selected->getID(); ?>"><button class="btn btn-danger" type="submit">Delete role</button></form><?php } else { ?><p>Select a role or create a new one.</p><?php } ?></div></div>
<?php $this->contentEnd(); $this->htmlEndPage(); }
}
?>
