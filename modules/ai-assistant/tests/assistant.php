<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Offline regression checks: php modules/ai-assistant/tests/assistant.php
require_once __DIR__.'/../inc/Assistant.php';
define('M_READ', 1);
function verify($condition, $message) {
    if (!$condition) throw new Exception($message);
}
function rejects($callback) {
    try { $callback(); } catch (RuntimeException $e) { return; }
    throw new Exception('Expected rejection');
}
class FakeAIClient extends LetoDMS_AIAssistant {
    public $sent;
    public $reply = '';
    protected function transport($endpoint, $headers, $body) {
        $this->sent = array($endpoint, $headers, json_decode($body, true));
        return $this->reply;
    }
    public function callProvider($profile, $text) { return $this->request($profile, $text); }
}
class FakeAIDB {
    public $sql;
    public function qstr($s) { return "'".str_replace("'", "''", $s)."'"; }
    public function getResult($sql) { $this->sql = $sql; return true; }
}
class FakeAIContent {
    public $access = 1;
    public function getAccessMode($user) { return $this->access; }
    public function getMimeType() { return 'text/plain'; }
    public function getPath() { return 'test.txt'; }
}
class FakeAIDocument {
    public $access = 1;
    public $content;
    public function getAccessMode($user) { return $this->access; }
    public function getContentByVersion($version) { return $version === 2 ? $this->content : false; }
}
class FakeAIDMS {
    public $contentDir;
    public $document;
    public function getDocument($id) { return $id === 7 ? $this->document : false; }
}
class FakeAIUser { public function getID() { return 9; } }
$oldProfiles = getenv('LETODMS_AI_PROVIDERS');
$oldKey = getenv('LETODMS_AI_TEST_KEY');
$temp = sys_get_temp_dir().'/letodms-ai-'.uniqid().'/';
mkdir($temp);
try {
    putenv('LETODMS_AI_TEST_KEY=offline-test-key');
    $profile = array('label' => 'Test provider', 'protocol' => 'openai', 'endpoint' => 'https://example.com/v1/chat/completions', 'model' => 'test-model', 'key_env' => 'LETODMS_AI_TEST_KEY');
    putenv('LETODMS_AI_PROVIDERS='.json_encode(array('test' => $profile)));
    $db = new FakeAIDB();
    $client = new FakeAIClient($db);
    $client->reply = json_encode(array('choices' => array(array('message' => array('content' => 'Summary and keywords')))));
    verify($client->callProvider($profile, 'Document text') === 'Summary and keywords', 'OpenAI response');
    verify($client->sent[2]['messages'][0]['role'] === 'system', 'System instruction');
    verify($client->sent[2]['messages'][1]['content'] === 'Document text', 'Document payload');
    $anthropic = $profile; $anthropic['protocol'] = 'anthropic';
    $client->reply = json_encode(array('content' => array(array('type' => 'text', 'text' => 'Claude summary'))));
    verify($client->callProvider($anthropic, 'Document text') === 'Claude summary', 'Anthropic response');
    verify(isset($client->sent[2]['system']) && $client->sent[2]['max_tokens'] === 1200, 'Anthropic request');
    $invalid = $profile; $invalid['endpoint'] = 'http://example.com';
    rejects(function () use ($client, $invalid) { $client->callProvider($invalid, 'text'); });
    $client->reply = '{"error":"provider failure"}';
    rejects(function () use ($client, $profile) { $client->callProvider($profile, 'text'); });
    $dms = new FakeAIDMS(); $dms->contentDir = $temp;
    $dms->document = new FakeAIDocument(); $dms->document->content = new FakeAIContent();
    $user = new FakeAIUser();
    $dms->document->access = 0;
    rejects(function () use ($client, $dms, $user) { $client->analyze($dms, $user, 7, 2, 'test'); });
    $dms->document->access = 1; $dms->document->content->access = 0;
    rejects(function () use ($client, $dms, $user) { $client->analyze($dms, $user, 7, 2, 'test'); });
    $dms->document->content->access = 1;
    rejects(function () use ($client, $dms, $user) { $client->content($dms, $user, 7, 3); });
    file_put_contents($temp.'test.txt', str_repeat('x', 60001));
    $client->sent = null;
    rejects(function () use ($client, $dms, $user) { $client->analyze($dms, $user, 7, 2, 'test'); });
    verify($client->sent === null, 'Oversized document must not reach provider');
    file_put_contents($temp.'test.txt', 'Document text');
    $client->reply = json_encode(array('choices' => array(array('message' => array('content' => 'Summary')))));
    $client->analyze($dms, $user, 7, 2, 'test');
    verify(strpos($db->sql, "VALUES (7, 2, 9, 'test', 'test-model', 'Summary',") !== false, 'Version-specific persistence and provenance');
    echo "AI assistant offline checks passed.\n";
} finally {
    if (file_exists($temp.'test.txt')) unlink($temp.'test.txt');
    rmdir($temp);
    putenv($oldProfiles === false ? 'LETODMS_AI_PROVIDERS' : 'LETODMS_AI_PROVIDERS='.$oldProfiles);
    putenv($oldKey === false ? 'LETODMS_AI_TEST_KEY' : 'LETODMS_AI_TEST_KEY='.$oldKey);
}
