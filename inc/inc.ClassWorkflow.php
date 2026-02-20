<?php
/**
 * Implementation of the workflow object in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to represent an workflow in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Workflow { /* {{{ */
	/**
	 * @var integer id of workflow
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var name of the workflow
	 *
	 * @access protected
	 */
	var $_name;

	/**
	 * @var initial state of the workflow
	 *
	 * @access protected
	 */
	var $_initstate;

	/**
	 * @var name of the workflow state
	 *
	 * @access protected
	 */
	var $_transitions;

	/**
	 * @var object reference to the dms instance this attribute belongs to
	 *
	 * @access protected
	 */
	var $_dms;

	function LetoDMS_Core_Workflow($id, $name, $initstate) { /* {{{ */
		$this->_id = $id;
		$this->_name = $name;
		$this->_initstate = $initstate;
		$this->_transitions = null;
		$this->_dms = null;
	} /* }}} */

	function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	function getID() { return $this->_id; }

	function getName() { return $this->_name; }

	function setName($newName) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflows SET name = ".$db->qstr($newName)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	function getInitState() { return $this->_initstate; }

	function setInitState($state) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflows SET initstate = ".$state->getID()." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_initstate = $state;
		return true;
	} /* }}} */

	function getTransitions() { /* {{{ */
		$db = $this->_dms->getDB();

		if($this->_transitions)
			return $this->_transitions;

		$queryStr = "SELECT * FROM tblWorkflowTransitions WHERE workflow=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$wkftransitions = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$wkftransition = new LetoDMS_Core_Workflow_Transition($resArr[$i]["id"], $this, $this->_dms->getWorkflowState($resArr[$i]["state"]), $this->_dms->getWorkflowAction($resArr[$i]["action"]), $this->_dms->getWorkflowState($resArr[$i]["nextstate"]), $resArr[$i]["maxtime"]);
			$wkftransition->setDMS($this->_dms);
			$wkftransitions[$resArr[$i]["id"]] = $wkftransition;
		}

		$this->_transitions = $wkftransitions;

		return $this->_transitions;
	} /* }}} */

	function getStates() { /* {{{ */
		$db = $this->_dms->getDB();

		if(!$this->_transitions)
			$this->getTransitions();

		$states = array();
		foreach($this->_transitions as $transition) {
			if(!isset($states[$transition->getState()->getID()]))
				$states[$transition->getState()->getID()] = $transition->getState();
			if(!isset($states[$transition->getNextState()->getID()]))
				$states[$transition->getNextState()->getID()] = $transition->getNextState();
		}

		return $states;
	} /* }}} */

	/**
	 * Get the transition by its id
	 *
	 * @param integer $id id of transition
	 * @param object transition
	 */
	function getTransition($id) { /* {{{ */
		$db = $this->_dms->getDB();

		if(!$this->_transitions)
			$this->getTransitions();

		if($this->_transitions[$id])
			return $this->_transitions[$id];

		return false;
	} /* }}} */

	/**
	 * Get the transitions that can be triggered while being in the given state
	 *
	 * @param object $state current workflow state
	 * @param array list of transitions
	 */
	function getNextTransitions($state) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM tblWorkflowTransitions WHERE workflow=".$this->_id." AND state=".$state->getID();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$wkftransitions = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$wkftransition = new LetoDMS_Core_Workflow_Transition($resArr[$i]["id"], $this, $this->_dms->getWorkflowState($resArr[$i]["state"]), $this->_dms->getWorkflowAction($resArr[$i]["action"]), $this->_dms->getWorkflowState($resArr[$i]["nextstate"]), $resArr[$i]["maxtime"]);
			$wkftransition->setDMS($this->_dms);
			$wkftransitions[$i] = $wkftransition;
		}

		return $wkftransitions;
	} /* }}} */

	/**
	 * Get the transitions that lead to the given state
	 *
	 * @param object $state current workflow state
	 * @param array list of transitions
	 */
	function getPreviousTransitions($state) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM tblWorkflowTransitions WHERE workflow=".$this->_id." AND nextstate=".$state->getID();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$wkftransitions = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$wkftransition = new LetoDMS_Core_Workflow_Transition($resArr[$i]["id"], $this, $this->_dms->getWorkflowState($resArr[$i]["state"]), $this->_dms->getWorkflowAction($resArr[$i]["action"]), $this->_dms->getWorkflowState($resArr[$i]["nextstate"]), $resArr[$i]["maxtime"]);
			$wkftransition->setDMS($this->_dms);
			$wkftransitions[$i] = $wkftransition;
		}

		return $wkftransitions;
	} /* }}} */

	/**
	 * Get all transitions from one state into another state
	 *
	 * @param object $state state to start from
	 * @param object $nextstate state after transition
	 * @param array list of transitions
	 */
	function getTransitionsByStates($state, $nextstate) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM tblWorkflowTransitions WHERE workflow=".$this->_id." AND state=".$state->getID()." AND nextstate=".$nextstate->getID();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$wkftransitions = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$wkftransition = new LetoDMS_Core_Workflow_Transition($resArr[$i]["id"], $this, $this->_dms->getWorkflowState($resArr[$i]["state"]), $this->_dms->getWorkflowAction($resArr[$i]["action"]), $this->_dms->getWorkflowState($resArr[$i]["nextstate"]), $resArr[$i]["maxtime"]);
			$wkftransition->setDMS($this->_dms);
			$wkftransitions[$i] = $wkftransition;
		}

		return $wkftransitions;
	} /* }}} */

	/**
	 * Remove a transition from a workflow
	 * Deprecated! User LetoDMS_Core_Workflow_Transition::remove() instead.
	 *
	 * @param object $transition
	 * @return boolean true if no error occured, otherwise false
	 */
	function removeTransition($transition) { /* {{{ */
		return $transition->remove();
	} /* }}} */

	/**
	 * Add new transition to workflow
	 *
	 * @param object $state 
	 * @param object $action 
	 * @param object $nextstate 
	 * @param array $users 
	 * @param array $groups 
	 * @return object instance of new transition
	 */
	function addTransition($state, $action, $nextstate, $users, $groups) { /* {{{ */
		$db = $this->_dms->getDB();
		
		$db->startTransaction();
		$queryStr = "INSERT INTO tblWorkflowTransitions (workflow, state, action, nextstate) VALUES (".$this->_id.", ".$state->getID().", ".$action->getID().", ".$nextstate->getID().")";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$transition = $this->getTransition($db->getInsertID());

		foreach($users as $user) {
			$queryStr = "INSERT INTO tblWorkflowTransitionUsers (transition, userid) VALUES (".$transition->getID().", ".$user->getID().")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		foreach($groups as $group) {
			$queryStr = "INSERT INTO tblWorkflowTransitionGroups (transition, groupid, minusers) VALUES (".$transition->getID().", ".$group->getID().", 1)";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		$db->commitTransaction();
		return $transition;
	} /* }}} */

	/**
	 * Check if workflow is currently used by any document
	 *
	 * @return boolean true if workflow is used, otherwise false
	 */
	function isUsed() { /* {{{ */
		$db = $this->_dms->getDB();
		
		$queryStr = "SELECT * FROM tblWorkflowDocumentContent WHERE workflow=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_array($resArr) && count($resArr) == 0)
			return false;
		return true;
	} /* }}} */

	/**
	 * Remove the workflow and all its transitions
	 * Do not remove actions and states of the workflow
	 *
	 * @return boolean true on success or false in case of an error
	 *         false is also returned if the workflow is currently in use
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		if($this->isUsed())
			return false;

		$db->startTransaction();

		$queryStr = "DELETE FROM tblWorkflowTransitions WHERE workflow = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM tblWorkflowMandatoryWorkflow WHERE workflow = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		// Delete workflow itself
		$queryStr = "DELETE FROM tblWorkflows WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();

		return true;
	} /* }}} */

} /* }}} */

