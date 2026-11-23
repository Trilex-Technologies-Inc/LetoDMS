<?php
namespace LetoDMS\WebDAV;

use Sabre\DAV\Exception;

class PayloadTooLarge extends \Sabre\DAV\Exception
{
    public function getHTTPCode() { return 413; }
}

/** Bridges DAV requests to the authenticated DMS APIs. */
class Context
{
    public const TRANSACTIONAL_TABLES = [
        'tblDocuments', 'tblFolders', 'tblDocumentContent', 'tblDocumentStatus', 'tblDocumentStatusLog',
        'tblDocumentReviewers', 'tblDocumentReviewLog', 'tblDocumentApprovers', 'tblDocumentApproveLog',
        'tblWorkflowDocumentContent', 'tblWorkflowLog', 'tblDocumentContentAttributes', 'tblDocumentAttributes',
        'tblFolderAttributes', 'tblDocumentCategory', 'tblDocumentFiles', 'tblDocumentLinks', 'tblDocumentLocks',
        'tblNotify', 'tblACLs', 'tblWebDAVMutex', 'tblWebDAVLocks',
    ];
    public $dms;
    public $db;
    public $settings;
    public $user;
    public $server;
    public $locks;
    public $copySource;
    private $writing = false;
    private $queued = [];
    private $checkedEngine = false;
    private $rollbackActions = [];

    public function __construct($dms, $settings)
    {
        $this->dms = $dms;
        $this->db = $dms->getDB();
        $this->settings = $settings;
    }

    public function execute($sql)
    {
        if (!$this->db->getResult($sql)) {
            throw new Exception\ServiceUnavailable('The WebDAV database operation failed.');
        }
    }

    public function rows($sql)
    {
        $rows = $this->db->getResultArray($sql);
        if (!is_array($rows)) {
            throw new Exception\ServiceUnavailable('The WebDAV database query failed.');
        }
        return $rows;
    }

    public function initialize()
    {
        $engine = in_array($this->settings->_dbDriver, ['mysql', 'mysqli']) ? ' ENGINE=InnoDB' : '';
        $this->execute('CREATE TABLE IF NOT EXISTS tblWebDAVMutex (id INTEGER PRIMARY KEY, serial INTEGER NOT NULL)'.$engine);
        // An insert racing another first request is harmless; verify that the row exists.
        if (!$this->rows('SELECT id FROM tblWebDAVMutex WHERE id=1')) {
            $this->db->getResult('INSERT INTO tblWebDAVMutex (id, serial) VALUES (1, 0)');
            if (!$this->rows('SELECT id FROM tblWebDAVMutex WHERE id=1')) {
                throw new Exception\ServiceUnavailable('Cannot initialize WebDAV.');
            }
        }
        $this->execute('CREATE TABLE IF NOT EXISTS tblWebDAVLocks (token VARCHAR(80) PRIMARY KEY, uri TEXT NOT NULL, owner TEXT NOT NULL, user_id INTEGER NOT NULL, expires BIGINT NOT NULL, depth INTEGER NOT NULL, document_id INTEGER NOT NULL, base_version INTEGER NOT NULL)'.$engine);
        if (!$this->rows("SELECT id FROM tblPermissions WHERE name='webdav.access'")) {
            $this->db->getResult("INSERT INTO tblPermissions (name,description) VALUES ('webdav.access','Access documents through WebDAV')");
            if (!$this->rows("SELECT id FROM tblPermissions WHERE name='webdav.access'")) {
                throw new Exception\ServiceUnavailable('Cannot register the WebDAV permission.');
            }
        }
    }

