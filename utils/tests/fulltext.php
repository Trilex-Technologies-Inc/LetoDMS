<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
$root=dirname(__DIR__,2);
set_include_path($root.'/LetoDMS_Core/Core'.PATH_SEPARATOR.$root.'/inc'.PATH_SEPARATOR.get_include_path());
require_once $root.'/LetoDMS_Core/Core.php';
require_once $root.'/inc/inc.ClassFulltext.php';
require_once $root.'/inc/inc.FulltextUpload.php';
function ftCheck($ok,$message) { if (!$ok) throw new RuntimeException($message); }
class FulltextTestDB extends LetoDMS_Core_DatabaseAccess {
    public $failTerms=false;
    public function getResult($sql,$silent=false) {
        if ($this->failTerms && strpos($sql,'INSERT INTO tblFulltextTerms')===0) { $this->failTerms=false; return false; }
        return parent::getResult($sql,$silent);
    }
}
class RacingFulltext extends LetoDMS_Fulltext {
    public $during;
    protected function extract($content) {
        $pages=parent::extract($content);
        if ($this->during) { $callback=$this->during; $this->during=null; $callback(); }
        return $pages;
    }
}
$temp=sys_get_temp_dir().'/letodms-fulltext-'.bin2hex(random_bytes(8)).'/'; mkdir($temp,0700);
$db=new FulltextTestDB('sqlite','','','',':memory:'); ftCheck($db->connect(),'Connect SQLite');
ftCheck($db->getResult(file_get_contents($root.'/install/create_tables-sqlite3.sql')),'Create real DMS schema');
$dms=new LetoDMS_Core_DMS($db,$temp); $dms->setEnableAdminRevApp(true);
$settings=(object)['_dbDriver'=>'sqlite','_enableFullSearch'=>true,'_fulltextOCR'=>false,'_fulltextOCRLanguages'=>'eng'];
$index=new RacingFulltext($db,$dms,$settings); $index->initialize();
$admin=$dms->getUser(1);
function ftDocument($name,$text,$folder=null) {
    global $temp,$dms,$admin;
    $file=$temp.'upload.txt'; file_put_contents($file,$text);
    $result=($folder?:$dms->getRootFolder())->addDocument($name,'',0,$admin,'',[],$file,$name,'.txt','text/plain',0,[],[],0);
    ftCheck((bool)$result,'Create document '.$name); return $result[0];
}
function ftVersion($id,$text) {
    global $temp,$dms,$admin;
    $file=$temp.'upload.txt'; file_put_contents($file,$text);
    return $dms->getDocument($id)->addContent('Revision',$admin,$file,'file.txt','.txt','text/plain');
}
try {
    $one=ftDocument('First document','A 7 café résumé. The red fox meets a blue fox. <script>alert("bad")</script>');
    $two=ftDocument('Second document','The red bird and blue fox. Numbers 227663 and Unicode 東京.');
    letoDMSQueueFulltextUpload($db,$dms,$settings,$one); letoDMSQueueFulltextUpload($db,$dms,$settings,$two);
    ftCheck($index->work(10)===2,'Process automatically queued files without AI module');
    ftCheck(count($index->search($admin,'red fox')['hits'])===2,'AND matches across words');
    ftCheck(count($index->search($admin,'"red fox"')['hits'])===1,'Quoted phrase excludes separated words');
    ftCheck(count($index->search($admin,'A 7')['hits'])===1,'Short words and digits are indexed');
    ftCheck(count($index->search($admin,'CAFÉ RÉSUMÉ')['hits'])===1,'Unicode case folding');
    ftCheck(count($index->search($admin,'東京 227663')['hits'])===1,'Unicode and document numbers');
    ftCheck(!$index->search($admin,"' OR 1=1 --")['hits'],'SQL-like input is literal text');
    $html=LetoDMS_Fulltext::highlight('<script>red</script> fox',['red','fox']);
    ftCheck(strpos($html,'<script>')===false && strpos($html,'<mark>red</mark>')!==false,'Escape excerpts before highlighting');
    try { LetoDMS_Fulltext::query('"unclosed'); throw new Exception('Expected invalid phrase'); } catch(RuntimeException $e) {}
    $large=ftDocument('Long document',str_repeat('ordinary text ',6000).'tailneedle');
    $index->enqueue($large->getID()); $index->work(5);
    ftCheck(count($index->search($admin,'tailneedle')['hits'])===1,'Index beyond AI 60,000-byte cap');
    // ACL changes must remove both results and excerpts immediately, without rebuilding.
    ftCheck($db->getResult("INSERT INTO tblUsers (id,login,pwd,fullName,language,theme,comment,role) VALUES (3,'reader','x','Reader','','','',0)"),'Create reader');
    $reader=$dms->getUser(3);
    ftCheck(count($index->search($reader,'red')['hits'])===2,'Readable documents searchable');
    ftCheck($db->getResult('UPDATE tblDocuments SET inheritAccess=0,defaultAccess=0 WHERE id='.(int)$one->getID()),'Restrict access');
    $hits=$index->search($reader,'red')['hits'];
    ftCheck(count($hits)===1 && $hits[0]['id']===$two->getID(),'ACL excludes hidden document and excerpt');
    ftCheck($db->getResult('UPDATE tblUsers SET disabled=1 WHERE id=3'),'Disable reader');
    ftCheck(!$index->search($dms->getUser(3),'red')['hits'],'Disabled user cannot search');
    ftCheck(ftVersion($two->getID(),'Current zebra only.'),'Create revised contents');
    ftCheck(count($index->search($admin,'red')['hits'])===1,'Never serve old version while current version awaits indexing');
    $index->enqueue($two->getID()); $index->work(5);
    ftCheck(count($index->search($admin,'zebra')['hits'])===1,'Revised contents searchable');
    ftCheck(count($index->search($admin,'red')['hits'])===1,'Old terms removed');
    // A newer upload revokes a worker claim before an older extraction publishes.
    $index->enqueue($two->getID(),true);
    $index->during=function()use($two,$index){ftVersion($two->getID(),'Newest giraffe only.');$index->enqueue($two->getID());};
    $index->runOne();
    ftCheck(!$index->search($admin,'zebra')['hits'],'Old worker cannot expose superseded contents');
    $index->work(5); ftCheck(count($index->search($admin,'giraffe')['hits'])===1,'New job survives stale worker');
    // A database error during publishing rolls back both the document record and terms.
    $index->enqueue($two->getID(),true); $db->failTerms=true; $index->runOne();
    ftCheck(count($index->search($admin,'giraffe')['hits'])===1,'Failed rebuild preserves previous complete index');
    $db->getResult('UPDATE tblFulltextJobs SET available_at=0 WHERE document_id='.$two->getID()); $index->work(5);
    // Workflow restrictions apply even when the document ACL grants an administrator full access.
    $db->getResult("INSERT INTO tblWorkflowStates (id,name) VALUES (1,'Restricted'); INSERT INTO tblWorkflows (id,name,initstate) VALUES (1,'Workflow',1)");
    $latest=$dms->getDocument($one->getID())->getLatestContent();
    ftCheck($latest->setWorkflow($dms->getWorkflow(1),$admin),'Assign workflow');
    ftCheck(!$index->search($admin,'résumé')['hits'],'Workflow-restricted version excluded');
    // Deleted documents do not produce cached matches.
    ftCheck($dms->getDocument($large->getID())->remove(),'Delete indexed document');
    ftCheck(!$index->search($admin,'tailneedle')['hits'],'Deleted content excluded');
    $oversize=ftDocument('Oversize document',str_repeat('x',LetoDMS_Fulltext::MAX_TEXT+1));
    $index->enqueue($oversize->getID());
    for($i=0;$i<3;$i++) { $db->getResult('UPDATE tblFulltextJobs SET available_at=0 WHERE document_id='.$oversize->getID());$index->work(5); }
    $job=$db->getResultArray('SELECT * FROM tblFulltextJobs WHERE document_id='.$oversize->getID())[0];
    ftCheck($job['state']==='failed' && (int)$job['attempts']===3,'Bounded retries with visible failure');
    ftCheck(!$db->getResultArray('SELECT * FROM tblFulltextDocuments WHERE document_id='.$oversize->getID()),'No partial index for oversized file');
    $db->getResult('UPDATE tblUsers SET disabled=0 WHERE id=3'); $reader=$dms->getUser(3);
    $pageIDs=[];
    for($i=0;$i<25;$i++) {
        $paged=ftDocument('Paged '.$i,'Paginationneedle document '.$i);
        $pageIDs[]=$paged->getID(); $index->enqueue($paged->getID());
        if($i<4) $db->getResult('UPDATE tblDocuments SET inheritAccess=0,defaultAccess=0 WHERE id='.$paged->getID());
    }
    $index->work(50);
    $first=$index->search($reader,'paginationneedle');
    ftCheck(count($first['hits'])===20 && $first['next']!==null,'First page contains 20 authorized matches');
    $second=$index->search($reader,'paginationneedle',$first['next']);
    ftCheck(count($second['hits'])===1 && $second['next']===null,'Second page contains only remaining authorized match');
    $allIDs=array_column(array_merge($first['hits'],$second['hits']),'id');
    ftCheck(count(array_unique($allIDs))===21 && !array_intersect(array_slice($pageIDs,0,4),$allIDs),'No duplicates or hidden document IDs in pagination');
    $folder=$dms->getRootFolder()->addSubFolder('Scoped','',$admin,0);
    $scoped=ftDocument('Scoped file','Scopeword zebra',$folder); $index->enqueue($scoped->getID()); $index->work(5);
    ftCheck(count($index->search($admin,'zebra',0,$folder->getID())['hits'])===1,'Folder filter matches descendants');
    ftCheck(!$index->search($admin,'paginationneedle',0,$folder->getID())['hits'],'Folder filter excludes outside documents');
    $db->getResult("INSERT INTO tblCategory (id,name) VALUES (9,'Scoped category'); INSERT INTO tblDocumentCategory (documentID,categoryID) VALUES (".$scoped->getID().',9)');
    ftCheck(count($index->search($admin,'zebra',0,0,1,[9])['hits'])===1,'Owner and category filters');
    ftCheck(!$index->search($admin,'zebra',0,0,3)['hits'],'Wrong owner excluded');
    $index->enqueue($scoped->getID(),true);
    $db->getResult("UPDATE tblFulltextJobs SET state='running',claim_token='abandoned',available_at=0 WHERE document_id=".$scoped->getID());
    $index->work(5);
    ftCheck($db->getResultArray('SELECT state FROM tblFulltextJobs WHERE document_id='.$scoped->getID())[0]['state']==='complete','Recover expired worker lease');
    // Explicit reindexing also recovers after the most recent version was deleted.
    ftCheck(ftVersion($scoped->getID(),'Temporary newest capybara.'),'Create temporary latest revision');
    $index->enqueue($scoped->getID()); $index->work(5);
    ftCheck(count($index->search($admin,'capybara')['hits'])===1,'Newest revision indexed');
    $current=$dms->getDocument($scoped->getID()); ftCheck($current->removeContent($current->getLatestContent()),'Delete latest version');
    $index->enqueue($scoped->getID(),true); $index->work(5);
    ftCheck(count($index->search($admin,'scopeword')['hits'])===1,'Reindex earlier current version after deletion');
    $settings->_enableFullSearch=false;
    $before=count($db->getResultArray('SELECT * FROM tblFulltextJobs'));
    $off=ftDocument('Disabled indexing','Disabled pipeline.');letoDMSQueueFulltextUpload($db,$dms,$settings,$off);
    ftCheck(count($db->getResultArray('SELECT * FROM tblFulltextJobs'))===$before && $index->work()===0,'Disabled indexing does not queue or process');
    echo "Full-text SQLite integration checks passed.\n";
} finally {
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) { if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname()); } rmdir($temp);
}
