<?php
/**
 * Implementation of Search result view
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
 * Class which outputs the html page for Search result view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Search extends LetoDMS_Bootstrap_Style {

	function markQuery($str, $tag = "b") {
		$querywords = preg_split("/ /", $this->query);
		
		foreach ($querywords as $queryword)
			$str = str_ireplace("($queryword)", "<" . $tag . ">\\1</" . $tag . ">", $str);
		
		return $str;
	}

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$this->query = $this->params['query'];
		$entries = $this->params['searchhits'];
		$totalpages = $this->params['totalpages'];
		$pageNumber = $this->params['pagenumber'];
		$searchTime = $this->params['searchtime'];
		$urlparams = $this->params['urlparams'];
		$searchin = $this->params['searchin'];
		$cachedir = $this->params['cachedir'];

		$this->htmlStartPage(getMLText("search_results"));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation(getMLText("search_results"), "");

		$foldercount = $doccount = 0;
		if($entries) {
			foreach ($entries as $entry) {
				if(get_class($entry) == 'LetoDMS_Core_Document') {
					$doccount++;
				} elseif(get_class($entry) == 'LetoDMS_Core_Folder') {
					$foldercount++;
				}
			}
			print "<div class=\"alert\">".getMLText("search_report", array("doccount" => $doccount, "foldercount" => $foldercount, 'searchtime'=>$searchTime))."</div>";
			$this->pageList($pageNumber, $totalpages, "../op/op.Search.php", $urlparams);
			$this->contentContainerStart();

			print "<table class=\"table\">";
			print "<thead>\n<tr>\n";
			print "<th></th>\n";
			print "<th>".getMLText("name")."</th>\n";
			print "<th>".getMLText("attributes")."</th>\n";
			print "<th>".getMLText("owner")."</th>\n";
			print "<th>".getMLText("status")."</th>\n";
			print "<th>".getMLText("version")."</th>\n";
//			print "<th>".getMLText("comment")."</th>\n";
			//print "<th>".getMLText("reviewers")."</th>\n";
			//print "<th>".getMLText("approvers")."</th>\n";
			print "</tr>\n</thead>\n<tbody>\n";

			$previewer = new LetoDMS_Preview_Previewer($cachedir, 40);
			foreach ($entries as $entry) {
				if(get_class($entry) == 'LetoDMS_Core_Document') {
					$document = $entry;
					$lc = $document->getLatestContent();
					$previewer->createPreview($lc);

					if (in_array(3, $searchin))
						$comment = $this->markQuery(htmlspecialchars($document->getComment()));
					else
						$comment = htmlspecialchars($document->getComment());
					if (strlen($comment) > 150) $comment = substr($comment, 0, 147) . "...";
					print "<tr>";
					//print "<td><img src=\"../out/images/file.gif\" class=\"mimeicon\"></td>";
					if (in_array(2, $searchin)) {
						$docName = $this->markQuery(htmlspecialchars($document->getName()), "i");
					} else {
						$docName = htmlspecialchars($document->getName());
					}
					print "<td><a class=\"standardText\" href=\"../out/out.ViewDocument.php?documentid=".$document->getID()."\">";
					if($previewer->hasPreview($lc)) {
						print "<img class=\"mimeicon\" width=\"40\"src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$lc->getVersion()."&width=40\" title=\"".htmlspecialchars($lc->getMimeType())."\">";
					} else {
						print "<img class=\"mimeicon\" src=\"".$this->getMimeIcon($lc->getFileType())."\" title=\"".htmlspecialchars($lc->getMimeType())."\">";
					}
					print "</a></td>";
					print "<td><a class=\"standardText\" href=\"../out/out.ViewDocument.php?documentid=".$document->getID()."\">/";
					$folder = $document->getFolder();
					$path = $folder->getPath();
					for ($i = 1; $i  < count($path); $i++) {
						print htmlspecialchars($path[$i]->getName())."/";
					}
					print $docName;
					print "</a>";
					if($comment) {
						print "<br /><span style=\"font-size: 85%;\">".htmlspecialchars($comment)."</span>";
					}
					print "</td>";

					$attributes = $lc->getAttributes();
					print "<td>";
					print "<ul class=\"unstyled\">\n";
					$attributes = $lc->getAttributes();
					if($attributes) {
						foreach($attributes as $attribute) {
							$attrdef = $attribute->getAttributeDefinition();
							print "<li>".htmlspecialchars($attrdef->getName()).": ".htmlspecialchars($attribute->getValue())."</li>\n";
						}
					}
					print "</ul>\n";
					print "</td>";

					$owner = $document->getOwner();
					print "<td>".htmlspecialchars($owner->getFullName())."</td>";
					$display_status=$lc->getStatus();
					print "<td>".getOverallStatusText($display_status["status"]). "</td>";

					print "<td>".$lc->getVersion()."</td>";
//						print "<td>".$comment."</td>";
					print "</tr>\n";
				} elseif(get_class($entry) == 'LetoDMS_Core_Folder') {
					$folder = $entry;
					if (in_array(2, $searchin)) {
						$folderName = $this->markQuery(htmlspecialchars($folder->getName()), "i");
					} else {
						$folderName = htmlspecialchars($folder->getName());
					}
					print "<td><a class=\"standardText\" href=\"../out/out.ViewFolder.php?folderid=".$folder->getID()."\"><img src=\"".$this->imgpath."folder.png\" width=\"24\" height=\"24\" border=0></a></td>";
					print "<td><a class=\"standardText\" href=\"../out/out.ViewFolder.php?folderid=".$folder->getID()."\">";
					$path = $folder->getPath();
					print "/";
					for ($i = 1; $i  < count($path)-1; $i++) {
						print htmlspecialchars($path[$i]->getName())."/";
					}
					print $folderName;
					print "</a></td>";
					print "<td></td>";
					
					$owner = $folder->getOwner();
					print "<td>".htmlspecialchars($owner->getFullName())."</td>";
					print "<td></td>";
					print "<td></td>";
					if (in_array(3, $searchin)) $comment = $this->markQuery(htmlspecialchars($folder->getComment()));
					else $comment = htmlspecialchars($folder->getComment());
					if (strlen($comment) > 50) $comment = substr($comment, 0, 47) . "...";
					print "<td>".$comment."</td>";
					print "</tr>\n";
				}
			}
			print "</tbody></table>\n";
			$this->contentContainerEnd();
			$this->pageList($pageNumber, $totalpages, "../op/op.Search.php", $_GET);
		} else {
			$numResults = $doccount + $foldercount;
			if ($numResults == 0) {
				print "<div class=\"alert alert-error\">".getMLText("search_no_results")."</div>";
			}
		}
		$this->htmlEndPage();
	} /* }}} */
}
?>

