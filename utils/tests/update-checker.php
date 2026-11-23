<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/inc/inc.ClassUpdateChecker.php';
function ucCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
class FakeUpdates extends LetoDMS_UpdateChecker {
    public $releases=[];public $requests=0;public $fail=false;public $clock=1700000000;
    protected function now(){return $this->clock;}
    protected function request(){ $this->requests++; if($this->fail)throw new RuntimeException('GitHub unavailable.');return $this->releases; }
}
$temp=sys_get_temp_dir().'/letodms-updates-'.bin2hex(random_bytes(8));mkdir($temp,0700);
try {
    ucCheck(LetoDMS_UpdateChecker::normalize('5.2b')==='5.2.0-beta.0','Legacy beta version');
    ucCheck(LetoDMS_UpdateChecker::normalize('v5.1b-php8.3')==='5.1.0-beta.0','Ignore runtime suffix');
    ucCheck(LetoDMS_UpdateChecker::normalize('v5.2.0-beta.1')==='5.2.0-beta.1','Semver beta');
    ucCheck(LetoDMS_UpdateChecker::normalize('v5.2.0+build.4')==='5.2.0','Build metadata');
    ucCheck(LetoDMS_UpdateChecker::normalize('unknown')===null,'Unknown version');
    ucCheck(LetoDMS_UpdateChecker::normalize('5.3<script>')===null,'Reject HTML version');
    $checker=new FakeUpdates('5.2b',$temp);
    ucCheck($checker->cached()===null && $checker->requests===0,'No network check during cache read');
    ucCheck($checker->report(null,true)['state']==='not_checked','Initial status');
    $checker->releases=[['tag_name'=>'5.1b-php8.3','name'=>'Older beta','prerelease'=>false,'draft'=>false,'published_at'=>'2026-09-05T01:16:57Z']];
    $data=$checker->check();ucCheck($checker->requests===1,'Explicit check fetches metadata');
    ucCheck($checker->report($data,true)['state']==='ahead','Do not recommend downgrade from 5.2b to 5.1b');
    ucCheck($checker->report($data,false)['state']==='no_releases','Legacy beta not reported as stable');
    $checker->check();ucCheck($checker->requests===1,'Cache limits requests');
    $checker->clock+=3601;
    $checker->releases=[['tag_name'=>'v5.2.0','name'=>'Stable','draft'=>false,'prerelease'=>false],['tag_name'=>'v5.3.0-rc.1','name'=>'Preview','prerelease'=>true],['tag_name'=>'v99.0','draft'=>true],['tag_name'=>'<script>alert(1)</script>']];
    $data=$checker->check();
    ucCheck($checker->report($data,false)['latest']['tag']==='v5.2.0','Stable channel selection');
    ucCheck($checker->report($data,true)['latest']['tag']==='v5.3.0-rc.1','Beta channel selection');
    ucCheck($checker->report($data,false)['state']==='update_available','Stable succeeds installed beta');
    ucCheck(strpos($checker->report($data,true)['latest']['url'],'https://github.com/Trilex-Technologies-Inc/LetoDMS/releases/tag/')===0,'Links use fixed project');
    ucCheck((new FakeUpdates('5.2.0',$temp))->report($data,false)['state']==='current','Same installed version');
    ucCheck((new FakeUpdates('custom-build',$temp))->report($data,true)['state']==='unknown_version','Unknown installed version not up-to-date');
    $checker->clock+=3601;$checker->fail=true;
    try {$checker->check();throw new Exception('Expected failed request');}catch(RuntimeException $e){}
    ucCheck($checker->report($checker->cached(),true)['stale'],'Failed request retains stale previous check');
    $checker->fail=false;$checker->releases=[];$checker->check();
    ucCheck($checker->report($checker->cached(),true)['state']==='no_releases','Empty repository releases');
    file_put_contents($temp.'/github-releases.json','{broken');ucCheck($checker->cached()===null,'Corrupt cache ignored');
    echo "Update-checker version, channel, cache, and failure checks passed.\n";
} finally {
    foreach(new DirectoryIterator($temp)as$file)if(!$file->isDot())unlink($file->getPathname());rmdir($temp);
}
