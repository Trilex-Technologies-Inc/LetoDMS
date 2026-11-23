<?php
require_once __DIR__.'/../inc.DatabaseCheck.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (letoDMSInstallDatabaseVersion($db) !== null) throw new Exception('Empty database must report missing schema.');
$db->exec('CREATE TABLE tblVersion (major INTEGER, minor INTEGER, subminor INTEGER)');
if (letoDMSInstallDatabaseVersion($db) !== null) throw new Exception('Empty version table must not count as installed.');
$db->exec('INSERT INTO tblVersion VALUES (5, 1, 0)');
$version = letoDMSInstallDatabaseVersion($db);
if (!$version || (int)$version['major'] !== 5) throw new Exception('Valid schema version not detected.');
$db->exec('DROP TABLE tblVersion');
$db->exec('CREATE TABLE tblVersion (unrelated INTEGER)');
if (letoDMSInstallDatabaseVersion($db) !== null) throw new Exception('Incomplete schema must not count as installed.');
echo "Installer database checks passed.\n";
