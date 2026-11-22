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
    $cfg = notif_config();
    $tailOn = setting('voice_tail', '1') === '1';
    foreach (rows('SELECT id, kind, text, msg_key, say_tail FROM notifications WHERE user_id=? AND id>? ORDER BY id LIMIT 5', [$uid, $since]) as $n) {
        $c = $cfg[$n['kind']] ?? $cfg['system'];
        $full = trim(preg_replace('/\s*\([^)]*\)/', '', $n['text']));
        $clip = $c['on'] && $c['voice'] ? voice_clip($n['msg_key']) : '';
        $tail = (string)$n['say_tail'];
        // alerts without their own recording: the recorded voice says "You have a new notification", then the text is read
        if (!$clip && !$n['msg_key'] && $c['on'] && $c['voice'] && ($clip = voice_clip('general'))) $tail = $full;
        $out['items'][] = ['id' => (int)$n['id'], 'text' => $n['text'], 'link' => 'notifications.php?open=' . $n['id'], 'label' => $c['label'], 'sound' => $c['on'] ? $c['sound'] : 'none', 'quiet' => !$c['on'], 'icon' => icon($c['icon'], 18),
            'say' => $c['on'] && $c['voice'] ? ($clip ? ($tailOn ? $tail : '') : $full) : '', 'clip' => $clip, 'full' => $full];
    }
    $out['chat'] = chat_unread_total();
} elseif (!empty($_SESSION['portal']) && $_SESSION['portal']['type'] === 'candidate') {
    $cid = (int)$_SESSION['portal']['id'];
    $out['count'] = (int)val("SELECT COUNT(*) FROM chat_messages WHERE candidate_id=? AND sender_type='Staff' AND read_at IS NULL AND archived=0", [$cid]);
    $cc = notif_config()['chat'];
    foreach (rows("SELECT id, body FROM chat_messages WHERE candidate_id=? AND sender_type='Staff' AND id>? AND archived=0 ORDER BY id LIMIT 3", [$cid, $since]) as $m) {
        $loc = strpos((string)$m['body'], 'Interview location') === 0;
        $full = $loc ? 'You have a new interview invitation. Please check the location in your portal.' : 'You have a new message from your deployment team.';
        $clip = $cc['voice'] ? voice_clip($loc ? 'candidate_location' : 'candidate_chat') : '';
        $out['items'][] = ['id' => (int)$m['id'], 'text' => $loc ? 'New interview invitation from your deployment team' : 'New message from your deployment team', 'link' => 'portal.php', 'label' => $loc ? 'Interview' : 'New message', 'sound' => $cc['sound'], 'quiet' => false, 'icon' => icon($loc ? 'map-pin' : 'chat', 18), 'say' => $cc['voice'] && !$clip ? $full : '', 'clip' => $clip, 'full' => $full];
    }
}
echo json_encode($out);
