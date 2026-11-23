<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Real SQLite schema + deterministic providers; no live accounts or documents.
require_once __DIR__.'/integration.php';
require_once __DIR__.'/../inc/Usage.php';
require_once __DIR__.'/../inc/Metadata.php';
require_once __DIR__.'/../inc/Duplicates.php';
define('M_READWRITE', 2);
class AdvancedDocument extends IntegrationDocument {
    private $db;
    public function __construct($id, $db) { parent::__construct($id); $this->db = $db; $this->access = 2; }
    private function row() { return $this->db->getResultArray('SELECT name, keywords, locked FROM tblDocuments WHERE id = '.$this->getID())[0]; }
    public function getName() { return $this->row()['name']; }
    public function getKeywords() { return $this->row()['keywords']; }
    public function isLocked() { return (int)$this->row()['locked'] !== -1; }
    public function setName($name) { return $this->db->getResult('UPDATE tblDocuments SET name = '.$this->db->qstr($name).' WHERE id = '.$this->getID()); }
    public function setKeywords($keywords) { return $this->db->getResult('UPDATE tblDocuments SET keywords = '.$this->db->qstr($keywords).' WHERE id = '.$this->getID()); }
}
class AdvancedAI extends IntegrationAI {
    public $metadataReply = null;
    protected function transport($endpoint, $headers, $body) {
        $parsed = json_decode($body, true);
        if (isset($parsed['messages'][0]['content']) && strpos($parsed['messages'][0]['content'], 'Suggest document metadata') === 0) {
            if ($this->fail) throw new RuntimeException('Test provider failure.');
            $this->requests[] = $parsed;
            $payload = $this->metadataReply ?: array('title' => 'Contract for review', 'keywords' => array('contract', 'expiry'), 'expiry_date' => '2027-06-30', 'entities' => array('Example supplier'), 'category_suggestions' => array('Contracts'), 'missing_information' => array('Signing date'));
            $result = array('choices' => array(array('message' => array('content' => json_encode($payload)))));
        } else $result = json_decode(parent::transport($endpoint, $headers, $body), true);
        $result['usage'] = isset($parsed['input']) ? array('total_tokens' => 10) : array('prompt_tokens' => 100, 'completion_tokens' => 20);
        return json_encode($result);
    }
}
$oldProfiles = getenv('LETODMS_AI_PROVIDERS'); $oldKey = getenv('LETODMS_AI_ADVANCED_KEY');
$temp = sys_get_temp_dir().'/letodms-advanced-'.uniqid().'/'; mkdir($temp);
try {
    putenv('LETODMS_AI_ADVANCED_KEY=test-key');
    putenv('LETODMS_AI_PROVIDERS='.json_encode(array('test' => array('label' => 'Test', 'protocol' => 'openai', 'endpoint' => 'https://example.com/chat', 'model' => 'test-chat', 'key_env' => 'LETODMS_AI_ADVANCED_KEY'))));
    $db = new LetoDMS_Core_DatabaseAccess('sqlite', '', '', '', ':memory:'); $db->connect();
    $db->getResult('CREATE TABLE tblPermissions (id INTEGER PRIMARY KEY, name TEXT, description TEXT)');
    $db->getResult('CREATE TABLE tblRolePermissions (roleID INTEGER, permissionID INTEGER)');
    $db->getResult('CREATE TABLE tblDocuments (id INTEGER PRIMARY KEY, name TEXT, keywords TEXT, locked INTEGER)');
    $db->getResult('CREATE TABLE tblDocumentContent (document INTEGER, version INTEGER)');
    $module = new LetoDMS_AIAssistant_Module(); aiCheck($module->install($db, 'sqlite'), 'Advanced schema install');
    aiCheck($module->install($db, 'sqlite'), 'Idempotent schema upgrade');
    $store = new LetoDMS_AIStore($db, 'sqlite'); $user = new IntegrationUser();
    $settings = new LetoDMS_AIProviderSettings($db, 'test-secret'); $client = new AdvancedAI($db, $settings, $store); $client->setUser($user);
    $options = array_merge($store->options(), array('analysis_provider' => 'test', 'embedding_provider' => 'test', 'embedding_endpoint' => 'https://example.com/embeddings', 'embedding_model' => 'test-embedding', 'input_price' => '1', 'output_price' => '2', 'embedding_price' => '0.1', 'monthly_request_limit' => '2'));
    $store->saveOptions($user, $options, $client); $usage = new LetoDMS_AIUsage($store);
    $one = $usage->reserve(9, 'test', 'model', 'generation');
    $usage->finish($one, '{}', '{"usage":{"prompt_tokens":100,"completion_tokens":20}}', true);
    $two = $usage->reserve(9, 'test', 'model', 'generation'); $usage->finish($two, '{}', '', false);
    aiReject(function () use ($usage) { $usage->reserve(9, 'test', 'model', 'generation'); });
    $rows = $store->rows('SELECT * FROM tblModuleAIUsage ORDER BY created_at');
    aiCheck(count($rows) === 2 && abs((float)$rows[0]['cost_estimate'] - 0.00014) < 0.00000001 && $rows[0]['token_source'] === 'provider', 'Reported tokens and configured cost estimate');
    aiCheck($rows[1]['state'] === 'failed' && (int)$rows[1]['cost_known'] === 0, 'Failure counted without claiming known cost');
    $store->execute('DELETE FROM tblModuleAIUsage'); $store->execute('DELETE FROM tblModuleAIQuota');
    $options['monthly_request_limit'] = '0'; $options['daily_user_limit'] = '1'; $store->saveOptions($user, $options, $client);
    $usage->reserve(9, 'test', 'model', 'generation');
    aiReject(function () use ($usage) { $usage->reserve(9, 'test', 'model', 'generation'); });
    $usage->reserve(10, 'test', 'model', 'generation');
    $month = $store->rows("SELECT call_count FROM tblModuleAIQuota WHERE bucket LIKE 'month:%'")[0];
    aiCheck((int)$month['call_count'] === 2, 'Rejected daily limit must roll back monthly reservation');
    $store->execute('DELETE FROM tblModuleAIUsage'); $store->execute('DELETE FROM tblModuleAIQuota');
    $options['daily_user_limit'] = '0'; $options['change_summaries'] = '1'; $options['metadata_suggestions'] = '1'; $store->saveOptions($user, $options, $client);
    $dms = new IntegrationDMS(); $dms->contentDir = $temp; $dms->db = $db; $dms->user = $user;
    $fixtures = array(7 => array(1 => 'Contract expires in May.', 2 => 'Contract expires in June.'), 8 => array(1 => 'Contract expires in June.'), 9 => array(1 => 'Contract   expires in June.'), 10 => array(1 => 'Contract covering procurement.'), 11 => array(1 => 'Contract expires in June.'), 12 => array(1 => 'Unrelated policy.'));
    foreach ($fixtures as $id => $versions) {
        $db->getResult('INSERT INTO tblDocuments VALUES ('.$id.", 'Document ".$id."', 'original', -1)");
        $doc = new AdvancedDocument($id, $db);
        foreach ($versions as $version => $text) {
            $path = $id.'-'.$version.'.txt'; file_put_contents($temp.$path, $text); $doc->versions[$version] = new IntegrationContent($path, $version);
            $db->getResult('INSERT INTO tblDocumentContent VALUES ('.$id.', '.$version.')');
        }
        $dms->documents[$id] = $doc;
    }
    $library = new LetoDMS_AILibrary($store, $client, $dms);
    foreach ($fixtures as $id => $versions) $library->index($user, $id, max(array_keys($versions)));
    $dms->documents[11]->access = 0;
    $store->execute('DELETE FROM tblModuleAIFingerprints WHERE document_id = 8');
    $matches = (new LetoDMS_AIDuplicates($store, $client, $dms))->find($user, 7, 2);
    $kinds = array_column($matches, 'kind', 'document_id');
    aiCheck($kinds[8] === 'Identical file' && $kinds[9] === 'Same extracted text' && strpos($kinds[10], 'Similar content') === 0 && !isset($kinds[11]) && !isset($kinds[12]), 'Exact, normalized, similar, unrelated, and restricted duplicates');
    $metadata = new LetoDMS_AIMetadata($store, $client, $dms); $doc = $dms->documents[7];
    $suggestion = $metadata->generate($user, 7, 2, 'test');
    $db->getResult("UPDATE tblDocuments SET keywords = 'external edit' WHERE id = 7");
    aiReject(function () use ($metadata, $user, $suggestion) { $metadata->review($user, 7, 2, $suggestion, 'apply', true, true); });
    $metadata->review($user, 7, 2, $suggestion, 'dismiss', false, false);
    $suggestion = $metadata->generate($user, 7, 2, 'test');
    $doc->access = 1; aiReject(function () use ($metadata, $user, $suggestion) { $metadata->review($user, 7, 2, $suggestion, 'apply', true, true); }); $doc->access = 2;
    $db->getResult('UPDATE tblDocuments SET locked = 9 WHERE id = 7');
    aiReject(function () use ($metadata, $user, $suggestion) { $metadata->review($user, 7, 2, $suggestion, 'apply', true, true); });
    $db->getResult('UPDATE tblDocuments SET locked = -1 WHERE id = 7');
    $db->getResult('INSERT INTO tblDocumentContent VALUES (7, 3)');
    aiReject(function () use ($metadata, $user, $suggestion) { $metadata->review($user, 7, 2, $suggestion, 'apply', true, true); });
    $db->getResult('DELETE FROM tblDocumentContent WHERE document = 7 AND version = 3');
    $metadata->review($user, 7, 2, $suggestion, 'apply', true, false);
    aiCheck($doc->getName() === 'Contract for review' && $doc->getKeywords() === 'external edit', 'Only selected metadata fields applied');
    aiReject(function () use ($metadata, $user, $suggestion) { $metadata->review($user, 7, 2, $suggestion, 'apply', true, false); });
    $review = $store->rows('SELECT state, reviewed_by, review_text FROM tblModuleAISuggestions WHERE suggestion_id = '.$store->quote($suggestion))[0];
    aiCheck($review['state'] === 'applied' && (int)$review['reviewed_by'] === 9 && strpos($review['review_text'], 'external edit') !== false, 'Metadata review audit preserved');
    aiReject(function () { LetoDMS_AIMetadata::payload('{"title":"Test","keywords":[],"expiry_date":"2026-02-30"}'); });
    aiReject(function () { LetoDMS_AIMetadata::payload('{"title":"<script>untrusted</script>","keywords":[]}'); });
    $queue = new LetoDMS_AIQueue($store); $enabled = function () { return true; };
    $job = $queue->enqueue($user, $client, $dms, 7, 2, 'test');
    $queue->runOne($dms, $client, $library, $enabled);
    aiCheck($queue->jobs($user, 7, 2)[0]['state'] === 'complete' && count($store->rows('SELECT * FROM tblModuleAIChanges')) === 1, 'Queued processing creates automatic change summary');
    $queue->enqueue($user, $client, $dms, 7, 2, 'test'); $queue->runOne($dms, $client, $library, $enabled);
    aiCheck(count($store->rows('SELECT * FROM tblModuleAIChanges')) === 1, 'Reprocessing does not duplicate change summaries');
    $options['monthly_request_limit'] = '1'; $options['embedding_model'] = 'changed-model'; $store->saveOptions($user, $options, $client);
    $queue->enqueue($user, $client, $dms, 7, 2, 'test'); $queue->runOne($dms, $client, $library, $enabled);
    $paused = $store->rows('SELECT state, attempts, available_at, message FROM tblModuleAIJobs')[0];
    aiCheck($paused['state'] === 'pending' && (int)$paused['attempts'] === 0 && (int)$paused['available_at'] > time() && strpos($paused['message'], 'limit resets') !== false, 'Quota limits pause queued work without consuming retry attempts');
    $options['monthly_request_limit'] = '0'; $options['embedding_model'] = 'test-embedding'; $store->saveOptions($user, $options, $client);
    $queue->manage($user, $client, $dms, $job, 'retry'); aiCheck($queue->jobs($user, 7, 2)[0]['state'] === 'pending', 'Dashboard retry resets completed job');
    $store->execute('UPDATE tblModuleAIJobs SET document_id = 999 WHERE job_id = '.$store->quote($job));
    $queue->manage($user, $client, $dms, $job, 'cancel'); aiCheck($store->rows('SELECT state FROM tblModuleAIJobs')[0]['state'] === 'cancelled', 'Deleted document job can be cancelled');
    aiReject(function () use ($queue, $user, $client, $dms, $job) { $queue->manage($user, $client, $dms, $job, 'retry'); });
    $nonAdmin = new class extends IntegrationUser { public function isAdmin() { return false; } };
    aiReject(function () use ($queue, $nonAdmin, $client, $dms, $job) { $queue->manage($nonAdmin, $client, $dms, $job, 'cancel'); });
    aiReject(function () use ($usage, $nonAdmin) { $usage->report($nonAdmin); });
    // Simulate a request-period reset between two embedding batches. Partial
    // vectors must resume without publishing an incomplete searchable document.
    $db->getResult("INSERT INTO tblDocuments VALUES (13, 'Long contract', 'original', -1)");
    $db->getResult('INSERT INTO tblDocumentContent VALUES (13, 1)');
    $long = new AdvancedDocument(13, $db); $long->versions[1] = new IntegrationContent('13-1.txt', 1); $dms->documents[13] = $long;
    file_put_contents($temp.'13-1.txt', str_repeat('Contract clause with obligations. ', 800));
    $options['monthly_request_limit'] = '1'; $store->saveOptions($user, $options, $client);
    $store->execute('UPDATE tblModuleAIQuota SET call_count = 0');
    $beforeCalls = count($client->requests);
    aiReject(function () use ($library, $user) { $library->index($user, 13, 1); });
    aiCheck(count($store->rows('SELECT * FROM tblModuleAIEmbeddingCache WHERE document_id = 13')) === 8 && !$store->rows('SELECT * FROM tblModuleAIChunks WHERE document_id = 13'), 'Partial vectors checkpointed but not published');
    $store->execute('UPDATE tblModuleAIQuota SET call_count = 0');
    $library->index($user, 13, 1);
    aiCheck(count($client->requests) - $beforeCalls === 2 && !$store->rows('SELECT * FROM tblModuleAIEmbeddingCache WHERE document_id = 13'), 'Quota retry resumes only remaining vector batch and clears checkpoint');
    $options['monthly_request_limit'] = '0'; $store->saveOptions($user, $options, $client);
    $queue->enqueue($user, $client, $dms, 13, 1, 'test'); $queue->runOne($dms, $client, $library, $enabled);
    aiCheck(count($store->rows('SELECT * FROM tblModuleAISuggestions WHERE document_id = 13')) === 1 && $queue->jobs($user, 13, 1)[0]['state'] === 'complete', 'Automatic metadata generated for a newly queued document');
    // Published OCR text can serve web reads without rerunning OCR, while still
    // validating the file hash and current version access.
    $db->getResult("INSERT INTO tblDocuments VALUES (14, 'Cached scan', '', -1)");
    $db->getResult('INSERT INTO tblDocumentContent VALUES (14, 1)');
    $scan = new AdvancedDocument(14, $db);
    $scan->versions[1] = new class('14-1.pdf', 1) extends IntegrationContent { public function getMimeType() { return 'application/pdf'; } };
    $dms->documents[14] = $scan; file_put_contents($temp.'14-1.pdf', 'fixture scanned PDF');
    $store->execute('INSERT INTO tblModuleAIFingerprints VALUES (14, 1, '.$store->quote(hash_file('sha256', $temp.'14-1.pdf')).', '.$store->quote(hash('sha256', 'Scanned contract')).', '.$store->quote(LetoDMS_AIExtractor::signature($store->options())).')');
    $store->execute('INSERT INTO tblModuleAIChunks VALUES (14, 1, 0, 1, '.$store->quote('Scanned ').", '[1,0]', ".$store->quote(LetoDMS_AILibrary::signature($options)).')');
    $store->execute('INSERT INTO tblModuleAIChunks VALUES (14, 1, 1, 1, '.$store->quote('contract').", '[1,0]', ".$store->quote(LetoDMS_AILibrary::signature($options)).')');
    $reader = new AdvancedAI($db, $settings, $store);
    aiCheck($reader->extract($dms, $user, 14, 1)[0]['text'] === 'Scanned contract', 'Published text cache reconstructs sections without invoking OCR');
    $scan->versions[1]->access = 0;
    aiReject(function () use ($reader, $dms, $user) { $reader->extract($dms, $user, 14, 1); });
    $report = $usage->report($user); aiCheck(count($report) > 0, 'Usage report populated across inference and embedding calls');
    ob_start(); LetoDMS_AI_DocumentPanel::show($dms, $doc, $user, array('test' => 'Test'), true); $html = ob_get_clean();
    aiCheck(strpos($html, 'Metadata') !== false && strpos($html, 'Duplicates') !== false && strpos($html, 'Automatic change summary') !== false, 'New document tabs and cached change summaries rendered');
    $doc->versions[1]->access = 0;
    ob_start(); LetoDMS_AI_DocumentPanel::show($dms, $doc, $user, array('test' => 'Test'), true); $html = ob_get_clean();
    aiCheck(strpos($html, 'Automatic change summary:') === false, 'Cached changes hidden when previous version access is revoked');
    if ($previewDirectory = getenv('LETODMS_AI_PREVIEW_DIR')) {
        $metadata->generate($user, 7, 2, 'test');
        require_once __DIR__.'/../../../inc/inc.ClassViewCommon.php';
        require_once __DIR__.'/../views/Settings.php';
        class AdvancedPreviewSettings extends LetoDMS_Module_View_AISettings {
            public function htmlStartPage($title = '', $bodyClass = '') {}
            public function htmlEndPage() {}
            public function globalNavigation($folder = null) {}
            public function contentStart() { echo '<main class="container-fluid">'; }
            public function contentEnd() { echo '</main>'; }
            public function contentHeading($heading, $noescape = false) { echo '<h2>'.htmlspecialchars($heading).'</h2>'; }
        }
        $root = dirname(__DIR__, 3);
        $styles = file_get_contents($root.'/styles/bootstrap/bootstrap/css/bootstrap.css').file_get_contents($root.'/styles/bootstrap/application.css');
        $wrapper = '<!doctype html><html><head><meta charset="utf-8"><style>'.$styles.'</style></head><body>';
        $params = array('user' => $user, 'saved' => false, 'providers' => array(), 'choices' => $client->providers(), 'options' => $store->options(), 'worker_status' => $store->workerStatus($user), 'usage_report' => $usage->report($user));
        $_GET = array('panel' => 'jobs');
        ob_start(); (new AdvancedPreviewSettings($params))->show(); $preview = ob_get_clean();
        file_put_contents($previewDirectory.'/jobs.html', $wrapper.$preview.'</body></html>');
        $_GET = array();
        ob_start(); (new AdvancedPreviewSettings($params))->show(); $preview = ob_get_clean();
        file_put_contents($previewDirectory.'/settings.html', $wrapper.$preview.'</body></html>');
        $doc->versions[1]->access = 1; $_GET = array('ai_done' => 'metadata');
        ob_start(); LetoDMS_AI_DocumentPanel::show($dms, $doc, $user, array('test' => 'Test'), true); $preview = ob_get_clean();
        file_put_contents($previewDirectory.'/document.html', $wrapper.'<main class="container-fluid">'.$preview.'</main></body></html>');
        $_GET = array();
        echo 'Previews exported to '.$previewDirectory."\n";
    }
    aiCheck($module->uninstall($db, 'sqlite'), 'Advanced module uninstall');
    echo "Advanced AI feature integration checks passed.\n";
} finally {
    foreach (glob($temp.'*') as $file) unlink($file); rmdir($temp);
    putenv($oldProfiles === false ? 'LETODMS_AI_PROVIDERS' : 'LETODMS_AI_PROVIDERS='.$oldProfiles);
    putenv($oldKey === false ? 'LETODMS_AI_ADVANCED_KEY' : 'LETODMS_AI_ADVANCED_KEY='.$oldKey);
}
