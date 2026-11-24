<?php
// Call & Announce: the Super Admin calls a staff member to the office (loud alert + spoken name) or sends an announcement to staff.

ent('office_calls', ['label' => 'Office calls', 'one' => 'Office call', 'title' => 'to_user', 'icon' => 'bell', 'related' => false,
    'fields' => ['to_user' => ['Staff', 'user', 'req'], 'from_user' => ['Called by', 'user'], 'message' => ['Message', 'text'], 'status' => ['Status', 'enum:Waiting|Coming|Closed', 'default' => 'Waiting'], 'answered_at' => ['Answered', 'datetime']],
    'list' => ['created_at', 'to_user', 'message', 'status']]);
ent('announcements', ['label' => 'Announcements', 'one' => 'Announcement', 'title' => 'title', 'icon' => 'megaphone', 'related' => false,
    'fields' => ['kind' => ['Type', 'enum:Announcement|News|Task|Holiday|Urgent', 'default' => 'Announcement'], 'title' => ['Title', 'text', 'req'], 'body' => ['Message', 'textarea'],
        'audience' => ['Sent to', 'text'], 'need_ack' => ['Ask to confirm', 'bool'], 'recipients' => ['Recipients', 'int']],
    'list' => ['created_at', 'kind', 'title', 'audience']]);
ent('announcement_reads', ['label' => 'Announcement reads', 'one' => 'Read', 'title' => 'announcement_id', 'icon' => 'check', 'related' => false,
    'fields' => ['announcement_id' => ['Announcement', 'int', 'req'], 'user_id' => ['Staff', 'user', 'req'], 'read_at' => ['Read at', 'datetime']], 'list' => ['announcement_id', 'user_id', 'read_at']]);

const ANN_KINDS = ['Announcement' => ['megaphone', 'blue'], 'News' => ['activity', 'violet'], 'Task' => ['check-circle', 'amber'], 'Holiday' => ['calendar', 'green'], 'Urgent' => ['alert', 'red']];

