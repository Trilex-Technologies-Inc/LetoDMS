<?php
require_once('class.ModuleManager.php');

class LetoDMS_View_ModuleGuide extends LetoDMS_View_ModuleManager {
	public function show() {
		$this->htmlStartPage('Module Development Guide');
		$this->globalNavigation();
		$this->contentStart();
?>
<p><a class="btn" href="../out/out.ModuleManager.php"><i class="icon-arrow-left"></i> Back to Modules</a></p>
<?php
		$this->developmentGuide();
		$this->contentEnd();
		$this->htmlEndPage();
	}
}

