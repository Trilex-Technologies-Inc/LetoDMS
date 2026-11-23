<?php
// AI requests enter the normal authenticated module dispatcher (including CSRF).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$quickQuery = isset($_POST['query']) && is_string($_POST['query']) ? trim($_POST['query']) : '';
if (isset($_POST['action']) && $_POST['action'] === 'search') {
    $_GET['module'] = 'ai-assistant';
    $_POST['question'] = $quickQuery;
    $_POST['quick_search'] = '1';
    require __DIR__.'/op.Module.php';
    exit;
}
$quickParams = array('query' => $quickQuery, 'navBar' => 1, 'searchin' => array(1, 2, 3));
if (isset($_POST['folderid']) && is_scalar($_POST['folderid']) && (int)$_POST['folderid'] > 0) $quickParams['folderid'] = (int)$_POST['folderid'];
$quickTarget = isset($_POST['action']) && $_POST['action'] === 'fulltext' ? 'op.SearchFulltext.php' : 'op.Search.php';
header('Location: ../op/'.$quickTarget.'?'.http_build_query($quickParams), true, 303);
exit;