/** Call a staff member to the office. */
function office_call($uid, $msg = '')
{
    $u = row('SELECT * FROM users WHERE id=? AND active=1', [$uid]);
    if (!$u) return 'Choose a staff member.';
    $id = ins('office_calls', ['to_user' => $uid, 'from_user' => me()['id'], 'message' => mb_substr(trim($msg), 0, 200) ?: null, 'status' => 'Waiting', 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
    notify($uid, explode(' ', $u['name'])[0] . ', please come to the office.' . ($msg ? ' ' . $msg : ''), 'news.php?call=' . $id, 'call');
    audit('office-call', 'office_calls', $id, null, ['to' => $u['name'], 'message' => $msg]);
    return (int)$id;
}
/** Send an announcement to a list of user ids. */
function announce_send($kind, $title, $body, array $uids, $audience, $needAck)
{
    $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
    if (!$uids) return 'Choose who receives it.';
    $id = ins('announcements', ['kind' => $kind, 'title' => $title, 'body' => $body ?: null, 'audience' => $audience, 'need_ack' => $needAck ? 1 : 0, 'recipients' => count($uids), 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
    foreach ($uids as $u) if ($u !== (int)me()['id']) notify($u, $kind . ': ' . $title, 'news.php?a=' . $id, 'announce');
    audit('announcement', 'announcements', $id, null, ['title' => $title, 'audience' => $audience, 'recipients' => count($uids)]);
    return (int)$id;
}
/** Things that must pop up for the signed-in person until answered: an office call and announcements to confirm. */
function big_alerts()
{
    $u = me(); if (!$u || is_super()) return [];
    $out = [];
    $c = row("SELECT * FROM office_calls WHERE to_user=? AND status='Waiting' AND archived=0 AND created_at>=? ORDER BY id DESC LIMIT 1", [$u['id'], date('Y-m-d H:i:s', time() - 4 * 3600)]);
    if ($c) $out[] = big_call_item($c);
    foreach (rows("SELECT a.* FROM announcements a WHERE a.archived=0 AND a.need_ack=1 AND a.created_at>=? AND EXISTS (SELECT 1 FROM notifications n WHERE n.user_id=? AND n.link=" . (db_is_mysql() ? "CONCAT('news.php?a=', a.id)" : "'news.php?a=' || a.id") . ") AND NOT EXISTS (SELECT 1 FROM announcement_reads r WHERE r.announcement_id=a.id AND r.user_id=?) ORDER BY a.id DESC LIMIT 3", [date('Y-m-d H:i:s', time() - 14 * 86400), $u['id'], $u['id']]) as $a) $out[] = big_ann_item($a);
    return $out;
}
function big_call_item($c)
{
    $name = user_name($c['to_user']);
    return ['type' => 'call', 'id' => (int)$c['id'], 'title' => 'Please come to the office', 'text' => ($c['message'] ? rtrim($c['message'], '. ') . ".\n" : '') . 'Called by ' . user_name($c['from_user']) . ' at ' . substr($c['created_at'], 11, 5) . '.',
        'seq' => [vname($name), vclip('office_call')], 'say' => explode(' ', $name)[0] . ', please come to the office. The management is calling you.', 'csrf' => csrf_token()];
}
function big_ann_item($a)
{
    return ['type' => 'ann', 'id' => (int)$a['id'], 'kind' => $a['kind'], 'title' => $a['title'], 'text' => (string)$a['body'], 'from' => user_name($a['created_by']), 'at' => dtfmt($a['created_at']),
        'seq' => [vclip('announce_new'), ['s' => $a['title']]], 'say' => 'Attention please. There is a new announcement from the management. ' . $a['title'], 'csrf' => csrf_token()];
}
/** The staff member answers a call ("I'm coming") or confirms an announcement ("Got it"). Returns JSON. */
function big_ack()
{
    header('Content-Type: application/json');
    $u = me(); $id = (int)p('id');
    if (p('type') === 'call') {
        $c = row('SELECT * FROM office_calls WHERE id=? AND to_user=?', [$id, $u['id']]);
        if ($c && $c['status'] === 'Waiting') {
            upd('office_calls', $id, ['status' => 'Coming', 'answered_at' => now(), 'updated_at' => now()]);
            notify($c['from_user'], $u['name'] . ' is on the way to your office.', 'office.php', 'call');
            audit('office-call-answer', 'office_calls', $id, null, ['status' => 'Coming']);
        }
    } elseif (p('type') === 'ann') {
        if ($id && !val('SELECT id FROM announcement_reads WHERE announcement_id=? AND user_id=?', [$id, $u['id']])) ins('announcement_reads', ['announcement_id' => $id, 'user_id' => $u['id'], 'read_at' => now(), 'created_at' => now(), 'archived' => 0]);
    }
    echo json_encode(['ok' => true]); exit;
}
function ann_targets($aud, $team, array $picked)
{
    $staff = attendance_users();
    if ($aud === 'team') return [array_column(array_filter($staff, function ($s) use ($team) { return (string)val('SELECT team FROM users WHERE id=?', [$s['id']]) === $team; }), 'id'), 'Team: ' . $team];
    if ($aud === 'some') { $ids = array_intersect(array_map('intval', $picked), array_map('intval', array_column($staff, 'id'))); return [$ids, count($ids) . ' selected staff']; }
    return [array_column($staff, 'id'), 'All staff'];
}

mod('office', ['label' => 'Call & Announce', 'icon' => 'megaphone', 'area' => 'Control', 'order' => 2,
    'desc' => 'Call any staff member to your office with one click — their screen shows a loud alert and the voice calls their name. Send news, tasks, holidays or announcements to all staff, a team or chosen people.',
    'tabs' => [
        'call' => ['label' => 'Call to office', 'icon' => 'bell', 'post' => function ($base, $mod) {
            if (!is_post()) return;
            if (p('do') === 'close') { q("UPDATE office_calls SET status='Closed', updated_at=? WHERE id=?", [now(), (int)p('id')]); redirect($base); }
            $r = office_call((int)p('uid'), p('message'));
            flash(is_string($r) ? $r : e(user_name((int)p('uid'))) . ' has been called. Their screen is ringing now.', is_string($r) ? 'bad' : 'ok'); redirect($base);
        }, 'fn' => function ($base, $mod) {
            echo '<form method="post" action="' . e($base) . '" class="card call-box">' . csrf_field() . '<div class="card-h"><h3>' . icon('bell', 17) . ' Who do you want to call?</h3></div>'
                . '<div class="fld" style="margin-bottom:14px"><label>Message (optional)</label><input name="message" maxlength="200" placeholder="For example: bring the Baku booking file"></div><div class="call-grid">';
            foreach (attendance_users() as $s) {
                $att = attendance_today($s['id']); $in = $att && !$att['check_out'];
                $role = (string)val('SELECT r.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?', [$s['id']]);
                echo '<button class="call-card" name="uid" value="' . (int)$s['id'] . '"><span class="avatar">' . e(strtoupper(substr($s['name'], 0, 1))) . '</span><span class="cc-t"><b>' . e($s['name']) . '</b><small>' . e($role) . '</small><small class="' . ($in ? 'cc-in' : 'cc-out') . '"><i></i>' . ($in ? 'In office since ' . e(substr($att['check_in'], 11, 5)) : ($att ? 'Checked out' : 'Not checked in')) . '</small></span><span class="cc-go">' . icon('bell', 18) . ' Call</span></button>';
            }
            echo '</div></form>';
            $calls = rows('SELECT * FROM office_calls WHERE archived=0 ORDER BY id DESC LIMIT 20');
            card_open(icon('clock', 17) . ' Recent calls');
            echo simple_table(['When', 'Staff', 'Message', 'Answer', ''], array_map(function ($c) use ($base) { return [e(dtfmt($c['created_at'])), '<b>' . e(user_name($c['to_user'])) . '</b>', e($c['message'] ?: '—'),
                $c['status'] === 'Coming' ? badge('Coming') . ' <small class="muted">' . e(substr((string)$c['answered_at'], 11, 5)) . '</small>' : ($c['status'] === 'Waiting' ? '<span class="badge b-warn">Waiting…</span>' : badge('Closed')),
                $c['status'] !== 'Closed' ? '<form method="post" action="' . e($base) . '">' . csrf_field() . '<input type="hidden" name="do" value="close"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="btn ghost sm">Close</button></form>' : '']; }, $calls), 'No calls yet.');
            card_close();
        }],
        'announce' => ['label' => 'Announcements', 'icon' => 'megaphone', 'post' => function ($base, $mod) {
            if (!is_post()) return;
            $kind = isset(ANN_KINDS[p('kind')]) ? p('kind') : 'Announcement'; $title = mb_substr(trim(p('title')), 0, 150);
            if ($title === '') { flash('Write a title.', 'bad'); redirect($base); }
            list($ids, $aud) = ann_targets(p('aud'), p('team'), (array)($_POST['uid'] ?? []));
            $r = announce_send($kind, $title, mb_substr(trim(p('body')), 0, 3000), $ids, $aud, p('need_ack') === '1');
            flash(is_string($r) ? $r : 'Sent to ' . e($aud) . ' (' . count($ids) . ' people).', is_string($r) ? 'bad' : 'ok'); redirect($base);
        }, 'fn' => function ($base, $mod) {
            echo '<form method="post" action="' . e($base) . '" class="card">' . csrf_field() . '<div class="card-h"><h3>' . icon('megaphone', 17) . ' New announcement</h3></div><div class="grid-form">'
                . '<div class="fld"><label>Type</label><select name="kind">' . implode('', array_map(function ($k) { return '<option>' . $k . '</option>'; }, array_keys(ANN_KINDS))) . '</select></div>'
                . '<div class="fld"><label>Title *</label><input name="title" required maxlength="150" placeholder="For example: Office closed on 2 December (National Day)"></div>'
                . '<div class="fld wide"><label>Message</label><textarea name="body" rows="3" maxlength="3000"></textarea></div>'
                . '<div class="fld wide"><label>Send to</label><div class="seg seg-aud"><label><input type="radio" name="aud" value="all" checked data-switch-aud> All staff</label><label><input type="radio" name="aud" value="team"> A team</label><label><input type="radio" name="aud" value="some"> Chosen staff</label></div></div>'
                . '<div class="fld" data-aud="team" hidden><label>Team</label><select name="team">' . implode('', array_map(function ($t) { return '<option>' . e($t) . '</option>'; }, options('staff_teams'))) . '</select></div>'
                . '<div class="fld wide" data-aud="some" hidden><label>Staff</label><div class="check-grid">' . implode('', array_map(function ($s) { return '<label class="check"><input type="checkbox" name="uid[]" value="' . (int)$s['id'] . '"> ' . e($s['name']) . '</label>'; }, attendance_users())) . '</div></div>'
                . '<div class="fld wide"><label class="check"><input type="checkbox" name="need_ack" value="1" checked> Show it on their screen until they press "Got it"</label></div></div>'
                . '<div class="form-actions"><button class="btn big">' . icon('send', 16) . ' Send announcement</button></div></form>';
            $list = rows('SELECT * FROM announcements WHERE archived=0 ORDER BY id DESC LIMIT 20');
            card_open(icon('clock', 17) . ' Sent announcements');
            foreach ($list as $a) {
                $reads = rows('SELECT user_id, read_at FROM announcement_reads WHERE announcement_id=? ORDER BY read_at', [$a['id']]);
                $k = ANN_KINDS[$a['kind']] ?? ANN_KINDS['Announcement'];
                echo '<details class="ann-row"><summary><span class="ann-ico t-' . $k[1] . '">' . icon($k[0], 16) . '</span><span class="ann-t"><b>' . e($a['title']) . '</b><small>' . e($a['kind']) . ' · ' . e($a['audience']) . ' · ' . e(dtfmt($a['created_at'])) . '</small></span>'
                    . ($a['need_ack'] ? '<span class="badge ' . (count($reads) >= (int)$a['recipients'] ? 'b-ok' : 'b-warn') . '">' . count($reads) . ' / ' . (int)$a['recipients'] . ' read</span>' : '') . '</summary>'
                    . ($a['body'] ? '<p>' . nl2br(e($a['body'])) . '</p>' : '') . ($reads ? '<p class="small muted">Read by: ' . e(implode(', ', array_map(function ($r) { return user_name($r['user_id']) . ' (' . substr($r['read_at'], 11, 5) . ')'; }, $reads))) . '</p>' : '') . '</details>';
            }
            if (!$list) echo '<p class="muted">Nothing sent yet.</p>';
            card_close();
        }],
    ]]);

mod('news', ['label' => 'Announcements', 'icon' => 'megaphone', 'area' => 'Home', 'order' => 5, 'desc' => 'News, tasks, holidays and announcements from the management.',
    'tabs' => ['list' => ['label' => 'Announcements', 'post' => function ($base, $mod) { if (is_post() && p('ack')) big_ack(); }, 'fn' => function ($base, $mod) {
        $u = me();
        if (is_super()) { echo '<div class="alert info">' . icon('megaphone', 15) . ' To send an announcement or call someone, open <a href="office.php?t=announce">Call &amp; Announce</a>.</div>'; }
        $where = is_super() ? '1=1' : "EXISTS (SELECT 1 FROM notifications n WHERE n.user_id=" . (int)$u['id'] . " AND n.link=" . (db_is_mysql() ? "CONCAT('news.php?a=', a.id)" : "'news.php?a=' || a.id") . ")";
        $list = rows("SELECT a.* FROM announcements a WHERE a.archived=0 AND $where ORDER BY a.id DESC LIMIT 50");
        if (!$list) { echo '<div class="card"><div class="empty">' . icon('megaphone', 30) . '<p>No announcements yet.</p></div></div>'; return; }
        echo '<div class="dr-list">';
        foreach ($list as $a) {
            $k = ANN_KINDS[$a['kind']] ?? ANN_KINDS['Announcement']; $read = (bool)val('SELECT id FROM announcement_reads WHERE announcement_id=? AND user_id=?', [$a['id'], $u['id']]);
            echo '<div class="card ann-card' . ((int)g('a') === (int)$a['id'] ? ' hl' : '') . '"><div class="ann-head"><span class="ann-ico t-' . $k[1] . '">' . icon($k[0], 18) . '</span><div><b>' . e($a['title']) . '</b><small>' . e($a['kind']) . ' · ' . e(user_name($a['created_by'])) . ' · ' . e(dtfmt($a['created_at'])) . '</small></div>'
                . (!is_super() ? ($read ? '<span class="badge b-ok">Read</span>' : '<button type="button" class="btn sm" data-ack-ann="' . (int)$a['id'] . '" data-csrf="' . e(csrf_token()) . '">' . icon('check', 14) . ' Got it</button>') : '') . '</div>' . ($a['body'] ? '<p>' . nl2br(e($a['body'])) . '</p>' : '') . '</div>';
        }
        echo '</div>';
    }]]]);
