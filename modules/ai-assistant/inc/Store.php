<?php
class LetoDMS_AIStore {
    private $db;
    private $driver;
    public function __construct($db, $driver = 'mysql') { $this->db = $db; $this->driver = strtolower($driver); }
    public function initialize() {
        $tables = array(
            'CREATE TABLE IF NOT EXISTS tblModuleAIUsage (call_id VARCHAR(64) PRIMARY KEY, user_id INTEGER NOT NULL, provider VARCHAR(80) NOT NULL, model VARCHAR(255) NOT NULL, operation VARCHAR(30) NOT NULL, state VARCHAR(20) NOT NULL, input_tokens INTEGER NOT NULL, output_tokens INTEGER NOT NULL, token_source VARCHAR(20) NOT NULL, cost_estimate DECIMAL(18,8) NOT NULL, cost_known INTEGER NOT NULL, created_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS tblModuleAIQuota (bucket VARCHAR(80) PRIMARY KEY, call_count INTEGER NOT NULL, claim_token VARCHAR(64) NOT NULL)',
            'CREATE TABLE IF NOT EXISTS tblModuleAISuggestions (suggestion_id VARCHAR(64) PRIMARY KEY, document_id INTEGER NOT NULL, document_version INTEGER NOT NULL, provider VARCHAR(80) NOT NULL, model VARCHAR(255) NOT NULL, payload_text TEXT NOT NULL, original_text TEXT NOT NULL, state VARCHAR(20) NOT NULL, reviewed_by INTEGER NOT NULL, review_text TEXT NOT NULL, created_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS tblModuleAIChanges (document_id INTEGER NOT NULL, document_version INTEGER NOT NULL, previous_version INTEGER NOT NULL, provider VARCHAR(80) NOT NULL, model VARCHAR(255) NOT NULL, interaction_id VARCHAR(64) NOT NULL, PRIMARY KEY (document_id, document_version, provider, model))',
            'CREATE TABLE IF NOT EXISTS tblModuleAIFingerprints (document_id INTEGER NOT NULL, document_version INTEGER NOT NULL, file_hash VARCHAR(64) NOT NULL, text_hash VARCHAR(64) NOT NULL, extraction_signature VARCHAR(64) NOT NULL, PRIMARY KEY (document_id, document_version))',
            'CREATE TABLE IF NOT EXISTS tblModuleAIEmbeddingCache (cache_key VARCHAR(64) PRIMARY KEY, document_id INTEGER NOT NULL, document_version INTEGER NOT NULL, vector_data TEXT NOT NULL, created_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS tblModuleAIOptions (option_name VARCHAR(80) PRIMARY KEY, option_value TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS tblModuleAIChunks (document_id INTEGER NOT NULL, document_version INTEGER NOT NULL, chunk_no INTEGER NOT NULL, page_no INTEGER NOT NULL, text_content TEXT NOT NULL, vector_data TEXT NOT NULL, embedding_signature VARCHAR(64) NOT NULL, PRIMARY KEY (document_id, document_version, chunk_no))',
            'CREATE TABLE IF NOT EXISTS tblModuleAIJobs (job_id VARCHAR(64) PRIMARY KEY, document_id INTEGER NOT NULL, document_version INTEGER NOT NULL, user_id INTEGER NOT NULL, provider VARCHAR(80) NOT NULL, state VARCHAR(20) NOT NULL, attempts INTEGER NOT NULL, available_at INTEGER NOT NULL, claim_token VARCHAR(64) NOT NULL, message VARCHAR(255) NOT NULL, created_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS tblModuleAIInteractions (interaction_id VARCHAR(64) PRIMARY KEY, user_id INTEGER NOT NULL, action_name VARCHAR(20) NOT NULL, provider VARCHAR(80) NOT NULL, sources_text TEXT NOT NULL, question_text TEXT NOT NULL, answer_text TEXT NOT NULL, created_at INTEGER NOT NULL)'
        );
        foreach ($tables as $sql) {
            if (strpos($this->driver, 'mysql') !== false) $sql = str_replace('vector_data TEXT', 'vector_data MEDIUMTEXT', $sql).' ENGINE=InnoDB';
            $this->execute($sql);
        }
    }
    public function execute($sql) {
        if (!$this->db->getResult($sql)) throw new RuntimeException('Could not save AI module data.');
    }
    public function rows($sql) {
        $rows = $this->db->getResultArray($sql);
        if (!is_array($rows)) throw new RuntimeException('Could not read AI module data.');
        return $rows;
    }
    public function quote($text) { return $this->db->qstr($text); }
    public function options() {
        $options = array('automatic' => '0', 'analysis_provider' => '', 'embedding_provider' => '', 'embedding_endpoint' => '', 'embedding_model' => '',
            'ocr_enabled' => '0', 'ocr_languages' => 'eng', 'change_summaries' => '0', 'metadata_suggestions' => '0',
            'monthly_request_limit' => '0', 'daily_user_limit' => '0', 'input_price' => '0', 'output_price' => '0', 'embedding_price' => '0');
        foreach ($this->rows('SELECT option_name, option_value FROM tblModuleAIOptions') as $row)
            if (array_key_exists($row['option_name'], $options)) $options[$row['option_name']] = $row['option_value'];
        return $options;
    }
    public function workerHeartbeat() {
        $this->execute("INSERT INTO tblModuleAIOptions (option_name, option_value) SELECT 'worker_last_seen', '0' WHERE NOT EXISTS (SELECT 1 FROM tblModuleAIOptions WHERE option_name = 'worker_last_seen')");
        $this->execute("UPDATE tblModuleAIOptions SET option_value = ".$this->quote((string)time())." WHERE option_name = 'worker_last_seen'");
    }
    public function workerStatus($user, $filter = '') {
        if (!$user->isAdmin()) throw new RuntimeException('Administrator access is required.');
        $where = is_string($filter) && in_array($filter, array('pending', 'running', 'complete', 'failed', 'cancelled'), true) ? ' WHERE state = '.$this->quote($filter) : '';
        $heartbeat = $this->rows("SELECT option_value FROM tblModuleAIOptions WHERE option_name = 'worker_last_seen'");
        return array('last_seen' => $heartbeat ? (int)$heartbeat[0]['option_value'] : 0,
            'counts' => $this->rows('SELECT state, COUNT(*) AS job_count FROM tblModuleAIJobs GROUP BY state'),
            'recent' => $this->rows('SELECT job_id, document_id, document_version, provider, state, attempts, message FROM tblModuleAIJobs'.$where.' ORDER BY created_at DESC LIMIT 30'));
    }
    public function saveOptions($user, $input, $assistant) {
        if (!$user->isAdmin()) throw new RuntimeException('Only administrators can manage automatic analysis.');
        $options = $this->options();
        foreach ($options as $key => $unused) {
            $value = isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : $options[$key];
            if (strlen($value) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $value)) throw new RuntimeException('Invalid AI processing setting.');
            $options[$key] = $value;
        }
        $options['automatic'] = $options['automatic'] === '1' ? '1' : '0';
        foreach (array('automatic', 'ocr_enabled', 'change_summaries', 'metadata_suggestions') as $key) $options[$key] = isset($input[$key]) && $input[$key] === '1' ? '1' : '0';
        if (!preg_match('/^[a-zA-Z0-9_]+(?:\+[a-zA-Z0-9_]+)*$/', $options['ocr_languages']) || strlen($options['ocr_languages']) > 100) throw new RuntimeException('Enter OCR languages such as eng or eng+fra.');
        foreach (array('monthly_request_limit', 'daily_user_limit') as $key)
            if (!ctype_digit($options[$key]) || strlen($options[$key]) > 8) throw new RuntimeException('Request limits must be whole numbers between 0 and 99999999.');
        foreach (array('input_price', 'output_price', 'embedding_price') as $key)
            if (!preg_match('/^\d{1,5}(?:\.\d{1,6})?$/', $options[$key])) throw new RuntimeException('Estimated token prices must be nonnegative numbers with up to six decimal places.');
        if ($options['automatic'] === '1') $assistant->profile($options['analysis_provider']);
        if ($options['embedding_provider'] !== '') {
            $profile = $assistant->profile($options['embedding_provider']);
            if ($profile['protocol'] !== 'openai') throw new RuntimeException('Embeddings require an OpenAI-compatible provider; it may be different from the analysis provider.');
            self::validateEndpoint($options['embedding_endpoint']);
            if ($options['embedding_model'] === '' || strlen($options['embedding_model']) > 255) throw new RuntimeException('Enter an embeddings model ID.');
        }
        $this->db->startTransaction();
        try {
            foreach ($options as $key => $value) {
                $this->execute('DELETE FROM tblModuleAIOptions WHERE option_name = '.$this->quote($key));
                $this->execute('INSERT INTO tblModuleAIOptions (option_name, option_value) VALUES ('.$this->quote($key).', '.$this->quote($value).')');
            }
            $this->db->commitTransaction();
        } catch (Throwable $e) { $this->db->rollbackTransaction(); throw $e; }
    }
    public static function validateEndpoint($endpoint) {
        $url = parse_url($endpoint);
        if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !$url || !isset($url['scheme'], $url['host']) || $url['scheme'] !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) throw new RuntimeException('Enter a full HTTPS endpoint without credentials or query parameters.');
    }
    public function seedQuota($bucket) {
        $sql = 'INSERT INTO tblModuleAIQuota (bucket, call_count, claim_token) VALUES ('.$this->quote($bucket).", 0, '')";
        $sql .= strpos($this->driver, 'mysql') !== false ? ' ON DUPLICATE KEY UPDATE bucket = bucket' : ' ON CONFLICT (bucket) DO NOTHING';
        $this->execute($sql);
    }
    public function transaction($callback) {
        $this->db->startTransaction();
        try { $callback(); $this->db->commitTransaction(); }
        catch (Throwable $e) { $this->db->rollbackTransaction(); throw $e; }
    }
}
