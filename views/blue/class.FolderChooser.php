<?php
/**
 * Implementation of FolderChooser view
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
require_once("class.BlueStyle.php");

/**
 * Class which outputs the html page for FolderChooser view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_FolderChooser extends LetoDMS_Blue_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$mode = $this->params['mode'];
		$exclude = $this->params['exclude'];
		$form = $this->params['form'];
		$rootfolderid = $this->params['rootfolderid'];

		$this->htmlStartPage(getMLText("choose_target_folder"));
		$this->globalBanner();
		$this->pageNavigation(getMLText("choose_target_folder"));
?>

<script language="JavaScript">

function toggleTree(id){
	
	obj = document.getElementById("tree" + id);
	
	if ( obj.style.display == "none" ) obj.style.display = "";
	else obj.style.display = "none";
	
}

function decodeString(s) {
	s = new String(s);
	s = s.replace(/&amp;/, "&");
	s = s.replace(/&#0037;/, "%"); // percent
	s = s.replace(/&quot;/, "\""); // double quote
	s = s.replace(/&#0047;&#0042;/, "/*"); // start of comment
	s = s.replace(/&#0042;&#0047;/, "*/"); // end of comment
	s = s.replace(/&lt;/, "<");
	s = s.replace(/&gt;/, ">");
	s = s.replace(/&#0061;/, "=");
	s = s.replace(/&#0041;/, ")");
	s = s.replace(/&#0040;/, "(");
	s = s.replace(/&#0039;/, "'");
	s = s.replace(/&#0043;/, "+");

	return s;
}

var targetName;
var targetID;

function folderSelected(id, name) {
//	targetName.value = decodeString(name);
	targetName.value = name;
	targetID.value = id;
	window.close();
	return true;
}
</script>


<?php
		$this->contentContainerStart();
		$this->printFoldersTree($mode, $exclude, $rootfolderid);
		$this->contentContainerEnd();
?>


<script language="JavaScript">
targetName = opener.document.<?php echo $form?>.targetname<?php print $form ?>;
targetID   = opener.document.<?php echo $form?>.targetid<?php print $form ?>;
</script>

<?php
		$this->htmlEndPage();
	} /* }}} */
}
?>
