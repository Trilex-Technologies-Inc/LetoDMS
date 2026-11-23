<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../inc/ProviderSettings.php';
foreach (array('' => 'The saved LetoDMS encryption key is empty.', 'test-key' => function_exists('openssl_encrypt') ? 'The stored AI key cannot be read.' : 'The web server PHP runtime cannot use OpenSSL') as $secret => $expected) {
    try {
        (new LetoDMS_AIProviderSettings(null, $secret))->decrypt('invalid');
        throw new Exception('Expected a configuration error.');
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), $expected) !== 0) throw new Exception('Incorrect configuration diagnostic.');
    }
}
echo "Encryption diagnostic checks passed.\n";
