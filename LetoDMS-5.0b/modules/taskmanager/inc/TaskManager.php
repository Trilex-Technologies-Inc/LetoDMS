<?php
class LetoDMS_TaskManager {
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function getTasks($userId) {
		return $this->db->getResultArray("SELECT id, title, description, due_date, completed, created_at FROM tblModuleTasks WHERE user_id = ".(int)$userId." ORDER BY completed ASC, due_date ASC, created_at DESC");
	}
	public function add($userId, $title, $description, $dueDate) {
		$sql = "INSERT INTO tblModuleTasks (user_id, title, description, due_date, completed, created_at) VALUES (".(int)$userId.", ".$this->db->qstr($title).", ".$this->db->qstr($description).", ".$this->db->qstr($dueDate).", 0, ".time().")";
		return (bool) $this->db->getResult($sql);
	}
	public function toggle($userId, $id) {
		$sql = "UPDATE tblModuleTasks SET completed = CASE WHEN completed = 0 THEN 1 ELSE 0 END WHERE id = ".(int)$id." AND user_id = ".(int)$userId;
		return (bool) $this->db->getResult($sql);
	}
	public function remove($userId, $id) {
		return (bool) $this->db->getResult("DELETE FROM tblModuleTasks WHERE id = ".(int)$id." AND user_id = ".(int)$userId);
	}
}

