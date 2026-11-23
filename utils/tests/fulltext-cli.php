<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);
$temp=sys_get_temp_dir().'/letodms-ft-cli-'.bin2hex(random_bytes(8)).'/';mkdir($temp,0700);mkdir($temp.'1',0700);
function ftCLI($args) {
    $process=proc_open(array_merge([PHP_BINARY,'-d','extension=pdo_sqlite','-d','error_reporting=22527'], $args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start worker fixture');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Worker failed: '.$error.$out);
    return $out;
}
try {
    $pdo=new PDO('sqlite:'.$temp.'index.sqlite');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec(file_get_contents($root.'/install/create_tables-sqlite3.sql'));
    $text='Full-text CLI wombat. Emoji 🦊 is preserved.';file_put_contents($temp.'1/1.txt',$text);
    $pdo->exec("INSERT INTO tblDocuments (id,name,comment,date,expires,owner,folder,folderList,inheritAccess,defaultAccess,locked,keywords,sequence) VALUES (1,'CLI document','',1,0,1,1,':1:',1,2,-1,'',0)");
    $pdo->exec("INSERT INTO tblDocumentContent (document,version,comment,date,createdBy,dir,orgFileName,fileType,mimeType,fileSize,checksum) VALUES (1,1,'',1,1,'1/','cli.txt','.txt','text/plain',".strlen($text).",'".md5($text)."')");
    $pdo=null;
    $xml=simplexml_load_file($root.'/conf/settings.xml.template');
    $xml->system->database['dbDriver']='sqlite';$xml->system->database['dbDatabase']=$temp.'index.sqlite';$xml->system->database['dbHostname']='';$xml->system->database['dbUser']='';$xml->system->database['dbPass']='';
    $xml->system->server['rootDir']=$root.'/';$xml->system->server['contentDir']=$temp;
    $xml->advanced->server['contentOffsetDir']='';$xml->site->edition['enableFullSearch']='true';
    $xml->asXML($temp.'settings.xml');
    $out=ftCLI([$root.'/utils/fulltext-worker.php','--config='.$temp.'settings.xml','--rebuild','--limit=5']);
    if(strpos($out,'Documents queued: 1')===false || strpos($out,'Full-text jobs attempted: 1')===false)throw new RuntimeException('Unexpected CLI worker output: '.$out);
    $pdo=new PDO('sqlite:'.$temp.'index.sqlite');
    $job=$pdo->query('SELECT state FROM tblFulltextJobs')->fetchColumn();
    $json=$pdo->query('SELECT pages FROM tblFulltextDocuments')->fetchColumn();
    if($job!=='complete' || json_decode($json,true)[0]['text']!==$text)throw new RuntimeException('CLI did not index the fixture correctly');
    if(preg_match('/[^\x00-\x7f]/',$json))throw new RuntimeException('Stored JSON should be compatible with legacy MySQL connection encoding');
    $pdo=null;
    require_once $root.'/inc/inc.ClassSettings.php';
    $config=new Settings($temp.'settings.xml');$config->_fulltextOCR=true;$config->_fulltextOCRLanguages='eng+fra';
    if(!$config->save())throw new RuntimeException('Cannot save indexing configuration');
    $again=new Settings($temp.'settings.xml');
    if(!$again->_fulltextOCR || $again->_fulltextOCRLanguages!=='eng+fra')throw new RuntimeException('OCR settings did not round-trip');
    $again->_enableFullSearch=false;$again->save();
    $out=ftCLI([$root.'/utils/fulltext-worker.php','--config='.$temp.'settings.xml']);
    if(strpos($out,'Full-text search is disabled')===false)throw new RuntimeException('Disabled worker did not stop');
    echo "Full-text CLI worker and settings checks passed.\n";
} finally {
    if(isset($pdo))$pdo=null;
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as$file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($temp);
}
