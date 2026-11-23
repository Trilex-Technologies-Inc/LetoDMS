<?php
require_once __DIR__.'/inc/Assistant.php';
require_once __DIR__.'/inc/ProviderSettings.php';
require_once __DIR__.'/inc/Library.php';
require_once __DIR__.'/inc/Queue.php';
$ui = new UI($GLOBALS['theme'] ?? 'bootstrap');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !checkFormKey('ai-assistant'))
    $ui->exitError('AI Assistant', getMLText('invalid_request_token'));
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'analyze';
if ($action === 'list_models') {
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    if (!$user->isAdmin()) { http_response_code(403); echo json_encode(array('error' => 'Administrator access is required.')); exit; }
    try {
        require_once __DIR__.'/inc/ModelDiscovery.php';
        $providerSettings = new LetoDMS_AIProviderSettings($db, $settings->_encryptionKey);
        $providerSettings->initialize();
        echo json_encode((new LetoDMS_AIModelDiscovery($providerSettings))->models($user, $_POST));
    } catch (RuntimeException $e) { http_response_code(400); echo json_encode(array('error' => $e->getMessage())); }
    exit;
}
if ($action === 'save_provider' || $action === 'remove_provider' || $action === 'save_options') {
    if (!$user->isAdmin()) { $ui->exitError('AI Assistant', getMLText('access_denied')); exit; }
    try {
        $providerSettings = new LetoDMS_AIProviderSettings($db, $settings->_encryptionKey);
        $providerSettings->initialize();
        if ($action === 'save_options') {
            $store = new LetoDMS_AIStore($db, $settings->_dbDriver); $store->initialize();
            $store->saveOptions($user, $_POST, new LetoDMS_AIAssistant($db, $providerSettings));
        } elseif ($action === 'save_provider') $providerSettings->save($user, $_POST);
        else $providerSettings->remove($user, isset($_POST['provider_id']) ? $_POST['provider_id'] : '');
    } catch (RuntimeException $e) { $ui->exitError('AI Assistant', $e->getMessage()); exit; }
    header('Location: ../out/out.Module.php?module=ai-assistant&settings=1&saved=1');
    exit;
}
if (!in_array($action, array('analyze', 'queue', 'index', 'search', 'ask', 'compare', 'metadata', 'apply_metadata', 'dismiss_metadata', 'duplicates', 'retry_job', 'cancel_job'), true)) { $ui->exitError('AI Assistant', 'Invalid action.'); exit; }
$id = isset($_POST['documentid']) && is_scalar($_POST['documentid']) ? (int)$_POST['documentid'] : 0;
$version = isset($_POST['version']) && is_scalar($_POST['version']) ? (int)$_POST['version'] : 0;
$provider = isset($_POST['provider']) && is_string($_POST['provider']) ? $_POST['provider'] : '';
try {
    $providerSettings = new LetoDMS_AIProviderSettings($db, $settings->_encryptionKey);
    $providerSettings->initialize();
    $store = new LetoDMS_AIStore($db, $settings->_dbDriver); $store->initialize();
    $assistant = new LetoDMS_AIAssistant($db, $providerSettings, $store); $assistant->setUser($user);
    $library = new LetoDMS_AILibrary($store, $assistant, $dms);
    if ($action === 'retry_job' || $action === 'cancel_job') {
        (new LetoDMS_AIQueue($store))->manage($user, $assistant, $dms, isset($_POST['job_id']) ? $_POST['job_id'] : '', $action === 'retry_job' ? 'retry' : 'cancel');
        header('Location: ../out/out.Module.php?module=ai-assistant&settings=1&saved=1&panel=jobs'); exit;
    }
    if (in_array($action, array('metadata', 'apply_metadata', 'dismiss_metadata'), true)) {
        require_once __DIR__.'/inc/Metadata.php';
        $metadata = new LetoDMS_AIMetadata($store, $assistant, $dms);
        if ($action === 'metadata') $metadata->generate($user, $id, $version, $provider);
        else $metadata->review($user, $id, $version, isset($_POST['suggestion_id']) ? $_POST['suggestion_id'] : '', $action === 'apply_metadata' ? 'apply' : 'dismiss', isset($_POST['apply_title']) && $_POST['apply_title'] === '1', isset($_POST['apply_keywords']) && $_POST['apply_keywords'] === '1');
        header('Location: ../out/out.ViewDocument.php?documentid='.$id.'&ai_version='.$version.'&ai_done='.$action.'#document-ai'); exit;
    }
    if ($action === 'duplicates') {
        $assistant->content($dms, $user, $id, $version);
        header('Location: ../out/out.ViewDocument.php?documentid='.$id.'&ai_version='.$version.'&ai_duplicates=1#document-ai'); exit;
    }
    if (in_array($action, array('search', 'ask', 'compare'), true)) {
        $question = isset($_POST['question']) && is_string($_POST['question']) ? trim($_POST['question']) : '';
        $result = null; $matches = array(); $message = '';
        $quick = $action === 'search' && isset($_POST['quick_search']) && $_POST['quick_search'] === '1';
        if ($action === 'search') {
            try { $matches = $library->search($user, $question); }
            catch (RuntimeException $e) { if (!$quick) throw $e; $message = $e->getMessage(); }
        }
        elseif ($action === 'ask') $result = $library->ask($user, $provider, $question, $id, $version);
        else {
            $newVersion = isset($_POST['new_version']) && is_scalar($_POST['new_version']) ? (int)$_POST['new_version'] : 0;
            $result = $library->compare($user, $provider, $id, $version, $newVersion);
        }
        if (($action === 'ask' || $action === 'compare') && isset($_POST['return_document']) && $_POST['return_document'] === '1' && isset($result['interaction_id'])) {
            header('Location: ../out/out.ViewDocument.php?documentid='.$id.'&ai_result='.$result['interaction_id'].'&ai_version='.$version.'#document-ai'); exit;
        }
        require_once __DIR__.'/views/Tools.php';
        header('Cache-Control: no-store');
        $view = new LetoDMS_Module_View_AITools(array('dms' => $dms, 'user' => $user, 'providers' => $assistant->providers(), 'result' => $result, 'matches' => $matches, 'quick_search' => $quick, 'searchquery' => $question, 'message' => $message !== '' ? $message : ($action === 'search' && !$matches ? 'No accessible indexed results. Index documents using the current embeddings configuration first.' : '')), 'bootstrap');
        UI::configureView($view)->show(); exit;
    } elseif ($action === 'queue') {
        (new LetoDMS_AIQueue($store))->enqueue($user, $assistant, $dms, $id, $version, $provider);
    } elseif ($action === 'index') $library->index($user, $id, $version);
    else $assistant->analyze($dms, $user, $id, $version, $provider);
} catch (RuntimeException $e) {
    $ui->exitError('AI Assistant', $e->getMessage());
}
if (in_array($action, array('analyze', 'index', 'queue'), true) && isset($_POST['return_document']) && $_POST['return_document'] === '1') {
    header('Location: ../out/out.ViewDocument.php?documentid='.$id.'&ai_version='.$version.'&ai_done='.$action.'#document-ai'); exit;
}
if ($action === 'index' && isset($_POST['return_folder']) && is_scalar($_POST['return_folder'])) {
    $returnFolder = (int)$_POST['return_folder'];
    $documentFolder = $dms->getDocument($id)->getFolder();
    if ($returnFolder > 0 && $documentFolder && (int)$documentFolder->getID() === $returnFolder) {
        header('Location: ../out/out.ViewFolder.php?folderid='.$returnFolder.'&ai_indexed=1'); exit;
    }
}
header('Location: ../out/out.Module.php?module=ai-assistant&documentid='.$id.'&version='.$version.'&'.($action === 'queue' ? 'queued' : ($action === 'index' ? 'indexed' : 'saved')).'=1');
exit;
