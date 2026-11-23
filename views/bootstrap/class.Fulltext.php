<?php
require_once 'class.Bootstrap.php';
class LetoDMS_View_Fulltext extends LetoDMS_Bootstrap_Style
{
    public function show()
    {
        $p=$this->params; $d=$p['dashboard']; $esc=function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
        $this->htmlStartPage('Full-text indexing'); $this->globalNavigation(); $this->contentStart();
        $this->contentHeading('Full-text indexing'); $this->contentContainerStart();
        echo '<p>Search document contents locally. The word index is independent of hosted AI and does not need Zend/Lucene or a separate search service.</p><p><a href="../out/out.Settings.php">Processing settings</a> · <a href="../op/op.SearchFulltext.php">Open full-text search</a></p>';
        if (!$p['fulltextenabled']) echo '<div class="alert alert-info">Full-text search is disabled. Enable it under Admin Tools → Settings → Site → Edition.</div>';
        if ($p['error']) echo '<div class="alert alert-error">'.$esc($p['error']).'</div>';
        if ($p['notice']) echo '<div class="alert alert-success">'.$esc($p['notice']).'</div>';
        echo '<h3>Background worker</h3><p>Schedule this command every minute. Queueing documents does not process them:</p><pre>/usr/bin/php /path/to/LetoDMS/utils/fulltext-worker.php --config=/path/to/LetoDMS/conf/settings.xml --limit=5</pre>';
        echo '<p>If the updated AI worker is already scheduled, it also processes full-text jobs. Use one of these schedules to avoid unnecessary duplicate workers.</p>';
        echo '<p>Last worker activity: '.($d['activity']?$esc(date('Y-m-d H:i:s',(int)$d['activity'][0]['value'])):'None recorded').'</p>';
        echo '<p>Stored document indexes: '.(int)($d['indexed'][0]['total']??0).'. Stale versions and inaccessible documents are excluded from search.</p>';
        foreach ($d['counts'] as $count) echo '<span class="label">'.$esc($count['state']).': '.(int)$count['total'].'</span> ';
        if ($p['fulltextenabled']) {
            echo '<h3>Index existing documents</h3><p>Uploads and new versions are queued automatically while full-text search is enabled. Queue existing documents in batches of up to 200, then allow the worker to run.</p>';
            echo '<form method="post" action="out.Fulltext.php">'.createHiddenFieldWithKey('fulltext-index').'<input type="hidden" name="action" value="batch"><input type="hidden" name="after" value="'.($p['more']?(int)$p['cursor']:0).'"><button class="btn" type="submit">'.($p['more']?'Queue next batch':'Queue existing documents / rebuild').'</button></form>';
        }
        echo '<h3>Recent jobs</h3><div style="overflow-x:auto"><table class="table"><thead><tr><th>Document</th><th>Version</th><th>Status</th><th>Attempts</th><th>Details</th><th>Action</th></tr></thead><tbody>';
        foreach ($d['jobs'] as $job) {
            echo '<tr><td><a href="../out/out.ViewDocument.php?documentid='.(int)$job['document_id'].'">'.(int)$job['document_id'].'</a></td><td>'.(int)$job['document_version'].'</td><td>'.$esc($job['state']).'</td><td>'.(int)$job['attempts'].'</td><td>'.$esc($job['message']).'</td><td>';
            if ($p['fulltextenabled'] && $job['state']!=='running') echo '<form method="post" action="out.Fulltext.php" style="margin:0">'.createHiddenFieldWithKey('fulltext-index').'<input type="hidden" name="action" value="retry"><input type="hidden" name="document_id" value="'.(int)$job['document_id'].'"><button class="btn btn-small" type="submit">Reindex</button></form>';
            echo '</td></tr>';
        }
        if (!$d['jobs']) echo '<tr><td colspan="6">No jobs recorded. Queue existing documents or upload a new file after enabling full-text search.</td></tr>';
        echo '</tbody></table></div><p>PDF requires pdftotext; older Word files require antiword; DOCX requires PHP ZIP and DOM. Scanned PDFs need optional OCRmyPDF and image OCR needs Tesseract. PHP mbstring is required. Limits: 50 MiB input, 2 MiB extracted text, 150,000 unique words. Oversized documents fail visibly; a partial index is never published. See docs/FULLTEXT.md for setup and search behavior.</p>';
        $this->contentContainerEnd(); $this->htmlEndPage();
    }
}
