<?php
require_once __DIR__.'/Assistant.php';
class LetoDMS_AIModelDiscovery {
    private $settings;
    public function __construct($settings) { $this->settings = $settings; }
    public function models($user, $input) {
        if (!$user->isAdmin()) throw new RuntimeException('Only administrators can load provider models.');
        $key = isset($input['api_key']) && is_string($input['api_key']) ? trim($input['api_key']) : '';
        if ($key === '') {
            $id = isset($input['provider_id']) && is_string($input['provider_id']) ? $input['provider_id'] : '';
            $profiles = $this->settings->profiles();
            if (!isset($profiles[$id]) || $profiles[$id]['protocol'] !== 'openai' || $profiles[$id]['endpoint'] !== 'https://api.openai.com/v1/chat/completions')
                throw new RuntimeException('Enter an OpenAI API key or select a saved OpenAI provider.');
            $key = $this->settings->decrypt($profiles[$id]['encrypted_key']);
        }
        if (strlen($key) > 8192 || preg_match('/[\x00-\x20\x7f]/', $key)) throw new RuntimeException('Enter a valid OpenAI API key.');
        return self::parse($this->retrieve($key));
    }
    protected function retrieve($key) {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required.');
        // Fixed destination: never send a saved key to a browser-supplied URL.
        $curl = curl_init('https://api.openai.com/v1/models'); $response = '';
        curl_setopt_array($curl, array(CURLOPT_HTTPHEADER => array('Authorization: Bearer '.$key),
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$response) {
                if (strlen($response) + strlen($chunk) > 2 * 1024 * 1024) return 0;
                $response .= $chunk; return strlen($chunk);
            }));
        $ok = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_errno($curl); curl_close($curl);
        if ($ok === false || $status < 200 || $status >= 300) throw new RuntimeException(LetoDMS_AIAssistant::failureMessage($status, $error, $response));
        return $response;
    }
    public static function parse($response) {
        $data = json_decode($response, true);
        if (!isset($data['data']) || !is_array($data['data'])) throw new RuntimeException('OpenAI returned an invalid model list.');
        $chat = array(); $embeddings = array();
        foreach ($data['data'] as $item) {
            if (!isset($item['id']) || !is_string($item['id']) || strlen($item['id']) > 255 || !preg_match('/^[a-zA-Z0-9_.:\/-]+$/', $item['id'])) continue;
            $id = $item['id'];
            if (strpos($id, 'text-embedding-') === 0) $embeddings[] = $id;
            elseif (preg_match('/^(gpt-|chatgpt-|o[1-9](?:-|$)|ft:(?:gpt-|o[1-9]))/', $id)
                && !preg_match('/audio|realtime|transcrib|tts|image|instruct|codex|search|embedding/', $id)) $chat[] = $id;
        }
        $chat = array_values(array_unique($chat)); $embeddings = array_values(array_unique($embeddings));
        sort($chat, SORT_NATURAL); sort($embeddings, SORT_NATURAL);
        return array('chat' => $chat, 'embeddings' => $embeddings);
    }
}
