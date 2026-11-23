<?php
namespace LetoDMS\WebDAV;

use Sabre\DAV;
use Sabre\DAV\Locks\LockInfo;

/** Persistent, exclusive file locks. Browser locks are checked separately by Context. */
class Locks extends DAV\Locks\Backend\AbstractBackend
{
    private $context;

    public function __construct(Context $context) { $this->context = $context; }

    private function active()
    {
        return $this->context->rows('SELECT * FROM tblWebDAVLocks WHERE expires>'.time());
    }

    private function matches($row, $uri, $children)
    {
        return $row['uri'] === $uri || ($children && ($uri === '' || strpos($row['uri'], $uri.'/') === 0));
    }

    public function getLocks($uri, $returnChildLocks)
    {
        $result = [];
        foreach ($this->active() as $row) {
            if (!$this->matches($row, $uri, $returnChildLocks)) continue;
            $lock = new LockInfo();
            $lock->uri = $row['uri'];
            $lock->token = $row['token'];
            $lock->owner = $row['owner'];
            $lock->scope = LockInfo::EXCLUSIVE;
            $lock->depth = 0;
            $lock->created = time();
            $lock->timeout = max(1, (int)$row['expires'] - time());
            $result[] = $lock;
        }
        return $result;
    }

    public function lock($uri, LockInfo $lockInfo)
    {
        $ctx = $this->context;
        if ($lockInfo->scope !== LockInfo::EXCLUSIVE) throw new DAV\Exception\Forbidden('Only exclusive file locks are supported.');
        return $ctx->transaction(function () use ($ctx, $uri, $lockInfo) {
            $ctx->permission('document.update');
            $node = $ctx->server->tree->getNodeForPath($uri);
            if (!($node instanceof File)) throw new DAV\Exception\MethodNotAllowed('Collection locks are not supported.');
            [$doc, $content] = $node->current(M_READWRITE);
            $existing = null;
            foreach ($this->active() as $row) {
                if ($row['uri'] !== $uri && $row['document_id'] != $doc->getID()) continue;
                if ($row['token'] !== $lockInfo->token || $row['user_id'] != $ctx->user->getID()) throw new DAV\Exception\Locked();
                $existing = $row;
            }
            // Refresh must keep the original base version: a browser edit must remain detectable.
            $version = $existing ? (int)$existing['base_version'] : (int)$content->getVersion();
            $ctx->execute('DELETE FROM tblWebDAVLocks WHERE expires<='.time());
            $token = $ctx->db->qstr($lockInfo->token);
            if ($existing) {
                $ctx->execute('UPDATE tblWebDAVLocks SET expires='.(time()+1800).' WHERE token='.$token);
            } else {
                $ctx->execute('INSERT INTO tblWebDAVLocks (token,uri,owner,user_id,expires,depth,document_id,base_version) VALUES ('.
                    $token.','.$ctx->db->qstr($uri).','.$ctx->db->qstr((string)$lockInfo->owner).','.(int)$ctx->user->getID().','.(time()+1800).',0,'.(int)$doc->getID().','.$version.')');
            }
            $lockInfo->timeout = 1800;
            $lockInfo->created = time();
            $lockInfo->depth = 0;
            $lockInfo->uri = $uri;
            return true;
        });
    }

    public function unlock($uri, LockInfo $lockInfo)
    {
        $ctx = $this->context;
        return $ctx->transaction(function () use ($ctx, $uri, $lockInfo) {
            foreach ($this->active() as $row) {
                if ($row['uri'] === $uri && $row['token'] === $lockInfo->token) {
                    if ($row['user_id'] != $ctx->user->getID()) throw new DAV\Exception\Forbidden('This lock belongs to another user.');
                    $ctx->execute('DELETE FROM tblWebDAVLocks WHERE token='.$ctx->db->qstr($lockInfo->token));
                    return true;
                }
            }
            return false;
        });
    }

    public function assertWrite($uri, $doc = null, $content = null)
    {
        $ctx = $this->context;
        if (!empty($ctx->settings->_webDAVReadOnly)) throw new DAV\Exception\Forbidden('WebDAV is read-only.');
        $if = $ctx->server ? (string)$ctx->server->httpRequest->getHeader('If') : '';
        foreach ($this->active() as $row) {
            if ($row['uri'] !== $uri && (!$doc || $row['document_id'] != $doc->getID())) continue;
            if ($row['user_id'] != $ctx->user->getID() || strpos($if, '<opaquelocktoken:'.$row['token'].'>') === false) {
                throw new DAV\Exception\Locked();
            }
            if ($content && (int)$row['base_version'] !== (int)$content->getVersion()) {
                throw new DAV\Exception\PreconditionFailed('The document changed after it was locked. Reopen it before saving.');
            }
        }
    }

    public function updateVersion($documentID, $version)
    {
        $this->context->execute('UPDATE tblWebDAVLocks SET base_version='.(int)$version.' WHERE document_id='.(int)$documentID.' AND user_id='.(int)$this->context->user->getID());
    }

    public function moved($source, $destination)
    {
        $ctx = $this->context;
        $ctx->transaction(function () use ($ctx, $source, $destination) {
            foreach ($this->active() as $row) {
                if (!$this->matches($row, $source, true)) continue;
                $uri = $destination.substr($row['uri'], strlen($source));
                $ctx->execute('UPDATE tblWebDAVLocks SET uri='.$ctx->db->qstr($uri).' WHERE token='.$ctx->db->qstr($row['token']));
            }
        });
    }
}

class ExclusiveLockProperty implements \Sabre\Xml\XmlSerializable
{
    public function xmlSerialize(\Sabre\Xml\Writer $writer)
    {
        $writer->writeElement('{DAV:}lockentry', [
            '{DAV:}lockscope' => ['{DAV:}exclusive' => null],
            '{DAV:}locktype' => ['{DAV:}write' => null],
        ]);
    }
}

class LockPlugin extends DAV\Locks\Plugin
{
    public function httpLock(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
    {
        $body = $request->getBodyAsString();
        $request->setBody($body);
        if ($body && $this->parseLockRequest($body)->scope !== LockInfo::EXCLUSIVE) {
            throw new DAV\Exception\Forbidden('Only exclusive file locks are supported.');
        }
        if ($this->server->tree->nodeExists($request->getPath()) && $this->server->tree->getNodeForPath($request->getPath()) instanceof Folder) {
            throw new DAV\Exception\MethodNotAllowed('Collection locks are not supported.');
        }
        return parent::httpLock($request, $response);
    }

    public function propFind(DAV\PropFind $propFind, DAV\INode $node)
    {
        $propFind->handle('{DAV:}supportedlock', function () use ($node) {
            return $node instanceof File ? new ExclusiveLockProperty() : new EmptyLockProperty();
        });
        $propFind->handle('{DAV:}lockdiscovery', function () use ($propFind) {
            return new DAV\Xml\Property\LockDiscovery($this->getLocks($propFind->getPath()));
        });
    }
}

class EmptyLockProperty implements \Sabre\Xml\XmlSerializable
{
    public function xmlSerialize(\Sabre\Xml\Writer $writer) {}
}
