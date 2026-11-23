<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Actual SabreDAV requests, actual LetoDMS core, SQLite, and a private temporary content store.
// php -d extension=pdo_sqlite webdav/tests/integration.php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root.'/LetoDMS_Core/Core'.PATH_SEPARATOR.$root.'/inc'.PATH_SEPARATOR.get_include_path());
require_once $root.'/LetoDMS_Core/Core.php';
require_once __DIR__.'/../vendor/autoload.php';
foreach (['Context', 'Auth', 'Nodes', 'Locks', 'Server'] as $file) require_once __DIR__.'/../src/'.$file.'.php';

function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
class TestContext extends LetoDMS\WebDAV\Context
{
    public function queue($doc, $content)
    {
        $GLOBALS['queued'][] = [$doc->getID(), $content->getVersion(), $content->getFileSize()];
        if (!empty($GLOBALS['testRealQueue'])) parent::queue($doc, $content);
    }
}

$temp = sys_get_temp_dir().'/letodms-dav-'.bin2hex(random_bytes(8)).'/';
mkdir($temp, 0700);
$db = new LetoDMS_Core_DatabaseAccess('sqlite', '', '', '', ':memory:');
check($db->connect(), 'Database connection');
check($db->getResult(file_get_contents($root.'/install/create_tables-sqlite3.sql')), 'Create application schema');
$settings = (object)[
    '_rootFolderID' => 1, '_dbDriver' => 'sqlite', '_webDAVReadOnly' => false,
    '_webDAVMaxUploadSize' => 1048576, '_quota' => 0, '_adminIP' => '', '_loginFailure' => 0,
    '_passwordExpiration' => 0,
];
$GLOBALS['queued'] = [];
function request($method, $path, $body = '', $headers = [], $credentials = 'admin:admin')
{
    global $db, $settings, $temp;
    $dms = new LetoDMS_Core_DMS($db, $temp);
    $dms->setEnableAdminRevApp(true);
    $ctx = new TestContext($dms, $settings);
    $ctx->initialize();
    $server = LetoDMS\WebDAV\createServer($ctx, '/dav/');
    if ($credentials !== null) $headers['Authorization'] = 'Basic '.base64_encode($credentials);
    $req = new Sabre\HTTP\Request($method, '/dav/'.$path, $headers, $body);
    $req->setBaseUrl('/dav/');
    $res = new Sabre\HTTP\Response();
    $server->httpRequest = $req;
    $server->httpResponse = $res;
    try {
        $server->invokeMethod($req, $res, false);
    } catch (Sabre\DAV\Exception $e) {
        $res->setStatus($e->getHTTPCode());
        $res->setBody(get_class($e).': '.$e->getMessage());
    }
    $responseBody = $res->getBody();
    if (is_resource($responseBody)) {
        $text = stream_get_contents($responseBody);
        fclose($responseBody);
        $res->setBody($text);
    }
    return $res;
}
function status($response, $expected, $message)
{
    check($response->getStatus() === $expected, $message.': expected '.$expected.', got '.$response->getStatus().' '.$response->getBodyAsString());
}
function contentText($id, $version)
{
    global $db, $temp;
    $dms = new LetoDMS_Core_DMS($db, $temp);
    $content = $dms->getDocument($id)->getContentByVersion($version);
    return file_get_contents($temp.$content->getPath());
}
try {
    status(request('PROPFIND', '', '', ['Depth' => '1'], null), 401, 'Authentication required');
    status(request('PROPFIND', '', '', ['Depth' => '1'], 'admin:wrong'), 401, 'Reject bad password');
    status(request('PROPFIND', '', '', ['Depth' => '1']), 207, 'Root listing');
    status(request('PROPFIND', '', '', ['Depth' => 'infinity']), 403, 'Bound recursive listing');
    status(request('PUT', 'report.txt', 'First version'), 201, 'Create document');
    $rows = $db->getResultArray("SELECT id FROM tblDocuments WHERE name='report.txt'");
    $id = (int)$rows[0]['id'];
    check(contentText($id, 1) === 'First version', 'Persist original file');
    $res = request('GET', 'report.txt'); status($res, 200, 'Download');
    check($res->getBodyAsString() === 'First version', 'Download bytes');
    $etag = $res->getHeader('ETag');
    status(request('PUT', 'report.txt', 'Second version', ['If-Match' => $etag]), 204, 'Save new version');
    check(contentText($id, 1) === 'First version' && contentText($id, 2) === 'Second version', 'Preserve history');
    status(request('PUT', 'report.txt', 'Stale', ['If-Match' => $etag]), 412, 'Reject stale ETag');
    status(request('MKCOL', 'Folder'), 201, 'Create folder');
    status(request('MOVE', 'report.txt', '', ['Destination' => 'http://localhost/dav/Folder/renamed.txt']), 201, 'Move document');
    status(request('GET', 'report.txt'), 404, 'Old path absent');
    status(request('GET', 'Folder/renamed.txt'), 200, 'New path readable');
    $rows = $db->getResultArray('SELECT folder,name FROM tblDocuments WHERE id='.$id);
    check($rows[0]['name'] === 'renamed.txt', 'Move preserves document ID');
    status(request('COPY', 'Folder/renamed.txt', '', ['Destination' => 'http://localhost/dav/copy.txt']), 201, 'Copy file');
    status(request('PUT', 'replacement.txt', 'Replacement'), 201, 'Create replacement');
    status(request('MOVE', 'replacement.txt', '', ['Destination' => 'http://localhost/dav/Folder/renamed.txt']), 204, 'Overwrite adds version');
    check(contentText($id, 1) === 'First version' && contentText($id, 3) === 'Replacement', 'Overwrite preserves target history and ID');
    status(request('GET', 'replacement.txt'), 404, 'Move removes source');
    $lockXML = '<?xml version="1.0"?><d:lockinfo xmlns:d="DAV:"><d:lockscope><d:exclusive/></d:lockscope><d:locktype><d:write/></d:locktype><d:owner>Integration test</d:owner></d:lockinfo>';
    $res = request('LOCK', 'Folder/renamed.txt', $lockXML, ['Depth' => '0', 'Content-Type' => 'application/xml']);
    status($res, 200, 'Lock document');
    $token = $res->getHeader('Lock-Token');
    check($token !== null, 'Lock token returned');
    status(request('PUT', 'Folder/renamed.txt', 'No token'), 423, 'Lock enforced across requests');
    status(request('PUT', 'Folder/renamed.txt', 'Locked save', ['If' => '('.$token.')']), 204, 'Save with token');
    status(request('LOCK', 'Folder/renamed.txt', '', ['If' => '('.$token.')']), 200, 'Refresh lock');
    // Simulate a save through the normal browser API while the DAV lock remains active.
    $dms = new LetoDMS_Core_DMS($db, $temp); $doc = $dms->getDocument($id);
    $browserTemp = $temp.'browser.txt'; file_put_contents($browserTemp, 'Browser edit');
    check($doc->addContent('Browser', $dms->getUser(1), $browserTemp, 'renamed.txt', '.txt', 'text/plain'), 'Browser save');
    status(request('PUT', 'Folder/renamed.txt', 'Stale desktop edit', ['If' => '('.$token.')']), 412, 'Detect browser change during DAV lock');
    status(request('UNLOCK', 'Folder/renamed.txt', '', ['Lock-Token' => $token]), 204, 'Unlock');
    status(request('LOCK', 'Folder', $lockXML, ['Depth' => '0']), 405, 'Reject unsupported folder locks');
    $settings->_webDAVReadOnly = true;
    status(request('GET', 'Folder/renamed.txt'), 200, 'Read-only downloads');
    status(request('PUT', 'Folder/renamed.txt', 'Blocked'), 403, 'Read-only write blocked');
    $settings->_webDAVReadOnly = false;
    $settings->_webDAVMaxUploadSize = 3;
    status(request('PUT', 'large.txt', 'Too large'), 413, 'Upload size limit');
    $settings->_webDAVMaxUploadSize = 1048576;
    check(!$db->getResultArray("SELECT id FROM tblDocuments WHERE name='large.txt'"), 'Rejected upload leaves no document');
    status(request('DELETE', 'copy.txt'), 204, 'Delete file');
    status(request('MOVE', 'Folder', '', ['Destination' => 'http://localhost/dav/NewFolder']), 201, 'Rename folder');
    status(request('GET', 'NewFolder/renamed.txt'), 200, 'Folder rename preserves children');
    status(request('DELETE', ''), 403, 'Protect root');
    status(request('MKCOL', 'Disposable'), 201, 'Create disposable folder');
    status(request('MKCOL', 'Disposable/Child'), 201, 'Create child folder');
    status(request('PUT', 'Disposable/Child/file.txt', 'Child document'), 201, 'Create nested file');
    status(request('DELETE', 'Disposable'), 204, 'Delete folder and children');
    status(request('GET', 'Disposable/Child/file.txt'), 404, 'Nested content removed');
    status(request('PUT', 'after-delete.txt', 'New write'), 201, 'Writes still commit after recursive deletion');
    // Role permissions supplement, rather than replace, document ACLs.
    check($db->getResult("INSERT INTO tblUsers (id,login,pwd,fullName,language,theme,comment,role) VALUES (3,'writer','".md5('writer')."','Writer','','','',0),(4,'other','".md5('other')."','Other','','','',0)"), 'Create normal users');
    status(request('GET', 'after-delete.txt', '', [], 'writer:writer'), 401, 'Role permission required');
    check($db->getResult("INSERT INTO tblRoles (id,name,description) VALUES (1,'DAV','DAV testing'); INSERT INTO tblUserRoles (userID,roleID) VALUES (3,1),(4,1); INSERT INTO tblRolePermissions (roleID,permissionID) SELECT 1,id FROM tblPermissions WHERE name='webdav.access'"), 'Grant DAV role');
    status(request('GET', 'after-delete.txt', '', [], 'writer:writer'), 200, 'Authorized normal user can read');
    status(request('PUT', 'no-role.txt', 'Blocked', [], 'writer:writer'), 403, 'Document create permission required');
    check($db->getResult("INSERT INTO tblRolePermissions (roleID,permissionID) SELECT 1,id FROM tblPermissions WHERE name IN ('document.create','document.update','document.delete'); INSERT INTO tblACLs (target,targetType,userID,mode) VALUES (1,1,3,".M_READWRITE."),(1,1,4,".M_READWRITE.")"), 'Grant write permissions and root ACL');
    status(request('PUT', 'writer.txt', 'Writer document', [], 'writer:writer'), 201, 'Normal user uploads');
    $writerID = (int)$db->getResultArray("SELECT id FROM tblDocuments WHERE name='writer.txt'")[0]['id'];
    $res = request('LOCK', 'writer.txt', $lockXML, ['Depth' => '0'], 'writer:writer');
    status($res, 200, 'Normal user lock'); $writerToken = $res->getHeader('Lock-Token');
    status(request('PUT', 'writer.txt', 'Stolen token', ['If' => '('.$writerToken.')'], 'other:other'), 423, 'Other user cannot use known lock token');
    status(request('UNLOCK', 'writer.txt', '', ['Lock-Token' => $writerToken], 'other:other'), 403, 'Other user cannot unlock');
    status(request('UNLOCK', 'writer.txt', '', ['Lock-Token' => $writerToken], 'writer:writer'), 204, 'Owner can unlock');
    $dms = new LetoDMS_Core_DMS($db, $temp);
    check($dms->getDocument($writerID)->setLocked($dms->getUser(3)), 'Set browser lock');
    status(request('PUT', 'writer.txt', 'Browser locked', [], 'writer:writer'), 423, 'Respect browser lock even for owner');
    check($dms->getDocument($writerID)->setLocked(false), 'Release browser lock');
    check($db->getResult("UPDATE tblDocuments SET inheritAccess=0,defaultAccess=0 WHERE id=".$writerID), 'Restrict document');
    status(request('GET', 'writer.txt', '', [], 'other:other'), 404, 'ACL hides inaccessible document');
    $listing = request('PROPFIND', '', '', ['Depth' => '1'], 'other:other');
    status($listing, 207, 'Filtered listing'); check(strpos($listing->getBodyAsString(), 'writer.txt') === false, 'No hidden document in listing');
    status(request('MKCOL', 'Protected', '', [], 'writer:writer'), 201, 'Create owned folder');
    status(request('PUT', 'Protected/secret.txt', 'Hidden child'), 201, 'Create hidden child');
    check($db->getResult("UPDATE tblDocuments SET inheritAccess=0,defaultAccess=0 WHERE name='secret.txt'"), 'Restrict child ACL');
    status(request('DELETE', 'Protected', '', [], 'writer:writer'), 403, 'Cannot recursively delete hidden child');
    status(request('COPY', 'Protected', '', ['Destination' => 'http://localhost/dav/Partial'], 'writer:writer'), 403, 'Cannot silently copy partial tree');
    status(request('GET', 'Protected/secret.txt'), 200, 'Rejected folder operation preserves child');
    check($db->getResult("UPDATE tblUsers SET disabled=1 WHERE id=3"), 'Disable user');
    status(request('GET', 'writer.txt', '', [], 'writer:writer'), 401, 'Disabled account denied');
    check($db->getResult("UPDATE tblUsers SET disabled=0,pwdExpiration='2000-01-01 00:00:00' WHERE id=3"), 'Expire password');
    $settings->_passwordExpiration = 30;
    status(request('GET', 'writer.txt', '', [], 'writer:writer'), 401, 'Expired password denied');
    $settings->_passwordExpiration = 0;
    check($db->getResult("UPDATE tblUsers SET pwdExpiration='0000-00-00 00:00:00' WHERE id=3"), 'Clear expiry');
    status(request('PUT', 'reviewed.txt', 'Review content'), 201, 'Create review document');
    $reviewID = (int)$db->getResultArray("SELECT id FROM tblDocuments WHERE name='reviewed.txt'")[0]['id'];
    check($db->getResult('INSERT INTO tblDocumentReviewers (documentID,version,type,required) VALUES ('.$reviewID.',1,0,3); INSERT INTO tblDocumentApprovers (documentID,version,type,required) VALUES ('.$reviewID.',1,0,4)'), 'Set existing review policy');
    status(request('PUT', 'reviewed.txt', 'New review content'), 204, 'Save retains review assignments');
    check(count($db->getResultArray('SELECT * FROM tblDocumentReviewers WHERE documentID='.$reviewID.' AND version=2 AND required=3')) === 1, 'Reviewer carried to version');
    check(count($db->getResultArray('SELECT * FROM tblDocumentApprovers WHERE documentID='.$reviewID.' AND version=2 AND required=4')) === 1, 'Approver carried to version');
    status(request('COPY', 'reviewed.txt', '', ['Destination' => 'http://localhost/dav/review-copy.txt']), 201, 'Copy managed document');
    $reviewCopyID = (int)$db->getResultArray("SELECT id FROM tblDocuments WHERE name='review-copy.txt'")[0]['id'];
    check(count($db->getResultArray('SELECT * FROM tblDocumentReviewers WHERE documentID='.$reviewCopyID.' AND required=3')) === 1, 'Copy retains source review policy');
    check($db->getResult('INSERT INTO tblMandatoryReviewers (userID,reviewerUserID) VALUES (1,3)'), 'Set mandatory reviewer');
    status(request('PUT', 'mandatory.txt', 'New mandatory review'), 201, 'Mandatory reviewer on upload');
    $mandatoryID = (int)$db->getResultArray("SELECT id FROM tblDocuments WHERE name='mandatory.txt'")[0]['id'];
    check(count($db->getResultArray('SELECT * FROM tblDocumentReviewers WHERE documentID='.$mandatoryID.' AND required=3')) === 1, 'Mandatory review persisted');
    check($db->getResult('DELETE FROM tblMandatoryReviewers'), 'Clear mandatory reviewer');
    status(request('LOCK', 'after-delete.txt', $lockXML, ['Depth' => '0']), 200, 'Create expiring lock');
    check($db->getResult('UPDATE tblWebDAVLocks SET expires=1'), 'Expire locks');
    status(request('PUT', 'after-delete.txt', 'After expiry'), 204, 'Expired locks do not block writes');
    status(request('LOCK', 'after-delete.txt', $lockXML, ['Depth' => '0']), 200, 'Can reacquire expired lock');
    status(request('PROPFIND', '', '', ['Depth' => '2']), 403, 'Reject recursive numeric depth');
    status(request('PROPFIND', '', str_repeat('x', 1048577), ['Depth' => '1']), 413, 'Bound XML body size');
    $sharedXML = str_replace('exclusive', 'shared', $lockXML);
    status(request('LOCK', 'shared-placeholder.txt', $sharedXML, ['Depth' => '0']), 403, 'Reject unsupported shared lock');
    check(!$db->getResultArray("SELECT id FROM tblDocuments WHERE name='shared-placeholder.txt'"), 'Rejected lock creates no placeholder');
    status(request('PUT', rawurlencode('Résumé 2026.txt'), 'Unicode document'), 201, 'Unicode and spaces in filenames');
    status(request('GET', rawurlencode('Résumé 2026.txt')), 200, 'Read Unicode filename');
    status(request('PUT', 'duplicate-a.txt', 'Duplicate A'), 201, 'Create duplicate A');
    status(request('PUT', 'duplicate-b.txt', 'Duplicate B'), 201, 'Create duplicate B');
    check($db->getResult("UPDATE tblDocuments SET name='Same title' WHERE name IN ('duplicate-a.txt','duplicate-b.txt')"), 'Set duplicate titles');
    $duplicates = $db->getResultArray("SELECT id FROM tblDocuments WHERE name='Same title' ORDER BY id");
    $res = request('GET', rawurlencode('Same title [D'.$duplicates[0]['id'].'].txt'));
    status($res, 200, 'Resolve duplicate title by stable ID alias');
    check($res->getBodyAsString() === 'Duplicate A', 'Alias resolves correct file');
    check($db->getResult('UPDATE tblUsers SET quota=1 WHERE id=3'), 'Set owner quota');
    status(request('PUT', 'writer.txt', 'Over quota', [], 'writer:writer'), 507, 'Owner quota applies to new versions');
    check($db->getResult('UPDATE tblUsers SET quota=0 WHERE id=3'), 'Reset quota');
    // Optional version metadata is retained; required metadata cannot be bypassed with DAV.
    check($db->getResult("INSERT INTO tblAttributeDefinitions (id,name,objtype,type,minvalues) VALUES (1,'Version note',3,1,0)"), 'Create version attribute');
    $dms = new LetoDMS_Core_DMS($db, $temp);
    $latest = $dms->getDocument($reviewID)->getLatestContent();
    check($latest->setAttributeValue($dms->getAttributeDefinition(1), 'Retain this value'), 'Set version metadata');
    status(request('PUT', 'reviewed.txt', 'Metadata version'), 204, 'Update with metadata');
    $dms = new LetoDMS_Core_DMS($db, $temp);
    check($dms->getDocument($reviewID)->getLatestContent()->getAttributeValue($dms->getAttributeDefinition(1)) === 'Retain this value', 'Version metadata preserved');
    check($db->getResult('UPDATE tblAttributeDefinitions SET minvalues=1 WHERE id=1'), 'Require version metadata');
    status(request('PUT', 'missing-metadata.txt', 'No metadata'), 409, 'Required metadata prevents incomplete upload');
    check($db->getResult('UPDATE tblAttributeDefinitions SET minvalues=0 WHERE id=1'), 'Clear required attribute');
    // A workflow with no eligible transition must stay inaccessible, including to DAV admins.
    check($db->getResult("INSERT INTO tblWorkflowStates (id,name) VALUES (1,'Restricted'); INSERT INTO tblWorkflows (id,name,initstate) VALUES (1,'Approval flow',1); INSERT INTO tblWorkflowMandatoryWorkflow (userid,workflow) VALUES (1,1)"), 'Set mandatory workflow');
    status(request('PUT', 'workflow.txt', 'Workflow content'), 201, 'Upload assigns mandatory workflow');
    $workflowID = (int)$db->getResultArray("SELECT id FROM tblDocuments WHERE name='workflow.txt'")[0]['id'];
    check(count($db->getResultArray('SELECT * FROM tblWorkflowDocumentContent WHERE document='.$workflowID.' AND workflow=1')) === 1, 'Workflow relation persisted');
    status(request('GET', 'workflow.txt'), 404, 'Workflow visibility applies to DAV');
    check($db->getResult('DELETE FROM tblWorkflowMandatoryWorkflow'), 'Clear mandatory workflow');
    // The real optional module hook queues a job without calling an AI provider.
    require_once $root.'/inc/inc.ClassModuleManager.php';
    $manager = new LetoDMS_ModuleManager($db, $root.'/modules', 'sqlite');
    check($manager->install('ai-assistant'), 'Install optional AI module');
    require_once $root.'/modules/ai-assistant/inc/Assistant.php';
    putenv('LETODMS_DAV_TEST_KEY=offline-test-key');
    $oldProfiles = getenv('LETODMS_AI_PROVIDERS');
    putenv('LETODMS_AI_PROVIDERS='.json_encode(['dav-test' => ['label' => 'Offline test', 'protocol' => 'openai', 'endpoint' => 'https://example.com/chat', 'model' => 'offline', 'key_env' => 'LETODMS_DAV_TEST_KEY']]));
    $settings->_rootDir = $root.'/'; $settings->_encryptionKey = 'test-only-encryption-key';
    $store = new LetoDMS_AIStore($db, 'sqlite'); $store->initialize();
    $providers = new LetoDMS_AIProviderSettings($db, $settings->_encryptionKey); $providers->initialize();
    $assistant = new LetoDMS_AIAssistant($db, $providers);
    $store->saveOptions($dms->getUser(1), array_merge($store->options(), ['automatic' => '1', 'analysis_provider' => 'dav-test']), $assistant);
    $GLOBALS['testRealQueue'] = true;
    status(request('PUT', 'ai-queued.txt', 'Document to analyze'), 201, 'Upload through real AI queue hook');
    $jobs = $db->getResultArray("SELECT j.* FROM tblModuleAIJobs j JOIN tblDocuments d ON d.id=j.document_id WHERE d.name='ai-queued.txt'");
    check(count($jobs) === 1 && $jobs[0]['state'] === 'pending' && (int)$jobs[0]['document_version'] === 1, 'AI job queued for committed document version');
    status(request('PUT', 'ai-queued.txt', 'New analysis version'), 204, 'Queue new version');
    check(count($db->getResultArray("SELECT j.* FROM tblModuleAIJobs j JOIN tblDocuments d ON d.id=j.document_id WHERE d.name='ai-queued.txt'")) === 2, 'Each DAV version gets an AI job');
    $settings->_enableFullSearch = true;
    check($db->getResult("UPDATE tblModules SET enabled=0 WHERE name='ai-assistant'"), 'Disable AI independently');
    status(request('PUT', 'local-index.txt', 'Local fulltext hummingbird'), 201, 'DAV upload without AI');
    $fulltext = new LetoDMS_Fulltext($db,new LetoDMS_Core_DMS($db,$temp),$settings);
    check($fulltext->work(5)===1, 'DAV upload queues local indexing even with AI disabled');
    check(count($fulltext->search((new LetoDMS_Core_DMS($db,$temp))->getUser(1),'hummingbird')['hits'])===1, 'DAV content is full-text searchable');
    putenv($oldProfiles === false ? 'LETODMS_AI_PROVIDERS' : 'LETODMS_AI_PROVIDERS='.$oldProfiles);
    putenv('LETODMS_DAV_TEST_KEY');
    check(count($GLOBALS['queued']) >= 5, 'Successful uploads reach AI queue hook');
    echo "WebDAV integration checks passed.\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($temp);
}
