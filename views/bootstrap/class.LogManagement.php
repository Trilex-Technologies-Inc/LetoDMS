<?php
/**
 * Implementation of LogManagement view
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
 * Class which outputs the html page for LogManagement view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_LogManagement extends LetoDMS_Bootstrap_Style {

	function filelist($entries) { /* {{{ */
		$print_header = true;
		foreach ($entries as $entry){
			
			if ($print_header){
				print "<table class=\"table-condensed\">\n";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";
				print "<th>".getMLText("creation_date")."</th>\n";
				print "<th>".getMLText("file_size")."</th>\n";
				print "<th></th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
				$print_header=false;
			}
					
			print "<tr>\n";
			print "<td><a href=\"out.LogManagement.php?logname=".$entry."\">".$entry."</a></td>\n";
			print "\n";
			print "<td>".getLongReadableDate(filectime($this->contentdir.$entry))."</td>\n";
			print "<td>".LetoDMS_Core_File::format_filesize(filesize($this->contentdir.$entry))."</td>\n";
			print "<td>";
			
			print "<a href=\"out.RemoveLog.php?logname=".$entry."\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("rm_file")."</a>";
			print "&nbsp;";
			print "<a href=\"../op/op.Download.php?logname=".$entry."\" class=\"btn btn-mini\"><i class=\"icon-download\"></i> ".getMLText("download")."</a>";
			print "&nbsp;";
			print "<a data-target=\"#logViewer\" data-cache=\"false\" href=\"out.LogManagement.php?logname=".$entry."\" role=\"button\" class=\"btn btn-mini\" data-toggle=\"modal\"><i class=\"icon-eye-open\"></i> ".getMLText('view')." …</a>";
			print "</td>\n";	
			print "</tr>\n";
		}

		if ($print_header) printMLText("empty_notify_list");
		else print "</table>\n";
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$this->contentdir = $this->params['contentdir'];
		$logname = $this->params['logname'];

		if(!$logname) {
		$this->htmlStartPage(getMLText("backup_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		$this->contentHeading(getMLText("log_management"));

		$entries = array();
		$wentries = array();
		$handle = opendir($this->contentdir);
		if($handle) {
			while ($e = readdir($handle)){
				if (is_dir($this->contentdir.$e)) continue;
				if (strpos($e,".log")==FALSE) continue;
				if (strcmp($e,"current.log")==0) continue;
				if(substr($e, 0, 6) ==  'webdav') {
					$wentries[] = $e;
				} else {
					$entries[] = $e;
				}
			}
			closedir($handle);

			sort($entries);
			sort($wentries);
			$entries = array_reverse($entries);
			$wentries = array_reverse($wentries);
		}
?>
  <ul class="nav nav-tabs" id="logtab">
	  <li class="active"><a data-target="#regular" data-toggle="tab">web</a></li>
	  <li><a data-target="#webdav" data-toggle="tab">webdav</a></li>
	</ul>
	<div class="tab-content">
	  <div class="tab-pane active" id="regular">
<?php
		$this->contentContainerStart();
		$this->filelist($entries);
		$this->contentContainerEnd();
?>
		</div>
	  <div class="tab-pane" id="webdav">
<?php
		$this->contentContainerStart();
		$this->filelist($wentries);
		$this->contentContainerEnd();
?>
		</div>
	</div>
  <div class="modal hide" style="width: 900px; margin-left: -450px;" id="logViewer" tabindex="-1" role="dialog" aria-labelledby="docChooserLabel" aria-hidden="true">
    <div class="modal-body">
      <p>Please wait, until document tree is loaded …</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" data-dismiss="modal" aria-hidden="true">Close</button>
    </div>
  </div>
<?php
		$this->htmlEndPage();
		} elseif(file_exists($this->contentdir.$logname)){
//			$this->htmlStartPage(getMLText("backup_tools"));

//			$this->contentSubHeading(sanitizeString($logname));

			echo $logname."<pre>\n";
			readfile($this->contentdir.$logname);
			echo "</pre>\n";

//			echo "</body>\n</html>\n";
		}

	} /* }}} */
}
?>
