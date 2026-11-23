<?php
/** Called only after a successful upload/version commit. No provider calls here. */
function letoDMSQueueAIUpload($db, $dms, $settings, $user, $document, $content = null) {
    try {
        require_once $settings->_rootDir.'inc/inc.ClassModuleManager.php';
        $manager = new LetoDMS_ModuleManager($db, $settings->_rootDir.'modules', $settings->_dbDriver);
        if (!$manager->isEnabled('ai-assistant') || !$user->hasPermission('module.ai-assistant.access')) return;
        require_once __DIR__.'/ProviderSettings.php'; require_once __DIR__.'/Assistant.php'; require_once __DIR__.'/Queue.php';
        $store = new LetoDMS_AIStore($db, $settings->_dbDriver); $store->initialize();
        $options = $store->options();
        if ($options['automatic'] !== '1') return;
        $providers = new LetoDMS_AIProviderSettings($db, $settings->_encryptionKey); $providers->initialize();
        $assistant = new LetoDMS_AIAssistant($db, $providers);
        if (!$content) $content = $document->getLatestContent();
        if ($content) (new LetoDMS_AIQueue($store))->enqueue($user, $assistant, $dms, $document->getID(), $content->getVersion(), $options['analysis_provider']);
    } catch (Throwable $e) {
        // A queue failure must not fail the upload or echo sensitive diagnostics.
        error_log('LetoDMS AI: could not queue automatic upload analysis; inspect module configuration.');
    }
}
