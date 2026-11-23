<?php
namespace LetoDMS\WebDAV;

use Sabre\DAV;

class Tree extends DAV\Tree
{
    protected function copyNode(DAV\INode $source, DAV\ICollection $destinationParent, ?string $destinationName = null, int $depth = DAV\Server::DEPTH_INFINITY)
    {
        if ($source instanceof File && $destinationParent instanceof Folder) {
            $ctx = $destinationParent->context;
            $previous = $ctx->copySource;
            $ctx->copySource = $source;
            $stream = $source->get();
            try {
                $destinationParent->createFile($destinationName ?: $source->getName(), $stream);
            } finally {
                fclose($stream);
                $ctx->copySource = $previous;
            }
            return;
        }
        parent::copyNode($source, $destinationParent, $destinationName, $depth);
    }

    public function copy($sourcePath, $destinationPath, int $depth = DAV\Server::DEPTH_INFINITY)
    {
        $node = $this->getNodeForPath($sourcePath);
        // A filtered listing must not produce an apparently successful partial folder copy.
        if ($node instanceof Folder && $depth !== 0) $this->checkReadable($node);
        $node->context->transaction(function () use ($sourcePath, $destinationPath, $depth) {
            parent::copy($sourcePath, $destinationPath, $depth);
        });
    }

    private function checkReadable(Folder $node, &$count = 0)
    {
        if (++$count > 10000) throw new DAV\Exception\Forbidden('Use the web interface for this large copy.');
        $folder = $node->current();
        $folders = $folder->getSubFolders();
        $documents = $folder->getDocuments();
        if (!is_array($folders) || !is_array($documents)) throw new DAV\Exception\ServiceUnavailable('Cannot inspect the source folder.');
        foreach ($documents as $doc) $node->context->document($doc->getID());
        foreach ($folders as $child) $this->checkReadable(new Folder($node->context, $child->getID()), $count);
    }
}

class SafetyPlugin extends DAV\ServerPlugin
{
    private $context;
    private $server;

    public function __construct(Context $context) { $this->context = $context; }
    public function getPluginName() { return 'letodms'; }
    public function initialize(DAV\Server $server)
    {
        $this->server = $server;
        $server->on('beforeMethod:*', [$this, 'beforeMethod'], 20);
        $server->on('beforeMove', function ($source, $target) { return $this->overwrite($source, $target, true); });
        $server->on('beforeCopy', function ($source, $target) { return $this->overwrite($source, $target, false); });
        $server->on('afterMove', [$this->context->locks, 'moved']);
    }

    public function beforeMethod($request, $response)
    {
        if (!$this->context->user) throw new DAV\Exception\NotAuthenticated();
        $method = $request->getMethod();
        if (!empty($this->context->settings->_webDAVReadOnly) && !in_array($method, ['GET','HEAD','OPTIONS','PROPFIND','REPORT'])) {
            throw new DAV\Exception\Forbidden('WebDAV is read-only.');
        }
        $response->setHeader('Cache-Control', 'private, no-store');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        // Avoid unbounded recursive listings from clients.
        if ($method === 'PROPFIND' && !in_array($this->server->getHTTPDepth(1), [0, 1], true)) {
            throw new DAV\Exception\Forbidden('Use Depth: 0 or Depth: 1.');
        }
        if ($method === 'PUT' && (int)$request->getHeader('Content-Length') > $this->context->settings->_webDAVMaxUploadSize) {
            throw new PayloadTooLarge('The upload exceeds the configured WebDAV limit.');
        }
        if (in_array($method, ['LOCK', 'PROPFIND', 'PROPPATCH', 'REPORT'])) {
            // XML requests are small. Bound actual bytes even when Content-Length is absent.
            $body = $request->getBody();
            if (is_resource($body)) {
                $stream = fopen('php://temp', 'w+b');
                $size = stream_copy_to_stream($body, $stream, 1048577);
                if ($size === false || $size > 1048576) {
                    fclose($stream);
                    throw new PayloadTooLarge('WebDAV XML requests are limited to 1 MiB.');
                }
                rewind($stream);
                $request->setBody($stream);
            } elseif (strlen((string)$body) > 1048576) {
                throw new PayloadTooLarge('WebDAV XML requests are limited to 1 MiB.');
            }
        }
    }

    /** Sabre's default overwrite deletes the target and its version history. Save a version instead. */
    private function overwrite($source, $target, $move)
    {
        if (!$this->server->tree->nodeExists($target)) return true;
        $from = $this->server->tree->getNodeForPath($source);
        $to = $this->server->tree->getNodeForPath($target);
        if (!($from instanceof File) || !($to instanceof File)) {
            throw new DAV\Exception\Conflict('Overwriting a folder is not supported.');
        }
        if ($from->id === $to->id) throw new DAV\Exception\Conflict('Source and destination are the same document.');
        if ($move) $from->preflight();
        $stream = $from->get();
        try {
            $etag = $to->put($stream);
        } finally {
            fclose($stream);
        }
        if ($move) {
            $from->delete();
            $this->server->emit('afterUnbind', [$source]);
        }
        $this->server->tree->markDirty('');
        $this->server->httpResponse->setStatus(204);
        $this->server->httpResponse->setHeader('Content-Length', '0');
        $this->server->httpResponse->setHeader('ETag', $etag);
        return false;
    }
}

function createServer(Context $context, $baseURI)
{
    $root = new Folder($context, $context->settings->_rootFolderID);
    $server = new DAV\Server(new Tree($root));
    $server->setBaseUri($baseURI);
    DAV\Server::$exposeVersion = false;
    $server->debugExceptions = false;
    $context->server = $server;
    $context->locks = new Locks($context);
    $server->addPlugin(new DAV\Auth\Plugin(new Auth($context)));
    $server->addPlugin(new LockPlugin($context->locks));
    $server->addPlugin(new SafetyPlugin($context));
    return $server;
}
