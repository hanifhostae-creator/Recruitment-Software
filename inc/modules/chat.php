<?php
// Private chat between the Deployment team and a candidate.
// Only the assigned Deployment officer and team can read it. Anyone else needs the Super Admin's permission
// (Admin Control Centre > Chat access). Every view by a non-participant is written to the audit log.

ent('chat_messages', ['label' => 'Chat Messages', 'one' => 'Chat message', 'title' => 'body', 'icon' => 'chat', 'related' => false,
    'fields' => ['candidate_id' => ['Candidate', 'ref:candidates', 'req', 'norel' => true], 'sender_type' => ['Sender', 'enum:Candidate|Staff', 'req'], 'sender_id' => ['Sender id', 'int'],
        'body' => ['Message', 'textarea'], 'file' => ['Attachment', 'file', 'dir' => 'chat'], 'read_at' => ['Read at', 'datetime']]]);
ent('chat_access', ['label' => 'Chat Access', 'one' => 'Chat access', 'title' => 'user_id', 'icon' => 'lock', 'related' => false,
    'fields' => ['user_id' => ['Staff member', 'user', 'req'], 'candidate_id' => ['Candidate (blank = all chats)', 'ref:candidates', 'norel' => true], 'note' => ['Reason', 'text'], 'granted_by' => ['Granted by', 'user']]]);

mod('chat', ['label' => 'Candidate Chat', 'icon' => 'chat', 'area' => 'Inside Hiring', 'page' => 'chat.php', 'order' => 3, 'desc' => 'Private chat between the Deployment team and candidates.']);

/** Staff who are part of the chat: the deployment officer and the team (leader and members). */
function chat_participants($cid)
{
    $c = row('SELECT deploy_officer, team_id FROM candidates WHERE id=?', [$cid]);
    $ids = [];
    if ($c && $c['deploy_officer']) $ids[] = (int)$c['deploy_officer'];
    if ($c && $c['team_id']) {
        $t = row('SELECT leader, members FROM deployment_teams WHERE id=?', [$c['team_id']]);
        if ($t) { $ids[] = (int)$t['leader']; foreach (explode(',', (string)$t['members']) as $m) if ((int)$m) $ids[] = (int)$m; }
    }
    return array_values(array_unique(array_filter($ids)));
}
/**
 * SQL condition (on candidates c) for the chats the current user may open.
 * $participantsOnly = only chats where the user is part of the deployment team (used for unread counters).
 */
function chat_scope($participantsOnly = false)
{
    $u = me();
    if (!$participantsOnly) {
        if (is_super()) return ['1=1', []];
        if (val('SELECT id FROM chat_access WHERE user_id=? AND archived=0 AND candidate_id IS NULL', [$u['id']])) return ['1=1', []];
    }
    $teams = array_column(rows("SELECT id FROM deployment_teams WHERE archived=0 AND (leader=? OR (','||COALESCE(members,'')||',') LIKE ?)", [$u['id'], '%,' . $u['id'] . ',%']), 'id');
    $w = ['c.deploy_officer=?'];
    $p = [$u['id']];
    if ($teams) $w[] = 'c.team_id IN (' . implode(',', array_map('intval', $teams)) . ')';
    if (!$participantsOnly) {
        $ids = array_column(rows('SELECT candidate_id FROM chat_access WHERE user_id=? AND archived=0 AND candidate_id IS NOT NULL', [$u['id']]), 'candidate_id');
        if ($ids) $w[] = 'c.id IN (' . implode(',', array_map('intval', $ids)) . ')';
    }
    return ['(' . implode(' OR ', $w) . ')', $p];
}
function chat_can($cid)
{
    if (!me()) return false;
    list($w, $p) = chat_scope();
    return (bool)val("SELECT c.id FROM candidates c WHERE c.id=? AND $w", array_merge([(int)$cid], $p));
}
function chat_is_participant($cid) { return me() && in_array((int)me()['id'], chat_participants($cid), true); }
function chat_menu_visible()
{
    static $v = null;
    if ($v === null) { list($w, $p) = chat_scope(); $v = (bool)val("SELECT c.id FROM candidates c WHERE c.archived=0 AND $w LIMIT 1", $p); }
    return $v;
}
/** Unread candidate messages in the chats of the current user's own deployment team. */
function chat_unread_total()
{
    if (!me()) return 0;
    list($w, $p) = chat_scope(true);
    return (int)val("SELECT COUNT(*) FROM chat_messages m JOIN candidates c ON c.id=m.candidate_id WHERE m.sender_type='Candidate' AND m.read_at IS NULL AND m.archived=0 AND $w", $p);
}
function chat_add($cid, $type, $senderId, $body, $file = null)
{
    $id = ins('chat_messages', ['candidate_id' => $cid, 'sender_type' => $type, 'sender_id' => $senderId, 'body' => $body, 'file' => $file, 'created_at' => now(), 'archived' => 0]);
    return $id;
}
function chat_mark_read_staff($cid) { if (chat_is_participant($cid)) q("UPDATE chat_messages SET read_at=? WHERE candidate_id=? AND sender_type='Candidate' AND read_at IS NULL", [now(), $cid]); }

