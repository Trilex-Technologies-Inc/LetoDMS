<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Run: php modules/ai-assistant/tests/settings.php
require_once __DIR__.'/assistant.php';
require_once __DIR__.'/../inc/ProviderSettings.php';
class SettingsDB extends FakeAIDB {
    public $rows = array();
    public function getResultArray($sql) { return $this->rows; }
}
class SettingsUser {
    private $admin;
    public function __construct($admin) { $this->admin = $admin; }
    public function isAdmin() { return $this->admin; }
}
$db = new SettingsDB();
$settings = new LetoDMS_AIProviderSettings($db, 'offline-server-secret');
$admin = new SettingsUser(true); $reader = new SettingsUser(false);
$input = array('provider_id' => 'hosted', 'label' => 'Hosted AI', 'protocol' => 'openai', 'endpoint' => 'https://example.com/v1/chat/completions', 'model' => 'chat-model', 'api_key' => 'private-test-api-key', 'enabled' => '1');
rejects(function () use ($settings, $reader) { $settings->listing($reader); });
rejects(function () use ($settings, $reader, $input) { $settings->save($reader, $input); });
rejects(function () use ($settings, $reader) { $settings->remove($reader, 'hosted'); });
$settings->initialize();
$settings->save($admin, $input);
verify(strpos($db->sql, 'private-test-api-key') === false, 'API key must not appear in SQL plaintext');
verify(preg_match("/'([A-Za-z0-9+\/=]+)', 1\)$/", $db->sql, $matches) === 1, 'Encrypted key stored');
$cipher = $matches[1];
verify($settings->decrypt($cipher) === 'private-test-api-key', 'Encrypted key round trip');
rejects(function () use ($db, $cipher) { (new LetoDMS_AIProviderSettings($db, 'wrong-secret'))->decrypt($cipher); });
rejects(function () use ($db, $admin, $input) { (new LetoDMS_AIProviderSettings($db, ''))->save($admin, $input); });
$db->rows = array(array('provider_id' => 'hosted', 'label' => 'Hosted AI', 'protocol' => 'openai', 'endpoint' => $input['endpoint'], 'model' => 'chat-model', 'encrypted_key' => $cipher, 'enabled' => 1));
$listing = $settings->listing($admin);
verify($listing[0]['has_key'] && !isset($listing[0]['encrypted_key']), 'Admin listing omits stored secret');
$input['api_key'] = '';
$settings->save($admin, $input);
verify(strpos($db->sql, $cipher) !== false && strpos($db->sql, 'UPDATE') === 0, 'Blank edit preserves key');
$invalid = $input; $invalid['endpoint'] = 'https://different.example.com/v1/chat/completions';
rejects(function () use ($settings, $admin, $invalid) { $settings->save($admin, $invalid); });
foreach (array('http://example.com', 'https://user:pass@example.com', 'https://example.com?key=secret', 'https://example.com/#fragment') as $endpoint) {
    $invalid = $input; $invalid['endpoint'] = $endpoint;
    rejects(function () use ($settings, $admin, $invalid) { $settings->save($admin, $invalid); });
}
$invalid = $input; $invalid['api_key'] = "key\r\nInjected: header";
rejects(function () use ($settings, $admin, $invalid) { $settings->save($admin, $invalid); });
$oldProfiles = getenv('LETODMS_AI_PROVIDERS');
try {
    putenv('LETODMS_AI_PROVIDERS='.json_encode(array('legacy' => array('label' => 'Legacy'), 'hosted' => array('label' => 'Environment duplicate'))));
    $client = new FakeAIClient($db, $settings);
    verify($client->providers() === array('legacy' => 'Legacy', 'hosted' => 'Hosted AI'), 'Stored settings override environment entries');
    $client->reply = json_encode(array('choices' => array(array('message' => array('content' => 'Summary')))));
    verify($client->callProvider($settings->profiles()['hosted'], 'Text') === 'Summary', 'Saved profile can call adapter');
    verify(in_array('Authorization: Bearer private-test-api-key', $client->sent[1], true), 'Stored key used for request');
    $db->rows[0]['enabled'] = 0;
    $client = new FakeAIClient($db, $settings);
    verify(!isset($client->providers()['hosted']), 'Disabled provider suppresses environment duplicate');
    $settings->remove($admin, 'hosted');
    verify(strpos($db->sql, "DELETE FROM tblModuleAIProviders WHERE provider_id = 'hosted'") === 0, 'Remove configured provider');
} finally { putenv($oldProfiles === false ? 'LETODMS_AI_PROVIDERS' : 'LETODMS_AI_PROVIDERS='.$oldProfiles); }
echo "AI provider settings offline checks passed.\n";
