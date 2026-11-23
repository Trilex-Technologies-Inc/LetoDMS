<?php
namespace LetoDMS\WebDAV;

function loadSettings($root, $config = '')
{
    // Core's bare includes must not resolve to the parallel application classes.
    set_include_path($root.'/LetoDMS_Core/Core'.PATH_SEPARATOR.$root.'/inc'.PATH_SEPARATOR.get_include_path());
    require_once $root.'/inc/inc.ClassSettings.php';
    if ($config === '') {
        $directory = \Settings::getConfigDir();
        $config = $directory ? $directory.'/settings.xml' : '';
    }
    if ($config === '' || !is_readable($config)) throw new \RuntimeException('LetoDMS configuration is unavailable.');
    if (is_file(dirname($config).'/ENABLE_INSTALL_TOOL')) throw new \RuntimeException('Disable the LetoDMS installation tool before using WebDAV.');
    $settings = new \Settings($config);
    if (!empty($settings->_extraPath)) {
        set_include_path($root.'/LetoDMS_Core/Core'.PATH_SEPARATOR.$root.'/inc'.PATH_SEPARATOR.$settings->_extraPath.PATH_SEPARATOR.get_include_path());
    }
    return $settings;
}

function connect($root, $settings)
{
    require_once $root.'/LetoDMS_Core/Core.php';
    $db = new \LetoDMS_Core_DatabaseAccess($settings->_dbDriver, $settings->_dbHostname, $settings->_dbUser, $settings->_dbPass, $settings->_dbDatabase);
    if (!$db->connect()) throw new \RuntimeException('Cannot connect to the LetoDMS database.');
    $dms = new \LetoDMS_Core_DMS($db, $settings->_contentDir.$settings->_contentOffsetDir);
    if (!$dms->checkVersion()) throw new \RuntimeException('The LetoDMS database needs an update.');
    $dms->setRootFolderID($settings->_rootFolderID);
    $dms->setMaxDirID($settings->_maxDirID);
    $dms->setEnableAdminRevApp($settings->_enableAdminRevApp);
    $dms->setEnableConverting(false);
    $dms->setViewOnlineFileTypes($settings->_viewOnlineFileTypes);
    return $dms;
}
