<?php
/** Bootstrap view for adding multiple documents. */
require_once("class.Bootstrap.php");

class LetoDMS_View_AddMultiDocument extends LetoDMS_Bootstrap_Style {
	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$folder = $this->params['folder'];
		$folderid = $folder->getId();

		$this->htmlStartPage(getMLText("folder_title", array("foldername" => htmlspecialchars($folder->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true), "view_folder", $folder);
?>
	<link rel="stylesheet" href="../styles/vendor/dropzone/dropzone.min.css" />
	<script src="../styles/vendor/dropzone/dropzone.min.js"></script>

	<div class="add-multi-document-page">
		<div class="add-multi-heading">
			<div>
				<h1><?php printMLText("add_multiple_documents"); ?></h1>
				<p class="muted"><?php printMLText("max_upload_size"); ?>: <?php echo htmlspecialchars(ini_get("upload_max_filesize")); ?></p>
			</div>
			<span class="add-multi-heading-icon"><i class="icon-upload icon-white"></i></span>
		</div>

		<div class="row-fluid add-multi-layout">
			<section class="span5 well add-multi-meta-card">
				<div class="add-multi-card-header">
					<span class="badge badge-info">1</span>
					<div><h2><?php printMLText("document_infos"); ?></h2><p class="muted">Apply metadata to the uploaded documents.</p></div>
				</div>
				<form id="dropzoneMetaForm" name="dropzoneMetaForm" class="form-vertical">
					<?php echo createHiddenFieldWithKey('adddocument'); ?>
					<input type="hidden" name="folderid" value="<?php echo $folderid; ?>">
					<input type="hidden" name="showtree" value="<?php echo showtree(); ?>">
					<input type="hidden" name="expires" value="false">
					<div class="control-group">
						<label for="dz_name"><?php printMLText("name"); ?></label>
						<input class="input-block-level" type="text" id="dz_name" name="name">
					</div>
					<div class="control-group">
						<label for="dz_comment"><?php printMLText("comment"); ?></label>
						<textarea class="input-block-level" id="dz_comment" name="comment" rows="3"></textarea>
					</div>
					<div class="control-group">
						<label for="keywords"><?php printMLText("keywords"); ?></label>
						<?php $this->printKeywordChooser('dropzoneMetaForm'); ?>
					</div>
					<div class="control-group">
						<label for="dz_categories"><?php printMLText("categories"); ?></label>
						<select id="dz_categories" class="chzn-select input-block-level" name="categories[]" multiple="multiple" data-placeholder="<?php printMLText('select_ind_reviewers'); ?>">
<?php
		foreach ($dms->getDocumentCategories() as $category)
			echo '<option value="' . $category->getID() . '">' . htmlspecialchars($category->getName()) . '</option>';
?>
						</select>
					</div>
					<div class="row-fluid">
						<div class="span6 control-group">
							<label><?php printMLText("sequence"); ?></label>
							<?php $this->printSequenceChooser($folder->getDocuments()); ?>
						</div>
						<div class="span6 control-group">
							<label for="dz_reqversion"><?php printMLText("version"); ?></label>
							<input class="input-block-level" type="text" id="dz_reqversion" name="reqversion" value="1">
						</div>
					</div>
					<div class="control-group">
						<label for="dz_version_comment"><?php printMLText("comment_for_current_version"); ?></label>
						<textarea class="input-block-level" id="dz_version_comment" name="version_comment" rows="3"></textarea>
					</div>
				</form>
			</section>

			<section class="span7 well add-multi-upload-card">
				<div class="add-multi-card-header">
					<span class="badge badge-info">2</span>
					<div><h2><?php printMLText("local_file"); ?></h2><p class="muted">Choose or drop all files you want to upload.</p></div>
				</div>
				<form action="../op/op.AddMultiDocument.php" class="dropzone add-multi-dropzone" id="dmsDropzone">
					<div class="dz-message">
						<span class="dropzone-upload-icon"><i class="icon-upload"></i></span>
						<strong><?php printMLText("add_multiple_documents"); ?></strong>
						<small class="muted">Drag files here or click to browse</small>
					</div>
				</form>
				<div id="dropzoneStatus" class="add-multi-status" aria-live="polite"></div>
				<div class="form-actions add-multi-actions">
					<a class="btn" href="../out/out.ViewFolder.php?folderid=<?php echo $folderid; ?>&showtree=<?php echo showtree(); ?>"><?php printMLText("folder"); ?></a>
					<button id="dzSubmit" type="button" class="btn btn-primary"><i class="icon-upload icon-white"></i> <?php printMLText("add_multiple_documents"); ?></button>
				</div>
			</section>
		</div>
	</div>

	<script type="text/javascript">
	(function() {
		Dropzone.autoDiscover = false;
		var folderUrl = "../out/out.ViewFolder.php?folderid=<?php echo $folderid; ?>&showtree=<?php echo showtree(); ?>";
		var submit = document.getElementById("dzSubmit");
		var status = document.getElementById("dropzoneStatus");
		var hadErrors = false;
		var dz = new Dropzone("#dmsDropzone", {
			url: "../op/op.AddMultiDocument.php",
			paramName: "file",
			autoProcessQueue: false,
			uploadMultiple: false,
			parallelUploads: 2,
			createImageThumbnails: true,
			thumbnailWidth: 160,
			thumbnailHeight: 120,
			thumbnailMethod: "contain",
			addRemoveLinks: true
		});

		function addFormValues(formData) {
			var fields = document.getElementById("dropzoneMetaForm").elements;
			for (var i = 0; i < fields.length; i++) {
				var field = fields[i];
				if (!field.name || field.disabled || ((field.type === "checkbox" || field.type === "radio") && !field.checked)) continue;
				if (field.tagName === "SELECT" && field.multiple) {
					for (var j = 0; j < field.options.length; j++)
						if (field.options[j].selected) formData.append(field.name, field.options[j].value);
				} else formData.append(field.name, field.value);
			}
		}

		dz.on("sending", function(file, xhr, formData) {
			addFormValues(formData);
			formData.append("fileId", file.upload && file.upload.uuid ? file.upload.uuid.replace(/-/g, "") : "dropzone" + Date.now());
			formData.append("partitionIndex", "0");
			formData.append("partitionCount", "1");
			submit.disabled = true;
			status.className = "add-multi-status alert alert-info";
			status.textContent = "Uploading " + file.name + "…";
		});

		submit.addEventListener("click", function() {
			if (!dz.getQueuedFiles().length) {
				status.className = "add-multi-status alert alert-warning";
				status.textContent = <?php echo json_encode(getMLText('js_no_file')); ?>;
				return;
			}
			dz.processQueue();
		});

		dz.on("success", function(file) {
			status.className = "add-multi-status alert alert-success";
			status.textContent = "Uploaded: " + file.name;
		});
		dz.on("error", function(file, message) {
			hadErrors = true;
			submit.disabled = false;
			status.className = "add-multi-status alert alert-error";
			status.textContent = "Upload failed: " + file.name + " (" + message + ")";
		});
		dz.on("complete", function() {
			if (!dz.getUploadingFiles().length && dz.getQueuedFiles().length) dz.processQueue();
		});
		dz.on("queuecomplete", function() {
			if (!dz.getQueuedFiles().length && !dz.getRejectedFiles().length && !hadErrors) window.location.href = folderUrl;
		});
	})();
	</script>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