/** Escape a chat message and make web links clickable. */
function chat_linkify($text)
{
    return preg_replace('~(https?://[^\s<]+[^\s<.,;:!?)\]])~i', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', nl2br(e((string)$text)));
}
/** HTML of the conversation. $viewer: 'staff' or 'candidate'. */
function chat_messages_html($cid, $viewer)
{
    $ms = rows('SELECT * FROM chat_messages WHERE candidate_id=? AND archived=0 ORDER BY id', [$cid]);
    if (!$ms) return $viewer === 'staff' ? '<div class="chat-start">' . icon('chat', 30) . '<b>Start the conversation with ' . e(ent_title('candidates', $cid)) . '</b><span>Say hello and explain the next step. Interview locations you send also appear here.</span></div>'
        : '<div class="chat-start">' . icon('chat', 30) . '<b>Your deployment team will write to you here</b><span>You can also send a message, a photo or your location.</span></div>';
    $meId = $viewer === 'staff' ? (int)me()['id'] : 0;
    $h = '';
    foreach ($ms as $m) {
        $mine = $viewer === 'candidate' ? $m['sender_type'] === 'Candidate' : ($m['sender_type'] === 'Staff' && (int)$m['sender_id'] === $meId);
        if ($m['sender_type'] === 'Candidate') $name = $viewer === 'candidate' ? 'You' : ent_title('candidates', $cid);
        else $name = ($viewer === 'staff' && $mine ? 'You' : user_name($m['sender_id'])) . ' · Deployment team';
        $seen = $mine ? ($m['read_at'] ? ' · Seen' : ' · Sent') : '';
        $img = $m['file'] && preg_match('/\.(jpe?g|png)$/i', $m['file']);
        $aud = $m['file'] && preg_match('/\.(webm|ogg|m4a|mp3)$/i', $m['file']);
        $att = $m['file'] ? ($aud ? '<div class="msg-voice">' . icon('mic', 16) . '<audio controls preload="metadata" src="file.php?f=' . e(rawurlencode($m['file'])) . '"></audio></div>' : ($img ? '<a class="msg-img" href="file.php?f=' . e(rawurlencode($m['file'])) . '" target="_blank" rel="noopener"><img src="file.php?f=' . e(rawurlencode($m['file'])) . '" alt="Photo" loading="lazy"></a>' : '<div>' . file_link($m['file'], 'Attachment') . '</div>')) : '';
        $h .= '<div class="msg' . ($mine ? ' me' : '') . '"><small class="who">' . e($name) . '</small>' . $att . chat_linkify($m['body']) . '<small>' . e(dtfmt($m['created_at'])) . $seen . '</small></div>';
    }
    return $h;
}

/** Message box like a phone messenger: attach, camera, location, text, send. */
function chat_composer($action, $placeholder = 'Type a message…')
{
    return '<form method="post" enctype="multipart/form-data" class="composer" action="' . e($action) . '" data-composer>' . csrf_field()
        . '<div class="comp-file" hidden><span data-comp-name></span><button type="button" class="link" data-comp-clear>Remove</button></div>'
        . '<div class="comp-row"><label class="comp-btn" title="Attach a document">' . icon('paperclip', 19) . '<input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-comp-file hidden></label>'
        . '<label class="comp-btn" title="Take a photo">' . icon('camera', 19) . '<input type="file" accept="image/*" capture="environment" data-comp-cam hidden></label>'
        . '<button type="button" class="comp-btn" title="Send my location" data-comp-loc>' . icon('map-pin', 19) . '</button>'
        . '<textarea name="body" rows="1" placeholder="' . e($placeholder) . '" maxlength="4000" data-comp-text></textarea>'
        . '<button type="button" class="comp-mic" title="Record a voice message" aria-label="Record a voice message" data-comp-mic>' . icon('mic', 19) . '</button>'
        . '<button class="comp-send" aria-label="Send">' . icon('send', 18) . '</button></div>'
        . '<div class="comp-rec" hidden><span class="rec-dot"></span><b data-rec-time>0:00</b><span class="rec-wave"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span><button type="button" class="btn ghost sm" data-rec-cancel>' . icon('trash', 14) . ' Cancel</button><button type="button" class="btn sm" data-rec-send>' . icon('send', 14) . ' Send voice</button></div></form>';
}
/** WhatsApp link for a phone number (UAE local numbers 05x become 9715x). */
function wa_link($num, $text = '')
{
    $d = preg_replace('/\D/', '', (string)$num);
    if ($d === '') return '';
    if (strpos($d, '00') === 0) $d = substr($d, 2);
    elseif ($d[0] === '0') $d = '971' . substr($d, 1);
    return 'https://wa.me/' . $d . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}
/** Candidate portal state: ['active' => bool, 'link' => valid registration link or null, 'expires' => …]. */
function portal_state($cid)
{
    $l = row('SELECT * FROM candidate_logins WHERE candidate_id=?', [$cid]);
    if ($l && $l['password_hash']) return ['active' => true, 'link' => null, 'expires' => null, 'last' => $l['last_login']];
    $ok = $l && $l['token'] && $l['token_expires'] >= now();
    return ['active' => false, 'link' => $ok ? portal_base_url() . 'portal.php?register=' . $l['token'] : null, 'expires' => $ok ? $l['token_expires'] : null, 'last' => null];
}
