<?php
/** Public GitHub release metadata only; never downloads or executes application updates. */
class LetoDMS_UpdateChecker
{
    const REPOSITORY = 'Trilex-Technologies-Inc/LetoDMS';
    const RELEASES_URL = 'https://github.com/Trilex-Technologies-Inc/LetoDMS/releases';
    const API_URL = 'https://api.github.com/repos/Trilex-Technologies-Inc/LetoDMS/releases?per_page=100';
    const CACHE_SECONDS = 3600;
    private $installed;
    private $directory;

    public function __construct($installed, $directory)
    {
        $this->installed = $installed;
        $this->directory = rtrim($directory, '/\\');
    }
    protected function now() { return time(); }

    /** Compare app versions without treating legacy PHP-runtime suffixes as app revisions. */
    public static function normalize($version)
    {
        if (!is_string($version) || strlen($version)>128) return null;
        $version = preg_replace('/-php\d+(?:\.\d+)*$/i', '', trim($version));
        if (!preg_match('/^v?(\d+)\.(\d+)(?:\.(\d+))?(?:[-.]?(alpha|beta|a|b|rc)(?:[.-]?(\d+))?)?(?:\+[0-9A-Za-z.-]+)?$/iD',$version,$parts)) return null;
        $normalized = (int)$parts[1].'.'.(int)$parts[2].'.'.(isset($parts[3]) && $parts[3]!=='' ? (int)$parts[3] : 0);
        if (!empty($parts[4])) {
            $suffix = strtolower($parts[4]);
            if ($suffix==='a') $suffix='alpha';
            if ($suffix==='b') $suffix='beta';
            $normalized .= '-'.$suffix.'.'.(!empty($parts[5])?(int)$parts[5]:0);
        }
        return $normalized;
    }
    public static function isPrerelease($version)
    {
        $normalized=self::normalize($version);
        return $normalized!==null && strpos($normalized,'-')!==false;
    }
    private function path() { return $this->directory.'/github-releases.json'; }
    private static function shortText($text, $limit)
    {
        if (!is_string($text)) return '';
        $text=substr($text,0,$limit);
        // Avoid requiring mbstring just to check for application updates.
        while ($text!=='' && !preg_match('//u',$text)) $text=substr($text,0,-1);
        return $text;
    }
    public function cached()
    {
        $path=$this->path();
        if (!is_file($path) || is_link($path) || filesize($path)>1048576) return null;
        $data=json_decode((string)@file_get_contents($path),true);
        if (!is_array($data) || ($data['repository']??'')!==self::REPOSITORY || !isset($data['checked_at'],$data['releases']) || !is_array($data['releases']) || !is_int($data['checked_at']) || $data['checked_at']>$this->now()+60) return null;
        return $data;
    }
    public function check()
    {
        $cached=$this->cached();
        if ($cached && $this->now()-$cached['checked_at']<self::CACHE_SECONDS) return $cached;
        if (!is_dir($this->directory) && !@mkdir($this->directory,0700,true) && !is_dir($this->directory)) throw new RuntimeException('The update cache is not writable. Check the configured cache directory permissions.');
        $lock=@fopen($this->directory.'/check.lock','c');
        if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('Another update check is in progress. Please try again shortly.');
        }
        try {
            // Another request may have completed between the first read and obtaining the lock.
            $cached=$this->cached();
            if ($cached && $this->now()-$cached['checked_at']<self::CACHE_SECONDS) return $cached;
            $raw=$this->request();
            $releases=[];
            foreach ($raw as $release) {
                if (!is_array($release) || !empty($release['draft'])) continue;
                $tag=$release['tag_name']??null;
                if (self::normalize($tag)===null) continue;
                $releases[]=[
                    'tag'=>$tag,
                    'name'=>is_string($release['name']??null)?self::shortText($release['name'],200):$tag,
                    'prerelease'=>!empty($release['prerelease']) || self::isPrerelease($tag),
                    'published_at'=>is_string($release['published_at']??null)?substr($release['published_at'],0,40):'',
                    'notes'=>self::shortText($release['body']??'',2000),
                ];
            }
            $data=['repository'=>self::REPOSITORY,'checked_at'=>$this->now(),'releases'=>$releases,'published_count'=>count($raw)];
            $temp=$this->directory.'/'.bin2hex(random_bytes(12)).'.tmp';
            try {
                if (@file_put_contents($temp,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX)===false) throw new RuntimeException('Could not save the update-check cache. Check cache directory permissions.');
                @chmod($temp,0600);
                if (!@rename($temp,$this->path())) throw new RuntimeException('Could not publish the update-check cache. Check cache directory permissions.');
            } finally { if (is_file($temp)) @unlink($temp); }
            return $data;
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
    protected function request()
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Checking GitHub requires the PHP cURL extension.');
        $body='';$oversized=false;
        $curl=curl_init(self::API_URL);
        curl_setopt_array($curl,[
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'LetoDMS-Update-Checker',
            CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json','X-GitHub-Api-Version: 2022-11-28'],
            CURLOPT_WRITEFUNCTION=>function($handle,$chunk)use(&$body,&$oversized){
                if (strlen($body)+strlen($chunk)>1048576) {$oversized=true;return 0;}
                $body.=$chunk;return strlen($chunk);
            },
        ]);
        if (defined('CURLOPT_PROTOCOLS')) curl_setopt($curl,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS);
        try {
            $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
            if ($oversized) throw new RuntimeException('GitHub returned more release data than the checker can safely process. View releases on GitHub instead.');
            if ($ok===false) throw new RuntimeException('Could not contact GitHub securely. Check server internet access and CA certificates, then try again.');
            if ($status===403 || $status===429) throw new RuntimeException('GitHub is rate-limiting or restricting this request. Try again later.');
            if ($status!==200) throw new RuntimeException('GitHub releases are temporarily unavailable (HTTP '.$status.'). Try again later.');
            $data=json_decode($body,true);
            if (!is_array($data) || ($data!==[] && array_keys($data)!==range(0,count($data)-1))) throw new RuntimeException('GitHub returned an unexpected release response.');
            return $data;
        } finally { curl_close($curl); }
    }
    public function report($data, $includePrereleases)
    {
        $report=['state'=>'not_checked','installed'=>$this->installed,'latest'=>null,'checked_at'=>null,'include_prereleases'=>(bool)$includePrereleases,'stale'=>false];
        if (!$data) return $report;
        $report['checked_at']=(int)$data['checked_at'];
        $report['stale']=$this->now()-$report['checked_at']>=self::CACHE_SECONDS;
        foreach ($data['releases'] as $release) {
            if (!is_array($release) || !isset($release['tag']) || self::normalize($release['tag'])===null || (!$includePrereleases && (!empty($release['prerelease']) || self::isPrerelease($release['tag'])))) continue;
            if (!$report['latest'] || version_compare(self::normalize($release['tag']),self::normalize($report['latest']['tag']),'>')) $report['latest']=$release;
        }
        if (!$report['latest']) {$report['state']='no_releases';return $report;}
        // Construct a link on the fixed project host rather than trusting external URL fields.
        $report['latest']['url']=self::RELEASES_URL.'/tag/'.rawurlencode($report['latest']['tag']);
        $installed=self::normalize($this->installed);
        if ($installed===null) {$report['state']='unknown_version';return $report;}
        $comparison=version_compare($installed,self::normalize($report['latest']['tag']));
        $report['state']=$comparison<0?'update_available':($comparison>0?'ahead':'current');
        return $report;
    }
}
