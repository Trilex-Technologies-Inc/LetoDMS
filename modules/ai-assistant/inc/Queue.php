<?php
require_once __DIR__.'/Store.php';
class LetoDMS_AIQueue {
    private $store;
    public function __construct($store) { $this->store = $store; }
    public function enqueue($user, $assistant, $dms, $id, $version, $provider) {
        if (!$user->hasPermission('module.ai-assistant.access')) throw new RuntimeException('AI module access is required.');
        $assistant->profile($provider);
        $assistant->content($dms, $user, $id, $version);
        $job = hash('sha256', $id.':'.$version.':'.$provider);
        $existing = $this->store->rows('SELECT state FROM tblModuleAIJobs WHERE job_id = '.$this->store->quote($job));
        if ($existing) {
            if (in_array($existing[0]['state'], array('failed', 'complete'), true)) $this->store->execute("UPDATE tblModuleAIJobs SET state = 'pending', attempts = 0, user_id = ".(int)$user->getID().', available_at = '.time().", message = '', claim_token = '' WHERE job_id = ".$this->store->quote($job)." AND (state = 'failed' OR state = 'complete')");
            return $job;
        }
        $this->store->execute('INSERT INTO tblModuleAIJobs (job_id, document_id, document_version, user_id, provider, state, attempts, available_at, claim_token, message, created_at) VALUES ('.$this->store->quote($job).', '.(int)$id.', '.(int)$version.', '.(int)$user->getID().', '.$this->store->quote($provider).", 'pending', 0, ".time().", '', '', ".time().')');
        return $job;
    }
    public function runOne($dms, $assistant, $library, $enabled) {
        if (!$enabled()) return false;
        $this->store->workerHeartbeat();
        // Thirty-minute leases cover bounded OCR work; attempts bound repeated failures.
        $this->store->execute("UPDATE tblModuleAIJobs SET state = 'failed', message = 'Worker lease expired after three attempts.' WHERE state = 'running' AND available_at < ".time().' AND attempts >= 3');
        $rows = $this->store->rows("SELECT * FROM tblModuleAIJobs WHERE (state = 'pending' OR state = 'running') AND available_at <= ".time().' AND attempts < 3 ORDER BY created_at LIMIT 1');
        if (!$rows) return false;
        $job = $rows[0]; $token = bin2hex(random_bytes(16)); $id = $this->store->quote($job['job_id']);
        $this->store->execute("UPDATE tblModuleAIJobs SET state = 'running', claim_token = ".$this->store->quote($token).', available_at = '.(time() + 1800).', attempts = attempts + 1 WHERE job_id = '.$id." AND (state = 'pending' OR state = 'running') AND available_at <= ".time().' AND attempts < 3');
        $claimed = $this->store->rows('SELECT * FROM tblModuleAIJobs WHERE job_id = '.$id.' AND claim_token = '.$this->store->quote($token));
        if (!$claimed) return true;
        try {
            $user = $dms->getUser($job['user_id']);
            if (!$enabled() || !$user || $user->isDisabled() || !$user->hasPermission('module.ai-assistant.access')) throw new RuntimeException('The uploader no longer has AI module access.');
            $assistant->setUser($user);
            $assistant->content($dms, $user, (int)$job['document_id'], (int)$job['document_version']);
            $this->stage($id, $token, 'Analyzing document text.');
            if (!$assistant->hasAnalysis((int)$job['document_id'], (int)$job['document_version'], $job['provider']))
                $assistant->analyze($dms, $user, (int)$job['document_id'], (int)$job['document_version'], $job['provider']);
            if ($this->store->options()['embedding_provider'] !== '') {
                if (!$enabled()) throw new RuntimeException('The AI module has been disabled.');
                $this->stage($id, $token, 'Indexing extracted document text.');
                $library->index($user, (int)$job['document_id'], (int)$job['document_version'], true);
            }
            $options = $this->store->options();
            if (!$enabled()) throw new RuntimeException('The AI module has been disabled.');
            if ($options['metadata_suggestions'] === '1') {
                $this->stage($id, $token, 'Preparing metadata suggestions.');
                require_once __DIR__.'/Metadata.php';
                (new LetoDMS_AIMetadata($this->store, $assistant, $dms))->generate($user, (int)$job['document_id'], (int)$job['document_version'], $job['provider'], true);
            }
            if ($options['change_summaries'] === '1') {
                $this->stage($id, $token, 'Comparing document versions.');
                $this->changes($user, $dms, $assistant, $library, $job);
            }
            $this->store->execute("UPDATE tblModuleAIJobs SET state = 'complete', message = 'Analysis complete.' WHERE job_id = ".$id.' AND claim_token = '.$this->store->quote($token));
        } catch (Throwable $e) {
            if ($e instanceof LetoDMS_AIQuotaException) {
                $this->store->execute("UPDATE tblModuleAIJobs SET state = 'pending', attempts = attempts - 1, available_at = ".$e->retryAt().", message = 'Paused until the local AI request limit resets.' WHERE job_id = ".$id.' AND claim_token = '.$this->store->quote($token));
                return true;
            }
            $failed = (int)$claimed[0]['attempts'] >= 3;
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Processing failed. Check server logs and configuration.';
            $this->store->execute('UPDATE tblModuleAIJobs SET state = '.$this->store->quote($failed ? 'failed' : 'pending').', available_at = '.(time() + 300).', message = '.$this->store->quote(substr($message, 0, 255)).' WHERE job_id = '.$id.' AND claim_token = '.$this->store->quote($token));
        }
        return true;
    }
    public function jobs($user, $id, $version) {
        // Caller checks document/version access before requesting these rows.
        return $this->store->rows('SELECT state, provider, attempts, message, created_at FROM tblModuleAIJobs WHERE document_id = '.(int)$id.' AND document_version = '.(int)$version.' ORDER BY created_at DESC');
    }
    private function stage($quotedId, $token, $message) {
        $this->store->execute('UPDATE tblModuleAIJobs SET message = '.$this->store->quote($message).' WHERE job_id = '.$quotedId.' AND claim_token = '.$this->store->quote($token)." AND state = 'running'");
    }
    private function changes($user, $dms, $assistant, $library, $job) {
        $id = (int)$job['document_id']; $version = (int)$job['document_version'];
        $profile = $assistant->profile($job['provider']);
        $existing = $this->store->rows('SELECT interaction_id FROM tblModuleAIChanges WHERE document_id = '.$id.' AND document_version = '.$version.' AND provider = '.$this->store->quote($job['provider']).' AND model = '.$this->store->quote($profile['model']));
        if ($existing) return;
        $previous = 0;
        foreach ($dms->getDocument($id)->getContent() as $content) if ((int)$content->getVersion() < $version) $previous = max($previous, (int)$content->getVersion());
        if (!$previous) return;
        // Do not silently compare a more distant version when the immediate predecessor is restricted.
        try { $assistant->content($dms, $user, $id, $previous); } catch (RuntimeException $e) { return; }
        $answer = $library->compare($user, $job['provider'], $id, $previous, $version);
        $this->store->execute('INSERT INTO tblModuleAIChanges (document_id, document_version, previous_version, provider, model, interaction_id) VALUES ('.$id.', '.$version.', '.$previous.', '.$this->store->quote($job['provider']).', '.$this->store->quote($profile['model']).', '.$this->store->quote($answer['interaction_id']).')');
    }
    public function manage($user, $assistant, $dms, $jobId, $action) {
        if (!$user->isAdmin()) throw new RuntimeException('Administrator access is required.');
        if (!is_string($jobId) || !preg_match('/^[a-f0-9]{64}$/', $jobId) || !in_array($action, array('retry', 'cancel'), true)) throw new RuntimeException('Invalid job action.');
        $rows = $this->store->rows('SELECT * FROM tblModuleAIJobs WHERE job_id = '.$this->store->quote($jobId));
        if (!$rows || $rows[0]['state'] === 'running') throw new RuntimeException('An active worker job cannot be modified.');
        $job = $rows[0];
        if ($action === 'cancel' && !in_array($job['state'], array('pending', 'failed', 'cancelled'), true)) throw new RuntimeException('Only pending or failed jobs can be cancelled.');
        if ($action === 'retry') {
            $assistant->content($dms, $user, (int)$job['document_id'], (int)$job['document_version']);
            $assistant->profile($job['provider']);
        }
        $this->store->execute('UPDATE tblModuleAIJobs SET state = '.$this->store->quote($action === 'retry' ? 'pending' : 'cancelled').', attempts = 0, user_id = '.(int)$user->getID().', available_at = '.time().", message = '', claim_token = '' WHERE job_id = ".$this->store->quote($jobId)." AND state <> 'running'");
    }
}
