<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['config:','limit:','rebuild','queue-only','help']);
if (isset($options['help'])) {
    echo "Usage: php utils/fulltext-worker.php [--config=/path/settings.xml] [--limit=5] [--rebuild] [--queue-only]\n";
    echo "--rebuild queues all current documents; extraction runs locally. Schedule without --rebuild.\n";
    exit;
}
$root = dirname(__DIR__);
chdir($root.'/op');
set_include_path($root.'/LetoDMS_Core/Core'.PATH_SEPARATOR.$root.'/inc'.PATH_SEPARATOR.get_include_path());
require_once $root.'/inc/inc.ClassSettings.php';
try {
    $config = $options['config'] ?? (is_file($root.'/conf/settings.xml') ? $root.'/conf/settings.xml' : '');
    if ($config && is_file(dirname($config).'/ENABLE_INSTALL_TOOL')) throw new RuntimeException('Disable the installation tool before indexing.');
    $settings = new Settings($config);
    if (function_exists('set_time_limit')) set_time_limit(0);
    if (!$settings->_enableFullSearch) { echo "Full-text search is disabled. Enable it in administrator settings.\n"; exit; }
    require_once $root.'/LetoDMS_Core/Core.php';
    require_once $root.'/inc/inc.ClassFulltext.php';
    $db = new LetoDMS_Core_DatabaseAccess($settings->_dbDriver,$settings->_dbHostname,$settings->_dbUser,$settings->_dbPass,$settings->_dbDatabase);
    if (!$db->connect()) throw new RuntimeException('Database connection failed.');
    $dms = new LetoDMS_Core_DMS($db,$settings->_contentDir.$settings->_contentOffsetDir);
    $dms->setRootFolderID($settings->_rootFolderID); $dms->setMaxDirID($settings->_maxDirID);
    $index = new LetoDMS_Fulltext($db,$dms,$settings); $index->initialize();
    if (isset($options['rebuild'])) {
        $cursor = 0; $total = 0;
        do { $batch=$index->enqueueBatch($cursor); $cursor=$batch['cursor']; $total+=$batch['queued']; } while($batch['more']);
        echo 'Documents queued: '.$total."\n";
    }
    if (!isset($options['queue-only'])) echo 'Full-text jobs attempted: '.$index->work(isset($options['limit'])?(int)$options['limit']:5)."\n";
} catch (Throwable $e) {
    fwrite(STDERR,'Full-text worker failed: '.$e->getMessage()."\n"); exit(1);
}
