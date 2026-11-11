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

/** HTML of the conversation. $viewer: 'staff' or 'candidate'. */
function chat_messages_html($cid, $viewer)
{
    $ms = rows('SELECT * FROM chat_messages WHERE candidate_id=? AND archived=0 ORDER BY id', [$cid]);
    if (!$ms) return '<p class="muted">No messages yet. Say hello!</p>';
    $meId = $viewer === 'staff' ? (int)me()['id'] : 0;
    $h = '';
    foreach ($ms as $m) {
        $mine = $viewer === 'candidate' ? $m['sender_type'] === 'Candidate' : ($m['sender_type'] === 'Staff' && (int)$m['sender_id'] === $meId);
        if ($m['sender_type'] === 'Candidate') $name = $viewer === 'candidate' ? 'You' : ent_title('candidates', $cid);
        else $name = ($viewer === 'staff' && $mine ? 'You' : user_name($m['sender_id'])) . ' · Deployment team';
        $seen = $mine ? ($m['read_at'] ? ' · Seen' : ' · Sent') : '';
        $h .= '<div class="msg' . ($mine ? ' me' : '') . '"><small class="who">' . e($name) . '</small>' . nl2br(e($m['body'])) . ($m['file'] ? '<div>' . file_link($m['file'], 'Attachment') . '</div>' : '') . '<small>' . e(dtfmt($m['created_at'])) . $seen . '</small></div>';
    }
    return $h;
}
