<?php
// Authenticated download handler — uploads are never linked directly.
require __DIR__ . '/inc/boot.php';
$f = (string)g('f');
$base = realpath(ROOT . '/uploads');
$path = realpath(ROOT . '/uploads/' . $f);
if (!$f || !$base || !$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) { http_response_code(404); exit('File not found.'); }

$allowed = false;
if (me()) $allowed = true;
elseif (!empty($_SESSION['portal'])) {
    $pt = $_SESSION['portal'];
    if ($pt['type'] === 'candidate') {
        $allowed = (bool)val('SELECT id FROM candidates WHERE id=? AND cv_file=?', [$pt['id'], $f])
            || (bool)val('SELECT id FROM candidate_messages WHERE candidate_id=? AND file=?', [$pt['id'], $f])
            || (bool)val("SELECT id FROM documents WHERE candidate_id=? AND file=? AND status IN ('Received','Verified')", [$pt['id'], $f]);
    } elseif ($pt['type'] === 'client') {
        $co = (int)val('SELECT company_id FROM client_users WHERE id=?', [$pt['id']]);
        $allowed = (bool)val('SELECT w.id FROM workers w JOIN demands d ON d.id=w.demand_id WHERE d.company_id=? AND w.cv_file=?', [$co, $f]);
    } elseif ($pt['type'] === 'agent') {
        $ag = (int)val('SELECT agent_id FROM agent_users WHERE id=?', [$pt['id']]);
        $allowed = (bool)val('SELECT id FROM workers WHERE agent_id=? AND cv_file=?', [$ag, $f])
            || (bool)val('SELECT d.id FROM documents d JOIN workers w ON w.id=d.worker_id WHERE w.agent_id=? AND d.file=?', [$ag, $f]);
    }
}
if (!$allowed) { http_response_code(403); exit('You do not have access to this file.'); }
audit('download', null, null, null, ['file' => $f]);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