    /** Serialize DAV writes, including version allocation and lock creation. */
    public function transaction(callable $callback)
    {
        if ($this->writing) {
            return $callback();
        }
        $this->checkStorageEngine();
        $this->db->startTransaction();
        $this->writing = true;
        try {
            $this->execute('UPDATE tblWebDAVMutex SET serial=serial+1 WHERE id=1');
            $freshUser = $this->user ? $this->dms->getUser($this->user->getID()) : null;
            if (!$freshUser || $freshUser->isDisabled() || $freshUser->isGuest() || !$freshUser->hasPermission('webdav.access')) {
                throw new Exception\Forbidden('WebDAV access has been revoked.');
            }
            $this->user = $freshUser;
            $this->dms->setUser($freshUser);
            $result = $callback();
            $this->db->commitTransaction();
            $this->writing = false;
            $this->rollbackActions = [];
            $queued = $this->queued;
            $this->queued = [];
            foreach ($queued as [$doc, $content]) $this->queue($doc, $content);
            return $result;
        } catch (\Throwable $e) {
            if ($this->writing) $this->db->rollbackTransaction();
            foreach (array_reverse($this->rollbackActions) as $cleanup) $cleanup();
            $this->rollbackActions = [];
            $this->queued = [];
            throw $e;
        } finally {
            $this->writing = false;
        }
    }

    public function cleanupOnRollback($content)
    {
        $path = $this->dms->contentDir.$content->getPath();
        $this->rollbackActions[] = function () use ($path) {
            @unlink($path);
            @rmdir(dirname($path)); // Only an empty content directory can be removed.
        };
    }

    private function checkStorageEngine()
    {
        if ($this->checkedEngine) return;
        if ($this->nonTransactionalTables()) {
            throw new Exception\ServiceUnavailable('WebDAV writes require InnoDB document and workflow tables. Run webdav/setup.php --check; use read-only mode until the database has been migrated.');
        }
        $this->checkedEngine = true;
    }

    public function nonTransactionalTables()
    {
        $result = [];
        if (in_array($this->settings->_dbDriver, ['mysql', 'mysqli'])) {
            $names = implode(',', array_map([$this->db, 'qstr'], self::TRANSACTIONAL_TABLES));
            $tables = $this->rows('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$names.')');
            foreach ($tables as $table) {
                if (strcasecmp($table['ENGINE'], 'InnoDB') !== 0) $result[] = $table['TABLE_NAME'];
            }
        }
        return $result;
    }

    public function permission($permission)
    {
        if (!$this->user || !$this->user->hasPermission($permission)) throw new Exception\Forbidden('Permission denied.');
    }

    public function checkWritePreconditions($doc, $content)
    {
        if (!$this->server) return;
        $request = $this->server->httpRequest;
        $ifMatch = $request->getHeader('If-Match');
        if ($ifMatch && trim($ifMatch) !== '*') {
            $etag = '"d'.$doc->getID().'-v'.$content->getVersion().'-'.$content->getChecksum().'"';
            $tags = array_map('trim', explode(',', $ifMatch));
            if (!in_array($etag, $tags, true)) throw new Exception\PreconditionFailed('The document has changed. Reopen it before saving.');
        }
        $since = $request->getHeader('If-Unmodified-Since');
        if (!$ifMatch && $since && ($time = strtotime($since)) !== false && $content->getDate() > $time) {
            throw new Exception\PreconditionFailed('The document has changed.');
        }
    }

    public function access($object, $mode = M_READ)
    {
        if (!$this->user || !$object || $object->getAccessMode($this->user) < $mode) {
            throw new Exception\Forbidden('Permission denied.');
        }
        if ($mode > M_READ && !empty($this->settings->_webDAVReadOnly)) {
            throw new Exception\Forbidden('WebDAV is read-only.');
        }
    }

    public function document($id, $mode = M_READ)
    {
        $doc = $this->dms->getDocument($id);
        if (!$doc) {
            throw new Exception\NotFound('Document unavailable.');
        }
        $this->access($doc, $mode);
        $content = $doc->getLatestContent();
        if (!$content) {
            throw new Exception\NotFound('Document version unavailable.');
        }
        $workflows = $this->rows('SELECT workflow FROM tblWorkflowDocumentContent WHERE document='.(int)$id.' AND version='.(int)$content->getVersion());
        if ($workflows && !$content->getWorkflow()) throw new Exception\ServiceUnavailable('Document workflow unavailable.');
        // Do not expose an older released version when the current one is restricted.
        $this->access($content);
        if ($mode > M_READ && ($doc->isLocked() || $this->rows('SELECT userID FROM tblDocumentLocks WHERE document='.(int)$id))) {
            throw new Exception\Locked();
        }
        return [$doc, $content];
    }

