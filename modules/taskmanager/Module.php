<?php
class LetoDMS_TaskManager_Module {
	private function sqlFile($driver, $action) {
		if ($action === 'uninstall') return __DIR__.'/sql/uninstall.sql';
		if (strpos($driver, 'sqlite') !== false) $type = 'sqlite';
		elseif (strpos($driver, 'pgsql') !== false || strpos($driver, 'postgres') !== false) $type = 'pgsql';
		else $type = 'mysql';
		return __DIR__.'/sql/'.$type.'/install.sql';
	}

	public function install($db, $driver) {
		return (bool) $db->getResult(trim(file_get_contents($this->sqlFile($driver, 'install'))));
	}

	public function uninstall($db, $driver) {
		return (bool) $db->getResult(trim(file_get_contents($this->sqlFile($driver, 'uninstall'))));
	}
}