/**
 * Class to represent a workflow state in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Workflow_State { /* {{{ */
	/**
	 * @var integer id of workflow state
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var name of the workflow state
	 *
	 * @access protected
	 */
	var $_name;

	/**
	 * @var maximum of seconds allowed in this state
	 *
	 * @access protected
	 */
	var $_maxtime;

	/**
	 * @var maximum of seconds allowed in this state
	 *
	 * @access protected
	 */
	var $_precondfunc;

	/**
	 * @var matching documentstatus when this state is reached
	 *
	 * @access protected
	 */
	var $_documentstatus;

	/**
	 * @var object reference to the dms instance this attribute belongs to
	 *
	 * @access protected
	 */
	var $_dms;

	function LetoDMS_Core_Workflow_State($id, $name, $maxtime, $precondfunc, $documentstatus) {
		$this->_id = $id;
		$this->_name = $name;
		$this->_maxtime = $maxtime;
		$this->_precondfunc = $precondfunc;
		$this->_documentstatus = $documentstatus;
		$this->_dms = null;
	}

	function setDMS($dms) {
		$this->_dms = $dms;
	}

	function getID() { return $this->_id; }

	function getName() { return $this->_name; }

	function setName($newName) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowStates SET name = ".$db->qstr($newName)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	function getMaxTime() { return $this->_maxtime; }

	function setMaxTime($maxtime) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowStates SET maxtime = ".intval($maxtime)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_maxtime = $maxtime;
		return true;
	} /* }}} */

	function getPreCondFunc() { return $this->_precondfunc; }

	function setPreCondFunc($precondfunc) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowStates SET precondfunc = ".$db->qstr($precondfunc)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_maxtime = $maxtime;
		return true;
	} /* }}} */

	/**
	 * Get the document status which is set when this state is reached
	 *
	 * The document status uses the define states S_REJECTED and S_RELEASED
	 * Only those two states will update the document status
	 *
	 * @return integer document status
	 */
	function getDocumentStatus() { return $this->_documentstatus; }

	function setDocumentStatus($docstatus) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowStates SET documentstatus = ".intval($docstatus)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_documentstatus = $docstatus;
		return true;
	} /* }}} */

	/**
	 * Check if workflow state is currently used by any workflow transition
	 *
	 * @return boolean true if workflow is used, otherwise false
	 */
	function isUsed() { /* {{{ */
		$db = $this->_dms->getDB();
		
		$queryStr = "SELECT * FROM tblWorkflowTransitions WHERE state=".$this->_id. " OR nextstate=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_array($resArr) && count($resArr) == 0)
			return false;
		return true;
	} /* }}} */

	/**
	 * Remove the workflow state
	 *
	 * @return boolean true on success or false in case of an error
	 *         false is also returned if the workflow state is currently in use
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		if($this->isUsed())
			return false;

		$db->startTransaction();

		// Delete workflow state itself
		$queryStr = "DELETE FROM tblWorkflowStates WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();

		return true;
	} /* }}} */

} /* }}} */

