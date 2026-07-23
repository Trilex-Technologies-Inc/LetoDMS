<?php
/**
 * Implementation of AdminTools view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for AdminTools view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AdminTools extends LetoDMS_Bootstrap_Style {
	private function adminCard($title, $icon, $items) { /* {{{ */
		if (!$items) return;
?>
		<section class="span6 well admin-card">
			<header class="admin-card-header">
				<span class="admin-card-icon"><i class="<?php echo $icon; ?> icon-white"></i></span>
				<h2><?php echo htmlspecialchars($title); ?></h2>
			</header>
			<ul class="nav nav-list admin-link-list">
<?php foreach ($items as $item) { ?>
				<li><a href="<?php echo htmlspecialchars($item[0]); ?>"><i class="<?php echo $item[2]; ?>"></i><span><?php echo htmlspecialchars($item[1]); ?></span><i class="icon-chevron-right admin-link-arrow"></i></a></li>
<?php } ?>
			</ul>
		</section>
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$logfileenable = $this->params['logfileenable'];
		$enablefullsearch = $this->params['enablefullsearch'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->contentHeading(getMLText("admin_tools"));

		$userItems = array(
			array("../out/out.UsrMgr.php", getMLText("user_management"), "icon-user"),
			array("../out/out.GroupMgr.php", getMLText("group_management"), "icon-th-large")
		);
		$definitionItems = array(
			array("../out/out.DefaultKeywords.php", getMLText("global_default_keywords"), "icon-tags"),
			array("../out/out.Categories.php", getMLText("global_document_categories"), "icon-folder-open"),
			array("../out/out.AttributeMgr.php", getMLText("global_attributedefinitions"), "icon-list-alt")
		);
		if ($this->params['workflowmode'] != 'traditional') {
			$definitionItems[] = array("../out/out.WorkflowMgr.php", getMLText("global_workflows"), "icon-random");
			$definitionItems[] = array("../out/out.WorkflowStatesMgr.php", getMLText("global_workflow_states"), "icon-tasks");
			$definitionItems[] = array("../out/out.WorkflowActionsMgr.php", getMLText("global_workflow_actions"), "icon-play");
		}
		$maintenanceItems = array(
			array("../out/out.BackupTools.php", getMLText("backup_tools"), "icon-hdd")
		);
		if ($logfileenable)
			$maintenanceItems[] = array("../out/out.LogManagement.php", getMLText("log_management"), "icon-list");
		$systemItems = array(
			array("../out/out.Settings.php", getMLText("settings"), "icon-cog"),
			array("../out/out.ModuleManager.php", "Modules", "icon-th"),
			array("../out/out.Statistic.php", getMLText("folders_and_documents_statistic"), "icon-signal"),
			array("../out/out.ObjectCheck.php", getMLText("objectcheck"), "icon-check"),
			array("../out/out.Info.php", getMLText("version_info"), "icon-info-sign")
		);
?>
<div class="admin-dashboard">
	<div class="row-fluid admin-grid-row">
		<?php $this->adminCard(getMLText("user_group_management"), "icon-user", $userItems); ?>
		<?php $this->adminCard(getMLText("definitions"), "icon-book", $definitionItems); ?>
	</div>
	<div class="row-fluid admin-grid-row">
		<?php $this->adminCard(getMLText("backup_log_management"), "icon-hdd", $maintenanceItems); ?>
		<?php $this->adminCard(getMLText("misc"), "icon-wrench", $systemItems); ?>
	</div>
<?php
		if($enablefullsearch) {
			$searchItems = array(
				array("../out/out.Indexer.php", getMLText("update_fulltext_index"), "icon-refresh"),
				array("../out/out.CreateIndex.php", getMLText("create_fulltext_index"), "icon-plus-sign"),
				array("../out/out.IndexInfo.php", getMLText("fulltext_info"), "icon-info-sign")
			);
			echo '<div class="row-fluid admin-grid-row">';
			$this->adminCard(getMLText("fullsearch"), "icon-search", $searchItems);
			echo '</div>';
		}
?>
</div>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
