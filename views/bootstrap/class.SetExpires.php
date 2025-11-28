<?php
/**
 * Implementation of SetExpires view
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
 * Class which outputs the html page for SetExpires view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_SetExpires extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document");
		$this->contentHeading(getMLText("set_expiry"));
		$this->contentContainerStart();

		if($document->expires())
			$expdate = date('d-m-Y', $document->getExpires());
		else
			$expdate = '';
?>

<form action="../op/op.SetExpires.php" method="post">
<input type="hidden" name="documentid" value="<?php print $document->getID();?>">
	
<table class="table-condensed">
<tr>
	<td><?php printMLText("expires");?>:</td>
	<td>
    <span class="input-append date" id="expirationdate" data-date="<?php echo $expdate; ?>" data-date-format="dd-mm-yyyy">
      <input class="span4" size="16" name="expdate" type="text" value="<?php echo $expdate; ?>">
      <span class="add-on"><i class="icon-calendar"></i></span>
    </span>&nbsp;
    <label class="checkbox inline">
		  <input type="checkbox" name="expires" value="false"<?php if (!$document->expires()) print " checked";?>><?php printMLText("does_not_expire");?><br>
    </label>
	</td>
</tr>
<tr>
	<td></td>
	<td><input type="submit" class="btn" value="<?php printMLText("save");?>"></td>
</tr>
</table>
</form>
<?php
		$this->contentContainerEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
