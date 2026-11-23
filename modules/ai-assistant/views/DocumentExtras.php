<?php
require_once __DIR__.'/../inc/Metadata.php';
require_once __DIR__.'/../inc/Duplicates.php';
class LetoDMS_AI_DocumentExtras {
    private static function escape($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
    public static function metadata($db, $document, $user, $providers, $readable, $selected) {
        LetoDMS_AI_DocumentPanel::form('metadata', $document, $providers);
        LetoDMS_AI_DocumentPanel::versions($readable, 'version', 'Document version', $selected);
        echo '<p>Suggest a title, keywords, categories, entities, expiry date, and missing information. Nothing changes until reviewed.</p><button class="btn btn-primary" type="submit">Suggest metadata</button></form>';
        $rows = $db->getResultArray('SELECT * FROM tblModuleAISuggestions WHERE document_id = '.(int)$document->getID().' AND document_version = '.(int)$selected." ORDER BY CASE WHEN state = 'pending' THEN 0 ELSE 1 END, created_at DESC LIMIT 5");
        if (!$rows) { echo '<p class="muted">No metadata suggestions for this version yet.</p>'; return; }
        $latest = $document->getLatestContent();
        $canReview = $document->getAccessMode($user) >= M_READWRITE && !$document->isLocked() && $latest && (int)$latest->getVersion() === $selected;
        foreach ($rows as $row) {
            try { $payload = LetoDMS_AIMetadata::payload($row['payload_text']); } catch (RuntimeException $e) { continue; }
            echo '<details class="well"'.($row['state'] === 'pending' ? ' open' : '').'><summary><strong>Suggested metadata</strong> <span class="label">'.self::escape($row['state']).'</span></summary><dl>';
            foreach (array('title' => 'Title', 'keywords' => 'Keywords', 'category_suggestions' => 'Categories (advisory)', 'entities' => 'Entities', 'expiry_date' => 'Expiry date (advisory)', 'missing_information' => 'Missing information') as $key => $label) {
                $value = is_array($payload[$key]) ? implode(', ', $payload[$key]) : $payload[$key];
                echo '<dt>'.$label.'</dt><dd>'.self::escape($value === null || $value === '' ? 'Not identified' : $value).'</dd>';
            }
            echo '</dl>';
            if ($row['state'] === 'pending' && $canReview) {
                $original = json_decode($row['original_text'], true);
                $stale = $original !== array('title' => $document->getName(), 'keywords' => $document->getKeywords());
                LetoDMS_AI_DocumentPanel::form('apply_metadata', $document, array($row['provider'] => $row['provider']));
                echo '<input type="hidden" name="version" value="'.$selected.'"><input type="hidden" name="suggestion_id" value="'.self::escape($row['suggestion_id']).'">';
                if ($stale) echo '<p class="muted">Metadata has changed since these suggestions were generated. Generate new suggestions before applying, or dismiss these.</p>';
                echo '<label class="checkbox"><input type="checkbox" name="apply_title" value="1"'.($payload['title'] === '' ? ' disabled' : '').'>Replace title: '.self::escape($document->getName()).'</label><label class="checkbox"><input type="checkbox" name="apply_keywords" value="1"'.($payload['keywords'] ? ' checked' : ' disabled').'>Replace keywords: '.self::escape($document->getKeywords()).'</label><button class="btn btn-primary" type="submit"'.($stale ? ' disabled' : '').'>Apply selected fields</button> <button class="btn" type="submit" name="action" value="dismiss_metadata">Dismiss</button></form>';
            } elseif ($row['state'] === 'pending') echo '<p class="muted">Review requires write access to the unlocked, latest document version.</p>';
            echo '</details>';
        }
    }
    public static function changes($db, $document, $readable, $selected) {
        $rows = $db->getResultArray('SELECT c.previous_version, i.answer_text, i.sources_text FROM tblModuleAIChanges c INNER JOIN tblModuleAIInteractions i ON i.interaction_id = c.interaction_id WHERE c.document_id = '.(int)$document->getID().' AND c.document_version = '.(int)$selected);
        foreach ((array)$rows as $row) {
            if (!isset($readable[(int)$row['previous_version']])) continue;
            $sources = json_decode($row['sources_text'], true); $allowed = is_array($sources) && count($sources) > 0;
            foreach ((array)$sources as $source) if (!isset($source['document_id'], $source['version']) || (int)$source['document_id'] !== (int)$document->getID() || !isset($readable[(int)$source['version']])) $allowed = false;
            if (!$allowed) continue;
            $answer = self::escape($row['answer_text']);
            $answer = preg_replace_callback('/\[(\d+)\]/', function ($match) use ($sources, $document) {
                $i = (int)$match[1] - 1;
                if (!isset($sources[$i])) return $match[0];
                return '<a href="../out/out.Module.php?module=ai-assistant&amp;passage=1&amp;documentid='.(int)$document->getID().'&amp;version='.(int)$sources[$i]['version'].'&amp;page='.(int)$sources[$i]['page'].'">'.$match[0].'</a>';
            }, $answer);
            echo '<details><summary>Automatic change summary: version '.(int)$row['previous_version'].' to '.$selected.'</summary><div style="white-space:pre-wrap;padding:12px 0">'.$answer.'</div></details>';
        }
    }
    public static function duplicates($db, $dms, $document, $user, $providers, $readable, $selected, $requested) {
        LetoDMS_AI_DocumentPanel::form('duplicates', $document, $providers);
        LetoDMS_AI_DocumentPanel::versions($readable, 'version', 'Document version', $selected);
        echo '<p>Find identical accessible files, matching processed text, and highly similar indexed content. Similarity is a review aid, not proof of duplication.</p><button class="btn" type="submit">Find duplicates</button></form>';
        if (!$requested) return;
        try {
            $store = new LetoDMS_AIStore($db);
            $matches = (new LetoDMS_AIDuplicates($store, new LetoDMS_AIAssistant($db, null, $store), $dms))->find($user, $document->getID(), $selected);
            if (!$matches) echo '<p>No duplicate candidates found. Analyze or index older documents to include text and similarity matching.</p>';
            foreach ($matches as $match) echo '<p><a href="../out/out.ViewDocument.php?documentid='.$match['document_id'].'">'.self::escape($match['name']).'</a> · version '.$match['version'].' · '.self::escape($match['kind']).'</p>';
        } catch (RuntimeException $e) { echo '<div class="alert">'.self::escape($e->getMessage()).'</div>'; }
    }
}
