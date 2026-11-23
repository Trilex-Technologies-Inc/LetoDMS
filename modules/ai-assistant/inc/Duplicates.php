<?php
require_once __DIR__.'/Library.php';
class LetoDMS_AIDuplicates {
    private $store; private $assistant; private $dms;
    public function __construct($store, $assistant, $dms) { $this->store = $store; $this->assistant = $assistant; $this->dms = $dms; }
    public function record($user, $id, $version) {
        $content = $this->assistant->content($this->dms, $user, $id, $version);
        $path = $this->dms->contentDir.$content->getPath();
        if (!is_file($path) || filesize($path) > 20 * 1024 * 1024) throw new RuntimeException('The document is unavailable or exceeds the fingerprint limit.');
        $fileHash = hash_file('sha256', $path);
        $pages = $this->assistant->extract($this->dms, $user, $id, $version);
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(implode("\n", array_column($pages, 'text')), 'UTF-8'));
        $textHash = hash('sha256', trim($normalized));
        $extractionSignature = LetoDMS_AIExtractor::signature($this->store->options());
        $this->store->transaction(function () use ($id, $version, $fileHash, $textHash, $extractionSignature) {
            $this->store->execute('DELETE FROM tblModuleAIFingerprints WHERE document_id = '.(int)$id.' AND document_version = '.(int)$version);
            $this->store->execute('INSERT INTO tblModuleAIFingerprints (document_id, document_version, file_hash, text_hash, extraction_signature) VALUES ('.(int)$id.', '.(int)$version.', '.$this->store->quote($fileHash).', '.$this->store->quote($textHash).', '.$this->store->quote($extractionSignature).')');
        });
        return array('file_hash' => $fileHash, 'text_hash' => $textHash);
    }
    private function centroid($id, $version, $signature) {
        $rows = $this->store->rows('SELECT vector_data FROM tblModuleAIChunks WHERE document_id = '.(int)$id.' AND document_version = '.(int)$version.' AND embedding_signature = '.$this->store->quote($signature));
        $sum = array(); $count = 0;
        foreach ($rows as $row) {
            $vector = json_decode($row['vector_data'], true);
            if (!is_array($vector) || !$vector) continue;
            if (!$sum) $sum = array_fill(0, count($vector), 0);
            if (count($sum) !== count($vector)) return array();
            foreach ($vector as $i => $number) $sum[$i] += (float)$number;
            $count++;
        }
        if ($count) foreach ($sum as &$number) $number /= $count;
        return $sum;
    }
    public function find($user, $id, $version) {
        if (!$user->hasPermission('module.ai-assistant.access')) throw new RuntimeException('AI module access is required.');
        $this->assistant->content($this->dms, $user, $id, $version);
        $mine = $this->store->rows('SELECT file_hash, text_hash FROM tblModuleAIFingerprints WHERE document_id = '.(int)$id.' AND document_version = '.(int)$version);
        if (!$mine) $mine = array($this->record($user, $id, $version));
        $options = $this->store->options(); $signature = LetoDMS_AILibrary::signature($options);
        $mineVector = $options['embedding_provider'] !== '' ? $this->centroid($id, $version, $signature) : array();
        $candidates = $this->store->rows('SELECT document AS document_id, version AS document_version FROM tblDocumentContent WHERE document <> '.(int)$id.' ORDER BY document, version');
        $matches = array();
        foreach ($candidates as $candidate) {
            $otherId = (int)$candidate['document_id']; $otherVersion = (int)$candidate['document_version'];
            try {
                $content = $this->assistant->content($this->dms, $user, $otherId, $otherVersion);
                $document = $this->dms->getDocument($otherId); $latest = $document->getLatestContent();
                if (!$latest || (int)$latest->getVersion() !== $otherVersion) continue;
            } catch (RuntimeException $e) { continue; }
            $fingerprints = $this->store->rows('SELECT file_hash, text_hash FROM tblModuleAIFingerprints WHERE document_id = '.$otherId.' AND document_version = '.$otherVersion);
            if ($fingerprints) $other = $fingerprints[0];
            else {
                $path = $this->dms->contentDir.$content->getPath();
                if (!is_file($path) || filesize($path) > 20 * 1024 * 1024) continue;
                $other = array('file_hash' => hash_file('sha256', $path), 'text_hash' => '');
            }
            $kind = ''; $score = 1;
            if (hash_equals($mine[0]['file_hash'], $other['file_hash'])) $kind = 'Identical file';
            elseif (hash_equals($mine[0]['text_hash'], $other['text_hash'])) $kind = 'Same extracted text';
            elseif ($mineVector) {
                $score = LetoDMS_AILibrary::cosine($mineVector, $this->centroid($otherId, $otherVersion, $signature));
                if ($score >= 0.95) $kind = 'Similar content (review required)';
            }
            if ($kind !== '') $matches[] = array('document_id' => $otherId, 'version' => $otherVersion, 'name' => $document->getName(), 'kind' => $kind, 'score' => $score);
        }
        usort($matches, function ($a, $b) { return $b['score'] <=> $a['score']; });
        return array_slice($matches, 0, 20);
    }
}
