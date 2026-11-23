<?php
require_once __DIR__.'/../../../views/bootstrap/class.Bootstrap.php';
class LetoDMS_Module_View_AISettings extends LetoDMS_Bootstrap_Style {
    private function escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    public function show() {
        if (!$this->params['user']->isAdmin()) { UI::exitError('AI Assistant', getMLText('access_denied')); return; }
        $this->htmlStartPage('AI provider settings'); $this->globalNavigation(); $this->contentStart(); $this->contentHeading('AI provider settings');
        echo '<p><a href="../out/out.MyDocuments.php?inProcess=1">Back to documents</a></p>';
        if ($this->params['saved']) echo '<div class="alert alert-success">Changes saved.</div>';
?>
<p class="muted">Connect your AI providers and choose how documents are analyzed and searched.</p>
<div class="alert alert-info"><strong>Hosted AI usage:</strong> Connecting a provider allows enabled AI features to send users' queries and required document text to that service. API usage charges may apply to your provider account. Configure billing and spending limits with your provider.</div>
<ul class="nav nav-tabs" id="ai-settings-tabs">
<li class="<?php echo isset($_GET['panel']) && $_GET['panel'] === 'jobs' ? '' : 'active'; ?>"><a href="#ai-processing" data-toggle="tab">Processing settings</a></li>
<li class="<?php echo isset($_GET['panel']) && $_GET['panel'] === 'jobs' ? 'active' : ''; ?>"><a href="#ai-dashboard" data-toggle="tab">Jobs &amp; usage</a></li>
<li><a href="#ai-providers" data-toggle="tab">Configured providers</a></li>
<li><a href="#ai-add-provider" data-toggle="tab">Add provider</a></li>
</ul>
<div class="tab-content">
<div class="tab-pane<?php echo isset($_GET['panel']) && $_GET['panel'] === 'jobs' ? '' : ' active'; ?>" id="ai-processing">
<?php
        $this->processingForm();
        echo '</div><div class="tab-pane'.(isset($_GET['panel']) && $_GET['panel'] === 'jobs' ? ' active' : '').'" id="ai-dashboard">';
        require_once __DIR__.'/Dashboard.php'; LetoDMS_AI_Dashboard::show($this->params);
        echo '</div><div class="tab-pane" id="ai-providers">';
        if (!$this->params['providers']) echo '<div class="alert">No providers have been added here yet. Open the Add provider tab to connect a service.</div>';
        foreach ($this->params['providers'] as $row) $this->providerForm($row);
        echo '</div><div class="tab-pane" id="ai-add-provider">';
        echo '<h3>Add a provider</h3>';
        $this->providerForm(array('provider_id' => '', 'label' => '', 'protocol' => 'openai', 'endpoint' => '', 'model' => '', 'enabled' => 1, 'has_key' => false));
        echo '</div></div><script src="../modules/ai-assistant/assets/settings.js"></script>';
        $this->contentEnd(); $this->htmlEndPage();
    }
    private function choices($name, $selected, $empty) {
        echo '<select name="'.$name.'"><option value="">'.$empty.'</option>';
        foreach ($this->params['choices'] as $id => $label) echo '<option value="'.$this->escape($id).'" '.($selected === $id ? 'selected' : '').'>'.$this->escape($label).'</option>';
        echo '</select>';
    }
    private function processingForm() {
        $options = $this->params['options'];
        $options += array('ocr_enabled' => '0', 'ocr_languages' => 'eng', 'change_summaries' => '0', 'metadata_suggestions' => '0', 'monthly_request_limit' => '0', 'daily_user_limit' => '0', 'input_price' => '0', 'output_price' => '0', 'embedding_price' => '0');
?>
<div class="well"><h3>Automatic analysis and semantic search</h3>
<form action="../op/op.Module.php?module=ai-assistant" method="post" class="ai-processing-form">
<?php echo createHiddenFieldWithKey('ai-assistant'); ?><input type="hidden" name="action" value="save_options">
<label>Automatic analysis provider <?php $this->choices('analysis_provider', $options['analysis_provider'], 'Choose a provider'); ?></label>
<label class="checkbox"><input type="checkbox" name="automatic" value="1" <?php echo $options['automatic'] === '1' ? 'checked' : ''; ?>>Automatically queue new uploads and versions for hosted analysis</label>
<p>New uploads are processed in the background using the selected analysis service and, when configured, the embeddings service.</p>
<label>Embeddings provider <?php $this->choices('embedding_provider', $options['embedding_provider'], 'Disabled'); ?></label>
<p>Choose an OpenAI-compatible service that supports embeddings. Its saved API key will be used. The analysis service can be a different provider.</p>
<label for="ai-embedding-endpoint">Full embeddings endpoint</label><input class="input-xxlarge" id="ai-embedding-endpoint" name="embedding_endpoint" type="url" maxlength="2048" value="<?php echo $this->escape($options['embedding_endpoint']); ?>" placeholder="https://api.openai.com/v1/embeddings">
<label for="ai-embedding-model">Embeddings model ID</label><input id="ai-embedding-model" name="embedding_model" maxlength="255" value="<?php echo $this->escape($options['embedding_model']); ?>">
<button type="button" class="btn ai-load-embedding-models">Load OpenAI embedding models</button>
<select class="ai-embedding-picker" aria-label="Available embeddings models" style="display:none"></select>
<p class="help-block ai-model-status" role="status" aria-live="polite"></p>
<p>Changing the embeddings provider, endpoint, or model requires reindexing documents. Old indexes are excluded from searches automatically.</p>
<h4>Document automation</h4>
<label class="checkbox"><input type="checkbox" name="change_summaries" value="1" <?php echo $options['change_summaries'] === '1' ? 'checked' : ''; ?>>Generate a change summary when a queued version has a readable previous version</label>
<label class="checkbox"><input type="checkbox" name="metadata_suggestions" value="1" <?php echo $options['metadata_suggestions'] === '1' ? 'checked' : ''; ?>>Generate metadata suggestions during queued analysis (human review required)</label>
<h4>Scanned documents</h4>
<label class="checkbox"><input type="checkbox" name="ocr_enabled" value="1" <?php echo $options['ocr_enabled'] === '1' ? 'checked' : ''; ?>>Enable local OCR for scanned PDFs and PNG/JPEG/TIFF images</label>
<label>OCR languages <input name="ocr_languages" maxlength="100" value="<?php echo $this->escape($options['ocr_languages']); ?>" placeholder="eng or eng+fra"></label>
<p class="help-block">Install OCRmyPDF, Tesseract, and the selected language packs on the server. OCR uses a temporary copy and never replaces the original document.</p>
<h4>Local request limits</h4>
<label>Requests per month, across this installation <input type="number" min="0" max="99999999" name="monthly_request_limit" value="<?php echo $this->escape($options['monthly_request_limit']); ?>"></label>
<label>Requests per user per day <input type="number" min="0" max="99999999" name="daily_user_limit" value="<?php echo $this->escape($options['daily_user_limit']); ?>"></label>
<p class="help-block">0 means unlimited. Limits count individual API attempts, including embeddings and failures, using UTC periods. After increasing a limit, use Retry in Jobs &amp; usage to resume a paused job immediately.</p>
<h4>Optional cost estimates</h4>
<p class="help-block">Enter your own default USD rates per million tokens. These approximate all selected models; use the provider's invoice for actual charges. 0 means the rate is unknown.</p>
<?php foreach (array('input_price' => 'Generation input', 'output_price' => 'Generation output', 'embedding_price' => 'Embeddings input') as $field => $label) { ?>
<label><?php echo $label; ?> <input type="number" name="<?php echo $field; ?>" min="0" max="99999" step="0.000001" value="<?php echo $this->escape($options[$field]); ?>"></label>
<?php } ?>
<button class="btn btn-primary" type="submit">Save processing settings</button>
</form>
<p>Schedule the AI worker every minute on your server:</p><pre>php /path/to/LetoDMS/modules/ai-assistant/worker.php --config=/path/to/settings.xml --limit=5</pre>
<p>Open <strong>Jobs &amp; usage</strong> for worker activity, processing errors, retry/cancel actions, and usage estimates.</p>
<p>PDF extraction requires pdftotext; older Word (.doc) extraction requires antiword. DOCX requires PHP ZIP and DOM. PHP mbstring is required for text sections. Failed jobs retry up to three times and their status appears on the document version's AI page.</p>
</div>
<?php
    }
    private function providerForm($row) {
        $existing = $row['provider_id'] !== '';
        $prefix = 'ai-'.($existing ? $row['provider_id'] : 'new').'-';
?>
<div class="well">
<?php if ($existing) echo '<h3>'.$this->escape($row['label']).'</h3>'; ?>
<form action="../op/op.Module.php?module=ai-assistant" method="post" autocomplete="off" class="ai-provider-form">
<?php echo createHiddenFieldWithKey('ai-assistant'); ?>
<input type="hidden" name="action" value="save_provider">
<label for="<?php echo $prefix; ?>preset">AI company</label>
<select id="<?php echo $prefix; ?>preset" class="ai-provider-preset">
<option value="custom">Custom provider</option>
<option value="openai" <?php echo $row['endpoint'] === 'https://api.openai.com/v1/chat/completions' ? 'selected' : ''; ?>>OpenAI</option>
<option value="anthropic" <?php echo $row['endpoint'] === 'https://api.anthropic.com/v1/messages' ? 'selected' : ''; ?>>Anthropic</option>
</select>
<span class="help-block">Choose a company to fill in its endpoint and API format.</span>
<label for="<?php echo $prefix; ?>id">Provider ID</label>
<input id="<?php echo $prefix; ?>id" name="provider_id" maxlength="80" pattern="[a-z][a-z0-9_-]{0,79}" value="<?php echo $this->escape($row['provider_id']); ?>" <?php echo $existing ? 'readonly' : ''; ?> required>
<span class="help-block">A stable identifier, for example openai or anthropic.</span>
<label for="<?php echo $prefix; ?>label">Display name</label><input id="<?php echo $prefix; ?>label" name="label" maxlength="160" value="<?php echo $this->escape($row['label']); ?>" required>
<label for="<?php echo $prefix; ?>protocol">API format</label><select id="<?php echo $prefix; ?>protocol" name="protocol">
<option value="openai" <?php echo $row['protocol'] === 'openai' ? 'selected' : ''; ?>>OpenAI-compatible</option>
<option value="anthropic" <?php echo $row['protocol'] === 'anthropic' ? 'selected' : ''; ?>>Anthropic Messages</option>
</select>
<label for="<?php echo $prefix; ?>endpoint">Full API endpoint</label><input class="input-xxlarge" id="<?php echo $prefix; ?>endpoint" type="url" name="endpoint" maxlength="2048" value="<?php echo $this->escape($row['endpoint']); ?>" required>
<details style="margin-bottom:16px"><summary>Endpoint examples</summary><dl style="margin:8px 0"><dt>OpenAI-compatible</dt><dd style="margin-left:0;overflow-wrap:anywhere"><code>https://api.openai.com/v1/chat/completions</code></dd><dt>Anthropic Messages</dt><dd style="margin-left:0;overflow-wrap:anywhere"><code>https://api.anthropic.com/v1/messages</code></dd></dl></details>
<label for="<?php echo $prefix; ?>model">Model ID</label><input id="<?php echo $prefix; ?>model" name="model" maxlength="255" value="<?php echo $this->escape($row['model']); ?>" required>
<label for="<?php echo $prefix; ?>key">API key</label><input id="<?php echo $prefix; ?>key" type="password" name="api_key" maxlength="8192" autocomplete="new-password" <?php echo $existing ? '' : 'required'; ?>>
<span class="help-block"><?php echo $row['has_key'] ? 'A key is stored. Leave blank to keep it, or enter a replacement. Re-enter it when changing the endpoint or API format.' : 'Enter the provider API key. It will not be displayed after saving.'; ?></span>
<button type="button" class="btn ai-load-models">Load OpenAI models</button>
<select class="ai-model-picker" aria-label="Available chat models" style="display:none"></select>
<p class="help-block ai-model-status" role="status" aria-live="polite">For OpenAI, enter your key and load models, then select one. You can also enter a model ID manually.</p>
<label class="checkbox"><input type="checkbox" name="enabled" value="1" <?php echo $row['enabled'] ? 'checked' : ''; ?>>Available for document analysis</label>
<button class="btn btn-primary" type="submit"><?php echo $existing ? 'Save provider' : 'Add provider'; ?></button>
</form>
<?php if ($existing) { ?>
<form action="../op/op.Module.php?module=ai-assistant" method="post" onsubmit="return confirm('Remove this provider and its stored key? Saved document suggestions will remain.');">
<?php echo createHiddenFieldWithKey('ai-assistant'); ?><input type="hidden" name="action" value="remove_provider"><input type="hidden" name="provider_id" value="<?php echo $this->escape($row['provider_id']); ?>"><button class="btn btn-danger" type="submit">Remove provider</button>
</form>
<?php } ?></div><?php
    }
}
