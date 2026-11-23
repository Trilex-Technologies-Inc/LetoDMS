<?php
require_once __DIR__.'/../../../views/bootstrap/class.Bootstrap.php';
class LetoDMS_Module_View_AIAssistant extends LetoDMS_Bootstrap_Style {
    private function escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    public function show() {
        $p = $this->params;
        $this->htmlStartPage('AI Assistant'); $this->globalNavigation(); $this->contentStart(); $this->contentHeading('AI Assistant');
        if ($p['error']) echo '<div class="alert alert-error">'.$this->escape($p['error']).'</div>';
        if ($p['saved']) echo '<div class="alert alert-success">Suggestions saved for this version.</div>';
        if ($p['message']) echo '<div class="alert alert-info">'.$this->escape($p['message']).'</div>';
?>
<p>Generate a summary and suggested keywords for a document version. Suggestions require human review and do not change document metadata.</p>
<?php if ($p['user']->isAdmin()) { ?><p><a class="btn" href="../out/out.Module.php?module=ai-assistant&amp;settings=1">Provider settings</a></p><?php } ?>
<p><a class="btn" href="../out/out.Module.php?module=ai-assistant&amp;tools=1">Semantic search, document Q&amp;A, and version comparison</a></p>
<p>Supported: PDF, Word (.doc/.docx), UTF-8 text, Markdown, and CSV. Maximum file size: 20 MB; maximum extracted text: 60,000 bytes. Scanned files need OCR.</p>
<form action="../out/out.Module.php" method="get" class="well">
<input type="hidden" name="module" value="ai-assistant">
<label for="ai-document">Document ID</label><input id="ai-document" type="number" min="1" name="documentid" value="<?php echo (int)$p['documentid']; ?>" required>
<label for="ai-version">Version</label><input id="ai-version" type="number" min="1" name="version" value="<?php echo (int)$p['version']; ?>" required>
<button class="btn" type="submit">View version suggestions</button>
</form>
<?php if (!$p['providers']) { ?>
<div class="alert">An administrator must configure a hosted AI provider before analysis is available.</div>
<?php } elseif (!$p['error'] && $p['documentid'] > 0 && $p['version'] > 0) { ?>
<form action="../op/op.Module.php?module=ai-assistant" method="post" class="well">
<?php echo createHiddenFieldWithKey('ai-assistant'); ?>
<input type="hidden" name="documentid" value="<?php echo (int)$p['documentid']; ?>">
<input type="hidden" name="version" value="<?php echo (int)$p['version']; ?>">
<label for="ai-provider">Hosted AI provider</label><select id="ai-provider" name="provider">
<?php foreach ($p['providers'] as $id => $label) echo '<option value="'.$this->escape($id).'">'.$this->escape($label).'</option>'; ?>
</select>
<button class="btn btn-primary" name="action" value="analyze" type="submit">Analyze now</button>
<button class="btn" name="action" value="queue" type="submit">Queue analysis and indexing</button>
<button class="btn" name="action" value="index" type="submit">Index for semantic search</button>
</form>
<?php }
        foreach ($p['jobs'] as $job) echo '<div class="alert">Background job: '.$this->escape($job['state']).' ('.$this->escape($job['provider']).', attempts '.(int)$job['attempts'].') '.$this->escape($job['message']).'</div>';
        foreach ((array)$p['results'] as $result) {
            echo '<div class="well"><h3>AI suggestions — review required</h3><p>'.$this->escape($result['provider']).' / '.$this->escape($result['model']).' · '.$this->escape(date('Y-m-d H:i', $result['created_at'])).'</p><div style="white-space:pre-wrap">'.$this->escape($result['result_text']).'</div></div>';
        }
        $this->contentEnd(); $this->htmlEndPage();
    }
}
