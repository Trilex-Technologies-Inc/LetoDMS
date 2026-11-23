<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$root=dirname(__DIR__,2);
require_once $root.'/inc/inc.ClassViewCommon.php';
set_include_path($root.'/views/bootstrap'.PATH_SEPARATOR.get_include_path());
require_once $root.'/views/bootstrap/class.Info.php';
function getMLText($key){return $key;}
function createHiddenFieldWithKey($key=''){return '<input type="hidden" name="formtoken" value="offline-test-token">';}
class UpdateViewVersion{function banner(){return 'LetoDMS, 5.2b';}}
class RenderUpdateInfo extends LetoDMS_View_Info {
    public function htmlStartPage($title='',$bodyClass=''){echo '<!doctype html><html><body>';}
    public function globalNavigation($folder=null){}
    public function contentStart(){}
    public function pageNavigation($title,$type=null,$extra=null){}
    public function contentEnd(){}
    public function htmlEndPage(){echo '</body></html>';}
}
$attack='"><script>alert(1)</script>';
$report=['state'=>'update_available','installed'=>'5.2b','checked_at'=>1700000000,'include_prereleases'=>true,'stale'=>false,'latest'=>['tag'=>'v5.3b','prerelease'=>true,'published_at'=>'2026-11-22T00:00:00Z','notes'=>$attack,'url'=>'https://github.com/Trilex-Technologies-Inc/LetoDMS/releases/tag/v5.3b']];
$view=new RenderUpdateInfo(['version'=>new UpdateViewVersion(),'updates'=>$report,'updateerror'=>$attack]);
ob_start();$view->show();$html=ob_get_clean();
$old=libxml_use_internal_errors(true);$dom=new DOMDocument();$dom->loadHTML($html);libxml_clear_errors();libxml_use_internal_errors($old);$xpath=new DOMXPath($dom);
if($xpath->query('//script|//*[@onerror]')->length)throw new RuntimeException('Untrusted release text became active HTML');
if($xpath->query('//form[@method="post"]/input[@name="formtoken"]')->length!==1)throw new RuntimeException('Missing update-check CSRF field');
if($xpath->query('//input[@name="include_prereleases" and @checked]')->length!==1)throw new RuntimeException('Missing beta preference');
if(strpos($html,'View update / download')===false || strpos($html,'5.2b')===false)throw new RuntimeException('Missing update state or installed version');
echo "Update information view checks passed.\n";
