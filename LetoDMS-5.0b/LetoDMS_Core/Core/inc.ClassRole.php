<?php
class LetoDMS_Core_Role {
	protected $_id, $_name, $_description, $_dms;
	function __construct($id, $name, $description='') { $this->_id=(int)$id; $this->_name=$name; $this->_description=$description; }
	function setDMS($dms) { $this->_dms=$dms; }
	function getID() { return $this->_id; }
	function getName() { return $this->_name; }
	function getDescription() { return $this->_description; }
	function getPermissions() { return $this->_dms->getRolePermissions($this->_id); }
}
?>
