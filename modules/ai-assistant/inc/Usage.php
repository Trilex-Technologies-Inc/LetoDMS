<?php
class LetoDMS_AIQuotaException extends RuntimeException {
    private $retryAt;
    public function __construct($retryAt) {
        parent::__construct('The local AI request limit has been reached. Ask an administrator to review the monthly or daily usage limits.');
        $this->retryAt = $retryAt;
    }
    public function retryAt() { return $this->retryAt; }
}
class LetoDMS_AIUsage {
    private $store;
    public function __construct($store) { $this->store = $store; }
    public function reserve($userId, $provider, $model, $operation) {
        $options = $this->store->options(); $id = bin2hex(random_bytes(16));
        $buckets = array('month:'.gmdate('Y-m') => (int)$options['monthly_request_limit']);
        if ($userId > 0) $buckets['user:'.$userId.':'.gmdate('Y-m-d')] = (int)$options['daily_user_limit'];
        // Seed before transaction. Conditional updates take row locks; the claim is verified
        // under the same transaction so concurrent requests cannot exceed a configured cap.
        foreach ($buckets as $bucket => $limit) $this->store->seedQuota($bucket);
        $this->store->transaction(function () use ($buckets, $id, $userId, $provider, $model, $operation) {
            foreach ($buckets as $bucket => $limit) {
                $this->store->execute('UPDATE tblModuleAIQuota SET call_count = call_count + 1, claim_token = '.$this->store->quote($id).' WHERE bucket = '.$this->store->quote($bucket).($limit > 0 ? ' AND call_count < '.$limit : ''));
                $claim = $this->store->rows('SELECT claim_token FROM tblModuleAIQuota WHERE bucket = '.$this->store->quote($bucket));
                if (!$claim || $claim[0]['claim_token'] !== $id) {
                    $retryAt = strpos($bucket, 'month:') === 0
                        ? gmmktime(0, 0, 0, (int)gmdate('m') + 1, 1, (int)gmdate('Y'))
                        : gmmktime(0, 0, 0, (int)gmdate('m'), (int)gmdate('d') + 1, (int)gmdate('Y'));
                    throw new LetoDMS_AIQuotaException($retryAt);
                }
            }
            $this->store->execute('INSERT INTO tblModuleAIUsage (call_id, user_id, provider, model, operation, state, input_tokens, output_tokens, token_source, cost_estimate, cost_known, created_at) VALUES ('.$this->store->quote($id).', '.(int)$userId.', '.$this->store->quote($provider).', '.$this->store->quote($model).', '.$this->store->quote($operation).", 'reserved', 0, 0, 'unknown', 0, 0, ".time().')');
        });
        return array('id' => $id, 'options' => $options, 'operation' => $operation);
    }
    public function finish($reservation, $request, $response, $success) {
        $usage = array();
        $decoded = json_decode($response, true);
        if (isset($decoded['usage']) && is_array($decoded['usage'])) $usage = $decoded['usage'];
        $in = isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : (isset($usage['input_tokens']) ? $usage['input_tokens'] : null);
        $out = isset($usage['completion_tokens']) ? $usage['completion_tokens'] : (isset($usage['output_tokens']) ? $usage['output_tokens'] : null);
        if ($reservation['operation'] === 'embeddings') { $in = isset($usage['total_tokens']) ? $usage['total_tokens'] : $in; $out = 0; }
        $reported = is_int($in) && $in >= 0 && $in < 100000000 && is_int($out) && $out >= 0 && $out < 100000000;
        if (!$reported) { $in = (int)ceil(strlen($request) / 4); $out = $success && $reservation['operation'] !== 'embeddings' ? (int)ceil(strlen($response) / 4) : 0; }
        $options = $reservation['options'];
        $inputPrice = (float)$options[$reservation['operation'] === 'embeddings' ? 'embedding_price' : 'input_price'];
        $outputPrice = $reservation['operation'] === 'embeddings' ? 0 : (float)$options['output_price'];
        $known = $success && $inputPrice > 0 && ($reservation['operation'] === 'embeddings' || $outputPrice > 0);
        $cost = $known ? ($in * $inputPrice + $out * $outputPrice) / 1000000 : 0;
        $this->store->execute('UPDATE tblModuleAIUsage SET state = '.$this->store->quote($success ? 'success' : 'failed').', input_tokens = '.$in.', output_tokens = '.$out.', token_source = '.$this->store->quote($reported ? 'provider' : 'estimated').', cost_estimate = '.sprintf('%.8F', $cost).', cost_known = '.($known ? 1 : 0).' WHERE call_id = '.$this->store->quote($reservation['id']));
    }
    public function report($user) {
        if (!$user->isAdmin()) throw new RuntimeException('Administrator access is required.');
        $start = gmmktime(0, 0, 0, (int)gmdate('m'), 1, (int)gmdate('Y'));
        return $this->store->rows('SELECT provider, model, operation, state, COUNT(*) AS calls, SUM(input_tokens) AS input_tokens, SUM(output_tokens) AS output_tokens, SUM(cost_estimate) AS estimated_cost, SUM(cost_known) AS priced_calls FROM tblModuleAIUsage WHERE created_at >= '.$start.' GROUP BY provider, model, operation, state ORDER BY provider, model, operation');
    }
}
