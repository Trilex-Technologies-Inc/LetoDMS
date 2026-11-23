<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../inc/Extractor.php';
function extractCheck($condition, $message) { if (!$condition) throw new Exception($message); }
function extractReject($callback) { try { $callback(); } catch (RuntimeException $e) { return; } throw new Exception('Expected extraction rejection'); }
class ExtractorFixture extends LetoDMS_AIExtractor {
    public $args;
    protected function command($args, $timeout = 30) { $this->args = $args; return "First page\f\fThird page\f"; }
    public function process($args) { return parent::command($args); }
}
$temp = sys_get_temp_dir().'/letodms-extraction-'.uniqid().'/'; mkdir($temp);
try {
    $extractor = new LetoDMS_AIExtractor();
    $docx = $temp.'sample with spaces.docx'; $zip = new ZipArchive(); $zip->open($docx, ZipArchive::CREATE);
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Résumé &amp; contract</w:t><w:tab/><w:t>2026</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Table cell</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>'); $zip->close();
    $pages = $extractor->extract($docx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    extractCheck(count($pages) === 1 && $pages[0]['page'] === 0 && strpos($pages[0]['text'], "Résumé & contract\t2026") !== false && strpos($pages[0]['text'], 'Table cell') !== false, 'DOCX text, Unicode, tabs, tables');
    $zip = new ZipArchive(); $zip->open($docx, ZipArchive::OVERWRITE); $zip->addFromString('word/document.xml', '<!DOCTYPE test [<!ENTITY entity SYSTEM "file:///etc/passwd">]><test>&entity;</test>'); $zip->close();
    extractReject(function () use ($extractor, $docx) { $extractor->extract($docx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'); });
    $text = $temp.'text.txt'; file_put_contents($text, str_repeat('é', 20000));
    $pages = $extractor->extract($text, 'text/plain; charset=UTF-8'); $chunks = LetoDMS_AIExtractor::chunks($pages);
    extractCheck(implode('', array_column($chunks, 'text')) === $pages[0]['text'], 'Chunking preserves multibyte text');
    foreach ($chunks as $chunk) extractCheck(strlen($chunk['text']) <= 2400 && preg_match('//u', $chunk['text']), 'UTF-8 chunk boundary');
    file_put_contents($text, str_repeat('x', 60001)); extractReject(function () use ($extractor, $text) { $extractor->extract($text, 'text/plain'); });
    file_put_contents($text, "\0binary"); extractReject(function () use ($extractor, $text) { $extractor->extract($text, 'text/plain'); });
    file_put_contents($text, 'fake file'); $probe = new ExtractorFixture();
    $pages = $probe->extract($text, 'application/pdf'); extractCheck($pages[0]['page'] === 1 && $pages[1]['page'] === 3, 'PDF blank pages preserve page numbering');
    extractCheck(in_array($text, $probe->args, true) && end($probe->args) === '-', 'PDF source passed as one process argument');
    $probe->extract($text, 'application/msword'); extractCheck(in_array('UTF-8.txt', $probe->args, true), 'Legacy Word UTF-8 output');
    $script = $temp.'extractor command.php'; file_put_contents($script, '<?php echo "Process text";');
    extractCheck($probe->process(array(PHP_BINARY, $script)) === 'Process text', 'Real process output capture with spaces in path');
    file_put_contents($script, '<?php exit(2);'); extractReject(function () use ($probe, $script) { $probe->process(array(PHP_BINARY, $script)); });
    file_put_contents($script, '<?php echo str_repeat("x", 60001);'); extractReject(function () use ($probe, $script) { $probe->process(array(PHP_BINARY, $script)); });
    echo "AI extraction checks passed (DOCX fixtures and bounded processes; PDF/DOC converter output simulated).\n";
} finally { foreach (glob($temp.'*') as $file) unlink($file); rmdir($temp); }
