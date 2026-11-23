<?php
require_once __DIR__.'/../inc/Assistant.php';
require_once __DIR__.'/../inc/Store.php';
require_once __DIR__.'/../inc/Queue.php';
class LetoDMS_AI_DocumentPanel {
    private static function escape($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
    public static function form($action, $document, $providers) {
        echo '<form method="post" action="../op/op.Module.php?module=ai-assistant">'.createHiddenFieldWithKey('ai-assistant');
        echo '<input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="documentid" value="'.(int)$document->getID().'"><input type="hidden" name="return_document" value="1">';
        if (count($providers) === 1) echo '<input type="hidden" name="provider" value="'.self::escape(key($providers)).'">';
        else {
            echo '<label>AI provider <select name="provider">';
            foreach ($providers as $id => $label) echo '<option value="'.self::escape($id).'">'.self::escape($label).'</option>';
            echo '</select></label>';
        }
    }
    public static function versions($versions, $name, $label, $selected) {
        echo '<label>'.self::escape($label).' <select name="'.$name.'">';
        foreach ($versions as $number => $unused) echo '<option value="'.$number.'"'.($number === $selected ? ' selected' : '').'>Version '.$number.'</option>';
        echo '</select></label>';
    }
    public static function interaction($db, $user, $document, $readable, $id) {
        if (!$user->hasPermission('module.ai-assistant.access') || $document->getAccessMode($user) < M_READ) throw new RuntimeException('The AI result is unavailable.');
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) throw new RuntimeException('The AI result is unavailable.');
        $rows = $db->getResultArray('SELECT answer_text, sources_text FROM tblModuleAIInteractions WHERE interaction_id = '.$db->qstr($id).' AND user_id = '.(int)$user->getID());
        if (!$rows) throw new RuntimeException('The AI result is unavailable.');
        $sources = json_decode($rows[0]['sources_text'], true);
        if (!is_array($sources) || !$sources) throw new RuntimeException('The AI result is unavailable.');
        foreach ($sources as $source) {
            if (!isset($source['document_id'], $source['version']) || (int)$source['document_id'] !== (int)$document->getID() || !isset($readable[(int)$source['version']])) throw new RuntimeException('The AI result contains a version you can no longer read.');
        }
        return array('answer' => $rows[0]['answer_text'], 'sources' => $sources);
    }
    public static function show($dms, $document, $user, $providers, $indexing) {
        if (!$providers || !$user->hasPermission('module.ai-assistant.access') || $document->getAccessMode($user) < M_READ) return;
        $readable = array();
        foreach ((array)$document->getContent() as $content) if ($content->getAccessMode($user) >= M_READ) $readable[(int)$content->getVersion()] = $content;
        if (!$readable) return;
        ksort($readable); $latest = max(array_keys($readable));
        $selected = isset($_GET['ai_version']) && is_scalar($_GET['ai_version']) ? (int)$_GET['ai_version'] : $latest;
        if (!isset($readable[$selected])) $selected = $latest;
        $db = $dms->getDB();
        $summaryTab = isset($_GET['ai_done']) && is_string($_GET['ai_done']) && in_array($_GET['ai_done'], array('analyze', 'index', 'queue'), true);
        $metadataTab = isset($_GET['ai_done']) && is_string($_GET['ai_done']) && in_array($_GET['ai_done'], array('metadata', 'apply_metadata', 'dismiss_metadata'), true);
        $duplicatesTab = isset($_GET['ai_duplicates']);
        echo '<section class="well" id="document-ai"><h3>Document AI</h3>';
        if (isset($_GET['ai_done']) && is_string($_GET['ai_done'])) {
            $messages = array('analyze' => 'Summary saved for version '.$selected.'.', 'index' => 'Version indexed for search.', 'queue' => 'Analysis and indexing queued for the background worker.', 'metadata' => 'Metadata suggestions are ready for review.', 'apply_metadata' => 'Selected metadata applied.', 'dismiss_metadata' => 'Metadata suggestions dismissed.');
            if (isset($messages[$_GET['ai_done']])) echo '<div class="alert alert-success">'.self::escape($messages[$_GET['ai_done']]).'</div>';
        }
        if (isset($_GET['ai_result'])) {
            try {
                $result = self::interaction($db, $user, $document, $readable, $_GET['ai_result']);
                $answer = self::escape($result['answer']);
                $answer = preg_replace_callback('/\[(\d+)\]/', function ($match) use ($result, $document) {
                    $number = (int)$match[1];
                    if (!isset($result['sources'][$number - 1])) return $match[0];
                    $source = $result['sources'][$number - 1];
                    return '<a href="../out/out.Module.php?module=ai-assistant&amp;passage=1&amp;documentid='.(int)$document->getID().'&amp;version='.(int)$source['version'].'&amp;page='.(int)$source['page'].'" title="Version '.(int)$source['version'].((int)$source['page'] ? ', page '.(int)$source['page'] : '').'">'.$match[0].'</a>';
                }, $answer);
                echo '<h4>AI result</h4><p class="muted">Review the linked evidence before relying on this answer.</p><div style="white-space:pre-wrap;margin-bottom:20px">'.$answer.'</div>';
            } catch (RuntimeException $e) { echo '<div class="alert">'.self::escape($e->getMessage()).'</div>'; }
        }
?>
<ul class="nav nav-tabs">
<li<?php echo $summaryTab || $metadataTab || $duplicatesTab ? '' : ' class="active"'; ?>><a href="#document-ai-ask" data-toggle="tab">Ask AI</a></li>
<li<?php echo $summaryTab ? ' class="active"' : ''; ?>><a href="#document-ai-summaries" data-toggle="tab">Summaries</a></li>
<li><a href="#document-ai-compare" data-toggle="tab">Compare versions</a></li>
<li<?php echo $metadataTab ? ' class="active"' : ''; ?>><a href="#document-ai-metadata" data-toggle="tab">Metadata</a></li>
<li<?php echo $duplicatesTab ? ' class="active"' : ''; ?>><a href="#document-ai-duplicates" data-toggle="tab">Duplicates</a></li>
</ul><div class="tab-content"><div class="tab-pane<?php echo $summaryTab || $metadataTab || $duplicatesTab ? '' : ' active'; ?>" id="document-ai-ask">
<?php
        self::form('ask', $document, $providers); self::versions($readable, 'version', 'Document version', $selected);
        echo '<label>Question <textarea name="question" class="input-block-level" rows="3" maxlength="2000" placeholder="What is this document about?" required></textarea></label><button class="btn btn-primary" type="submit">Ask AI</button></form>';
        echo '</div><div class="tab-pane'.($summaryTab ? ' active' : '').'" id="document-ai-summaries">';
        self::form('analyze', $document, $providers); self::versions($readable, 'version', 'Document version', $selected);
        echo '<button class="btn btn-primary" type="submit">Summarize</button> <button class="btn" type="submit" name="action" value="queue">Queue analysis</button>';
        if ($indexing) echo ' <button class="btn" type="submit" name="action" value="index">Index for search</button>';
        echo '</form>';
        $assistant = new LetoDMS_AIAssistant($db);
        $summaries = $assistant->results($document->getID(), $selected);
        echo '<h4>Saved summaries · version '.$selected.'</h4>';
        if (!$summaries) echo '<p class="muted">No summary saved for this version yet.</p>';
        foreach (array_slice((array)$summaries, 0, 3) as $summary) echo '<details><summary>'.self::escape($summary['provider']).' · '.self::escape(date('Y-m-d H:i', $summary['created_at'])).'</summary><div style="white-space:pre-wrap;padding:12px 0">'.self::escape($summary['result_text']).'</div></details>';
        try {
            $store = new LetoDMS_AIStore($db);
            foreach ((new LetoDMS_AIQueue($store))->jobs($user, $document->getID(), $selected) as $job) echo '<p class="muted">Background job: '.self::escape($job['state']).' '.self::escape($job['message']).'</p>';
        } catch (RuntimeException $e) { echo '<p class="muted">Job status is temporarily unavailable.</p>'; }
        echo '</div><div class="tab-pane" id="document-ai-compare">';
        if (count($readable) < 2) echo '<p>Upload another version to compare changes.</p>';
        else {
            $numbers = array_keys($readable);
            self::form('compare', $document, $providers);
            self::versions($readable, 'version', 'Earlier version', $numbers[count($numbers) - 2]);
            self::versions($readable, 'new_version', 'Later version', $latest);
            echo '<p class="muted">Compare substantive text changes between these versions.</p><button class="btn btn-primary" type="submit">Compare versions</button></form>';
        }
        require_once __DIR__.'/DocumentExtras.php';
        LetoDMS_AI_DocumentExtras::changes($db, $document, $readable, $selected);
        echo '</div><div class="tab-pane'.($metadataTab ? ' active' : '').'" id="document-ai-metadata">';
        LetoDMS_AI_DocumentExtras::metadata($db, $document, $user, $providers, $readable, $selected);
        echo '</div><div class="tab-pane'.($duplicatesTab ? ' active' : '').'" id="document-ai-duplicates">';
        LetoDMS_AI_DocumentExtras::duplicates($db, $dms, $document, $user, $providers, $readable, $selected, $duplicatesTab);
        echo '</div></div></section>';
    }
}
