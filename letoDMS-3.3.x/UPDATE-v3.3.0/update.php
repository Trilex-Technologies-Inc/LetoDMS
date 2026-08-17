<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//
//    This program is free software; you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation; either version 2 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program; if not, write to the Free Software
//    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

include("../inc/inc.Version.php");
include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if (!$user->isAdmin()) {
	print "<b>ERROR: You must be administrator to execute the update</b>";
	die;
}

function check($doupdate=0) {
	global $db, $settings;

	$arr = array();
	$arr['tblDocuments'] = array('key'=>'id', 'fields'=>array('name', 'comment', 'keywords'));
	$arr['tblDocumentFiles'] = array('key'=>'id', 'fields'=>array('name', 'comment', 'mimeType'));
	$arr['tblFolders'] = array('key'=>'id', 'fields'=>array('name', 'comment'));
	$arr['tblUsers'] = array('key'=>'id', 'fields'=>array('fullName', 'comment'));
	$arr['tblGroups'] = array('key'=>'id', 'fields'=>array('name', 'comment'));
	$arr['tblKeywords'] = array('key'=>'id', 'fields'=>array('keywords'));
	$arr['tblKeywordCategories'] = array('key'=>'id', 'fields'=>array('name'));
	$arr['tblCategory'] = array('key'=>'id', 'fields'=>array('name'));
	$arr['tblEvents'] = array('key'=>'id', 'fields'=>array('name', 'comment'));
	$arr['tblDocumentApproveLog'] = array('key'=>'approveLogId', 'fields'=>array('comment'));
	$arr['tblDocumentStatusLog'] = array('key'=>'statusLogId', 'fields'=>array('comment'));
	$arr['tblDocumentReviewLog'] = array('key'=>'reviewLogId', 'fields'=>array('comment'));
	$arr['tblDocumentContent'] = array('keys'=>array('document', 'version'), 'fields'=>array('comment', 'mimeType'));

	$allupdates = array();
	echo "<table>\n";
	echo "<tr><th>Table</th><th>Field</th><th>Old Value</th><th>New Value</th><th>Update statement</th></tr>\n";
	foreach($arr as $tblname => $schema) {
		if(isset($schema['key']))
			$queryStr = "SELECT ".$schema['key'].", `".implode('`,`', $schema['fields'])."` FROM ".$tblname;
		elseif(isset($schema['keys']))
			$queryStr = "SELECT ".implode(',', $schema['keys']).", `".implode('`,`', $schema['fields'])."` FROM ".$tblname;
		$recs = $db->getResultArray($queryStr);
		foreach($recs as $rec) {
			foreach($schema['fields'] as $field) {
				if($rec[$field] !== mydmsDecodeString($rec[$field])) {
					$updateSql = "UPDATE ".$tblname." SET `".$field."`=".$db->qstr(mydmsDecodeString($rec[$field]))." where ";
					if(isset($schema['key']))
						$updateSql .= $schema['key']."=".$rec[$schema['key']];
					elseif(isset($schema['keys'])) {
						$where = array();
						foreach($schema['keys'] as $key) {
							$where[] = $key."=".$rec[$key];
						}
						$updateSql .= implode(' AND ', $where);
					}
					$allupdates[] = $updateSql;
					echo "<tr><td>".$tblname."</td><td>".$field."</td><td>".htmlspecialchars($rec[$field])."</td><td>".htmlspecialchars(mydmsDecodeString($rec[$field]))."</td><td><pre>".htmlspecialchars($updateSql)."</pre></td></tr>\n";
					if($doupdate) {
						$res = $db->getResult($updateSql);
						if(!$res) {
							$errormsg = 'Could not execute update statement';
							echo "<tr><td colspan=\"5\"><span style=\"color: red;\">".$errormsg."</span></td></tr>\n";
						} else {
							$errormsg = 'Object updated';
							echo "<tr><td colspan=\"5\"><span style=\"color: green;\">".$errormsg."</span></td></tr>\n";
						}
					}
				}
			}
		}
	}
	echo "</table>\n";
	echo "<b>Summary of all updates</b><br />\n";
	echo "<pre>".implode("<br />", $allupdates)."</pre>";
	return true;
}

UI::htmlStartPage('Database update');
UI::globalNavigation();
UI::pageNavigation('Database update');
UI::contentContainerStart();

if(isset($_GET['doupdate']) && $_GET['doupdate'] == 1)
	$doupdate = 1;
else
	$doupdate = 0;

if (!check($doupdate)) {
	die;
}

if(!$doupdate) {
	print "<p>If the above update statements look ok, either execute them in your prefered mysql client or click on the link below.</p>";
	print "<a href=\"?doupdate=1\">Execute update</a><br />\n";
}

UI::contentContainerEnd();
UI::htmlEndPage();
?>
