<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../../inc/inc.ClassViewCommon.php';
require_once __DIR__.'/../views/Settings.php'; require_once __DIR__.'/../views/Tools.php'; require_once __DIR__.'/../views/Assistant.php';
function createHiddenFieldWithKey($name) { return '<input type="hidden" name="formtoken" value="offline-token">'; }
function getMLText($name) { return $name; }
class UI { public static function exitError($title, $message) { throw new RuntimeException($message); } }
class ViewUser { public $admin = true; public function isAdmin() { return $this->admin; } }
trait MinimalViewShell {
    public function htmlStartPage($title = '', $bodyClass = '') { echo '<!doctype html><html><body>'; }
    public function htmlEndPage() { echo '</body></html>'; }
    public function globalNavigation($folder = null) {}
    public function contentStart() { echo '<main>'; }
    public function contentEnd() { echo '</main>'; }
    public function contentHeading($heading, $noescape = false) {}
}
class RenderSettings extends LetoDMS_Module_View_AISettings { use MinimalViewShell; }
class RenderTools extends LetoDMS_Module_View_AITools { use MinimalViewShell; }
class RenderAssistant extends LetoDMS_Module_View_AIAssistant { use MinimalViewShell; }
function renderAIView($view) {
    ob_start();
    try { $view->show(); return ob_get_contents(); } finally { ob_end_clean(); }
}
function inspectAIHTML($html) {
    $previous = libxml_use_internal_errors(true); $dom = new DOMDocument(); $dom->loadHTML($html); libxml_clear_errors(); libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    if ($xpath->query('//script[not(@src="../modules/ai-assistant/assets/settings.js")] | //*[@onerror]')->length) throw new Exception('Untrusted content became active HTML');
    foreach ($xpath->query('//form[@method="post"]') as $form) if ($xpath->query('.//input[@name="formtoken"]', $form)->length !== 1) throw new Exception('Missing CSRF field');
    return $xpath;
}
$attack = '"><script>alert(1)</script>';
$user = new ViewUser();
$settings = array('user' => $user, 'saved' => false, 'choices' => array('hosted' => $attack),
    'providers' => array(array('provider_id' => 'hosted', 'label' => $attack, 'protocol' => 'openai', 'endpoint' => 'https://example.com/chat', 'model' => $attack, 'enabled' => 1, 'has_key' => true)),
    'options' => array('automatic' => '0', 'analysis_provider' => '', 'embedding_provider' => '', 'embedding_endpoint' => '', 'embedding_model' => ''));
$html = renderAIView(new RenderSettings($settings)); $xpath = inspectAIHTML($html);
foreach ($xpath->query('//input[@type="password"]') as $input) if ($input->hasAttribute('value')) throw new Exception('API key field must not be populated');
$settings['worker_status'] = array('last_seen' => 1, 'counts' => array(array('state' => 'failed', 'job_count' => 1)), 'recent' => array(array('job_id' => str_repeat('a', 64), 'document_id' => 7, 'document_version' => 2, 'state' => 'failed', 'attempts' => 3, 'message' => $attack)));
$settings['usage_report'] = array(array('provider' => $attack, 'model' => $attack, 'operation' => 'generation', 'state' => 'success', 'calls' => 1, 'input_tokens' => 20, 'output_tokens' => 10, 'estimated_cost' => 0, 'priced_calls' => 0));
$xpath = inspectAIHTML(renderAIView(new RenderSettings($settings)));
if ($xpath->query('//button[@value="retry_job"]')->length !== 1 || $xpath->query('//button[@value="cancel_job"]')->length !== 1) throw new Exception('Job retry/cancel controls missing');
$user->admin = false;
try { renderAIView(new RenderSettings($settings)); throw new Exception('Non-admin settings rendering allowed'); } catch (RuntimeException $e) {}
$user->admin = true;
$source = array('document_id' => 7, 'version' => 2, 'page' => 3, 'name' => $attack, 'text' => $attack, 'score' => 1);
$html = renderAIView(new RenderTools(array('user' => $user, 'providers' => array('hosted' => $attack), 'result' => array('answer' => $attack.' [1]', 'sources' => array(1 => $source)), 'matches' => array(), 'message' => '')));
$xpath = inspectAIHTML($html);
if ($xpath->query('//a[@href="#ai-source-1"]')->length !== 1 || $xpath->query('//a[contains(@href,"passage=1") and contains(@href,"page=3")]')->length !== 1) throw new Exception('Citation/source page links missing');
$html = renderAIView(new RenderAssistant(array('user' => $user, 'providers' => array('hosted' => $attack), 'documentid' => 7, 'version' => 2, 'error' => '', 'message' => '', 'saved' => false, 'jobs' => array(array('state' => 'pending', 'provider' => 'hosted', 'attempts' => 0, 'message' => $attack)), 'results' => array())));
inspectAIHTML($html);
if (strpos($html, 'name="consent"') !== false) throw new Exception('Repeated consent checkbox must not appear on analysis forms');
$html = renderAIView(new RenderTools(array('user' => $user, 'providers' => array(), 'quick_search' => true, 'searchquery' => $attack, 'result' => null, 'matches' => array($source), 'message' => '')));
$xpath = inspectAIHTML($html);
if ($xpath->query('//form')->length !== 0 || strpos($html, 'Document Q&amp;A') !== false || strpos($html, 'Usage charges') !== false) throw new Exception('Quick search must show focused results without AI tool forms or repeated notices');
if ($xpath->query('//a[contains(@href,"op.Search.php?")]')->length !== 1) throw new Exception('Standard search fallback missing');
echo "AI view rendering checks passed.\n";