/**
 * Class to represent a workflow action in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Workflow_Action { /* {{{ */
	/**
	 * @var integer id of workflow action
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var name of the workflow action
	 *
	 * @access protected
	 */
	var $_name;

	/**
	 * @var object reference to the dms instance this attribute belongs to
	 *
	 * @access protected
	 */
	var $_dms;

	function LetoDMS_Core_Workflow_Action($id, $name) {
		$this->_id = $id;
		$this->_name = $name;
		$this->_dms = null;
	}

	function setDMS($dms) {
		$this->_dms = $dms;
	}

	function getID() { return $this->_id; }

	function getName() { return $this->_name; }

	function setName($newName) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowActions SET name = ".$db->qstr($newName)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	/**
	 * Check if workflow action is currently used by any workflow transition
	 *
	 * @return boolean true if workflow action is used, otherwise false
	 */
	function isUsed() { /* {{{ */
		$db = $this->_dms->getDB();
		
		$queryStr = "SELECT * FROM tblWorkflowTransitions WHERE action=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_array($resArr) && count($resArr) == 0)
			return false;
		return true;
	} /* }}} */

	/**
	 * Remove the workflow action
	 *
	 * @return boolean true on success or false in case of an error
	 *         false is also returned if the workflow action is currently in use
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		if($this->isUsed())
			return false;

		$db->startTransaction();

		// Delete workflow state itself
		$queryStr = "DELETE FROM tblWorkflowActions WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();

		return true;
	} /* }}} */

} /* }}} */

