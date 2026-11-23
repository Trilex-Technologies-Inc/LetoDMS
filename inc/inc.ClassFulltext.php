<?php
require_once __DIR__.'/inc.ClassTextExtractor.php';

/** Local, database-backed inverted index. No AI credentials or external search service. */
class LetoDMS_Fulltext
{
    const MAX_TEXT = 2097152;
    const MAX_FILE = 52428800;
    private $db;
    private $dms;
    private $settings;

    public function __construct($db, $dms, $settings)
    {
        $this->db = $db; $this->dms = $dms; $this->settings = $settings;
    }
    private function sql($sql)
    {
        if (!$this->db->getResult($sql)) throw new RuntimeException('The full-text database operation failed.');
    }
    public function rows($sql)
    {
        $rows = $this->db->getResultArray($sql);
        if (!is_array($rows)) throw new RuntimeException('Full-text indexing is unavailable. Open its administration page to initialize the index.');
        return $rows;
    }
    private function quote($value) { return $this->db->qstr((string)$value); }
    public function initialize()
    {
        $mysql = in_array($this->settings->_dbDriver, ['mysql', 'mysqli', 'mysqlnd'], true);
        $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        $text = $mysql ? 'MEDIUMTEXT' : 'TEXT';
        $this->sql('CREATE TABLE IF NOT EXISTS tblFulltextDocuments (document_id INTEGER PRIMARY KEY, document_version INTEGER NOT NULL, checksum VARCHAR(128) NOT NULL, pages '.$text.' NOT NULL, indexed_at BIGINT NOT NULL)'.$engine);
        // Hashes keep the index portable and searchable for short words, numbers and Unicode words.
        $this->sql('CREATE TABLE IF NOT EXISTS tblFulltextTerms (term_hash VARCHAR(64) NOT NULL, document_id INTEGER NOT NULL, PRIMARY KEY (term_hash,document_id)'.($mysql?', KEY fulltext_document (document_id)':'').')'.$engine);
        $this->sql('CREATE TABLE IF NOT EXISTS tblFulltextJobs (document_id INTEGER PRIMARY KEY, document_version INTEGER NOT NULL, checksum VARCHAR(128) NOT NULL, state VARCHAR(16) NOT NULL, attempts INTEGER NOT NULL, available_at BIGINT NOT NULL, claim_token VARCHAR(64) NOT NULL, message VARCHAR(255) NOT NULL, updated_at BIGINT NOT NULL'.($mysql?', KEY fulltext_ready (state,available_at)':'').')'.$engine);
        if (!$mysql) {
            $this->sql('CREATE INDEX IF NOT EXISTS fulltext_document ON tblFulltextTerms (document_id)');
            $this->sql('CREATE INDEX IF NOT EXISTS fulltext_ready ON tblFulltextJobs (state,available_at)');
        }
        $this->sql('CREATE TABLE IF NOT EXISTS tblFulltextState (name VARCHAR(40) PRIMARY KEY, value VARCHAR(255) NOT NULL)'.$engine);
    }
    private function latest($id)
    {
        $doc = $this->dms->getDocument((int)$id);
        $content = $doc ? $doc->getLatestContent() : null;
        if (!$doc || !$content) throw new RuntimeException('This document version is unavailable.');
        return [$doc, $content];
    }
    public function enqueue($id, $force = false)
    {
        [$doc, $content] = $this->latest($id);
        $id = (int)$doc->getID(); $version = (int)$content->getVersion(); $checksum = $this->quote($content->getChecksum());
        $rows = $this->rows('SELECT * FROM tblFulltextJobs WHERE document_id='.$id);
        if (!$rows) {
            // Concurrent uploads can insert the same job; the conditional update below resolves that race.
            $this->db->getResult('INSERT INTO tblFulltextJobs (document_id,document_version,checksum,state,attempts,available_at,claim_token,message,updated_at) VALUES ('.$id.','.$version.','.$checksum.",'pending',0,".time().",'','',".time().')');
        }
        $reset = $force ? " OR state IN ('failed','complete')" : '';
        $this->sql('UPDATE tblFulltextJobs SET document_version='.$version.',checksum='.$checksum.",state='pending',attempts=0,available_at=".time().",claim_token='',message='',updated_at=".time().' WHERE document_id='.$id.' AND (document_version<'.$version.' OR (document_version='.$version.' AND checksum<>'.$checksum.')'.$reset.')');
        if (!$this->rows('SELECT document_id FROM tblFulltextJobs WHERE document_id='.$id)) throw new RuntimeException('Could not queue full-text indexing.');
    }
    public function enqueueBatch($after = 0, $limit = 200)
    {
        $limit=max(1,min(500,(int)$limit));
        $rows = $this->rows('SELECT id FROM tblDocuments WHERE id>'.max(0,(int)$after).' ORDER BY id LIMIT '.$limit);
        $last = (int)$after; $queued = 0;
        foreach ($rows as $row) {
            $last = (int)$row['id'];
            $doc = $this->dms->getDocument($last);
            if (!$doc || !$doc->getLatestContent()) continue;
            $this->enqueue($last, true); $queued++;
        }
        return ['queued'=>$queued,'cursor'=>$last,'more'=>count($rows)===$limit];
    }
    public static function words($text)
    {
        if (!function_exists('mb_strtolower')) throw new RuntimeException('Full-text search requires PHP mbstring.');
        if (!preg_match('//u', $text)) throw new RuntimeException('Search text must be UTF-8.');
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8'), $matches);
        return $matches[0];
    }
    public static function query($text)
    {
        if (!is_string($text) || strlen($text)>512) throw new RuntimeException('Enter a search of at most 512 bytes.');
        if (substr_count($text, '"') % 2) throw new RuntimeException('Close the quotation marks around your phrase.');
        preg_match_all('/"([^"]+)"/u', $text, $matches);
        $phrases = [];
        foreach ($matches[1] as $phrase) {
            $words = self::words($phrase);
            if ($words) $phrases[] = ' '.implode(' ', $words).' ';
        }
        $words = array_values(array_unique(self::words($text)));
        if (!$words || count($words)>20) throw new RuntimeException('Enter between 1 and 20 searchable words. Use quotes for phrases.');
        return ['words'=>$words, 'phrases'=>$phrases];
    }
    protected function extract($content)
    {
        $options = ['ocr_enabled'=>!empty($this->settings->_fulltextOCR) ? '1':'0', 'ocr_languages'=>$this->settings->_fulltextOCRLanguages ?? 'eng'];
        return (new LetoDMS_TextExtractor($options, self::MAX_TEXT, self::MAX_FILE))->extract($this->dms->contentDir.$content->getPath(), $content->getMimeType());
    }
    public function runOne()
    {
        if (empty($this->settings->_enableFullSearch)) return false;
        $now = time();
        $rows = $this->rows("SELECT * FROM tblFulltextJobs WHERE (state='pending' AND available_at<=".$now.") OR (state='running' AND available_at<=".$now.') ORDER BY available_at,document_id LIMIT 1');
        if (!$rows) return false;
        $job = $rows[0]; $id = (int)$job['document_id']; $version = (int)$job['document_version'];
        $token = bin2hex(random_bytes(24)); $qToken = $this->quote($token);
        $this->sql("UPDATE tblFulltextJobs SET state='running',claim_token=".$qToken.',attempts=attempts+1,available_at='.($now+900).",message='',updated_at=".$now.' WHERE document_id='.$id.' AND document_version='.$version." AND state IN ('pending','running') AND available_at<=".$now);
        $claimed = $this->rows('SELECT * FROM tblFulltextJobs WHERE document_id='.$id.' AND claim_token='.$qToken);
        if (!$claimed) return true;
        $job = $claimed[0];
        try {
            [$doc,$content] = $this->latest($id);
            if ($content->getVersion()!=$version || $content->getChecksum()!==$job['checksum']) {
                $this->enqueue($id);
                throw new RuntimeException('The queued version changed; queue the current document again.');
            }
            $pages = $this->extract($content);
            $words = [];
            foreach ($pages as $page) {
                foreach (self::words($page['text']) as $word) $words[hash('sha256',$word)] = true;
                if (count($words)>150000) throw new RuntimeException('Document exceeds the 150,000 unique-word indexing limit. No partial index was published.');
            }
            if (!$words) throw new RuntimeException('The document contains no searchable words.');
            // ASCII JSON also works with older application connections using MySQL utf8 (three-byte).
            $json = json_encode($pages, JSON_THROW_ON_ERROR);
            $this->db->startTransaction();
            try {
                // Lock this job before publishing so a newer upload cannot be overwritten by an old worker.
                $this->sql('UPDATE tblFulltextJobs SET updated_at='.time().' WHERE document_id='.$id.' AND claim_token='.$qToken);
                if (!$this->rows('SELECT document_id FROM tblFulltextJobs WHERE document_id='.$id.' AND claim_token='.$qToken)) {
                    $this->db->rollbackTransaction(); return true;
                }
                [$currentDoc,$current] = $this->latest($id);
                if ($current->getVersion()!=$version || $current->getChecksum()!==$job['checksum']) throw new RuntimeException('Document changed while it was being indexed.');
                $this->sql('DELETE FROM tblFulltextTerms WHERE document_id='.$id);
                $this->sql('DELETE FROM tblFulltextDocuments WHERE document_id='.$id);
                $this->sql('INSERT INTO tblFulltextDocuments (document_id,document_version,checksum,pages,indexed_at) VALUES ('.$id.','.$version.','.$this->quote($job['checksum']).','.$this->quote($json).','.time().')');
                foreach (array_chunk(array_keys($words),100) as $batch) {
                    $values = [];
                    foreach ($batch as $hash) $values[] = '('.$this->quote($hash).','.$id.')';
                    $this->sql('INSERT INTO tblFulltextTerms (term_hash,document_id) VALUES '.implode(',',$values));
                }
                $this->sql("UPDATE tblFulltextJobs SET state='complete',claim_token='',message='Indexed successfully.',updated_at=".time().' WHERE document_id='.$id.' AND claim_token='.$qToken);
                $this->db->commitTransaction();
            } catch (Throwable $e) { $this->db->rollbackTransaction(); throw $e; }
        } catch (Throwable $e) {
            $failed = (int)$job['attempts']>=3;
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Indexing failed. Check server tools and logs.';
            $this->sql('UPDATE tblFulltextJobs SET state='.$this->quote($failed?'failed':'pending').',claim_token=\'\',available_at='.(time()+60).',message='.$this->quote(substr($message,0,240)).',updated_at='.time().' WHERE document_id='.$id.' AND claim_token='.$qToken);
        }
        return true;
    }
    public function work($limit = 5)
    {
        if (empty($this->settings->_enableFullSearch)) return 0;
        $this->initialize();
        // Remove cached text for deleted documents; search also rejects these before cleanup runs.
        $this->sql('DELETE FROM tblFulltextTerms WHERE NOT EXISTS (SELECT id FROM tblDocuments WHERE tblDocuments.id=tblFulltextTerms.document_id)');
        $this->sql('DELETE FROM tblFulltextDocuments WHERE NOT EXISTS (SELECT id FROM tblDocuments WHERE tblDocuments.id=tblFulltextDocuments.document_id)');
        $this->sql('DELETE FROM tblFulltextJobs WHERE NOT EXISTS (SELECT id FROM tblDocuments WHERE tblDocuments.id=tblFulltextJobs.document_id)');
        $this->db->startTransaction();
        try {
            $this->sql("DELETE FROM tblFulltextState WHERE name='worker_activity'");
            $this->sql("INSERT INTO tblFulltextState (name,value) VALUES ('worker_activity',".$this->quote(time()).')');
            $this->db->commitTransaction();
        } catch (Throwable $e) { $this->db->rollbackTransaction(); throw $e; }
        $processed = 0;
        while ($processed<max(1,min(50,(int)$limit)) && $this->runOne()) $processed++;
        return $processed;
    }
    private function readable($user, $id, $version, $checksum)
    {
        if (!$user || $user->isDisabled()) return null;
        $doc = $this->dms->getDocument($id);
        if (!$doc || $doc->getAccessMode($user)<M_READ) return null;
        $content = $doc->getLatestContent();
        if (!$content || $content->getVersion()!=$version || $content->getChecksum()!==$checksum) return null;
        // Core's workflow lookup returns false for both "none" and SQL errors. Fail closed here.
        $workflows = $this->rows('SELECT workflow FROM tblWorkflowDocumentContent WHERE document='.(int)$id.' AND version='.(int)$version);
        if ($workflows && !$content->getWorkflow()) throw new RuntimeException('Document workflow lookup failed.');
        if ($content->getAccessMode($user)<M_READ) return null;
        return [$doc,$content];
    }
    public function search($user, $text, $after = 0, $folderID = 0, $ownerID = 0, $categories = [])
    {
        if (empty($this->settings->_enableFullSearch)) throw new RuntimeException('Full-text search is disabled in administrator settings.');
        $query = self::query($text); $hashes = [];
        foreach ($query['words'] as $word) $hashes[] = $this->quote(hash('sha256',$word));
        $cursor = max(0,(int)$after); $hits = []; $scanned = 0; $more = false;
        while (true) {
            $candidates = $this->rows('SELECT t.document_id,d.document_version,d.checksum FROM tblFulltextTerms t JOIN tblFulltextDocuments d ON d.document_id=t.document_id WHERE t.document_id>'.$cursor.' AND t.term_hash IN ('.implode(',',$hashes).') GROUP BY t.document_id,d.document_version,d.checksum HAVING COUNT(DISTINCT t.term_hash)='.count($hashes).' ORDER BY t.document_id LIMIT 200');
            if (!$candidates) break;
            foreach ($candidates as $candidate) {
                if ($scanned>=2000) throw new RuntimeException('This search is too broad to verify safely in one request. Add another word or narrow the folder.');
                $cursor = (int)$candidate['document_id']; $scanned++;
                $allowed = $this->readable($user,$cursor,(int)$candidate['document_version'],$candidate['checksum']);
                if (!$allowed) continue;
                [$doc,$content] = $allowed;
                if ($ownerID && $doc->getOwner()->getID()!=(int)$ownerID) continue;
                if ($folderID) {
                    $inside = false;
                    foreach ($doc->getFolder()->getPath() as $ancestor) if ($ancestor->getID()==(int)$folderID) $inside=true;
                    if (!$inside) continue;
                }
                if ($categories) {
                    $assigned = $this->rows('SELECT categoryID FROM tblDocumentCategory WHERE documentID='.$cursor);
                    if (!array_intersect(array_map('intval',$categories),array_map('intval',array_column($assigned,'categoryID')))) continue;
                }
                // Only fetch stored text after current document/version authorization succeeds.
                $stored = $this->rows('SELECT pages FROM tblFulltextDocuments WHERE document_id='.$cursor.' AND document_version='.(int)$content->getVersion());
                if (!$stored) continue;
                $pages = json_decode($stored[0]['pages'],true,512,JSON_THROW_ON_ERROR);
                $normalized = ' '; $snippet = null;
                foreach ($pages as $page) {
                    $normalized .= implode(' ',self::words($page['text'])).' ';
                    if ($snippet===null) {
                        $position = mb_stripos($page['text'],$query['words'][0],0,'UTF-8');
                        if ($position!==false) $snippet = ['page'=>(int)$page['page'],'text'=>mb_substr($page['text'],max(0,$position-80),360,'UTF-8')];
                    }
                }
                foreach ($query['phrases'] as $phrase) if (strpos($normalized,$phrase)===false) continue 2;
                if (count($hits)>=20) return ['hits'=>$hits,'next'=>$hits[count($hits)-1]['id'],'words'=>$query['words']];
                $hits[] = ['id'=>$cursor,'name'=>$doc->getName(),'version'=>(int)$content->getVersion(),'snippet'=>$snippet ?: ['page'=>0,'text'=>'']];
            }
            if (count($candidates)<200) break;
        }
        return ['hits'=>$hits,'next'=>$more?$cursor:null,'words'=>$query['words']];
    }
    public static function highlight($text, $words)
    {
        $parts = preg_split('/((?<![\p{L}\p{N}])(?:'.implode('|',array_map(function($w){return preg_quote($w,'/');},$words)).')(?![\p{L}\p{N}]))/iu',$text,-1,PREG_SPLIT_DELIM_CAPTURE);
        $html='';
        foreach ($parts as $i=>$part) $html .= $i%2 ? '<mark>'.htmlspecialchars($part,ENT_QUOTES,'UTF-8').'</mark>' : htmlspecialchars($part,ENT_QUOTES,'UTF-8');
        return $html;
    }
    public function dashboard()
    {
        return ['counts'=>$this->rows('SELECT state,COUNT(*) AS total FROM tblFulltextJobs GROUP BY state'),
            'jobs'=>$this->rows('SELECT * FROM tblFulltextJobs ORDER BY updated_at DESC,document_id DESC LIMIT 100'),
            'activity'=>$this->rows("SELECT value FROM tblFulltextState WHERE name='worker_activity'"),
            'indexed'=>$this->rows('SELECT COUNT(*) AS total FROM tblFulltextDocuments')];
    }
}
