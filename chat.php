<?php
// Staff side of the private candidate chat.
require __DIR__ . '/inc/boot.php';
require_login();
$u = me();
$cid = (int)g('c');

if ($cid && g('frag')) {
    if (!chat_can($cid)) { http_response_code(403); exit; }
    chat_mark_read_staff($cid);
    echo chat_messages_html($cid, 'staff');
    exit;
}
if ($cid && is_post()) {
    if (!chat_can($cid)) deny('You do not have permission to open this chat. Only the assigned deployment team can, unless the Super Admin grants access.');
    $body = mb_substr(p('body'), 0, 4000);
    list($path, $err) = upload_file('file', 'chat');
    if ($err) flash($err, 'bad');
    elseif ($body === '' && !$path) flash('Write a message or attach a file.', 'bad');
    else { chat_add($cid, 'Staff', $u['id'], $body, $path); if (!chat_is_participant($cid)) audit('chat-send', 'candidates', $cid); }
    redirect('chat.php?c=' . $cid);
}
if ($cid && !chat_can($cid)) { audit('chat-denied', 'candidates', $cid); deny('You do not have permission to open this chat. Only the assigned deployment team can, unless the Super Admin grants access.'); }

page_start('Candidate Chat', 'chat');
page_head('Candidate Chat', 'chat', 'Private conversations between the deployment team and candidates. Other staff cannot read them unless the Super Admin gives permission.');
list($w, $p) = chat_scope();
$convs = rows("SELECT c.id, c.full_name, c.ref_no, c.stage,
    (SELECT MAX(id) FROM chat_messages m WHERE m.candidate_id=c.id AND m.archived=0) AS last_id,
    (SELECT COUNT(*) FROM chat_messages m WHERE m.candidate_id=c.id AND m.sender_type='Candidate' AND m.read_at IS NULL AND m.archived=0) AS unread
    FROM candidates c WHERE c.archived=0 AND $w AND (c.deploy_officer IS NOT NULL OR c.team_id IS NOT NULL OR EXISTS (SELECT 1 FROM chat_messages m2 WHERE m2.candidate_id=c.id))
    ORDER BY last_id IS NULL, last_id DESC LIMIT 200", $p);

echo '<div class="card chat"><div class="chat-list">';
if (!$convs) echo '<p class="muted pad">No conversations yet. A chat appears here when a candidate is assigned to you or your team.</p>';
foreach ($convs as $c) {
    $last = $c['last_id'] ? (string)val('SELECT body FROM chat_messages WHERE id=?', [$c['last_id']]) : '';
    $mine = in_array((int)$u['id'], chat_participants($c['id']), true);
    echo '<a class="' . ($cid === (int)$c['id'] ? 'on' : '') . '" href="?c=' . $c['id'] . '"><span class="avatar">' . e(strtoupper(substr($c['full_name'], 0, 1))) . '</span><span style="flex:1;min-width:0"><b style="color:var(--ink)">' . e($c['full_name']) . '</b><br><small class="muted">' . e($last !== '' ? mb_strimwidth($last, 0, 34, '…') : $c['ref_no'] . ' · ' . $c['stage']) . '</small></span>' . ($mine && $c['unread'] ? '<span class="count">' . (int)$c['unread'] . '</span>' : '') . '</a>';
}
echo '</div><div class="chat-body">';
if (!$cid) echo '<div class="empty big">' . icon('chat', 36) . '<p>Choose a candidate to open the conversation.</p></div>';
else {
    $c = row('SELECT * FROM candidates WHERE id=?', [$cid]);
    $parts = chat_participants($cid);
    $isPart = in_array((int)$u['id'], $parts, true);
    chat_mark_read_staff($cid);
    if (!$isPart && empty($_SESSION['chat_viewed_' . $cid])) { audit('chat-view', 'candidates', $cid, null, null, 'Opened by a non-participant with permission'); $_SESSION['chat_viewed_' . $cid] = 1; }
    echo '<div class="card-h" style="padding:14px 16px 0"><h3>' . e($c['full_name']) . ' <span class="muted small">' . e($c['ref_no']) . '</span></h3>' . (can('candidates') ? '<a class="btn ghost sm" href="' . e(ent_url('candidates', $cid)) . '">Open candidate</a>' : '') . '</div>'
        . '<p class="muted small" style="padding:0 16px;margin:4px 0 0">' . icon('lock', 13) . ' Private · Deployment team: ' . e($parts ? implode(', ', array_map('user_name', $parts)) : 'not assigned yet') . '</p>';
    if (!$isPart) echo '<div class="alert warn" style="margin:10px 16px 0">You are viewing this chat with special permission. Your access is recorded in the audit log.</div>';
    echo '<div class="msgs" id="msgs">' . chat_messages_html($cid, 'staff') . '</div>'
        . '<form method="post" enctype="multipart/form-data" class="chat-form" action="chat.php?c=' . $cid . '">' . csrf_field() . '<div style="flex:1"><textarea name="body" rows="2" placeholder="Type a message to the candidate…" maxlength="4000"></textarea><input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="margin-top:6px;font-size:12px"></div><button class="btn">' . icon('send', 15) . ' Send</button></form>'
        . '<script>(function(){var m=document.getElementById("msgs"),last=m.innerHTML;m.scrollTop=m.scrollHeight;setInterval(function(){if(document.hidden)return;fetch("chat.php?c=' . $cid . '&frag=1",{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.ok?r.text():null}).then(function(h){if(h!==null&&h!==last){var atEnd=m.scrollHeight-m.scrollTop-m.clientHeight<80;m.innerHTML=h;last=h;if(atEnd)m.scrollTop=m.scrollHeight}}).catch(function(){})},8000)})();</script>';
}
echo '</div></div>';
page_end();