/**
 * Class to represent a workflow transition in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Workflow_Transition { /* {{{ */
	/**
	 * @var integer id of workflow transition
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var workflow this transition belongs to
	 *
	 * @access protected
	 */
	var $_workflow;

	/**
	 * @var state of the workflow transition
	 *
	 * @access protected
	 */
	var $_state;

	/**
	 * @var next state of the workflow transition
	 *
	 * @access protected
	 */
	var $_nextstate;

	/**
	 * @var action of the workflow transition
	 *
	 * @access protected
	 */
	var $_action;

	/**
	 * @var maximum of seconds allowed until this transition must be triggered
	 *
	 * @access protected
	 */
	var $_maxtime;

	/**
	 * @var list of users allowed to trigger this transaction
	 *
	 * @access protected
	 */
	var $_users;

	/**
	 * @var list of groups allowed to trigger this transaction
	 *
	 * @access protected
	 */
	var $_groups;

	/**
	 * @var object reference to the dms instance this attribute belongs to
	 *
	 * @access protected
	 */
	var $_dms;

	function LetoDMS_Core_Workflow_Transition($id, $workflow, $state, $action, $nextstate, $maxtime) {
		$this->_id = $id;
		$this->_workflow = $workflow;
		$this->_state = $state;
		$this->_action = $action;
		$this->_nextstate = $nextstate;
		$this->_maxtime = $maxtime;
		$this->_dms = null;
	}

	function setDMS($dms) {
		$this->_dms = $dms;
	}

	function getID() { return $this->_id; }

	function getWorkflow() { return $this->_workflow; }

	function setWorkflow($newWorkflow) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowTransitions SET workflow = ".$newWorkflow->getID()." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_workflow = $newWorkflow;
		return true;
	} /* }}} */

	function getState() { return $this->_state; }

	function setState($newState) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowTransitions SET state = ".$newState->getID()." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_state = $newState;
		return true;
	} /* }}} */

	function getNextState() { return $this->_nextstate; }

	function setNextState($newNextState) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowTransitions SET nextstate = ".$newNextState->getID()." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_nextstate = $newNextState;
		return true;
	} /* }}} */

	function getAction() { return $this->_action; }

	function setAction($newAction) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowTransitions SET action = ".$newAction->getID()." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_action = $newAction;
		return true;
	} /* }}} */

	function getMaxTime() { return $this->_maxtime; }

	function setMaxTime($maxtime) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE tblWorkflowTransitions SET maxtime = ".intval($maxtime)." WHERE id = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_maxtime = $maxtime;
		return true;
	} /* }}} */

	/**
	 * Get all users allowed to trigger this transition
	 *
	 * @return array list of users
	 */
	function getUsers() { /* {{{ */
		$db = $this->_dms->getDB();

		if($this->_users)
			return $this->_users;

		$queryStr = "SELECT * FROM tblWorkflowTransitionUsers WHERE transition=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$users = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$user = new LetoDMS_Core_Workflow_Transition_User($resArr[$i]['id'], $this, $this->_dms->getUser($resArr[$i]['userid']));
			$user->setDMS($this->_dms);
			$users[$i] = $user;
		}

		$this->_users = $users;

		return $this->_users;
	} /* }}} */

	/**
	 * Get all users allowed to trigger this transition
	 *
	 * @return array list of users
	 */
	function getGroups() { /* {{{ */
		$db = $this->_dms->getDB();

		if($this->_groups)
			return $this->_groups;

		$queryStr = "SELECT * FROM tblWorkflowTransitionGroups WHERE transition=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$groups = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$group = new LetoDMS_Core_Workflow_Transition_Group($resArr[$i]['id'], $this, $this->_dms->getGroup($resArr[$i]['groupid']), $resArr[$i]['minusers']);
			$group->setDMS($this->_dms);
			$groups[$i] = $group;
		}

		$this->_groups = $groups;

		return $this->_groups;
	} /* }}} */

	/**
	 * Remove the workflow transition
	 *
	 * @return boolean true on success or false in case of an error
	 *         false is also returned if the workflow action is currently in use
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		$db->startTransaction();

		// Delete workflow transition itself
		$queryStr = "DELETE FROM tblWorkflowTransitions WHERE id = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();

		return true;
	} /* }}} */

} /* }}} */

