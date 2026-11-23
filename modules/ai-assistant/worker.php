<?php
// Run from cron/task scheduler: php /path/to/LetoDMS/modules/ai-assistant/worker.php --config=/path/settings.xml --limit=5
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', array('config:', 'limit:', 'help'));
if (isset($options['help'])) { echo "Usage: php worker.php [--config=/path/settings.xml] [--limit=5]\n"; exit; }
$root = dirname(__DIR__, 2);
chdir($root.'/op');
set_include_path($root.'/inc'.PATH_SEPARATOR.get_include_path());
require_once $root.'/inc/inc.ClassSettings.php';
$workerConfig = isset($options['config']) ? $options['config'] : (is_file($root.'/conf/settings.xml') ? $root.'/conf/settings.xml' : '');
$settings = new Settings($workerConfig);
// Per-process OCR/cURL timeouts and per-job leases provide bounds in CLI mode.
if (function_exists('set_time_limit')) set_time_limit(0);
require_once __DIR__.'/inc/WorkerBootstrap.php';
letoDMSAIWorkerLoadCore($root);
require_once $root.'/inc/inc.ClassModuleManager.php';
require_once __DIR__.'/inc/ProviderSettings.php'; require_once __DIR__.'/inc/Assistant.php';
require_once __DIR__.'/inc/Library.php'; require_once __DIR__.'/inc/Queue.php';
try {
    $db = new LetoDMS_Core_DatabaseAccess($settings->_dbDriver, $settings->_dbHostname, $settings->_dbUser, $settings->_dbPass, $settings->_dbDatabase);
    if (!$db->connect()) throw new RuntimeException('Database connection failed.');
    $manager = new LetoDMS_ModuleManager($db, $root.'/modules', $settings->_dbDriver);
    $enabled = function () use ($manager) { return $manager->isEnabled('ai-assistant'); };
    $dms = new LetoDMS_Core_DMS($db, $settings->_contentDir.$settings->_contentOffsetDir);
    $dms->setRootFolderID($settings->_rootFolderID); $dms->setMaxDirID($settings->_maxDirID);
    $dms->setEnableAdminRevApp($settings->_enableAdminRevApp);
    if (!empty($settings->_enableFullSearch)) {
        try {
            require_once $root.'/inc/inc.ClassFulltext.php';
            $fulltext = new LetoDMS_Fulltext($db, $dms, $settings);
            echo 'Full-text jobs attempted: '.$fulltext->work(isset($options['limit']) ? (int)$options['limit'] : 5)."\n";
        } catch (Throwable $e) { error_log('LetoDMS full-text worker failed; check indexing configuration.'); }
    }
    if (!$enabled()) { echo "AI Assistant is disabled; no AI jobs processed.\n"; exit; }
    $providers = new LetoDMS_AIProviderSettings($db, $settings->_encryptionKey); $providers->initialize();
    $store = new LetoDMS_AIStore($db, $settings->_dbDriver); $store->initialize();
    $assistant = new LetoDMS_AIAssistant($db, $providers, $store); $library = new LetoDMS_AILibrary($store, $assistant, $dms);
    $queue = new LetoDMS_AIQueue($store); $processed = 0;
    $limit = isset($options['limit']) ? max(1, min(50, (int)$options['limit'])) : 5;
    while ($processed < $limit) {
        $assistant = new LetoDMS_AIAssistant($db, $providers, $store);
        $library = new LetoDMS_AILibrary($store, $assistant, $dms);
        if (!$queue->runOne($dms, $assistant, $library, $enabled)) break;
        $processed++;
    }
    echo 'Jobs attempted: '.$processed."\n";
} catch (Throwable $e) { fwrite(STDERR, "AI worker failed. Check database/module configuration and server logs.\n"); exit(1); }
