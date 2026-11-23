<?php
// Legacy version links now open the document; the module landing page is administration.
if (!isset($_GET['settings']) && !isset($_GET['tools']) && !isset($_GET['passage'])) {
    if (isset($_GET['documentid']) && is_scalar($_GET['documentid']) && (int)$_GET['documentid'] > 0) {
        $target = '../out/out.ViewDocument.php?documentid='.(int)$_GET['documentid'];
        if (isset($_GET['version']) && is_scalar($_GET['version'])) $target .= '&ai_version='.(int)$_GET['version'];
        header('Location: '.$target.'#document-ai'); exit;
    }
    $_GET['settings'] = '1';
}
require_once __DIR__.'/inc/Assistant.php';
require_once __DIR__.'/inc/ProviderSettings.php';
require_once __DIR__.'/inc/Store.php';
require_once __DIR__.'/inc/Queue.php';
$store = new LetoDMS_AIStore($db, $settings->_dbDriver);
$providerSettings = new LetoDMS_AIProviderSettings($db, $settings->_encryptionKey);
try { $providerSettings->initialize(); $store->initialize(); }
catch (RuntimeException $e) { UI::exitError('AI Assistant', $e->getMessage()); exit; }
if (isset($_GET['settings'])) {
    if (!$user->isAdmin()) { UI::exitError('AI Assistant', getMLText('access_denied')); exit; }
    require_once __DIR__.'/views/Settings.php';
    require_once __DIR__.'/inc/Usage.php';
    try { $providers = $providerSettings->listing($user); }
    catch (RuntimeException $e) { UI::exitError('AI Assistant', $e->getMessage()); exit; }
    header('Cache-Control: no-store');
    $assistant = new LetoDMS_AIAssistant($db, $providerSettings, $store); $assistant->setUser($user);
    $view = new LetoDMS_Module_View_AISettings(array('dms' => $dms, 'user' => $user, 'providers' => $providers, 'choices' => $assistant->providers(), 'options' => $store->options(), 'worker_status' => $store->workerStatus($user, isset($_GET['job_state']) ? $_GET['job_state'] : ''), 'usage_report' => (new LetoDMS_AIUsage($store))->report($user), 'saved' => isset($_GET['saved'])), 'bootstrap');
    UI::configureView($view)->show();
    exit;
}
$assistant = new LetoDMS_AIAssistant($db, $providerSettings, $store); $assistant->setUser($user);
if (isset($_GET['tools']) || isset($_GET['passage'])) {
    require_once __DIR__.'/views/Tools.php';
    $result = null;
    if (isset($_GET['passage'])) {
        try {
            $sourceId = isset($_GET['documentid']) && is_scalar($_GET['documentid']) ? (int)$_GET['documentid'] : 0;
            $sourceVersion = isset($_GET['version']) && is_scalar($_GET['version']) ? (int)$_GET['version'] : 0;
            $page = isset($_GET['page']) && is_scalar($_GET['page']) ? (int)$_GET['page'] : 0;
            $pages = $assistant->extract($dms, $user, $sourceId, $sourceVersion);
            $selected = array_filter($pages, function ($item) use ($page) { return $page === 0 || $item['page'] === $page; });
            if (!$selected) throw new RuntimeException('The source page was not found.');
            $result = array('answer' => implode("\n\n", array_column($selected, 'text')), 'sources' => array());
        } catch (RuntimeException $e) { UI::exitError('AI Assistant', $e->getMessage()); exit; }
    }
    header('Cache-Control: no-store');
    $view = new LetoDMS_Module_View_AITools(array('dms' => $dms, 'user' => $user, 'providers' => $assistant->providers(), 'result' => $result, 'matches' => array(), 'message' => '', 'source_view' => isset($_GET['passage'])), 'bootstrap');
    UI::configureView($view)->show(); exit;
}
exit;
