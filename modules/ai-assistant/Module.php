<?php
class LetoDMS_AIAssistant_Module {
    public function install($db, $driver) {
        require_once __DIR__.'/inc/ProviderSettings.php';
        require_once __DIR__.'/inc/Store.php';
        try { (new LetoDMS_AIProviderSettings($db, ''))->initialize(); (new LetoDMS_AIStore($db, $driver))->initialize(); }
        catch (RuntimeException $e) { return false; }
        if (!$db->getResult('CREATE TABLE IF NOT EXISTS tblModuleAIResults (
            document_id INTEGER NOT NULL, document_version INTEGER NOT NULL,
            user_id INTEGER NOT NULL, provider VARCHAR(80) NOT NULL,
            model VARCHAR(255) NOT NULL, result_text TEXT NOT NULL,
            created_at INTEGER NOT NULL)')) return false;
        return (bool) $db->getResult("INSERT INTO tblPermissions (name, description)
            SELECT 'module.ai-assistant.access', 'Use hosted AI document analysis'
            WHERE NOT EXISTS (SELECT 1 FROM tblPermissions WHERE name = 'module.ai-assistant.access')");
    }
    public function uninstall($db, $driver) {
        foreach (array('tblModuleAIChunks', 'tblModuleAIJobs', 'tblModuleAIOptions', 'tblModuleAIInteractions', 'tblModuleAIUsage', 'tblModuleAIQuota', 'tblModuleAISuggestions', 'tblModuleAIChanges', 'tblModuleAIFingerprints', 'tblModuleAIEmbeddingCache') as $table)
            if (!$db->getResult('DROP TABLE IF EXISTS '.$table)) return false;
        if (!$db->getResult('DROP TABLE IF EXISTS tblModuleAIProviders')) return false;
        if (!$db->getResult("DELETE FROM tblRolePermissions WHERE permissionID IN (SELECT id FROM tblPermissions WHERE name = 'module.ai-assistant.access')")) return false;
        if (!$db->getResult("DELETE FROM tblPermissions WHERE name = 'module.ai-assistant.access'")) return false;
        return (bool) $db->getResult('DROP TABLE IF EXISTS tblModuleAIResults');
    }
}
