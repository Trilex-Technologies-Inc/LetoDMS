<?php
namespace LetoDMS\WebDAV;

use Sabre\DAV;

class File extends DAV\File
{
    public $context;
    public $id;
    public $name;
    public $path;

    public function __construct(Context $context, $id, $name, $path)
    {
        $this->context = $context;
        $this->id = (int)$id;
        $this->name = $name;
        $this->path = $path;
    }

    public function getName() { return $this->name; }
    public function current($mode = M_READ) { return $this->context->document($this->id, $mode); }
    public function getSize() { return $this->current()[1]->getFileSize(); }
    public function getContentType() { return $this->current()[1]->getMimeType() ?: 'application/octet-stream'; }
    public function getLastModified() { return (int)$this->current()[1]->getDate(); }
    public function getETag()
    {
        $content = $this->current()[1];
        return '"d'.$this->id.'-v'.$content->getVersion().'-'.$content->getChecksum().'"';
    }

    public function get()
    {
        $content = $this->current()[1];
        $path = $this->context->dms->contentDir.$content->getPath();
        $stream = @fopen($path, 'rb');
        if (!$stream) throw new DAV\Exception\NotFound('Document file unavailable.');
        return $stream;
    }

    public function put($data)
    {
        $ctx = $this->context;
        $ctx->permission('document.update');
        $this->current(M_READWRITE);
        [$temp, $size] = $ctx->upload($data);
        try {
            [$doc, $content] = $ctx->transaction(function () use ($ctx, $temp, $size) {
                $ctx->permission('document.update');
                [$doc, $previous] = $this->current(M_READWRITE);
                $ctx->checkWritePreconditions($doc, $previous);
                $ctx->locks->assertWrite($this->path, $doc, $previous);
                $ctx->quota($doc->getOwner(), $size);
                [$reviewers, $approvers, $workflow] = $ctx->policy($doc, $previous);
                $ctx->attributes(2, $doc);
                $attributes = $ctx->attributes(3, $previous);
                $extension = pathinfo($this->name, PATHINFO_EXTENSION);
                $type = $extension === '' ? '' : '.'.$extension;
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temp) ?: 'application/octet-stream';
                $result = $doc->addContent('Uploaded through WebDAV', $ctx->user, $temp, $this->name,
                    $type, $mime, $reviewers, $approvers, 0, [], $workflow);
                if (!$result) throw new DAV\Exception\ServiceUnavailable('Could not save the document version.');
                $content = $result->getContent();
                $ctx->cleanupOnRollback($content);
                $ctx->applyAttributes($content, $attributes);
                $ctx->verifyPolicy($doc, $content, $reviewers, $approvers, $workflow);
                $ctx->locks->updateVersion($doc->getID(), $content->getVersion());
                return [$doc, $content];
            });
            $ctx->queue($doc, $content);
            return '"d'.$this->id.'-v'.$content->getVersion().'-'.$content->getChecksum().'"';
        } finally {
            @unlink($temp);
        }
    }

    public function preflight($mode = M_ALL)
    {
        [$doc, $content] = $this->current($mode);
        $this->context->locks->assertWrite($this->path, $doc, $content);
    }

    public function delete()
    {
        $this->context->permission('document.delete');
        $this->context->transaction(function () {
            $this->context->permission('document.delete');
            $this->preflight();
            $doc = $this->current(M_ALL)[0];
            if (!$doc->remove()) throw new DAV\Exception\ServiceUnavailable('Could not remove the document.');
        });
    }

    public function setName($name)
    {
        Context::validateName($name);
        $ctx = $this->context;
        $ctx->permission('document.update');
        $ctx->transaction(function () use ($ctx, $name) {
            $ctx->permission('document.update');
            $this->preflight(M_READWRITE);
            $doc = $this->current(M_READWRITE)[0];
            $parent = new Folder($ctx, $doc->getFolder()->getID(), '', dirname($this->path) === '.' ? '' : dirname($this->path));
            $parent->assertAvailable($name, $this->id, false);
            if (!$doc->setName($name)) throw new DAV\Exception\ServiceUnavailable('Could not rename the document.');
        });
        $this->name = $name;
    }
}

class Folder extends DAV\Collection implements DAV\IMoveTarget
{
    public $context;
    public $id;
    public $name;
    public $path;

    public function __construct(Context $context, $id, $name = '', $path = '')
    {
        $this->context = $context;
        $this->id = (int)$id;
        $this->name = $name;
        $this->path = $path;
    }

