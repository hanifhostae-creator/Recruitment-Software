<?php
// Live notification check (called every few seconds by assets/app.js): returns unread counts and new items.
require __DIR__ . '/inc/boot.php';
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-store');
$since = (int)g('since');
$out = ['count' => 0, 'chat' => 0, 'items' => []];
if (me()) {
    $uid = (int)me()['id'];
    $out['count'] = (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$uid]);
    foreach (rows('SELECT id, text FROM notifications WHERE user_id=? AND id>? ORDER BY id LIMIT 5', [$uid, $since]) as $n) $out['items'][] = ['id' => (int)$n['id'], 'text' => $n['text'], 'link' => 'notifications.php?open=' . $n['id']];
    $out['chat'] = chat_unread_total();
} elseif (!empty($_SESSION['portal']) && $_SESSION['portal']['type'] === 'candidate') {
    $cid = (int)$_SESSION['portal']['id'];
    $out['count'] = (int)val("SELECT COUNT(*) FROM chat_messages WHERE candidate_id=? AND sender_type='Staff' AND read_at IS NULL AND archived=0", [$cid]);
    foreach (rows("SELECT id FROM chat_messages WHERE candidate_id=? AND sender_type='Staff' AND id>? AND archived=0 ORDER BY id LIMIT 3", [$cid, $since]) as $m) $out['items'][] = ['id' => (int)$m['id'], 'text' => 'New message from your deployment team', 'link' => 'portal.php?p=chat'];
}
echo json_encode($out);
