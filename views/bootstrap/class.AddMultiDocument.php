<?php

/**
 * Implementation of AddMultiDocument view
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
 * Class which outputs the html page for AddMultiDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AddMultiDocument extends LetoDMS_Bootstrap_Style
{

	function show()
	{ /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];

		$this->htmlStartPage(getMLText("folder_title", array("foldername" => htmlspecialchars($folder->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation(getFolderPathHTML($folder, true), "view_folder", $folder);

?>
		<script language="JavaScript">
			var openDlg;

			function chooseKeywords(target) {
				openDlg = open("out.KeywordChooser.php?target=" + target, "openDlg", "width=500,height=400,scrollbars=yes,resizable=yes");
			}

			function chooseCategory(form, cats) {
				openDlg = open("out.CategoryChooser.php?form=" + form + "&cats=" + cats, "openDlg", "width=480,height=480,scrollbars=yes,resizable=yes,status=yes");
			}
		</script>

		<?php
		$this->contentHeading(getMLText("add_document"));
		$this->contentContainerStart();

		// Retrieve a list of all users and groups that have review / approve
		// privileges.
		$docAccess = $folder->getReadAccessList();
		?>
<link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" />
<script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
		<form id="dropzoneMetaForm" name="dropzoneMetaForm">
			<?php echo createHiddenFieldWithKey('adddocument'); ?>
			<input type="hidden" name="folderid" value="<?php print $folder->getId(); ?>">
			<input type="hidden" name="showtree" value="<?php echo showtree(); ?>">
			<table class="table-condensed">
				<tr>
					<td><?php printMLText("name"); ?>:</td>
					<td><input type="text" id="dz_name" name="name" size="60"></td>
				</tr>
				<tr>
					<td><?php printMLText("comment"); ?>:</td>
					<td><textarea id="dz_comment" name="comment" rows="3" cols="80"></textarea></td>
				</tr>
				<tr>
					<td><?php printMLText("keywords"); ?>:</td>
					<td><?php $this->printKeywordChooser('dropzoneMetaForm'); ?></td>
				</tr>
				<tr>
					<td><?php printMLText("categories") ?>:</td>
					<td>
						<select id="dz_categories" class="chzn-select" name="categories[]" multiple="multiple" data-placeholder="<?php printMLText('select_ind_reviewers'); ?>">
							<?php
							$categories = $dms->getDocumentCategories();
							foreach ($categories as $category) {
								echo "<option value=\"" . $category->getID() . "\">" . htmlspecialchars($category->getName()) . "</option>\n";
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php printMLText("sequence"); ?>:</td>
					<td><?php $this->printSequenceChooser($folder->getDocuments()); ?></td>
				</tr>
				<tr>
					<td><?php printMLText("version"); ?>:</td>
					<td><input type="text" id="dz_reqversion" name="reqversion" value="1"></td>
				</tr>
				<tr>
					<td><?php printMLText("comment_for_current_version"); ?>:</td>
					<td><textarea id="dz_version_comment" name="version_comment" rows="3" cols="80"></textarea></td>
				</tr>
			</table>
		</form>

		<form action="../op/op.AddMultiDocument.php" class="dropzone" id="dmsDropzone">
			<div class="dz-message"><?php echo getMLText('add_document') ?></div>
		</form>
		<p><button id="dzSubmit" class="btn"><?php echo getMLText('add_document') ?></button></p>
		<p class="dropzone-message" id="dropzoneStatus"></p>

		<script type="text/javascript">
			Dropzone.autoDiscover = false;
			var folderUrl = "../out/out.ViewFolder.php?folderid=<?php echo $folder->getId(); ?>&showtree=<?php echo showtree(); ?>";
			var baseAttributes = {
				folderid: "<?php echo $folder->getId(); ?>"
			};
			var dz = new Dropzone("#dmsDropzone", {
				url: "../op/op.AddMultiDocument.php",
				paramName: "file",
				autoProcessQueue: false,
				uploadMultiple: false,
				parallelUploads: 2,
				maxFiles: null,
				createImageThumbnails: false,
				addRemoveLinks: true
			});

			function appendField(formData, id, name) {
				var input = document.getElementById(id);
				if (input) {
					formData.append(name, input.value);
				}
			}

			dz.on("sending", function(file, xhr, formData) {
				Object.keys(baseAttributes).forEach(function(key) {
					formData.append(key, baseAttributes[key]);
				});
				appendField(formData, "dz_name", "name");
				appendField(formData, "dz_comment", "comment");
				appendField(formData, "dz_reqversion", "reqversion");
				appendField(formData, "dz_version_comment", "version_comment");
				appendField(formData, "keywords", "keywords");
				var select = document.getElementById("dz_categories");
				if (select) {
					for (var i = 0; i < select.options.length; i++) {
						if (select.options[i].selected) {
							formData.append("categories[]", select.options[i].value);
						}
					}
				}
				formData.append("fileId", (file.upload && file.upload.uuid) ? file.upload.uuid.replace(/-/g, "") : ("dropzone" + Date.now()));
				formData.append("partitionIndex", "0");
				formData.append("partitionCount", "1");
			});

			document.getElementById("dzSubmit").addEventListener("click", function(e) {
				e.preventDefault();
				if (dz.getQueuedFiles().length === 0) {
					alert("<?php echo addslashes(getMLText('js_no_file')); ?>");
					return;
				}
				dz.processQueue();
			});

			dz.on("success", function(file) {
				var status = document.getElementById("dropzoneStatus");
				if (status) {
					status.innerHTML = "Uploaded: " + file.name;
				}
			});

			dz.on("queuecomplete", function() {
				window.location.href = folderUrl;
			});

			dz.on("error", function(file, errorMessage) {
				var status = document.getElementById("dropzoneStatus");
				if (status) {
					status.innerHTML = "Upload failed: " + file.name + " (" + errorMessage + ")";
				}
			});
		</script>
<?php
		$this->contentContainerEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>