    public function getName() { return $this->name; }
    public function current($mode = M_READ) { return $this->context->folder($this->id, $mode); }
    public function getLastModified() { return (int)$this->current()->getDate(); }
    public function childPath($name) { return ltrim($this->path.'/'.$name, '/'); }

    public function getChildren()
    {
        $folder = $this->current();
        $folders = $folder->getSubFolders();
        $documents = $folder->getDocuments();
        if (!is_array($folders) || !is_array($documents)) {
            throw new DAV\Exception\ServiceUnavailable('Cannot list this folder.');
        }
        $entries = [];
        foreach ($folders as $child) {
            if ($child->getAccessMode($this->context->user) >= M_READ) {
                $entries[] = [true, $child->getID(), Context::displayName($child->getName())];
            }
        }
        foreach ($documents as $doc) {
            try {
                [$doc, $content] = $this->context->document($doc->getID());
            } catch (DAV\Exception\Forbidden $e) {
                continue;
            } catch (DAV\Exception\NotFound $e) {
                continue;
            }
            $name = Context::displayName($doc->getName());
            $ext = $content->getFileType();
            if ($ext && strcasecmp(substr($name, -strlen($ext)), $ext) !== 0) $name .= $ext;
            $entries[] = [false, $doc->getID(), $name];
        }
        usort($entries, function ($a, $b) { return [$a[2], $a[0], $a[1]] <=> [$b[2], $b[0], $b[1]]; });
        $counts = array_count_values(array_column($entries, 2));
        $reserved = array_fill_keys(array_column($entries, 2), true);
        $used = [];
        $nodes = [];
        foreach ($entries as [$isFolder, $id, $name]) {
            if ($counts[$name] > 1) {
                $suffix = ' ['.($isFolder ? 'F' : 'D').$id.']';
                $ext = $isFolder ? '' : pathinfo($name, PATHINFO_EXTENSION);
                $base = $ext === '' ? $name : substr($name, 0, -strlen($ext)-1);
                $name = $base.$suffix.($ext === '' ? '' : '.'.$ext);
                while (isset($reserved[$name]) || isset($used[$name])) $name = '_'.$name;
            }
            $used[$name] = true;
            $class = $isFolder ? Folder::class : File::class;
            $nodes[] = new $class($this->context, $id, $name, $this->childPath($name));
        }
        return $nodes;
    }

    /** Also check hidden names, without revealing whether the conflicting item is readable. */
    public function assertAvailable($name, $exceptID = 0, $exceptFolder = false)
    {
        Context::validateName($name);
        $folder = $this->current(M_READWRITE);
        $folders = $folder->getSubFolders();
        $docs = $folder->getDocuments();
        if (!is_array($folders) || !is_array($docs)) throw new DAV\Exception\ServiceUnavailable('Cannot check the destination.');
        foreach ($folders as $child) {
            if ($exceptFolder && $child->getID() == $exceptID) continue;
            if (Context::displayName($child->getName()) === $name) throw new DAV\Exception\Conflict('Destination name is unavailable.');
        }
        foreach ($docs as $doc) {
            if (!$exceptFolder && $doc->getID() == $exceptID) continue;
            $raw = Context::displayName($doc->getName());
            $content = $doc->getLatestContent();
            $ext = $content ? $content->getFileType() : '';
            if ($raw === $name || ($ext && strcasecmp(substr($raw, -strlen($ext)), $ext) !== 0 && $raw.$ext === $name)) {
                throw new DAV\Exception\Conflict('Destination name is unavailable.');
            }
        }
        foreach ($this->getChildren() as $node) {
            if ($node->id == $exceptID && ($node instanceof Folder) === $exceptFolder) continue;
            if ($node->getName() === $name) throw new DAV\Exception\Conflict('Destination name is unavailable.');
        }
    }

