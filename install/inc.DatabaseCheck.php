<?php
/** Read schema metadata without crashing when installation has not created it. */
function letoDMSInstallDatabaseVersion($connection) {
    try {
        $result = $connection->query('SELECT major, minor, subminor FROM tblVersion');
        if (!$result) return null;
        $version = $result->fetch(PDO::FETCH_ASSOC);
        if (!$version) return null;
        foreach (array('major', 'minor', 'subminor') as $part)
            if (!isset($version[$part]) || !is_numeric($version[$part])) return null;
        return $version;
    } catch (PDOException $e) {
        return null;
    }
}
