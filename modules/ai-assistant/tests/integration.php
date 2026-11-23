<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Real SQL, deterministic AI responses, no credentials/network needed.
// php -d extension=pdo_sqlite modules/ai-assistant/tests/integration.php
require_once __DIR__.'/../../../LetoDMS_Core/Core/inc.DBAccessPDO.php';
require_once __DIR__.'/../inc/Assistant.php'; require_once __DIR__.'/../inc/ProviderSettings.php';
require_once __DIR__.'/../inc/Library.php'; require_once __DIR__.'/../inc/Queue.php'; require_once __DIR__.'/../inc/Upload.php';
require_once __DIR__.'/../Module.php';
define('M_READ', 1);
function aiCheck($value, $message) { if (!$value) throw new Exception($message); }
function aiReject($callback) { try { $callback(); } catch (RuntimeException $e) { return; } throw new Exception('Expected rejection'); }
class IntegrationUser {
    public $disabled = false; public $permission = true;
    public function getID() { return 9; }
    public function isAdmin() { return true; }
    public function isDisabled() { return $this->disabled; }
    public function hasPermission($name) { return $this->permission; }
}
class IntegrationContent {
    public $access = 1; private $path; private $version;
    public function __construct($path, $version) { $this->path = $path; $this->version = $version; }
    public function getAccessMode($user) { return $this->access; }
    public function getPath() { return $this->path; }
    public function getMimeType() { return 'text/plain'; }
    public function getVersion() { return $this->version; }
}
class IntegrationDocument {
    public $access = 1; public $versions = array(); private $id;
    public function __construct($id) { $this->id = $id; }
    public function getID() { return $this->id; }
    public function getName() { return 'Document '.$this->id; }
    public function getAccessMode($user) { return $this->access; }
    public function getContentByVersion($version) { return isset($this->versions[$version]) ? $this->versions[$version] : false; }
    public function getLatestContent() { return $this->versions[max(array_keys($this->versions))]; }
    public function getContent() { return array_values($this->versions); }
}
class IntegrationDMS {
    public $contentDir; public $documents = array(); public $user; public $db;
    public function getDB() { return $this->db; }
    public function getDocument($id) { return isset($this->documents[$id]) ? $this->documents[$id] : false; }
    public function getUser($id) { return $id === 9 || (int)$id === 9 ? $this->user : false; }
}
class IntegrationAI extends LetoDMS_AIAssistant {
    public $requests = array(); public $fail = false; public $badCitation = false; public $during = null;
    protected function transport($endpoint, $headers, $body) {
        if ($this->during) { $callback = $this->during; $this->during = null; $callback(); }
        if ($this->fail) throw new RuntimeException('Simulated temporary provider failure.');
        $body = json_decode($body, true); $this->requests[] = $body;
        if (isset($body['input'])) {
            $data = array();
            foreach ($body['input'] as $i => $text) $data[] = array('index' => $i, 'embedding' => stripos($text, 'contract') !== false ? array(1.0, 0.1, 0.2) : array(0.1, 1.0, 0.2));
            return json_encode(array('data' => array_reverse($data)));
        }
        if (strpos($body['messages'][0]['content'], 'JSON object') !== false) {
            $answer = array('answer' => 'The contract expires in June [1].', 'citations' => array(1));
            if (strpos($body['messages'][1]['content'], 'Compare source 1') !== false) $answer = array('answer' => 'The expiry changes between versions [1] [2].', 'citations' => array(1, 2));
            if ($this->badCitation) $answer['citations'] = array(999);
            $text = json_encode($answer);
        } else $text = 'A summary and suggested keywords.';
        return json_encode(array('choices' => array(array('message' => array('content' => $text)))));
    }
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new Exception('Enable the PDO SQLite extension for integration tests.');
$oldProfiles = getenv('LETODMS_AI_PROVIDERS'); $oldKey = getenv('LETODMS_AI_INTEGRATION_KEY');
$temp = sys_get_temp_dir().'/letodms-ai-integration-'.uniqid().'/'; mkdir($temp);
try {
    putenv('LETODMS_AI_INTEGRATION_KEY=offline-key');
    putenv('LETODMS_AI_PROVIDERS='.json_encode(array('test' => array('label' => 'Test', 'protocol' => 'openai', 'endpoint' => 'https://example.com/chat', 'model' => 'test-chat', 'key_env' => 'LETODMS_AI_INTEGRATION_KEY'))));
    $db = new LetoDMS_Core_DatabaseAccess('sqlite', '', '', '', ':memory:'); $db->connect();
    $db->getResult('CREATE TABLE tblPermissions (id INTEGER PRIMARY KEY, name VARCHAR(100), description TEXT)');
    $db->getResult('CREATE TABLE tblRolePermissions (roleID INTEGER, permissionID INTEGER)');
    $module = new LetoDMS_AIAssistant_Module(); aiCheck($module->install($db, 'sqlite'), 'Module install');
    $store = new LetoDMS_AIStore($db, 'sqlite'); $store->initialize();
    $providerSettings = new LetoDMS_AIProviderSettings($db, 'test-server-key');
    $client = new IntegrationAI($db, $providerSettings);
    $user = new IntegrationUser(); $dms = new IntegrationDMS(); $dms->contentDir = $temp; $dms->user = $user; $dms->db = $db;
    foreach (array(7 => array(1 => 'Contract expires in May.', 2 => 'Contract expires in June.'), 8 => array(1 => 'CONFIDENTIAL forbidden policy text.')) as $id => $versions) {
        $doc = new IntegrationDocument($id);
        foreach ($versions as $version => $text) { $path = $id.'-'.$version.'.txt'; file_put_contents($temp.$path, $text); $doc->versions[$version] = new IntegrationContent($path, $version); }
        $dms->documents[$id] = $doc;
    }
    $options = array_merge($store->options(), array('automatic' => '1', 'analysis_provider' => 'test', 'embedding_provider' => 'test', 'embedding_endpoint' => 'https://example.com/embeddings', 'embedding_model' => 'test-vector'));
    $store->saveOptions($user, $options, $client); aiCheck($store->options() === $options, 'Settings SQL round trip');
    $library = new LetoDMS_AILibrary($store, $client, $dms);
    aiCheck($library->index($user, 7, 1) === 1, 'Index old version');
    $library->index($user, 7, 2); $library->index($user, 8, 1);
    $dms->documents[8]->access = 0;
    $results = $library->search($user, 'contract');
    aiCheck(count($results) === 1 && $results[0]['document_id'] === 7 && $results[0]['version'] === 2, 'Search excludes restricted and old versions');
    $client->requests = array(); $answer = $library->ask($user, 'test', 'When does the contract expire?');
    aiCheck(isset($answer['sources'][1]) && strpos(json_encode($client->requests), 'CONFIDENTIAL') === false, 'Q&A does not send inaccessible text');
    aiCheck(count($store->rows('SELECT * FROM tblModuleAIInteractions')) === 1, 'Interaction audited');
    $answer = $library->ask($user, 'test', 'When?', 7, 1); aiCheck($answer['sources'][1]['version'] === 1, 'Explicit historical version Q&A');
    $answer = $library->compare($user, 'test', 7, 1, 2); aiCheck(count($answer['sources']) === 2, 'Version comparison citations');
    require_once __DIR__.'/../views/DocumentPanel.php';
    function createHiddenFieldWithKey($name) { return '<input type="hidden" name="formtoken" value="test-token">'; }
    $doc = $dms->documents[7]; $comparisonId = $answer['interaction_id'];
    $saved = LetoDMS_AI_DocumentPanel::interaction($db, $user, $doc, $doc->versions, $comparisonId);
    aiCheck(strpos($saved['answer'], 'expiry changes') !== false, 'Document result can be loaded by requesting user');
    aiReject(function () use ($db, $user, $doc, $comparisonId) { LetoDMS_AI_DocumentPanel::interaction($db, $user, $doc, array(2 => $doc->versions[2]), $comparisonId); });
    $otherUser = new class extends IntegrationUser { public function getID() { return 10; } };
    aiReject(function () use ($db, $otherUser, $doc, $comparisonId) { LetoDMS_AI_DocumentPanel::interaction($db, $otherUser, $doc, $doc->versions, $comparisonId); });
    $_GET['ai_result'] = $comparisonId;
    ob_start(); LetoDMS_AI_DocumentPanel::show($dms, $doc, $user, array('test' => 'Test'), true); $panel = ob_get_clean();
    aiCheck(strpos($panel, 'Ask AI') !== false && strpos($panel, 'Compare versions') !== false && strpos($panel, 'name="documentid" value="7"') !== false && strpos($panel, 'Document ID') === false, 'Document-specific tools use hidden ID and version selectors');
    aiCheck(strpos($panel, 'passage=1') !== false, 'Result cites source links on the document page');
    unset($_GET['ai_result']);
    $doc->versions[1]->access = 0;
    ob_start(); LetoDMS_AI_DocumentPanel::show($dms, $doc, $user, array('test' => 'Test'), true); $panel = ob_get_clean();
    aiCheck(strpos($panel, '>Version 1<') === false && strpos($panel, 'Upload another version') !== false, 'Version selectors exclude restricted versions');
    $doc->versions[1]->access = 1;
    aiReject(function () use ($library, $user) { $library->compare($user, 'test', 7, 2, 1); });
    $client->badCitation = true; aiReject(function () use ($library, $user) { $library->ask($user, 'test', 'When?', 7, 2); }); $client->badCitation = false;
    $dms->documents[7]->versions[1]->access = 0;
    $before = count($client->requests); aiReject(function () use ($library, $user) { $library->compare($user, 'test', 7, 1, 2); });
    aiCheck(count($client->requests) === $before, 'Restricted comparison does not contact provider'); $dms->documents[7]->versions[1]->access = 1;
    $client->fail = true; aiReject(function () use ($library, $user) { $library->index($user, 7, 2); }); $client->fail = false;
    aiCheck(count($library->search($user, 'contract')) === 1, 'Failed reindex preserves old index');
    $changed = $options; $changed['embedding_model'] = 'different-model'; $store->saveOptions($user, $changed, $client);
    aiCheck($library->search($user, 'contract') === array(), 'Different model excludes stale vectors'); $store->saveOptions($user, $options, $client);
    $queue = new LetoDMS_AIQueue($store); $enabled = function () { return true; };
    $job = $queue->enqueue($user, $client, $dms, 7, 2, 'test'); $queue->enqueue($user, $client, $dms, 7, 2, 'test');
    aiCheck(count($store->rows('SELECT * FROM tblModuleAIJobs')) === 1, 'Pending job deduplication');
    $client->during = function () use ($queue, $dms, $client, $library, $enabled) { aiCheck(!$queue->runOne($dms, $client, $library, $enabled), 'Second worker cannot claim an active lease'); };
    $queue->runOne($dms, $client, $library, $enabled);
    aiCheck($queue->jobs($user, 7, 2)[0]['state'] === 'complete', 'Worker saves analysis and index');
    aiCheck($store->workerStatus($user)['last_seen'] > 0, 'Worker heartbeat recorded');
    aiCheck($store->options() === $options, 'Worker metadata must not alter processing options');
    $queue->enqueue($user, $client, $dms, 7, 2, 'test'); $user->disabled = true;
    for ($attempt = 0; $attempt < 3; $attempt++) { $store->execute('UPDATE tblModuleAIJobs SET available_at = 0'); $queue->runOne($dms, $client, $library, $enabled); }
    aiCheck($queue->jobs($user, 7, 2)[0]['state'] === 'failed', 'Disabled uploader fails and retries are bounded'); $user->disabled = false;
    $queue->enqueue($user, $client, $dms, 7, 2, 'test'); $store->execute("UPDATE tblModuleAIJobs SET state = 'running', available_at = 0, attempts = 1");
    $queue->runOne($dms, $client, $library, $enabled); aiCheck($queue->jobs($user, 7, 2)[0]['state'] === 'complete', 'Expired worker lease recovered');
    $queue->enqueue($user, $client, $dms, 7, 2, 'test');
    aiCheck(!$queue->runOne($dms, $client, $library, function () { return false; }), 'Disabled module does not process jobs');
    $settings = (object)array('_rootDir' => dirname(__DIR__, 3).'/', '_dbDriver' => 'sqlite', '_encryptionKey' => 'test-server-key');
    require_once $settings->_rootDir.'inc/inc.ClassModuleManager.php';
    $manager = new LetoDMS_ModuleManager($db, $settings->_rootDir.'modules', 'sqlite'); $manager->initialize();
    $db->getResult("INSERT INTO tblModules VALUES ('ai-assistant', '0.3.0', 1, 0)");
    $doc = $dms->documents[7]; $doc->versions[3] = new IntegrationContent('7-3.txt', 3); file_put_contents($temp.'7-3.txt', 'New contract.');
    $before = count($client->requests); letoDMSQueueAIUpload($db, $dms, $settings, $user, $doc, $doc->versions[3]);
    aiCheck(count($queue->jobs($user, 7, 3)) === 1 && count($client->requests) === $before, 'Upload queues without contacting AI');
    aiCheck($module->uninstall($db, 'sqlite'), 'Module uninstall');
    echo "AI SQLite integration checks passed.\n";
} finally {
    foreach (glob($temp.'*') as $file) unlink($file); rmdir($temp);
    putenv($oldProfiles === false ? 'LETODMS_AI_PROVIDERS' : 'LETODMS_AI_PROVIDERS='.$oldProfiles);
    putenv($oldKey === false ? 'LETODMS_AI_INTEGRATION_KEY' : 'LETODMS_AI_INTEGRATION_KEY='.$oldKey);
}
