<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//    Copyright (C) 2009-2012 Uwe Steinmann
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

include("../inc/inc.Settings.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if (!$user->isAdmin()) {
	UI::exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

$attrdefs = $dms->getAllAttributeDefinitions();

UI::htmlStartPage(getMLText("admin_tools"));
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
UI::globalNavigation();
UI::pageNavigation(getMLText("admin_tools"), "admin_tools");
UI::contentHeading(getMLText("attrdef_management"));
UI::contentContainerStart();
?>

	<table>
	<tr>
		<td><?php echo getMLText("selection")?>:</td>
		<td>
			<select onchange="showAttributeDefinitions(this)" id="selector">
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
			&nbsp;&nbsp;
		</td>

		<td id="attrdefs0" style="display : none;">	
			<form action="../op/op.AttributeMgr.php" method="post">
  		<?php echo createHiddenFieldWithKey('addattrdef'); ?>
			<input type="Hidden" name="action" value="addattrdef">
			<table>
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
			</table>
			<input type="Submit" value="<?php printMLText("new_attrdef"); ?>">
			</form>
		</td>
	
	<?php	
	
	if($attrdefs) {
		foreach ($attrdefs as $attrdef) {
		
			print "<td id=\"attrdefs".$attrdef->getID()."\" style=\"display : none;\">";	
	?>
				<table>
					<tr>
						<td colspan="2">
<?php
			if(!$attrdef->isUsed()) {
?>
							<form style="display: inline-block;" method="post" action="../op/op.AttributeMgr.php" >
							<?php echo createHiddenFieldWithKey('removeattrdef'); ?>
							<input type="Hidden" name="attrdefid" value="<?php echo $attrdef->getID()?>">
							<input type="Hidden" name="action" value="removeattrdef">
							<input value="<?php echo getMLText("rm_attrdef")?>" type="submit">
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
					<tr>
						<td colspan="2">
							<?php UI::contentSubHeading("");?>
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
							<input name="name" value="<?php echo htmlspecialchars($attrdef->getName()) ?>">
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
							<input type="checkbox" value="1" name="multiple" <?php echo $attrdef->getMultipleValues() ? "checked" : "" ?> />
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
						<td>
							<input type="Submit" value="<?php printMLText("save");?>">
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
	
<script language="JavaScript">

sel = document.getElementById("selector");
sel.selectedIndex=<?php print $selected ?>;
showAttributeDefinitions(sel);

</script>

<?php
UI::contentContainerEnd();
UI::htmlEndPage();
?>
