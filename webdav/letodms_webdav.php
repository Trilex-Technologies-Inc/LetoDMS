<?php
// Retired PEAR implementation. The supported endpoint is webdav/index.php/.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'The legacy WebDAV adapter has been retired. Use webdav/index.php/.';
