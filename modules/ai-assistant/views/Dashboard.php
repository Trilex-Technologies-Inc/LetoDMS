<?php
class LetoDMS_AI_Dashboard {
    private static function escape($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
    public static function show($params) {
        if (!$params['user']->isAdmin()) return;
        echo '<div class="well"><h3>Processing dashboard</h3>';
        $worker = isset($params['worker_status']) ? $params['worker_status'] : array('last_seen' => 0, 'counts' => array(), 'recent' => array());
        echo '<p>'.($worker['last_seen'] ? 'Last worker activity: '.self::escape(date('Y-m-d H:i:s', $worker['last_seen'])) : 'No worker activity recorded. Schedule the worker to process queued jobs.').'</p>';
        foreach ($worker['counts'] as $count) echo '<span class="label">'.self::escape($count['state']).': '.(int)$count['job_count'].'</span> ';
        echo '<form method="get" action="../out/out.Module.php" style="margin-top:16px"><input type="hidden" name="module" value="ai-assistant"><input type="hidden" name="settings" value="1"><input type="hidden" name="panel" value="jobs"><label>Show jobs <select name="job_state"><option value="">All statuses</option>';
        foreach (array('pending', 'running', 'complete', 'failed', 'cancelled') as $state) echo '<option value="'.$state.'"'.(isset($_GET['job_state']) && $_GET['job_state'] === $state ? ' selected' : '').'>'.ucfirst($state).'</option>';
        echo '</select></label><button class="btn" type="submit">Refresh jobs</button></form>';
        echo '<p class="muted">Retries run as the administrator requesting them and recheck document access. Active worker jobs cannot be modified.</p><div style="overflow-x:auto"><table class="table"><thead><tr><th>Document</th><th>Version</th><th>Status</th><th>Attempts</th><th>Details</th><th>Actions</th></tr></thead><tbody>';
        foreach ($worker['recent'] as $job) {
            echo '<tr><td><a href="../out/out.ViewDocument.php?documentid='.(int)$job['document_id'].'">'.(int)$job['document_id'].'</a></td><td>'.(int)$job['document_version'].'</td><td>'.self::escape($job['state']).'</td><td>'.(int)$job['attempts'].'</td><td>'.self::escape($job['message']).'</td><td>';
            if ($job['state'] !== 'running') {
                echo '<form style="margin:0;white-space:nowrap" method="post" action="../op/op.Module.php?module=ai-assistant">'.createHiddenFieldWithKey('ai-assistant').'<input type="hidden" name="job_id" value="'.self::escape($job['job_id']).'"><button class="btn btn-mini" type="submit" name="action" value="retry_job">Retry</button>';
                if (in_array($job['state'], array('pending', 'failed'), true)) echo ' <button class="btn btn-mini" type="submit" name="action" value="cancel_job">Cancel</button>';
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></div><div class="well"><h3>Usage this month (UTC)</h3><p>Request limits count hosted generation and embedding attempts, including failures. A document may require multiple requests. Model-list lookups are excluded.</p><p>Costs below are estimates based on your configured default rates, not an invoice or a spending cap. Missing prices are marked unpriced. Token counts use provider-reported usage when available and a byte-based estimate otherwise.</p><div style="overflow-x:auto"><table class="table"><thead><tr><th>Provider / model</th><th>Operation</th><th>Status</th><th>Requests</th><th>Input tokens</th><th>Output tokens</th><th>Estimated USD</th></tr></thead><tbody>';
        foreach (isset($params['usage_report']) ? $params['usage_report'] : array() as $row) echo '<tr><td>'.self::escape($row['provider']).'<br>'.self::escape($row['model']).'</td><td>'.self::escape($row['operation']).'</td><td>'.self::escape($row['state']).'</td><td>'.(int)$row['calls'].'</td><td>'.(int)$row['input_tokens'].'</td><td>'.(int)$row['output_tokens'].'</td><td>'.((int)$row['priced_calls'] ? '$'.number_format((float)$row['estimated_cost'], 6) : 'Unpriced').((int)$row['priced_calls'] > 0 && (int)$row['priced_calls'] < (int)$row['calls'] ? ' (partially priced)' : '').'</td></tr>';
        echo '</tbody></table></div></div>';
    }
}
