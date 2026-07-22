<?php
require_once('class.Bootstrap.php');
class LetoDMS_View_ModuleManager extends LetoDMS_Bootstrap_Style {
	public function show() {
		$this->htmlStartPage('Modules'); $this->globalNavigation(); $this->contentStart(); $this->contentHeading('Module management');
		if (!empty($this->params['message'])) echo '<div class="alert alert-info">'.htmlspecialchars($this->params['message']).'</div>';
?>
<p class="muted">Install modules to create their required data, then enable or disable them without losing data. <a href="#module-development-guide">Read the module development guide</a>.</p>
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
<?php $this->developmentGuide(); $this->contentEnd(); $this->htmlEndPage(); }

	private function code($source) {
		echo '<pre><code>'.htmlspecialchars($source, ENT_QUOTES, 'UTF-8').'</code></pre>';
	}

	private function developmentGuide() { ?>
<section id="module-development-guide" class="well" style="margin-top:30px">
<h2>Module Development Guide</h2>
<p>A module is a self-contained package under <code>modules/</code>. It owns its PHP classes, controllers, views, and database SQL. Core provides generic dispatchers, authentication, enabled-state checks, navigation, and lifecycle actions.</p>

<h3>1. Create the package structure</h3>
<p>Use a lowercase identifier containing letters, numbers, underscores, or hyphens. The directory name must match the manifest name.</p>
<?php $this->code("modules/example/\n├── manifest.php\n├── Module.php\n├── out.php\n├── op.php\n├── inc/\n│   └── Example.php\n├── views/\n│   └── Example.php\n└── sql/\n    ├── mysql/install.sql\n    ├── sqlite/install.sql\n    ├── pgsql/install.sql\n    └── uninstall.sql"); ?>

<h3>2. Define <code>manifest.php</code></h3>
<?php $this->code("<?php\nreturn array(\n    'name' => 'example',\n    'title' => 'Example Module',\n    'description' => 'A short description for administrators.',\n    'version' => '1.0.0',\n    'class' => 'LetoDMS_Example_Module',\n    'bootstrap' => 'Module.php',\n    'url' => '../out/out.Module.php?module=example',\n    'out_controller' => 'out.php',\n    'op_controller' => 'op.php',\n    'navigation' => true\n);"); ?>
<p><code>navigation</code> is optional. When true, an installed and enabled module appears in the sidebar. Controller and URL fields are optional for modules that only provide background functionality.</p>

<h3>3. Add lifecycle SQL</h3>
<p>Put database-specific installation SQL in the corresponding driver directory. Keep all table names module-specific to avoid collisions.</p>
<?php $this->code("-- sql/mysql/install.sql\nCREATE TABLE IF NOT EXISTS tblModuleExample (\n  id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,\n  user_id INTEGER NOT NULL,\n  title VARCHAR(255) NOT NULL\n)\n\n-- sql/uninstall.sql\nDROP TABLE IF EXISTS tblModuleExample"); ?>
<div class="alert alert-warning"><strong>Important:</strong> uninstall SQL permanently deletes module data. Disabling a module does not run this SQL and preserves its data.</div>

<h3>4. Implement <code>Module.php</code></h3>
<p>The lifecycle class reads the correct SQL and returns <code>true</code> on success. The class name must match the manifest.</p>
<?php $this->code("<?php\nclass LetoDMS_Example_Module {\n    private function installFile(\$driver) {\n        if (strpos(\$driver, 'sqlite') !== false) \$type = 'sqlite';\n        elseif (strpos(\$driver, 'pgsql') !== false) \$type = 'pgsql';\n        else \$type = 'mysql';\n        return __DIR__.'/sql/'.\$type.'/install.sql';\n    }\n\n    public function install(\$db, \$driver) {\n        \$sql = trim(file_get_contents(\$this->installFile(\$driver)));\n        return (bool) \$db->getResult(\$sql);\n    }\n\n    public function uninstall(\$db, \$driver) {\n        \$sql = trim(file_get_contents(__DIR__.'/sql/uninstall.sql'));\n        return (bool) \$db->getResult(\$sql);\n    }\n}"); ?>

<h3>5. Create the module service</h3>
<p>Keep queries and business logic in <code>inc/Example.php</code>. Use <code>$db-&gt;qstr()</code> for strings and cast numeric identifiers to integers.</p>
<?php $this->code("<?php\nclass LetoDMS_Example {\n    private \$db;\n    public function __construct(\$db) { \$this->db = \$db; }\n\n    public function getItems(\$userId) {\n        return \$this->db->getResultArray(\n            'SELECT id, title FROM tblModuleExample WHERE user_id = '.(int) \$userId\n        );\n    }\n}"); ?>

<h3>6. Create the output controller</h3>
<p>The generic dispatcher has already authenticated the request and verified that the module is installed and enabled. The controller can use the normal <code>$db</code>, <code>$dms</code>, <code>$user</code>, and <code>$theme</code> variables.</p>
<?php $this->code("<?php\nrequire_once __DIR__.'/inc/Example.php';\nrequire_once __DIR__.'/views/Example.php';\n\n\$service = new LetoDMS_Example(\$db);\n\$view = new LetoDMS_Module_View_Example(array(\n    'dms' => \$dms,\n    'user' => \$user,\n    'items' => \$service->getItems(\$user->getID())\n), 'bootstrap');\nUI::configureView(\$view)->show();\nexit;"); ?>

<h3>7. Create operations securely</h3>
<p>All changes must use POST and a LetoDMS form key. Validate input, scope records to the current user where appropriate, and redirect after processing.</p>
<?php $this->code("<!-- In the module view -->\n<form action=\"../op/op.Module.php?module=example\" method=\"post\">\n  <?php echo createHiddenFieldWithKey('example'); ?>\n  <input type=\"hidden\" name=\"action\" value=\"add\">\n  <button type=\"submit\">Add</button>\n</form>\n\n<?php\n// In op.php\nif (!checkFormKey('example'))\n    (new UI(\$GLOBALS['theme'] ?? 'bootstrap'))->exitError(\n        'Example Module', getMLText('invalid_request_token')\n    );\n// Validate POST values and call the service here.\nheader('Location: ../out/out.Module.php?module=example');\nexit;"); ?>

<h3>8. Build the view</h3>
<p>Store the view inside the module and extend the Bootstrap base view. Escape user-provided output with <code>htmlspecialchars()</code>.</p>
<?php $this->code("<?php\nrequire_once __DIR__.'/../../../views/bootstrap/class.Bootstrap.php';\nclass LetoDMS_Module_View_Example extends LetoDMS_Bootstrap_Style {\n    public function show() {\n        \$this->htmlStartPage('Example Module');\n        \$this->globalNavigation();\n        \$this->contentStart();\n        \$this->contentHeading('Example Module');\n        // Render escaped module data here.\n        \$this->contentEnd();\n        \$this->htmlEndPage();\n    }\n}"); ?>

<h3>9. Install and test</h3>
<ol>
<li>Reload this page. The discovered module should show as <strong>Not installed</strong>.</li>
<li>Click <strong>Install</strong> and verify its tables were created.</li>
<li>Open the module and test every operation with different users.</li>
<li>Click <strong>Disable</strong>; its navigation link and routes must become unavailable while data remains.</li>
<li>Enable it again and verify the existing data returns.</li>
<li>Only on disposable test data, click <strong>Uninstall</strong> and verify its owned tables are removed.</li>
</ol>
<p>For a complete implementation, inspect <code>modules/taskmanager/</code>.</p>
</section>
<?php }
	private function button($name, $action, $label, $class='', $confirm='') { ?>
<form action="../op/op.ModuleManager.php" method="post" style="display:inline"<?php echo $confirm ? ' onsubmit="return confirm(\''.htmlspecialchars($confirm, ENT_QUOTES).'\')"' : ''; ?>>
<?php echo createHiddenFieldWithKey('modulemanager'); ?><input type="hidden" name="module" value="<?php echo htmlspecialchars($name); ?>"><input type="hidden" name="action" value="<?php echo $action; ?>"><button class="btn <?php echo $class; ?>" type="submit"><?php echo $label; ?></button></form>
<?php }
}