    public function folder($id, $mode = M_READ)
    {
        $folder = $this->dms->getFolder($id);
        if (!$folder) {
            throw new Exception\NotFound('Folder unavailable.');
        }
        $this->access($folder, $mode);
        return $folder;
    }

    public static function validateName($name)
    {
        if ($name === '' || $name === '.' || $name === '..' || strlen($name) > 255 ||
            !preg_match('//u', $name) || preg_match('~[\\\\/\x00-\x1f\x7f]~', $name)) {
            throw new Exception\BadRequest('Invalid resource name.');
        }
    }

    public static function displayName($name)
    {
        $name = preg_replace('~[\\\\/\x00-\x1f\x7f]~u', '_', $name);
        return ($name === '' || $name === '.' || $name === '..') ? '_' : $name;
    }

    /** Read policies explicitly: a failed query must never silently release a document. */
    public function policy($doc = null, $content = null)
    {
        if (!$doc && $this->copySource) [$doc, $content] = $this->copySource->current();
        $result = [];
        foreach (['reviewer' => 'Reviewers', 'approver' => 'Approvers'] as $prefix => $suffix) {
            $method = 'getMandatory'.$suffix;
            $rows = $this->user->$method();
            if (!is_array($rows)) {
                throw new Exception\ServiceUnavailable('Cannot read the document review policy.');
            }
            $ids = ['i' => [], 'g' => []];
            foreach ($rows as $row) {
                if (!empty($row[$prefix.'UserID'])) $ids['i'][] = (int)$row[$prefix.'UserID'];
                if (!empty($row[$prefix.'GroupID'])) $ids['g'][] = (int)$row[$prefix.'GroupID'];
            }
            if ($doc) {
                foreach ($this->rows('SELECT type, required FROM tblDocument'.$suffix.' WHERE documentID='.(int)$doc->getID().' AND version='.(int)$content->getVersion()) as $row) {
                    $ids[(int)$row['type'] === 0 ? 'i' : 'g'][] = (int)$row['required'];
                }
            }
            $ids = array_map('array_unique', $ids);
            foreach ($ids as $type => $assigned) {
                foreach ($assigned as $id) {
                    $assignee = $type === 'i' ? $this->dms->getUser($id) : $this->dms->getGroup($id);
                    if (!$assignee) throw new Exception\Conflict('A required reviewer or approver is unavailable. Use the web interface.');
                }
            }
            $result[] = $ids;
        }
        $workflow = $this->user->getMandatoryWorkflow();
        if ($workflow === false) {
            throw new Exception\ServiceUnavailable('Cannot read the mandatory workflow.');
        }
        if ($doc && !$workflow) {
            $rows = $this->rows('SELECT workflow FROM tblWorkflowDocumentContent WHERE document='.(int)$doc->getID().' AND version='.(int)$content->getVersion());
            if ($rows) {
                $workflow = $this->dms->getWorkflow((int)$rows[0]['workflow']);
                if (!$workflow) throw new Exception\ServiceUnavailable('Document workflow unavailable.');
            }
        }
        $result[] = $workflow;
        return $result;
    }

    public function verifyPolicy($doc, $content, $reviewers, $approvers, $workflow)
    {
        foreach (['Reviewers' => $reviewers, 'Approvers' => $approvers] as $suffix => $expected) {
            $actual = ['i' => [], 'g' => []];
            foreach ($this->rows('SELECT type, required FROM tblDocument'.$suffix.' WHERE documentID='.(int)$doc->getID().' AND version='.(int)$content->getVersion()) as $row) {
                $actual[(int)$row['type'] === 0 ? 'i' : 'g'][] = (int)$row['required'];
            }
            foreach (['i', 'g'] as $type) {
                if (array_diff($expected[$type], $actual[$type])) throw new Exception\Conflict('The required reviewers or approvers could not be assigned. Use the web interface.');
            }
        }
        if ($workflow) {
            $rows = $this->rows('SELECT workflow FROM tblWorkflowDocumentContent WHERE document='.(int)$doc->getID().' AND version='.(int)$content->getVersion());
            if (!$rows || (int)$rows[0]['workflow'] !== (int)$workflow->getID()) throw new Exception\Conflict('The required workflow could not be assigned.');
        }
    }