    public function createFile($name, $data = null)
    {
        Context::validateName($name);
        $ctx = $this->context;
        $ctx->permission('document.create');
        $this->current(M_READWRITE);
        [$temp, $size] = $ctx->upload($data);
        try {
            [$doc, $content] = $ctx->transaction(function () use ($ctx, $name, $temp, $size) {
                $ctx->permission('document.create');
                $this->assertAvailable($name);
                $ctx->locks->assertWrite($this->childPath($name));
                $ctx->quota($ctx->user, $size);
                [$reviewers, $approvers, $workflow] = $ctx->policy();
                $source = $ctx->copySource ? $ctx->copySource->current() : [null, null];
                $docAttributes = $ctx->attributes(2, $source[0]);
                $versionAttributes = $ctx->attributes(3, $source[1]);
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temp) ?: 'application/octet-stream';
                $result = $this->current(M_READWRITE)->addDocument($name, '', 0, $ctx->user, '', [], $temp, $name,
                    $ext === '' ? '' : '.'.$ext, $mime, 0, $reviewers, $approvers, 0, 'Uploaded through WebDAV', [], [], $workflow);
                if (!$result || !$result[0] || !$result[1]) throw new DAV\Exception\ServiceUnavailable('Could not create the document.');
                $content = $result[1]->getContent();
                $ctx->cleanupOnRollback($content);
                $ctx->applyAttributes($result[0], $docAttributes);
                $ctx->applyAttributes($content, $versionAttributes);
                $ctx->verifyPolicy($result[0], $content, $reviewers, $approvers, $workflow);
                return [$result[0], $content];
            });
            $ctx->queue($doc, $content);
            return '"d'.$doc->getID().'-v'.$content->getVersion().'-'.$content->getChecksum().'"';
        } finally {
            @unlink($temp);
        }
    }

    public function createDirectory($name)
    {
        $this->context->permission('document.create');
        $this->context->transaction(function () use ($name) {
            $this->context->permission('document.create');
            $this->assertAvailable($name);
            $this->context->locks->assertWrite($this->childPath($name));
            $this->context->attributes(1);
            if (!$this->current(M_READWRITE)->addSubFolder($name, '', $this->context->user, 0)) {
                throw new DAV\Exception\ServiceUnavailable('Could not create the folder.');
            }
        });
    }

    public function preflight($mode = M_ALL, &$count = 0)
    {
        if (++$count > 10000) throw new DAV\Exception\Forbidden('Use the web interface for this large folder operation.');
        $folder = $this->current($mode);
        $this->context->locks->assertWrite($this->path);
        $folders = $folder->getSubFolders();
        $docs = $folder->getDocuments();
        if (!is_array($folders) || !is_array($docs)) throw new DAV\Exception\ServiceUnavailable('Cannot inspect the folder.');
        // Traverse raw children: filtered listing must not permit deletion of inaccessible descendants.
        foreach ($folders as $child) {
            $node = new self($this->context, $child->getID(), $child->getName(), $this->childPath(Context::displayName($child->getName())));
            $node->preflight($mode, $count);
        }
        foreach ($docs as $doc) {
            $node = new File($this->context, $doc->getID(), $doc->getName(), $this->childPath(Context::displayName($doc->getName())));
            $node->preflight($mode);
        }
    }

    private function requireNonRoot()
    {
        if ($this->id == $this->context->settings->_rootFolderID) throw new DAV\Exception\Forbidden('The DMS root cannot be modified.');
    }

    public function delete()
    {
        $this->context->permission('document.delete');
        $this->requireNonRoot();
        $this->context->transaction(function () {
            $this->context->permission('document.delete');
            $this->preflight();
            if (!$this->current(M_ALL)->remove()) throw new DAV\Exception\ServiceUnavailable('Could not remove the folder.');
        });
    }

    public function setName($name)
    {
        $this->context->permission('document.update');
        $this->requireNonRoot();
        Context::validateName($name);
        $this->context->transaction(function () use ($name) {
            $this->context->permission('document.update');
            $this->preflight();
            $folder = $this->current(M_ALL);
            $parent = new self($this->context, $folder->getParent()->getID());
            $parent->assertAvailable($name, $this->id, true);
            if (!$folder->setName($name)) throw new DAV\Exception\ServiceUnavailable('Could not rename the folder.');
        });
        $this->name = $name;
    }

    public function moveInto($targetName, $sourcePath, DAV\INode $sourceNode)
    {
        if (!($sourceNode instanceof self) && !($sourceNode instanceof File)) return false;
        $ctx = $this->context;
        $ctx->permission('document.update');
        $ctx->transaction(function () use ($ctx, $targetName, $sourceNode) {
            $ctx->permission('document.update');
            $sourceNode->preflight();
            $this->assertAvailable($targetName);
            $ctx->locks->assertWrite($this->childPath($targetName));
            $target = $this->current(M_READWRITE);
            if ($sourceNode instanceof self) {
                $sourceNode->requireNonRoot();
                foreach ($target->getPath() as $ancestor) {
                    if ($ancestor->getID() == $sourceNode->id) throw new DAV\Exception\Conflict('Cannot move a folder into itself.');
                }
                $source = $sourceNode->current(M_ALL);
                $ok = $source->setParent($target) && $source->setName($targetName);
            } else {
                $source = $sourceNode->current(M_ALL)[0];
                $ok = $source->setFolder($target) && $source->setName($targetName);
            }
            if (!$ok) throw new DAV\Exception\ServiceUnavailable('Could not move the resource.');
        });
        return true;
    }
}
