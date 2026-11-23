<?php
/** Modern LetoDMS WebDAV endpoint. No browser session or PEAR server is used. */
ini_set('display_errors', '0');
$root = dirname(__DIR__);
try {
    if (PHP_VERSION_ID < 80100) throw new RuntimeException('WebDAV requires PHP 8.1 or later.');
    require_once __DIR__.'/src/Bootstrap.php';
    $settings = LetoDMS\WebDAV\loadSettings($root);
    if (empty($settings->_enableWebDAV)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit('WebDAV is disabled. Enable it in the administrator settings.');
    }
    if ((empty($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) === 'off') && (int)($_SERVER['SERVER_PORT'] ?? 0) !== 443) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('WebDAV requires HTTPS.');
    }
    if (!is_file(__DIR__.'/vendor/autoload.php')) throw new RuntimeException('Install WebDAV dependencies: run composer install --no-dev inside webdav/.');
    require_once __DIR__.'/vendor/autoload.php';
    require_once __DIR__.'/src/Context.php';
    require_once __DIR__.'/src/Auth.php';
    require_once __DIR__.'/src/Nodes.php';
    require_once __DIR__.'/src/Locks.php';
    require_once __DIR__.'/src/Server.php';
    $dms = LetoDMS\WebDAV\connect($root, $settings);
    $context = new LetoDMS\WebDAV\Context($dms, $settings);
    $context->initialize();
    $script = $_SERVER['SCRIPT_NAME'];
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requestPath === $script || $requestPath === rtrim(dirname($script), '/')) {
        http_response_code(307);
        header('Location: '.$requestPath.'/');
        exit;
    }
    $base = strpos($requestPath, $script) === 0 ? $script.'/' : rtrim(dirname($script), '/').'/';
    LetoDMS\WebDAV\createServer($context, $base)->start();
} catch (Throwable $e) {
    error_log('LetoDMS WebDAV startup: '.$e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'WebDAV is unavailable. Check the server log and webdav/README.md for setup requirements.';
}
