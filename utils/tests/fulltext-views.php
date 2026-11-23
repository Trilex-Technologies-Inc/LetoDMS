<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);
require_once $root.'/inc/inc.ClassViewCommon.php';
require_once $root.'/inc/inc.ClassFulltext.php';
set_include_path($root.'/views/bootstrap'.PATH_SEPARATOR.get_include_path());
require_once $root.'/views/bootstrap/class.Fulltext.php';
require_once $root.'/views/bootstrap/class.SearchFulltext.php';
function getMLText($key) { return $key; }
function createHiddenFieldWithKey($id='') { return '<input type="hidden" name="formtoken" value="offline-csrf-token">'; }
class FTViewUser { public function isAdmin(){return true;} }
trait FTMinimalShell {
    public function htmlStartPage($title='',$bodyClass=''){echo '<!doctype html><html><body>';}
    public function globalNavigation($folder=null){}
    public function contentStart(){}
    public function contentHeading($heading,$noescape=false){echo '<h1>'.htmlspecialchars($heading).'</h1>';}
    public function htmlEndPage(){echo '</body></html>';}
}
class RenderFulltext extends LetoDMS_View_Fulltext {use FTMinimalShell;}
class RenderFTResults extends LetoDMS_View_SearchFulltext {use FTMinimalShell;}
function ftRender($view){ob_start();try{$view->show();return ob_get_contents();}finally{ob_end_clean();}}
function ftInspect($html){
    $old=libxml_use_internal_errors(true);$dom=new DOMDocument();$dom->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($old);
    $xpath=new DOMXPath($dom);
    if($xpath->query('//script|//*[@onerror]')->length)throw new RuntimeException('Untrusted HTML became active');
    return $xpath;
}
$attack='"><script>alert(1)</script>';$user=new FTViewUser();
$dashboard=['counts'=>[['state'=>'pending','total'=>1]],'indexed'=>[['total'=>0]],'activity'=>[], 'jobs'=>[['document_id'=>7,'document_version'=>1,'state'=>'failed','attempts'=>3,'message'=>$attack]]];
$html=ftRender(new RenderFulltext(['user'=>$user,'dashboard'=>$dashboard,'fulltextenabled'=>true,'error'=>'','notice'=>$attack,'more'=>true,'cursor'=>200]));
$xpath=ftInspect($html);
foreach($xpath->query('//form[@method="post"]')as$form)if($xpath->query('.//input[@name="formtoken"]',$form)->length!==1)throw new RuntimeException('Missing CSRF field');
if($xpath->query('//form[@method="post"]')->length!==2)throw new RuntimeException('Expected batch and retry forms');
$html=ftRender(new RenderFTResults(['user'=>$user,'query'=>$attack,'folderid'=>1,'ownerid'=>0,'categoryids'=>[9],'error'=>'','result'=>['hits'=>[['id'=>7,'name'=>$attack,'version'=>1,'snippet'=>['page'=>2,'text'=>'<script>confidential</script> searchword']]],'words'=>['searchword'],'next'=>7]]));
$xpath=ftInspect($html);
if($xpath->query('//mark')->length!==1||$xpath->query('//input[@name="folderid" and @value="1"]')->length!==1)throw new RuntimeException('Expected highlights and preserved search scope');
echo "Full-text view escaping and form checks passed.\n";
