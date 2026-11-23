<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../inc/Extractor.php';
function ocrCheck($value, $message) { if (!$value) throw new Exception($message); }
function ocrReject($callback) { try { $callback(); } catch (RuntimeException $e) { return; } throw new Exception('Expected OCR rejection'); }
class OCRFixture extends LetoDMS_AIExtractor {
    public $calls = array(); public $temporary; public $fail = false; public $empty = false;
    protected function command($args, $timeout = 30) {
        $this->calls[] = array('args' => $args, 'timeout' => $timeout);
        if (in_array('--skip-text', $args, true)) {
            $this->temporary = end($args);
            if ($this->fail) throw new RuntimeException('Simulated OCR failure');
            file_put_contents($this->temporary, 'private OCR PDF');
            return '';
        }
        return $this->empty ? "\f\f" : "Scanned contract page one\fScanned page two\f";
    }
}
$temp = sys_get_temp_dir().'/letodms-ocr-test-'.uniqid().'/'; mkdir($temp);
try {
    $pdf = $temp.'source.pdf'; file_put_contents($pdf, 'original PDF bytes'); $hash = hash_file('sha256', $pdf);
    $extractor = new OCRFixture(array('ocr_enabled' => '1', 'ocr_languages' => 'eng+fra'));
    $pages = $extractor->extract($pdf, 'application/pdf');
    ocrCheck(count($pages) === 2 && $pages[1]['page'] === 2 && $extractor->calls[0]['timeout'] === 180, 'OCR PDF preserves extracted pages and timeout');
    ocrCheck(in_array('eng+fra', $extractor->calls[0]['args'], true), 'OCR languages passed as one argument');
    ocrCheck(hash_file('sha256', $pdf) === $hash && !file_exists(dirname($extractor->temporary)), 'Original untouched and private OCR copy removed');
    $failed = new OCRFixture(array('ocr_enabled' => '1')); $failed->fail = true;
    ocrReject(function () use ($failed, $pdf) { $failed->extract($pdf, 'application/pdf'); });
    ocrCheck(!file_exists(dirname($failed->temporary)), 'OCR failure cleans private temporary directory');
    $empty = new OCRFixture(); $empty->empty = true;
    ocrReject(function () use ($empty, $pdf) { $empty->extract($pdf, 'application/pdf'); });
    $invalid = new OCRFixture(array('ocr_enabled' => '1', 'ocr_languages' => 'eng; injected'));
    ocrReject(function () use ($invalid, $pdf) { $invalid->extract($pdf, 'application/pdf'); });
    ocrCheck(!$invalid->calls, 'Invalid language must not start a process');
    $image = $temp.'pixel.png';
    file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aSTkAAAAASUVORK5CYII='));
    $imageOCR = new OCRFixture(array('ocr_enabled' => '1'));
    $imageOCR->extract($image, 'image/png');
    ocrCheck(in_array('stdout', $imageOCR->calls[0]['args'], true) && $imageOCR->calls[0]['timeout'] === 90, 'Image OCR uses bounded Tesseract stdout extraction');
    $disabled = new OCRFixture(); ocrReject(function () use ($disabled, $image) { $disabled->extract($image, 'image/png'); });
    echo "OCR orchestration checks passed (converter execution simulated).\n";
} finally { foreach (glob($temp.'*') as $file) unlink($file); rmdir($temp); }
