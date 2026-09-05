<?php
/** Lightweight module discovery and lifecycle management for LetoDMS. */
class LetoDMS_ModuleManager {
	private $db;
	private $modulesDir;
	private $driver;
	private $lastError = '';

	public function __construct($db, $modulesDir, $driver = 'mysql') {
		$this->db = $db;
		$this->modulesDir = rtrim($modulesDir, '/');
		$this->driver = strtolower((string) $driver);
	}

	public function getLastError() {
		return $this->lastError;
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

	/** Validate an uploaded ZIP package and extract it into the modules directory. */
	public function upload($file) {
		$this->lastError = '';
		if (!is_array($file) || empty($file['tmp_name'])) {
			$this->lastError = 'No module package was uploaded.';
			return false;
		}
		if (!empty($file['error'])) {
			$messages = array(
				UPLOAD_ERR_INI_SIZE => 'The package exceeds the server upload limit.',
				UPLOAD_ERR_FORM_SIZE => 'The package exceeds the allowed upload size.',
				UPLOAD_ERR_PARTIAL => 'The package was only partially uploaded.',
				UPLOAD_ERR_NO_FILE => 'No module package was uploaded.'
			);
			$this->lastError = isset($messages[$file['error']]) ? $messages[$file['error']] : 'The upload failed with error code '.(int) $file['error'].'.';
			return false;
		}
		if (!is_uploaded_file($file['tmp_name']) || $file['size'] <= 0) {
			$this->lastError = 'The uploaded package is invalid.';
			return false;
		}
		if (!class_exists('ZipArchive')) {
			$this->lastError = 'The PHP zip extension is required to upload modules.';
			return false;
		}
		if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
			$this->lastError = 'Module packages must be ZIP archives.';
			return false;
		}
		$zip = new ZipArchive();
		if ($zip->open($file['tmp_name']) !== true) {
			$this->lastError = 'The uploaded file is not a readable ZIP archive.';
			return false;
		}
		/* Reject path traversal and absolute paths before extracting anything. */
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->getNameIndex($i);
			if (strpos($entry, '..') !== false || preg_match('#^([a-z]:)?[/\\\\]#i', $entry)) {
				$zip->close();
				$this->lastError = 'The archive contains unsafe paths.';
				return false;
			}
		}
		$tempDir = sys_get_temp_dir().'/'.uniqid('letodms-module-');
		if (!$zip->extractTo($tempDir)) {
			$zip->close();
			$this->lastError = 'The archive could not be extracted.';
			return false;
		}
		$zip->close();

		/* The manifest may sit at the archive root or inside a single top-level directory. */
		$source = $tempDir;
		if (!is_file($source.'/manifest.php')) {
			$candidates = array();
			foreach (scandir($source) as $entry) {
				if ($entry === '.' || $entry === '..') continue;
				if (is_dir($source.'/'.$entry)) $candidates[] = $entry;
			}
			if (count($candidates) === 1 && is_file($source.'/'.$candidates[0].'/manifest.php'))
				$source .= '/'.$candidates[0];
		}
		$manifest = is_file($source.'/manifest.php') ? include $source.'/manifest.php' : null;
		if (!is_array($manifest) || empty($manifest['name']) || !preg_match('/^[a-z][a-z0-9_-]*$/', $manifest['name'])) {
			$this->removeTree($tempDir);
			$this->lastError = 'The package does not contain a valid manifest.php.';
			return false;
		}
		$target = $this->modulesDir.'/'.$manifest['name'];
		if (file_exists($target)) {
			$this->removeTree($tempDir);
			$this->lastError = 'A module named "'.$manifest['name'].'" already exists.';
			return false;
		}
		if (!@rename($source, $target)) {
			/* rename() fails across filesystems, so fall back to a recursive copy. */
			if (!$this->copyTree($source, $target)) {
				$this->removeTree($tempDir);
				$this->lastError = 'The module could not be moved into the modules directory.';
				return false;
			}
		}
		$this->removeTree($tempDir);
		return true;
	}

	private function copyTree($source, $target) {
		if (!mkdir($target)) return false;
		foreach (scandir($source) as $entry) {
			if ($entry === '.' || $entry === '..') continue;
			if (is_dir($source.'/'.$entry)) {
				if (!$this->copyTree($source.'/'.$entry, $target.'/'.$entry)) return false;
			} elseif (!copy($source.'/'.$entry, $target.'/'.$entry)) {
				return false;
			}
		}
		return true;
	}

	private function removeTree($dir) {
		if (!is_dir($dir)) return;
		foreach (scandir($dir) as $entry) {
			if ($entry === '.' || $entry === '..') continue;
			if (is_dir($dir.'/'.$entry)) $this->removeTree($dir.'/'.$entry);
			else @unlink($dir.'/'.$entry);
		}
		@rmdir($dir);
	}
}
