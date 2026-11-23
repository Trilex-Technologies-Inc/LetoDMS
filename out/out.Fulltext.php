<?php
require '../inc/inc.Settings.php';
require '../inc/inc.Utils.php';
require '../inc/inc.DBInit.php';
require '../inc/inc.Language.php';
require '../inc/inc.ClassUI.php';
require '../inc/inc.Authentication.php';
require_once '../inc/inc.ClassFulltext.php';
header('Cache-Control: private, no-store');
if (!$user->isAdmin()) { http_response_code(403); UI::exitError('Full-text indexing','Administrator access is required.'); exit; }
$index = new LetoDMS_Fulltext($db,$dms,$settings); $error=''; $notice='';
$cursor = isset($_GET['cursor']) && is_scalar($_GET['cursor']) ? max(0,(int)$_GET['cursor']) : 0;
$more = !empty($_GET['more']);
try {
    $index->initialize();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $expected = createFormKey('fulltext-index');
        if (!$expected || !isset($_POST['formtoken']) || !is_string($_POST['formtoken']) || !hash_equals($expected,$_POST['formtoken'])) throw new RuntimeException('The form expired. Refresh and try again.');
        if (!$settings->_enableFullSearch) throw new RuntimeException('Enable full-text search in administrator settings first.');
        $action = $_POST['action'] ?? '';
        if ($action==='batch') {
            $after = isset($_POST['after']) && is_scalar($_POST['after']) ? max(0,(int)$_POST['after']) : 0;
            $batch = $index->enqueueBatch($after);
            header('Location: out.Fulltext.php?'.http_build_query(['cursor'=>$batch['cursor'],'more'=>$batch['more']?1:0,'queued'=>$batch['queued']]),true,303); exit;
        } elseif ($action==='retry' && isset($_POST['document_id']) && is_scalar($_POST['document_id']) && (int)$_POST['document_id']>0) {
            $index->enqueue((int)$_POST['document_id'],true);
            header('Location: out.Fulltext.php?retried=1',true,303); exit;
        } else throw new RuntimeException('Invalid indexing action.');
    }
    $dashboard = $index->dashboard();
    if (isset($_GET['queued']) && is_scalar($_GET['queued'])) $notice=max(0,(int)$_GET['queued']).' documents queued. The scheduled worker will process them.';
    if (!empty($_GET['retried'])) $notice='The current document version was queued, or is already pending/running.';
} catch (RuntimeException $e) { $error=$e->getMessage(); $dashboard=['counts'=>[],'jobs'=>[],'activity'=>[],'indexed'=>[]]; }
$view=UI::factory($theme,'Fulltext',['dms'=>$dms,'user'=>$user,'dashboard'=>$dashboard,'error'=>$error,'notice'=>$notice,'cursor'=>$cursor,'more'=>$more,'fulltextenabled'=>$settings->_enableFullSearch]);
$view->show();
