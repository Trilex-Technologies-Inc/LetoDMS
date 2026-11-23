<?php
/** Queue only; extraction runs in the local worker after the upload commits. */
function letoDMSQueueFulltextUpload($db, $dms, $settings, $document, $content = null)
{
    if (empty($settings->_enableFullSearch)) return;
    try {
        require_once __DIR__.'/inc.ClassFulltext.php';
        $search = new LetoDMS_Fulltext($db, $dms, $settings);
        $search->initialize();
        $search->enqueue($document->getID());
    } catch (Throwable $e) {
        error_log('LetoDMS: full-text upload queue failed; inspect indexing configuration.');
    }
}
