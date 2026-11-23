<?php
class LetoDMS_TextExtractor {
    const MAX_TEXT = 60000;
    private $options; private $maxText; private $maxFile;
    public function __construct($options = array(), $maxText = 60000, $maxFile = 20971520) { $this->options = $options; $this->maxText = $maxText; $this->maxFile = $maxFile; }
    public static function signature($options) {
        return hash('sha256', 'extractor-v1:'.(isset($options['ocr_enabled']) ? $options['ocr_enabled'] : '0').':'.(isset($options['ocr_languages']) ? $options['ocr_languages'] : 'eng'));
    }
    public function extract($path, $mime) {
        if (!is_file($path) || filesize($path) > $this->maxFile) throw new RuntimeException('The file is missing or exceeds the configured extraction file limit.');
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $pdf = false;
        if (in_array($mime, array('text/plain', 'text/markdown', 'text/csv'), true)) {
            $text = file_get_contents($path, false, null, 0, $this->maxText + 1);
        } elseif ($mime === 'application/pdf') {
            $pdf = true;
            $text = !empty($this->options['ocr_enabled']) ? $this->ocrPDF($path) : $this->command(array(getenv('LETODMS_TEXT_PDFTOTEXT') ?: getenv('LETODMS_AI_PDFTOTEXT') ?: 'pdftotext', '-enc', 'UTF-8', '-layout', $path, '-'));
        } elseif (in_array($mime, array('image/png', 'image/jpeg', 'image/tiff'), true)) {
            if (empty($this->options['ocr_enabled'])) throw new RuntimeException('Enable OCR in processing settings to read scanned images.');
            $size = @getimagesize($path);
            if (!$size || $size[0] * $size[1] > 40000000) throw new RuntimeException('The OCR image is invalid or exceeds 40 megapixels.');
            $text = $this->command(array(getenv('LETODMS_TEXT_TESSERACT') ?: getenv('LETODMS_AI_TESSERACT') ?: 'tesseract', $path, 'stdout', '-l', $this->languages()), 90);
        } elseif ($mime === 'application/msword') {
            $text = $this->command(array(getenv('LETODMS_TEXT_ANTIWORD') ?: getenv('LETODMS_AI_ANTIWORD') ?: 'antiword', '-m', 'UTF-8.txt', $path));
        } elseif ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $text = $this->docx($path);
        } else throw new RuntimeException('Supported formats: PDF, Word (.doc/.docx), UTF-8 text, Markdown, CSV, and PNG/JPEG/TIFF images with OCR enabled.');
        if (!is_string($text) || trim(str_replace("\f", '', $text)) === '') throw new RuntimeException(!empty($this->options['ocr_enabled']) ? 'OCR found no readable text. Check scan quality and the configured language packs.' : 'No text could be extracted. Enable OCR for scanned PDFs in processing settings.');
        if (strlen($text) > $this->maxText) throw new RuntimeException('Extracted text exceeds the configured extraction limit ('.$this->maxText.' bytes). No partial text was indexed or sent.');
        if (strpos($text, "\0") !== false || !preg_match('//u', $text)) throw new RuntimeException('Extracted text must be UTF-8.');
        $pages = array();
        foreach ($pdf ? explode("\f", $text) : array($text) as $index => $page) {
            if (trim($page) !== '') $pages[] = array('page' => $pdf ? $index + 1 : 0, 'text' => trim($page));
        }
        return $pages;
    }
    private function languages() {
        $languages = isset($this->options['ocr_languages']) ? $this->options['ocr_languages'] : 'eng';
        if (!preg_match('/^[a-zA-Z0-9_]+(?:\+[a-zA-Z0-9_]+)*$/', $languages) || strlen($languages) > 100) throw new RuntimeException('Invalid OCR language configuration.');
        return $languages;
    }
    private function ocrPDF($path) {
        $directory = sys_get_temp_dir().'/letodms-ocr-'.bin2hex(random_bytes(12));
        if (!mkdir($directory, 0700)) throw new RuntimeException('Could not create private OCR temporary storage.');
        $output = $directory.'/searchable.pdf';
        try {
            // Never modify the authoritative document. Skip text pages so mixed scans work.
            $this->command(array(getenv('LETODMS_TEXT_OCRMYPDF') ?: getenv('LETODMS_AI_OCRMYPDF') ?: 'ocrmypdf', '--skip-text', '--output-type', 'pdf', '--optimize', '0', '--jobs', '1', '-l', $this->languages(), $path, $output), 180);
            if (!is_file($output) || filesize($output) > 40 * 1024 * 1024) throw new RuntimeException('OCR output is missing or exceeds 40 MB.');
            return $this->command(array(getenv('LETODMS_TEXT_PDFTOTEXT') ?: getenv('LETODMS_AI_PDFTOTEXT') ?: 'pdftotext', '-enc', 'UTF-8', '-layout', $output, '-'));
        } finally { if (is_file($output)) unlink($output); rmdir($directory); }
    }
    private function docx($path) {
        if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) throw new RuntimeException('DOCX extraction requires PHP ZIP and DOM.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('The DOCX archive could not be opened.');
        try {
            $stat = $zip->statName('word/document.xml');
            if (!$stat || $stat['size'] > 8 * 1024 * 1024) throw new RuntimeException('The Word document XML is missing or too large.');
            $xml = $zip->getFromName('word/document.xml');
        } finally { $zip->close(); }
        if (!$xml || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) throw new RuntimeException('The Word document XML is invalid.');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            if (!$dom->loadXML($xml, LIBXML_NONET)) throw new RuntimeException('The Word document XML is invalid.');
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $text = '';
            foreach ($xpath->query('//w:body//w:p') as $paragraph) {
                foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $paragraph) as $node) {
                    $text .= $node->localName === 't' ? $node->textContent : ($node->localName === 'tab' ? "\t" : "\n");
                }
                $text .= "\n";
                if (strlen($text) > $this->maxText) break;
            }
            return $text;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
    protected function command($args, $timeout = 30) {
        if (!function_exists('proc_open')) throw new RuntimeException('PDF/DOC extraction requires process execution on the server.');
        $process = @proc_open($args, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start the document extractor. Install pdftotext (PDF) and antiword (DOC).');
        fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $text = ''; $start = microtime(true); $exit = -1;
        try {
            do {
                $text .= stream_get_contents($pipes[1], $this->maxText + 1);
                stream_get_contents($pipes[2], 8192);
                $status = proc_get_status($process);
                if (strlen($text) > $this->maxText || microtime(true) - $start > $timeout) {
                    proc_terminate($process);
                    throw new RuntimeException('Document extraction exceeded its text or '.$timeout.'-second time limit.');
                }
                if (!$status['running']) { $exit = $status['exitcode']; break; }
                usleep(10000);
            } while (true);
            $text .= stream_get_contents($pipes[1], $this->maxText + 1);
        } finally { fclose($pipes[1]); fclose($pipes[2]); proc_close($process); }
        if ($exit !== 0) throw new RuntimeException('Document extraction failed. Check the configured converter/OCR tools, installed language packs, and whether the file is encrypted or damaged.');
        return $text;
    }
    public static function chunks($pages) {
        if (!function_exists('mb_strcut')) throw new RuntimeException('Text indexing and Q&A require the PHP mbstring extension.');
        $chunks = array();
        foreach ($pages as $page) {
            $text = $page['text'];
            while ($text !== '') {
                $part = mb_strcut($text, 0, 2400, 'UTF-8');
                $chunks[] = array('page' => $page['page'], 'text' => $part);
                $text = substr($text, strlen($part));
            }
        }
        if (count($chunks) > 100) throw new RuntimeException('The document has too many text sections (maximum 100).');
        return $chunks;
    }
}
