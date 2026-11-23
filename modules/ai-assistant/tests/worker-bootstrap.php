<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 3);
chdir($root.'/op');
set_include_path($root.'/inc'.PATH_SEPARATOR.get_include_path());
require_once __DIR__.'/../inc/WorkerBootstrap.php';
letoDMSAIWorkerLoadCore($root);
letoDMSAIWorkerLoadCore($root);
foreach (array('LetoDMS_Core_Object', 'LetoDMS_Core_Document', 'LetoDMS_Core_Folder', 'LetoDMS_Core_User') as $class) {
    $path = str_replace('\\', '/', (new ReflectionClass($class))->getFileName());
    if (strpos($path, '/LetoDMS_Core/Core/') === false) throw new Exception('Worker loaded the wrong core class: '.$class);
}
echo "Worker core bootstrap checks passed.\n";
