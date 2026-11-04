<?php
require __DIR__ . '/inc/boot.php';
require_login();
$u = me();
$with = (int)g('with');
if (is_post() && $with && p('body') !== '') {
    ins('staff_messages', ['from_user' => $u['id'], 'to_user' => $with, 'body' => p('body'), 'is_read' => 0, 'at' => now()]);
    notify($with, 'New message from ' . $u['name'], 'messenger.php?with=' . $u['id']);
    redirect('messenger.php?with=' . $with);
}
if ($with) q('UPDATE staff_messages SET is_read=1 WHERE to_user=? AND from_user=?', [$u['id'], $with]);
page_start('Messenger', 'messenger');
page_head('Messenger', 'message', 'Internal staff messaging with full history.');
echo '<div class="card chat"><div class="chat-list">';
foreach (staff_list() as $s) {
    if ((int)$s['id'] === (int)$u['id']) continue;
    $unread = (int)val('SELECT COUNT(*) FROM staff_messages WHERE to_user=? AND from_user=? AND is_read=0', [$u['id'], $s['id']]);
    $last = row('SELECT * FROM staff_messages WHERE (from_user=? AND to_user=?) OR (from_user=? AND to_user=?) ORDER BY id DESC LIMIT 1', [$u['id'], $s['id'], $s['id'], $u['id']]);
    echo '<a class="' . ($with === (int)$s['id'] ? 'on' : '') . '" href="?with=' . $s['id'] . '"><span class="avatar">' . e(strtoupper(substr($s['name'], 0, 1))) . '</span><span style="flex:1;min-width:0"><b style="color:var(--ink)">' . e($s['name']) . '</b><br><small class="muted">' . e($last ? mb_strimwidth($last['body'], 0, 32, '…') : '@' . $s['username']) . '</small></span>' . ($unread ? '<span class="count">' . $unread . '</span>' : '') . '</a>';
}
echo '</div><div class="chat-body">';
if ($with) {
    echo '<div class="card-h" style="padding:14px 16px 0"><h3>' . e(user_name($with)) . '</h3></div><div class="msgs" id="msgs">';
    $msgs = rows('SELECT * FROM staff_messages WHERE (from_user=? AND to_user=?) OR (from_user=? AND to_user=?) ORDER BY id DESC LIMIT 200', [$u['id'], $with, $with, $u['id']]);
    foreach (array_reverse($msgs) as $m) echo '<div class="msg' . ((int)$m['from_user'] === (int)$u['id'] ? ' me' : '') . '">' . nl2br(e($m['body'])) . '<small>' . e(dtfmt($m['at'])) . '</small></div>';
    if (!$msgs) echo '<p class="muted">No messages yet. Say hello!</p>';
    echo '</div><form method="post" class="chat-form">' . csrf_field() . '<input name="body" placeholder="Type a message…" required autocomplete="off"><button class="btn">' . icon('send', 15) . '</button></form><script>var m=document.getElementById("msgs");if(m)m.scrollTop=m.scrollHeight;</script>';
} else echo '<div class="empty big">' . icon('message', 36) . '<p>Choose a colleague to start a conversation.</p></div>';
echo '</div></div>';
page_end();
