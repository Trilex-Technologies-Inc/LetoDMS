<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../inc/ModelDiscovery.php';
class ModelSettingsFixture {
    public $endpoint = 'https://api.openai.com/v1/chat/completions';
    public function profiles() { return array('saved' => array('protocol' => 'openai', 'endpoint' => $this->endpoint, 'encrypted_key' => 'ciphertext')); }
    public function decrypt($key) { return 'saved-private-key'; }
}
class ModelUserFixture { public $admin = true; public function isAdmin() { return $this->admin; } }
class DiscoveryFixture extends LetoDMS_AIModelDiscovery {
    public $key;
    protected function retrieve($key) {
        $this->key = $key;
        return json_encode(array('data' => array_map(function ($id) { return array('id' => $id); }, array('gpt-4.1-mini', 'gpt-4.1-mini', 'text-embedding-3-small', 'gpt-audio', 'gpt-image-1', 'whisper-1', '<script>alert(1)</script>', 'o3-mini', 'ft:gpt-4.1-mini:custom'))));
    }
}
function modelReject($callback) { try { $callback(); } catch (RuntimeException $e) { return; } throw new Exception('Expected rejection'); }
$settings = new ModelSettingsFixture(); $user = new ModelUserFixture(); $discovery = new DiscoveryFixture($settings);
$models = $discovery->models($user, array('api_key' => 'new-private-key'));
if ($discovery->key !== 'new-private-key' || count($models['chat']) !== 3 || $models['embeddings'] !== array('text-embedding-3-small')) throw new Exception('Model filtering failed');
$models = $discovery->models($user, array('provider_id' => 'saved'));
if ($discovery->key !== 'saved-private-key' || strpos(json_encode($models), 'private-key') !== false) throw new Exception('Saved key lookup/secret isolation failed');
$settings->endpoint = 'https://another-provider.example/chat';
modelReject(function () use ($discovery, $user) { $discovery->models($user, array('provider_id' => 'saved')); });
$user->admin = false;
modelReject(function () use ($discovery, $user) { $discovery->models($user, array('api_key' => 'key')); });
$user->admin = true;
modelReject(function () use ($discovery, $user) { $discovery->models($user, array('api_key' => "key\r\nInjected: header")); });
modelReject(function () { LetoDMS_AIModelDiscovery::parse('{"error":"private upstream response"}'); });
echo "AI model discovery checks passed.\n";
