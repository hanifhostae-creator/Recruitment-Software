<?php
require __DIR__ . '/inc/boot.php';
require_login();
$u = me();
if (is_post()) { q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]); flash('All notifications marked as read.'); redirect('notifications.php'); }
if (g('open')) {
    $n = row('SELECT * FROM notifications WHERE id=? AND user_id=?', [(int)g('open'), $u['id']]);
    if ($n) { q('UPDATE notifications SET is_read=1 WHERE id=?', [$n['id']]); redirect($n['link'] ?: 'notifications.php'); }
}
page_start('Notifications', 'notifications');
page_head('Notifications', 'bell', '', '<form method="post">' . csrf_field() . '<button class="btn ghost sm">' . icon('check', 14) . ' Mark all as read</button></form>');
$list = rows('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100', [$u['id']]);
card_open();
echo simple_table(['', 'Notification', 'When'], array_map(function ($n) { return [$n['is_read'] ? '' : badge('New'), ($n['is_read'] ? '' : '<b>') . e($n['text']) . ($n['is_read'] ? '' : '</b>'), '<span class="muted">' . e(ago($n['at'])) . '</span>']; }, $list), 'No notifications yet.', array_map(function ($n) { return 'notifications.php?open=' . $n['id']; }, $list));
card_close();
page_end();
