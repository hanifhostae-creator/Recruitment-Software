<?php
require __DIR__ . '/inc/boot.php';
require_login();
$u = me();
if (g('pop')) {
    // small live list for the bell menu in the top bar
    header('Cache-Control: no-store');
    $cfg = notif_config();
    $list = rows('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 12', [$u['id']]);
    if (!$list) { echo '<p class="np-empty">' . icon('bell', 26) . '<br>You are all caught up.</p>'; exit; }
    foreach ($list as $n) {
        $c = $cfg[$n['kind']] ?? $cfg['system'];
        echo '<a class="np-item' . ($n['is_read'] ? '' : ' unread') . '" href="notifications.php?open=' . (int)$n['id'] . '"><span class="np-i">' . icon($c['icon'], 17) . '</span><span class="np-t"><span>' . e($n['text']) . '</span><small>' . e($c['label']) . ' · ' . e(ago($n['at'])) . '</small></span>'
            . '<button type="button" class="np-rep"' . voice_attrs($n['text'], $n['msg_key'], $n['say_tail']) . ' title="Hear it again" aria-label="Hear it again">' . icon('volume', 15) . '</button></a>';
    }
    exit;
}
if (is_post()) { q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]); flash('All notifications marked as read.'); redirect('notifications.php'); }
if (g('open')) {
    $n = row('SELECT * FROM notifications WHERE id=? AND user_id=?', [(int)g('open'), $u['id']]);
    if ($n) { q('UPDATE notifications SET is_read=1 WHERE id=?', [$n['id']]); $_SESSION['say_note'] = ['text' => $n['text'], 'kind' => $n['kind'], 'key' => $n['msg_key'], 'tail' => $n['say_tail']]; redirect($n['link'] ?: 'notifications.php'); }
}
page_start('Notifications', 'notifications');
page_head('Notifications', 'bell', '', '<form method="post">' . csrf_field() . '<button class="btn ghost sm">' . icon('check', 14) . ' Mark all as read</button></form>');
$list = rows('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100', [$u['id']]);
card_open();
echo simple_table(['', 'Notification', 'When'], array_map(function ($n) { return [$n['is_read'] ? '' : badge('New'), ($n['is_read'] ? '' : '<b>') . e($n['text']) . ($n['is_read'] ? '' : '</b>'), '<span class="muted">' . e(ago($n['at'])) . '</span> <button type="button" class="btn ghost sm rep"' . voice_attrs($n['text'], $n['msg_key'], $n['say_tail']) . ' title="Hear it again">' . icon('volume', 14) . ' Repeat</button>']; }, $list), 'No notifications yet.', array_map(function ($n) { return 'notifications.php?open=' . $n['id']; }, $list));
card_close();
page_end();
