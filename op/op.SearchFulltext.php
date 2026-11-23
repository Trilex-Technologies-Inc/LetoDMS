<?php
require '../inc/inc.Settings.php';
require '../inc/inc.Utils.php';
require '../inc/inc.DBInit.php';
require '../inc/inc.Language.php';
require '../inc/inc.ClassUI.php';
require '../inc/inc.Authentication.php';
require_once '../inc/inc.ClassFulltext.php';
header('Cache-Control: private, no-store');
$query = isset($_GET['query']) && is_string($_GET['query']) ? trim($_GET['query']) : '';
$folderID = isset($_GET['folderid']) && is_scalar($_GET['folderid']) ? max(0,(int)$_GET['folderid']) : 0;
$ownerID = isset($_GET['ownerid']) && is_scalar($_GET['ownerid']) ? max(0,(int)$_GET['ownerid']) : 0;
$cursor = isset($_GET['after']) && is_scalar($_GET['after']) ? max(0,(int)$_GET['after']) : 0;
$categories = isset($_GET['categoryids']) && is_array($_GET['categoryids']) ? array_slice(array_map('intval',$_GET['categoryids']),0,100) : [];
$result = ['hits'=>[],'next'=>null,'words'=>[]]; $error = '';
try {
    if ($folderID) {
        $folder = $dms->getFolder($folderID);
        if (!$folder || $folder->getAccessMode($user)<M_READ) throw new RuntimeException('Folder access denied.');
    }
    if ($query !== '') $result = (new LetoDMS_Fulltext($db,$dms,$settings))->search($user,$query,$cursor,$folderID,$ownerID,$categories);
    elseif (!$settings->_enableFullSearch) throw new RuntimeException('Full-text search is disabled in administrator settings.');
} catch (RuntimeException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { $error = 'Full-text search failed. Check the indexing dashboard and server logs.'; error_log('LetoDMS full-text search failed.'); }
$view = UI::factory($theme,'SearchFulltext',['dms'=>$dms,'user'=>$user,'query'=>$query,'searchquery'=>$query,'fulltextsearch'=>true,'result'=>$result,'error'=>$error,'folderid'=>$folderID,'ownerid'=>$ownerID,'categoryids'=>$categories]);
$view->show();
