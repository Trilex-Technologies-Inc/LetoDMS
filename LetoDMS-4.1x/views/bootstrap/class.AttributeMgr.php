<?php
/**
 * Implementation of AttributeMgr view
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
 * Class which outputs the html page for AttributeMgr view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AttributeMgr extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$attrdefs = $this->params['attrdefs'];

		$this->htmlStartPage(getMLText("admin_tools"));
?>

<script language="JavaScript">
obj = -1;
function showAttributeDefinitions(selectObj) {
	if (obj != -1)
		obj.style.display = "none";
	
	id = selectObj.options[selectObj.selectedIndex].value;
	if (id == -1)
		return;
	
	obj = document.getElementById("attrdefs" + id);
	obj.style.display = "";
}
</script>
<?php
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("attrdef_management"));
?>

<div class="row-fluid">
<div class="span4">
<div class="well">
<?php echo getMLText("selection")?>:
	<select onchange="showAttributeDefinitions(this)" id="selector" class="span9">
		<option value="-1"><?php echo getMLText("choose_attrdef")?>
		<option value="0"><?php echo getMLText("new_attrdef")?>
<?php
		$selected=0;
		$count=2;
		if($attrdefs) {
			foreach ($attrdefs as $attrdef) {
			
				if (isset($_GET["attrdefid"]) && $attrdef->getID()==$_GET["attrdefid"]) $selected=$count;				
				switch($attrdef->getObjType()) {
					case LetoDMS_Core_AttributeDefinition::objtype_all:
						$ot = getMLText("all");
						break;
					case LetoDMS_Core_AttributeDefinition::objtype_folder:
						$ot = getMLText("folder");
						break;
					case LetoDMS_Core_AttributeDefinition::objtype_document:
						$ot = getMLText("document");
						break;
					case LetoDMS_Core_AttributeDefinition::objtype_documentcontent:
						$ot = getMLText("version");
						break;
				}
				print "<option value=\"".$attrdef->getID()."\">" . htmlspecialchars($attrdef->getName() ." (".$ot.")");
				$count++;
			}
		}
?>
	</select>
</div>
</div>

<div class="span8">
<div class="well">
	<table class="table-condensed"><tr>
		<td id="attrdefs0" style="display : none;">
			<form action="../op/op.AttributeMgr.php" method="post">
  		<?php echo createHiddenFieldWithKey('addattrdef'); ?>
			<input type="hidden" name="action" value="addattrdef">
			<table class="table-condensed">
				<tr>
					<td><?php printMLText("attrdef_name");?>:</td><td><input type="text" name="name"></td>
				</tr>
				<tr>
					<td><?php printMLText("attrdef_objtype");?>:</td><td><select name="objtype"><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_all ?>">All</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_folder ?>">Folder</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_document ?>"><?php printMLText("document"); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_documentcontent ?>"><?php printMLText("version"); ?></option></select>
				</tr>
				<tr>
					<td><?php printMLText("attrdef_type");?>:</td><td><select name="type"><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_int ?>">Integer</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_float ?>">Float</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_string ?>">String</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_boolean ?>">Boolean</option></select></td>
				</tr>
				<tr>
					<td><?php printMLText("attrdef_multiple");?>:</td><td><input type="checkbox" value="1" name="multiple" /></td>
				</tr>
				<tr>
					<td><?php printMLText("attrdef_minvalues");?>:</td><td><input type="text" value="" name="minvalues" /></td>
				</tr>
				<tr>
					<td><?php printMLText("attrdef_maxvalues");?>:</td><td><input type="text" value="" name="maxvalues" /></td>
				</tr>
				<tr>
					<td><?php printMLText("attrdef_valueset");?>:</td><td><input type="text" value="" name="valueset" /></td>
				</tr>
				<tr>
					<td></td>
					<td><input type="submit" class="btn" value="<?php printMLText("new_attrdef"); ?>"></td>
				</tr>
			</table>
			</form>
		</td>
	
<?php	
	
			if($attrdefs) {
				foreach ($attrdefs as $attrdef) {
				
					print "<td id=\"attrdefs".$attrdef->getID()."\" style=\"display : none;\">";	
?>
				<table class="table-condensed">
					<tr>
						<td></td><td>
<?php
					if(!$attrdef->isUsed()) {
?>
							<form style="display: inline-block;" method="post" action="../op/op.AttributeMgr.php" >
							<?php echo createHiddenFieldWithKey('removeattrdef'); ?>
							<input type="hidden" name="attrdefid" value="<?php echo $attrdef->getID()?>">
							<input type="hidden" name="action" value="removeattrdef">
							<button type="submit" class="btn"><i class="icon-remove"></i> <?php echo getMLText("rm_attrdef")?></button>
							</form>
<?php
					} else {
?>
							<p><?php echo getMLText('attrdef_in_use') ?></p>
<?php
					}
?>
						</td>
					</tr>
					<form action="../op/op.AttributeMgr.php" method="post">
					<tr>
						<td>
								<?php echo createHiddenFieldWithKey('editattrdef'); ?>
								<input type="Hidden" name="action" value="editattrdef">
								<input type="Hidden" name="attrdefid" value="<?php echo $attrdef->getID()?>" />
								<?php printMLText("attrdef_name");?>:
						</td>
						<td>
							<input type="text" name="name" value="<?php echo htmlspecialchars($attrdef->getName()) ?>">
						</td>
					</tr>
					<tr>
						<td>
							<?php printMLText("attrdef_type");?>:
						</td>
						<td>
							<select name="type"><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_int ?>" <?php if($attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_int) echo "selected"; ?>>Integer</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_float ?>" <?php if($attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_float) echo "selected"; ?>>Float</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_string ?>" <?php if($attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_string) echo "selected"; ?>>String</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_boolean ?>" <?php if($attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_boolean) echo "selected"; ?>>Boolean</option></select>
						</td>
					</tr>
					<tr>
						<td>
							<?php printMLText("attrdef_objtype");?>:
						</td>
						<td>
							<select name="objtype"><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_all ?>">All</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_folder ?>" <?php if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_folder) echo "selected"; ?>>Folder</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_document ?>" <?php if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_document) echo "selected"; ?>>Document</option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_documentcontent ?>" <?php if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_documentcontent) echo "selected"; ?>>Document content</option></select>
						</td>
					</tr>
					<tr>
						<td>
							<?php printMLText("attrdef_multiple");?>:
						</td>
						<td>
							<input type="checkbox" value="1" name="multiple" <?php echo $attrdef->getMultipleValues() ? "checked" : "" ?>/>
						</td>
					</tr>
					<tr>
						<td>
							<?php printMLText("attrdef_minvalues");?>:
						</td>
						<td>
							<input type="text" value="<?php echo $attrdef->getMinValues() ?>" name="minvalues" />
						</td>
					</tr>
					<tr>
						<td>
							<?php printMLText("attrdef_maxvalues");?>:
						</td>
						<td>
							<input type="text" value="<?php echo $attrdef->getMaxValues() ?>" name="maxvalues" />
						</td>
					</tr>
					<tr>
						<td>
							<?php printMLText("attrdef_valueset");?>:
						</td>
						<td>
							<input type="text" value="<?php echo $attrdef->getValueSet() ?>" name="valueset" />
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<input type="submit" class="btn" value="<?php printMLText("save");?>">
						</td>
					</tr>
					</form>
					
				</table>
			</td>
<?php
				}
			}
?>
	</tr></table>
</div>
</div>
</div>
	
<script language="JavaScript">

sel = document.getElementById("selector");
sel.selectedIndex=<?php print $selected ?>;
showAttributeDefinitions(sel);

</script>

<?php
		$this->contentContainerEnd();
		$this->htmlEndPage();

	} /* }}} */
}
?>
