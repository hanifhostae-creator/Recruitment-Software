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
if ($cid && is_post() && p('do') === 'sendlink') {
    if (!chat_can($cid) || !can('candidates', 'assign')) deny();
    $st = portal_state($cid);
    if ($st['active']) { flash('The candidate has already activated the portal.'); redirect('chat.php?c=' . $cid); }
    $link = candidate_portal_login($cid);
    $mail = candidate_welcome_email($cid, $link);
    audit('portal-link-sent', 'candidates', $cid);
    flash('Portal link ready (valid 7 days). ' . $mail . ' You can also send it by WhatsApp below.');
    redirect('chat.php?c=' . $cid);
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

echo '<div class="card chat' . ($cid && can('candidates') ? ' work' : '') . ($cid ? ' has-open' : '') . '"><div class="chat-list">';
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
    $st = portal_state($cid);
    $wa = wa_link($c['whatsapp'] ?: $c['phone']);
    echo '<div class="chat-head"><a class="ch-back" href="chat.php" aria-label="All chats">' . icon('arrow-left', 18) . '</a><span class="avatar">' . e(strtoupper(substr($c['full_name'], 0, 1))) . '</span><div class="ch-name"><b>' . e($c['full_name']) . '</b><small>' . e($c['applied_position']) . ' · ' . ($st['active'] ? '<span class="dot-ok"></span> Portal active' : '<span class="dot-wait"></span> Portal not active yet') . '</small></div><div class="ch-acts">'
        . ($wa ? '<a class="btn wa sm" href="' . e($wa) . '" target="_blank" rel="noopener" title="Chat on WhatsApp">' . icon('whatsapp', 15) . ' <span>WhatsApp</span></a>' : '')
        . '<a class="btn ghost sm" href="tel:' . e(preg_replace('/[^\d+]/', '', $c['phone'])) . '" title="Call">' . icon('phone', 15) . '</a>'
        . (can('candidates') ? '<a class="btn ghost sm hide-m" href="' . e(ent_url('candidates', $cid)) . '">Open candidate</a>' : '') . '</div></div>'
        . '<p class="muted small ch-team">' . icon('lock', 13) . ' Private · Deployment team: ' . e($parts ? implode(', ', array_map('user_name', $parts)) : 'not assigned yet') . '</p>';
    if (!$isPart) echo '<div class="alert warn" style="margin:10px 16px 0">You are viewing this chat with special permission. Your access is recorded in the audit log.</div>';
    if (!$st['active'] && can('candidates', 'assign')) {
        echo '<div class="portal-strip"><div>' . icon('link', 18) . ' <b>Portal not active.</b> Send the portal link so the candidate can sign up and chat here.' . ($st['expires'] ? ' <small class="muted">Current link valid until ' . e(dtfmt($st['expires'])) . '.</small>' : '') . '</div><div class="action-bar">';
        if ($st['link']) {
            $msg = 'Hello ' . $c['full_name'] . ', this is RH Management. Please open this link to activate your candidate portal and chat with your deployment team: ' . $st['link'];
            echo ($wa ? '<a class="btn wa sm" href="' . e(wa_link($c['whatsapp'] ?: $c['phone'], $msg)) . '" target="_blank" rel="noopener">' . icon('whatsapp', 14) . ' Send link by WhatsApp</a>' : '')
                . '<input id="plink" class="sr-only" readonly value="' . e($st['link']) . '"><button type="button" class="btn ghost sm" data-copy="plink">' . icon('copy', 14) . ' Copy link</button>';
        }
        echo '<form method="post" action="chat.php?c=' . $cid . '">' . csrf_field() . '<input type="hidden" name="do" value="sendlink"><button class="btn sm">' . icon('send', 14) . ' ' . ($st['link'] ? 'Email the link again' : 'Send portal link') . '</button></form></div></div>';
    }
    echo '<div class="msgs" id="msgs">' . chat_messages_html($cid, 'staff') . '</div>'
        . ($st['active'] ? chat_composer('chat.php?c=' . $cid, 'Type a message…') : '<div class="composer-off">' . icon('lock', 15) . ' Messages open as soon as the candidate activates the portal. Until then, use WhatsApp' . ($wa ? '' : ' or phone') . '.</div>')
        . '<script nonce="' . csp_nonce() . '">(function(){var m=document.getElementById("msgs"),last=m.innerHTML;m.scrollTop=m.scrollHeight;setInterval(function(){if(document.hidden)return;fetch("chat.php?c=' . $cid . '&frag=1",{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.ok?r.text():null}).then(function(h){if(h!==null&&h!==last){var atEnd=m.scrollHeight-m.scrollTop-m.clientHeight<80;m.innerHTML=h;last=h;if(atEnd)m.scrollTop=m.scrollHeight}}).catch(function(){})},8000)})();</script>';
}
echo '</div>';
if ($cid && can('candidates') && function_exists('deployment_panel')) echo deployment_panel(row('SELECT * FROM candidates WHERE id=?', [$cid]));
echo '</div>';
page_end();