/**
 * Class to represent a user allowed to trigger a workflow transition
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Workflow_Transition_User { /* {{{ */
	/**
	 * @var integer id of workflow transition
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var object reference to the transtion this user belongs to
	 *
	 * @access protected
	 */
	var $_transition;

	/**
	 * @var object user allowed to trigger a transition
	 *
	 * @access protected
	 */
	var $_user;

	/**
	 * @var object reference to the dms instance this attribute belongs to
	 _Core_Workflow_Transition_Group
	 * @access protected
	 */
	var $_dms;

	function LetoDMS_Core_Workflow_Transition_User($id, $transition, $user) {
		$this->_id = $id;
		$this->_transition = $transition;
		$this->_user = $user;
	}

	function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	/**
	 * Get the transtion itself
	 *
	 * @return object group
	 */
	function getTransition() { /* {{{ */
		return $this->_transition;
	} /* }}} */

	/**
	 * Get the user who is allowed to trigger the transition
	 *
	 * @return object user
	 */
	function getUser() { /* {{{ */
		return $this->_user;
	} /* }}} */
} /* }}} */

/**
 * Class to represent a group allowed to trigger a workflow transition
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Workflow_Transition_Group { /* {{{ */
	/**
	 * @var integer id of workflow transition
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var object reference to the transtion this group belongs to
	 *
	 * @access protected
	 */
	var $_transition;
	
	/**
	 * @var integer number of users how must trigger the transition
	 *
	 * @access protected
	 */
	var $_numOfUsers;

	/**
	 * @var object group of users
	 *
	 * @access protected
	 */
	var $_group;

	/**
	 * @var object reference to the dms instance this attribute belongs to
	 *
	 * @access protected
	 */
	var $_dms;

	function LetoDMS_Core_Workflow_Transition_Group($id, $transition, $group, $numOfUsers) { /* {{{ */
		$this->_id = $id;
		$this->_transition = $transition;
		$this->_group = $group;
		$this->_numOfUsers = $numOfUsers;
	} /* }}} */

	function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	/**
	 * Get the transtion itself
	 *
	 * @return object group
	 */
	function getTransition() { /* {{{ */
		return $this->_transition;
	} /* }}} */

	/**
	 * Get the group whose user are allowed to trigger the transition
	 *
	 * @return object group
	 */
	function getGroup() { /* {{{ */
		return $this->_group;
	} /* }}} */

	/**
	 * Returns the number of users of this group needed to trigger the transition
	 *
	 * @return integer number of users
	 */
	function getNumOfUsers() { /* {{{ */
		return $this->_numOfUsers;
	} /* }}} */

} /* }}} */

/**
 * Class to represent a group allowed to trigger a workflow transition
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Workflow_Log { /* {{{ */
	/**
	 * @var integer id of workflow log
	 *
	 * @access protected
	 */
	var $_id;

	/**
	 * @var object document this log entry belongs to
	 *
	 * @access protected
	 */
	var $_document;

	/**
	 * @var integer version of document this log entry belongs to
	 *
	 * @access protected
	 */
	var $_version;

	/**
	 * @var object workflow
	 *
	 * @access protected
	 */
	var $_workflow;

	/**
	 * @var object user initiating this log entry
	 *
	 * @access protected
	 */
	var $_user;

	/**
	 * @var object transition
	 *
	 * @access protected
	 */
	var $_transition;

	/**
	 * @var string date
	 *
	 * @access protected
	 */
	var $_date;

	/**
	 * @var string comment
	 *
	 * @access protected
	 */
	var $_comment;

	/**
	 * @var object reference to the dms instance this attribute belongs to
	 *
	 * @access protected
	 */
	var $_dms;

	function LetoDMS_Core_Workflow_Log($id, $document, $version, $workflow, $user, $transition, $date, $comment) {
		$this->_id = $id;
		$this->_document = $document;
		$this->_version = $version;
		$this->_workflow = $workflow;
		$this->_user = $user;
		$this->_transition = $transition;
		$this->_date = $date;
		$this->_comment = $comment;
		$this->_dms = null;
	}

	function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	function getTransition() { /* {{{ */
		return $this->_transition;
	} /* }}} */

	function getUser() { /* {{{ */
		return $this->_user;
	} /* }}} */

	function getComment() { /* {{{ */
		return $this->_comment;
	} /* }}} */

	function getDate() { /* {{{ */
		return $this->_date;
	} /* }}} */

} /* }}} */
?>
