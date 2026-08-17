<?php
/** Lightweight module discovery and lifecycle management for LetoDMS. */
class LetoDMS_ModuleManager {
	private $db;
	private $modulesDir;
	private $driver;

	public function __construct($db, $modulesDir, $driver = 'mysql') {
		$this->db = $db;
		$this->modulesDir = rtrim($modulesDir, '/');
		$this->driver = strtolower((string) $driver);
	}

	public function initialize() {
		if (strpos($this->driver, 'sqlite') !== false) {
			$sql = "CREATE TABLE IF NOT EXISTS tblModules (name VARCHAR(80) PRIMARY KEY, version VARCHAR(30) NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, installed_at INTEGER NOT NULL)";
		} else {
			$sql = "CREATE TABLE IF NOT EXISTS tblModules (name VARCHAR(80) NOT NULL PRIMARY KEY, version VARCHAR(30) NOT NULL, enabled SMALLINT NOT NULL DEFAULT 0, installed_at INTEGER NOT NULL)";
		}
		try {
			return (bool) $this->db->getResult($sql);
		} catch (Exception $e) {
			return false;
		}
	}

	private function resultArray($sql) {
		try {
			$result = $this->db->getResultArray($sql);
			return is_array($result) ? $result : array();
		} catch (Exception $e) {
			/* A missing registry simply means that no modules are installed yet. */
			return array();
		}
	}

	public function discover() {
		$modules = array();
		if (!is_dir($this->modulesDir)) return $modules;
		foreach (scandir($this->modulesDir) as $entry) {
			if (!preg_match('/^[a-z][a-z0-9_-]*$/', $entry)) continue;
			$file = $this->modulesDir.'/'.$entry.'/manifest.php';
			if (!is_file($file)) continue;
			$manifest = include $file;
			if (!is_array($manifest) || empty($manifest['name']) || $manifest['name'] !== $entry) continue;
			$manifest['path'] = dirname($file);
			$modules[$entry] = $manifest;
		}
		ksort($modules);
		return $modules;
	}

	public function all() {
		$installed = array();
		$rows = $this->resultArray("SELECT name, version, enabled, installed_at FROM tblModules");
		if (is_array($rows)) foreach ($rows as $row) $installed[$row['name']] = $row;
		$modules = $this->discover();
		foreach ($modules as $name => &$module) {
			$module['installed'] = isset($installed[$name]);
			$module['enabled'] = isset($installed[$name]) && (bool) $installed[$name]['enabled'];
			$module['installed_version'] = isset($installed[$name]) ? $installed[$name]['version'] : null;
		}
		return $modules;
	}

	public function get($name) {
		$modules = $this->all();
		return isset($modules[$name]) ? $modules[$name] : null;
	}

	public function isEnabled($name) {
		$module = $this->get($name);
		return $module && $module['installed'] && $module['enabled'];
	}

	private function lifecycle($module) {
		if (empty($module['class']) || empty($module['bootstrap'])) return null;
		$file = $module['path'].'/'.$module['bootstrap'];
		if (!is_file($file)) return null;
		require_once $file;
		return class_exists($module['class']) ? new $module['class']() : null;
	}

	public function install($name) {
		if (!$this->initialize()) return false;
		$module = $this->get($name);
		if (!$module || $module['installed']) return false;
		$lifecycle = $this->lifecycle($module);
		if (!$lifecycle || !$lifecycle->install($this->db, $this->driver)) return false;
		$sql = "INSERT INTO tblModules (name, version, enabled, installed_at) VALUES (".$this->db->qstr($name).", ".$this->db->qstr($module['version']).", 1, ".time().")";
		return (bool) $this->db->getResult($sql);
	}

	public function uninstall($name) {
		$module = $this->get($name);
		if (!$module || !$module['installed']) return false;
		$lifecycle = $this->lifecycle($module);
		if (!$lifecycle || !$lifecycle->uninstall($this->db, $this->driver)) return false;
		return (bool) $this->db->getResult("DELETE FROM tblModules WHERE name = ".$this->db->qstr($name));
	}

	public function setEnabled($name, $enabled) {
		$module = $this->get($name);
		if (!$module || !$module['installed']) return false;
		return (bool) $this->db->getResult("UPDATE tblModules SET enabled = ".($enabled ? 1 : 0)." WHERE name = ".$this->db->qstr($name));
	}
}
