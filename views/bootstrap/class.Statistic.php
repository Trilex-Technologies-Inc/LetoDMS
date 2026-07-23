<?php
/**
 * Implementation of Statistic view
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
 * Class which outputs the html page for Statistic view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Statistic extends LetoDMS_Bootstrap_Style {
		var $dms;
		var $folder_count;
		var $document_count;
		var $file_count;
		var $storage_size;

	function getAccessColor($mode) { /* {{{ */
		if ($mode == M_NONE)
			return "gray";
		else if ($mode == M_READ)
			return "green";
		else if ($mode == M_READWRITE)
			return "blue";
		else // if ($mode == M_ALL)
			return "red";
	} /* }}} */

	function printFolder($folder) { /* {{{ */
		$this->folder_count++;
		$folder_size=0;
		$doc_count=0;

		$color = $folder->inheritsAccess() ? "black" : $this->getAccessColor($folder->getDefaultAccess());

		print "<li class=\"folderClass\">";
		print "<a style=\"color: $color\" href=\"out.ViewFolder.php?folderid=".$folder->getID()."\">".htmlspecialchars($folder->getName()) ."</a>";

		$owner = $folder->getOwner();
		$color = $this->getAccessColor(M_ALL);
		print " [<span style=\"color: $color\">".htmlspecialchars($owner->getFullName())."</span>] ";

		if (! $folder->inheritsAccess())
			$this->printAccessList($folder);

		$subFolders = $folder->getSubFolders();
		$documents = $folder->getDocuments();

		print "<ul>";

		foreach ($subFolders as $sub) $folder_size += $this->printFolder($sub);
		foreach ($documents as $document){
			$doc_count++;
			$folder_size += $this->printDocument($document);
		}

		print "</ul>";

		print "<small>".(new LetoDMS_Core_File())->format_filesize($folder_size).", ".$doc_count." ".getMLText("documents")."</small>\n";

		print "</li>";

		return $folder_size;
	} /* }}} */

	function printDocument($document) { /* {{{ */
		$this->document_count++;

		$local_file_count=0;
		$folder_size=0;

		if (file_exists($this->dms->contentDir.$document->getDir())) {
			$handle = opendir($this->dms->contentDir.$document->getDir());
			while ($entry = readdir($handle) ) {
				if (is_dir($this->dms->contentDir.$document->getDir().$entry)) continue;
				else{
					$local_file_count++;
					$folder_size += filesize($this->dms->contentDir.$document->getDir().$entry);
				}

			}
			closedir($handle);
		}
		$this->storage_size += $folder_size;

		$color = $document->inheritsAccess() ? "black" : $this->getAccessColor($document->getDefaultAccess());
		print "<li class=\"documentClass\">";
		print "<a style=\"color: $color\" href=\"out.ViewDocument.php?documentid=".$document->getID()."\">".htmlspecialchars($document->getName())."</a>";

		$owner = $document->getOwner();
		$color = $this->getAccessColor(M_ALL);
		print " [<span style=\"color: $color\">".htmlspecialchars($owner->getFullName())."</span>] ";

		if (! $document->inheritsAccess()) $this->printAccessList($document);

		print "<small>".(new LetoDMS_Core_File())->format_filesize($folder_size).", ".$local_file_count." ".getMLText("files")."</small>\n";

		print "</li>";

		$this->file_count += $local_file_count;
		return $folder_size;
	} /* }}} */

	function printAccessList($obj) { /* {{{ */
		$accessList = $obj->getAccessList();
		if (count($accessList["users"]) == 0 && count($accessList["groups"]) == 0)
			return;

		print " <span>(";

		for ($i = 0; $i < count($accessList["groups"]); $i++)
		{
			$group = $accessList["groups"][$i]->getGroup();
			$color = $this->getAccessColor($accessList["groups"][$i]->getMode());
			print "<span style=\"color: $color\">".htmlspecialchars($group->getName())."</span>";
			if ($i+1 < count($accessList["groups"]) || count($accessList["users"]) > 0)
				print ", ";
		}
		for ($i = 0; $i < count($accessList["users"]); $i++)
		{
			$user = $accessList["users"][$i]->getUser();
			$color = $this->getAccessColor($accessList["users"][$i]->getMode());
			print "<span style=\"color: $color\">".htmlspecialchars($user->getFullName())."</span>";
			if ($i+1 < count($accessList["users"]))
				print ", ";
		}
		print ")</span>";
	} /* }}} */

	function show() { /* {{{ */
		$this->dms = $this->params['dms'];
		$user = $this->params['user'];
		$rootfolder = $this->params['rootfolder'];

		$this->htmlStartPage(getMLText("folders_and_documents_statistic"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		$this->folder_count=0;
		$this->document_count=0;
		$this->file_count=0;
		$this->storage_size=0;

		ob_start();
		echo '<ul class="statistic-tree-root">';
		$this->printFolder($rootfolder);
		echo '</ul>';
		$treeHtml = ob_get_clean();
		$storageSize = (new LetoDMS_Core_File())->format_filesize($this->storage_size);

		$this->contentHeading(getMLText("folders_and_documents_statistic"));
		echo '<p class="statistics-intro">A live overview of folders, documents, files, storage usage, ownership, and access rules.</p>';
		echo '<div class="statistics-summary">';
		$this->statisticCard('&#128193;', getMLText("folders"), $this->folder_count, 'blue');
		$this->statisticCard('&#128196;', getMLText("documents"), $this->document_count, 'green');
		$this->statisticCard('&#128206;', getMLText("files"), $this->file_count, 'orange');
		$this->statisticCard('&#128190;', getMLText("storage_size"), $storageSize, 'purple');
		echo '</div>';

		echo '<div class="statistics-layout">';
		echo '<section class="well statistics-tree-panel">';
		echo '<div class="statistics-panel-heading"><div><span class="statistics-eyebrow">Repository</span><h2>Folder structure</h2></div></div>';
		echo '<div class="statistics-tree">' . $treeHtml . '</div>';
		echo '</section>';

		echo '<aside class="well statistics-legend-panel">';
		echo '<span class="statistics-eyebrow">' . getMLText("legend") . '</span>';
		echo '<h2>Access levels</h2>';
		echo '<ul class="statistics-access-list">';
		$this->accessLegendItem('inherit', getMLText("access_inheritance"));
		$this->accessLegendItem('all', getMLText("access_mode_all"));
		$this->accessLegendItem('write', getMLText("access_mode_readwrite"));
		$this->accessLegendItem('read', getMLText("access_mode_read"));
		$this->accessLegendItem('none', getMLText("access_mode_none"));
		echo '</ul>';
		echo '<p class="statistics-note">Colors in the hierarchy indicate the effective access configuration.</p>';
		echo '</aside>';
		echo '</div>';

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */

	private function statisticCard($icon, $label, $value, $color) { /* {{{ */
		echo '<div class="statistics-card statistics-card-' . $color . '">';
		echo '<div><span class="statistics-card-label">' . htmlspecialchars($label) . '</span>';
		echo '<strong>' . htmlspecialchars((string)$value) . '</strong></div>';
		echo '<span class="statistics-card-icon" aria-hidden="true">' . $icon . '</span>';
		echo '</div>';
	} /* }}} */

	private function accessLegendItem($class, $label) { /* {{{ */
		echo '<li><span class="statistics-access-dot access-' . $class . '"></span>' . htmlspecialchars($label) . '</li>';
	} /* }}} */
}
?>
