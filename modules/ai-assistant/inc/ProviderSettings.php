<?php
/** Module-owned provider configuration. Secrets never leave this service in UI data. */
class LetoDMS_AIProviderSettings {
    private $db;
    private $secret;
    public function __construct($db, $secret) {
        $this->db = $db;
        $this->secret = (string)$secret;
    }
    public function initialize() {
        // Idempotent upgrade for installations of 0.1.0, preserving saved analysis.
        if (!$this->db->getResult('CREATE TABLE IF NOT EXISTS tblModuleAIProviders (
            provider_id VARCHAR(80) PRIMARY KEY, label VARCHAR(160) NOT NULL,
            protocol VARCHAR(20) NOT NULL, endpoint VARCHAR(2048) NOT NULL,
            model VARCHAR(255) NOT NULL, encrypted_key TEXT NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1)'))
            throw new RuntimeException('Could not initialize AI provider settings.');
    }
    private function rows() {
        $rows = $this->db->getResultArray('SELECT provider_id, label, protocol, endpoint, model, encrypted_key, enabled FROM tblModuleAIProviders ORDER BY label');
        if (!is_array($rows)) throw new RuntimeException('Could not read AI provider settings.');
        return $rows;
    }
    private function admin($user) {
        if (!$user->isAdmin()) throw new RuntimeException('Only administrators can manage AI providers.');
    }
    public function listing($user) {
        $this->admin($user);
        $rows = $this->rows();
        foreach ($rows as &$row) {
            $row['has_key'] = $row['encrypted_key'] !== '';
            unset($row['encrypted_key']);
        }
        return $rows;
    }
    public function profiles() {
        $profiles = array();
        foreach ($this->rows() as $row) {
            // Include disabled profiles so they suppress matching legacy environment entries.
            $profiles[$row['provider_id']] = array(
                'label' => $row['label'], 'protocol' => $row['protocol'],
                'endpoint' => $row['endpoint'], 'model' => $row['model'],
                'encrypted_key' => $row['encrypted_key'], 'enabled' => (bool)$row['enabled']
            );
        }
        return $profiles;
    }
    private function encryptionKey() {
        if (trim($this->secret) === '')
            throw new RuntimeException('The saved LetoDMS encryption key is empty. Open Admin Tools > Settings > System > Authentication, save the encryption key, and verify that settings.xml is writable by the web server.');
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt') || !function_exists('openssl_get_cipher_methods'))
            throw new RuntimeException('The web server PHP runtime cannot use OpenSSL encryption functions. Enable PHP OpenSSL and check whether openssl_encrypt or openssl_decrypt is listed in disable_functions, then restart the PHP service.');
        if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true))
            throw new RuntimeException('The web server OpenSSL installation does not support AES-256-GCM, which is required to store AI API keys.');
        return hash('sha256', 'letodms-ai-provider:'.$this->secret, true);
    }
    public function decrypt($ciphertext) {
        $key = $this->encryptionKey();
        $data = base64_decode($ciphertext, true);
        if ($data === false || strlen($data) < 29) throw new RuntimeException('The stored AI key cannot be read. Re-enter it in provider settings.');
        $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16), 'letodms-ai');
        if ($plain === false) throw new RuntimeException('The stored AI key cannot be read. Re-enter it in provider settings.');
        return $plain;
    }
    private function encrypt($plain) {
        $key = $this->encryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'letodms-ai');
        if ($cipher === false) throw new RuntimeException('Could not protect the API key.');
        return base64_encode($iv.$tag.$cipher);
    }
    public function save($user, $input) {
        $this->admin($user);
        foreach (array('provider_id', 'label', 'protocol', 'endpoint', 'model', 'api_key') as $name) {
            if (!isset($input[$name]) || !is_string($input[$name])) throw new RuntimeException('Complete all provider fields.');
            $input[$name] = trim($input[$name]);
            if (preg_match('/[\x00-\x1f\x7f]/', $input[$name])) throw new RuntimeException('Provider fields must not contain control characters.');
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $input['provider_id'])) throw new RuntimeException('Use a provider ID starting with a lowercase letter, followed by lowercase letters, numbers, hyphens, or underscores.');
        if ($input['label'] === '' || strlen($input['label']) > 160 || $input['model'] === '' || strlen($input['model']) > 255)
            throw new RuntimeException('Enter a provider name (up to 160 bytes) and model ID (up to 255 bytes).');
        if (!in_array($input['protocol'], array('openai', 'anthropic'), true)) throw new RuntimeException('Choose a supported API format.');
        $url = parse_url($input['endpoint']);
        if (strlen($input['endpoint']) > 2048 || !filter_var($input['endpoint'], FILTER_VALIDATE_URL) || !$url || !isset($url['scheme'], $url['host']) || $url['scheme'] !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']))
            throw new RuntimeException('Enter a full HTTPS API endpoint without credentials, query parameters, or a fragment.');
        $existing = null;
        foreach ($this->rows() as $row) if ($row['provider_id'] === $input['provider_id']) $existing = $row;
        if (strlen($input['api_key']) > 8192) throw new RuntimeException('The API key is too long.');
        if ($input['api_key'] === '') {
            if (!$existing) throw new RuntimeException('Enter an API key for this provider.');
            if ($existing['endpoint'] !== $input['endpoint'] || $existing['protocol'] !== $input['protocol'])
                throw new RuntimeException('Re-enter the API key when changing the endpoint or API format.');
            $cipher = $existing['encrypted_key'];
        } else $cipher = $this->encrypt($input['api_key']);
        $values = array('label' => $input['label'], 'protocol' => $input['protocol'], 'endpoint' => $input['endpoint'], 'model' => $input['model'], 'encrypted_key' => $cipher);
        $enabled = isset($input['enabled']) && $input['enabled'] === '1' ? 1 : 0;
        $id = $this->db->qstr($input['provider_id']);
        if ($existing) {
            $set = array();
            foreach ($values as $name => $value) $set[] = $name.' = '.$this->db->qstr($value);
            $sql = 'UPDATE tblModuleAIProviders SET '.implode(', ', $set).', enabled = '.$enabled.' WHERE provider_id = '.$id;
        } else {
            $quoted = array();
            foreach ($values as $value) $quoted[] = $this->db->qstr($value);
            $sql = 'INSERT INTO tblModuleAIProviders (provider_id, label, protocol, endpoint, model, encrypted_key, enabled) VALUES ('.$id.', '.implode(', ', $quoted).', '.$enabled.')';
        }
        if (!$this->db->getResult($sql)) throw new RuntimeException('Could not save the provider settings.');
    }
    public function remove($user, $id) {
        $this->admin($user);
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $id)) throw new RuntimeException('Invalid provider ID.');
        if (!$this->db->getResult('DELETE FROM tblModuleAIProviders WHERE provider_id = '.$this->db->qstr($id))) throw new RuntimeException('Could not remove the provider.');
    }
}
