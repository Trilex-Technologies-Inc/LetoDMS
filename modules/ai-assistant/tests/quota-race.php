<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Six independent PHP processes contend for one SQLite quota.
require_once __DIR__.'/../../../LetoDMS_Core/Core/inc.DBAccessPDO.php';
require_once __DIR__.'/../inc/Store.php'; require_once __DIR__.'/../inc/Usage.php';
if (isset($argv[1]) && $argv[1] === 'child') {
    try {
        $db = new LetoDMS_Core_DatabaseAccess('sqlite', '', '', '', $argv[2]); $db->connect();
        while (microtime(true) < (float)$argv[3]) usleep(1000);
        (new LetoDMS_AIUsage(new LetoDMS_AIStore($db, 'sqlite')))->reserve((int)$argv[4], 'test', 'model', 'generation');
        echo 'allowed'; exit;
    } catch (LetoDMS_AIQuotaException $e) { echo 'limited'; exit; }
    catch (Throwable $e) { fwrite(STDERR, "Quota subprocess failed.\n"); exit(1); }
}
$directory = sys_get_temp_dir().'/letodms-quota-race-'.uniqid(); mkdir($directory);
$path = $directory.'/quota.sqlite'; $children = array();
try {
    $db = new LetoDMS_Core_DatabaseAccess('sqlite', '', '', '', $path); $db->connect();
    $store = new LetoDMS_AIStore($db, 'sqlite'); $store->initialize();
    $store->execute("INSERT INTO tblModuleAIOptions VALUES ('monthly_request_limit', '3')");
    $store->execute("INSERT INTO tblModuleAIOptions VALUES ('daily_user_limit', '2')");
    $store->seedQuota('month:'.gmdate('Y-m'));
    foreach (array(100, 101) as $id) $store->seedQuota('user:'.$id.':'.gmdate('Y-m-d'));
    $start = microtime(true) + 1;
    for ($i = 0; $i < 6; $i++) {
        $process = proc_open(array(PHP_BINARY, '-d', 'extension=pdo_sqlite', __FILE__, 'child', $path, (string)$start, (string)(100 + $i % 2)), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) throw new Exception('Could not start quota race subprocess.');
        fclose($pipes[0]); $children[] = array($process, $pipes);
    }
    $allowed = 0;
    foreach ($children as $child) {
        $output = stream_get_contents($child[1][1]); $error = stream_get_contents($child[1][2]);
        fclose($child[1][1]); fclose($child[1][2]); $code = proc_close($child[0]);
        if ($code !== 0) throw new Exception('Quota race subprocess failed: '.$error);
        if ($output === 'allowed') $allowed++;
    }
    $children = array();
    $rows = $store->rows('SELECT bucket, call_count FROM tblModuleAIQuota');
    if ($allowed !== 3 || count($store->rows('SELECT * FROM tblModuleAIUsage')) !== 3) throw new Exception('Concurrent monthly cap not enforced.');
    foreach ($rows as $row) if ((int)$row['call_count'] > (strpos($row['bucket'], 'month:') === 0 ? 3 : 2)) throw new Exception('Concurrent per-user/monthly quota exceeded.');
    echo "Concurrent AI quota checks passed (six processes, three allowed requests).\n";
} finally {
    foreach ($children as $child) if (is_resource($child[0])) { proc_terminate($child[0]); fclose($child[1][1]); fclose($child[1][2]); proc_close($child[0]); }
    unset($store, $db);
    foreach (glob($directory.'/*') as $file) unlink($file); rmdir($directory);
}
