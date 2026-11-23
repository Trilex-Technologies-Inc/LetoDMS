<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../inc/Assistant.php';
$cases = array(
    array(401, 0, '', 'rejected the API key'),
    array(429, 0, '{"error":{"code":"insufficient_quota"}}', 'insufficient API quota'),
    array(429, 0, '{"error":{"type":"rate_limit_error"}}', 'rate limit'),
    array(403, 0, '', 'denied access'), array(404, 0, '', 'model was not found'),
    array(400, 0, '{"error":{"code":"context_length_exceeded"}}', 'context limit'),
    array(400, 0, '', 'request format'), array(503, 0, '', 'server error'),
    array(0, 6, '', 'hostname'), array(0, 28, '', 'timed out'), array(0, 60, '', 'certificate')
);
foreach ($cases as $case) {
    $message = LetoDMS_AIAssistant::failureMessage($case[0], $case[1], $case[2]);
    if (strpos($message, $case[3]) === false) throw new Exception('Incorrect API error classification.');
}
$message = LetoDMS_AIAssistant::failureMessage(400, 0, '{"error":{"code":"private-secret","message":"private-secret document text"}}');
if (strpos($message, 'private-secret') !== false || strpos($message, 'document text') !== false) throw new Exception('Provider content exposed.');
echo "AI request diagnostic checks passed.\n";
