<?php
// Server administrator utility. Never expose database maintenance through HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/src/Bootstrap.php';
require_once __DIR__.'/src/Context.php';
$options = getopt('', ['config:', 'check', 'initialize', 'migrate', 'help']);
if (isset($options['help'])) {
    echo "Usage: php webdav/setup.php [--config=/path/to/settings.xml] [--initialize] [--check] [--migrate]\n";
    echo "--check reads readiness information. --initialize creates DAV tables and permission.\n";
    echo "--migrate converts the document tables needed by DAV to InnoDB. Back up the database first and stop document writes during migration.\n";
    exit(0);
}
try {
    $root = dirname(__DIR__);
    $settings = LetoDMS\WebDAV\loadSettings($root, $options['config'] ?? '');
    $context = new LetoDMS\WebDAV\Context(LetoDMS\WebDAV\connect($root, $settings), $settings);
    if (isset($options['initialize']) || isset($options['migrate'])) {
        $context->initialize();
        echo "WebDAV tables and permission initialized.\n";
    }
    $tables = $context->nonTransactionalTables();
    if (isset($options['migrate'])) {
        foreach ($tables as $table) {
            if (!in_array($table, LetoDMS\WebDAV\Context::TRANSACTIONAL_TABLES, true)) throw new RuntimeException('Unexpected table name.');
            $context->execute('ALTER TABLE `'.$table.'` ENGINE=InnoDB');
            echo 'Converted '.$table." to InnoDB.\n";
        }
        $tables = $context->nonTransactionalTables();
    }
    echo 'WebDAV: '.($settings->_enableWebDAV ? 'enabled' : 'disabled in administrator settings')."\n";
    echo 'Access: '.($settings->_webDAVReadOnly ? 'read-only' : 'read/write')."\n";
    echo 'Upload limit: '.(int)$settings->_webDAVMaxUploadSize." bytes\n";
    if ($tables) {
        echo 'Nontransactional tables: '.implode(', ', $tables)."\n";
        echo "Writes are blocked until these tables use InnoDB. See webdav/README.md.\n";
        exit(2);
    }
    echo "Storage engine check passed. HTTPS, endpoint routing, and client connections must be tested on the web server.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'WebDAV setup failed: '.$e->getMessage()."\n");
    exit(1);
}