    /** DAV cannot collect missing required metadata. Retain supplied source values or fail closed. */
    public function attributes($type, $source = null)
    {
        $values = [];
        if ($source) {
            $attributes = $source->getAttributes();
            if (!is_array($attributes)) throw new Exception\ServiceUnavailable('Cannot read document attributes.');
            foreach ($attributes as $id => $attribute) $values[(int)$id] = $attribute->getValue();
        }
        foreach ($this->rows('SELECT id,minvalues FROM tblAttributeDefinitions WHERE objtype=0 OR objtype='.(int)$type) as $row) {
            if ((int)$row['minvalues'] > 0 && (!isset($values[$row['id']]) || trim((string)$values[$row['id']]) === '')) {
                throw new Exception\Conflict('Required metadata is missing. Complete this operation in the LetoDMS web interface.');
            }
        }
        return $values;
    }

    public function applyAttributes($object, $values)
    {
        foreach ($values as $id => $value) {
            $definition = $this->dms->getAttributeDefinition($id);
            if (!$definition || !$object->setAttributeValue($definition, $value)) {
                throw new Exception\Conflict('Could not retain document metadata.');
            }
        }
    }

    /** Spool a bounded upload before obtaining a database write lock. */
    public function upload($data)
    {
        $path = tempnam(sys_get_temp_dir(), 'letodms-dav-');
        if ($path === false) throw new Exception\InsufficientStorage('Cannot stage the upload.');
        @chmod($path, 0600);
        $out = fopen($path, 'wb');
        try {
            if (!$out) throw new Exception\InsufficientStorage('Cannot stage the upload.');
            if (!is_resource($data)) {
                $stream = fopen('php://temp', 'w+b');
                fwrite($stream, (string)$data);
                rewind($stream);
                $data = $stream;
            }
            $limit = max(1, (int)$this->settings->_webDAVMaxUploadSize);
            $size = stream_copy_to_stream($data, $out, $limit + 1);
            if ($size === false || $size > $limit) {
                throw new PayloadTooLarge('The upload exceeds the configured WebDAV limit.');
            }
            return [$path, $size];
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        } finally {
            if (is_resource($out)) fclose($out);
            if (isset($stream)) fclose($stream);
        }
    }

    public function quota($owner, $size)
    {
        if (!$owner) throw new Exception\ServiceUnavailable('Document owner unavailable.');
        $quota = (int)$owner->getQuota();
        if (!$quota) $quota = (int)$this->settings->_quota;
        $used = $owner->getUsedDiskSpace();
        if ($used === false || ($quota > 0 && $used + $size > $quota)) {
            throw new Exception\InsufficientStorage('Document owner storage quota exceeded.');
        }
    }

    public function queue($doc, $content)
    {
        if (!$content || $content->getFileSize() == 0) return;
        if ($this->writing) {
            $this->queued[] = [$doc, $content];
            return;
        }
        require_once dirname(__DIR__, 2).'/inc/inc.FulltextUpload.php';
        letoDMSQueueFulltextUpload($this->db, $this->dms, $this->settings, $doc, $content);
        $helper = dirname(__DIR__, 2).'/modules/ai-assistant/inc/Upload.php';
        if (is_file($helper)) {
            try {
                require_once $helper;
                letoDMSQueueAIUpload($this->db, $this->dms, $this->settings, $this->user, $doc, $content);
            } catch (\Throwable $e) {
                error_log('LetoDMS WebDAV: AI queue unavailable after successful upload.');
            }
        }
    }
}
