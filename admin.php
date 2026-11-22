<?php
require __DIR__ . '/inc/boot.php';
require_module('admin');
$t = g('t', 'options');

if (g('a') === 'backup') {
    audit('backup-download', 'settings');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="rh-backup-' . date('Ymd-His') . '.sqlite"');
    readfile(ROOT . '/data/rh.sqlite');
    exit;
}
if (is_post()) {
    if ($t === 'options') {
        $list = preg_replace('/[^a-z_]/', '', g('list'));
        if (p('add') !== '') { ins('options', ['list' => $list, 'value' => p('add'), 'sort' => 999, 'active' => 1]); audit('option-add', 'options', null, null, ['list' => $list, 'value' => p('add')]); flash('Option added.'); }
        foreach ((array)($_POST['val'] ?? []) as $id => $v) {
            $old = row('SELECT * FROM options WHERE id=?', [(int)$id]);
            $act = isset($_POST['on'][$id]) ? 1 : 0;
            if ($old && ($old['value'] !== trim($v) || (int)$old['active'] !== $act)) { upd('options', (int)$id, ['value' => trim($v), 'active' => $act, 'sort' => (int)($_POST['sort'][$id] ?? 0)]); audit('option-update', 'options', (int)$id, $old, ['value' => $v, 'active' => $act]); }
            elseif ($old) upd('options', (int)$id, ['sort' => (int)($_POST['sort'][$id] ?? 0)]);
        }
        flash('Options saved.');
        redirect('admin.php?t=options&list=' . $list);
    }
    if ($t === 'fields') {
        if (p('label') && entdef(p('entity'))) { $id = ins('custom_fields', ['entity' => p('entity'), 'label' => p('label'), 'type' => p('type'), 'options' => p('options'), 'required' => p('required') ? 1 : 0, 'active' => 1]); audit('create', 'custom_fields', $id, null, $_POST); flash('Custom field defined.'); }
        if (p('toggle')) { q('UPDATE custom_fields SET active=1-active WHERE id=?', [(int)p('toggle')]); flash('Custom field updated.'); }
        redirect('admin.php?t=fields');
    }
    if ($t === 'sounds') {
        $cfg = [];
        foreach (NOTIF_KINDS as $k => $d) $cfg[$k] = ['on' => !empty($_POST['on'][$k]), 'sound' => isset(NOTIF_SOUNDS[$_POST['sound'][$k] ?? '']) ? $_POST['sound'][$k] : $d[1], 'icon' => preg_replace('/[^a-z-]/', '', (string)($_POST['icon'][$k] ?? $d[2])), 'voice' => !empty($_POST['voice'][$k])];
        set_setting('notif_config', json_encode($cfg));
        audit('notification-settings', 'settings', null, null, $cfg);
        $vm = [];
        foreach (VOICE_MSGS as $k => $d) { $v = trim(mb_substr((string)($_POST['msg'][$k] ?? ''), 0, 300)); if ($v !== '' && $v !== $d[2]) $vm[$k] = $v; }
        $oldVm = json_decode((string)setting('voice_msgs', ''), true) ?: [];
        if ($vm != $oldVm) { set_setting('voice_msgs', json_encode($vm)); audit('voice-messages', 'settings', null, $oldVm, $vm); }
        $vs = p('voice_style') === '' ? voice_style() : (int)p('voice_style');
        if (isset(VOICE_STYLES[$vs]) && $vs !== voice_style()) { audit('voice-style', 'settings', null, ['voice_style' => voice_style()], ['voice_style' => $vs]); set_setting('voice_style', (string)$vs); }
        $vt = p('voice_tail') ? '1' : '0';
        if ($vt !== setting('voice_tail', '1')) { set_setting('voice_tail', $vt); audit('voice-tail', 'settings', null, null, ['voice_tail' => $vt]); }
        flash('Notification sounds and icons saved. Everyone hears the new settings from their next alert.');
        redirect('admin.php?t=sounds');
    }
    if ($t === 'office') {
        $nets = office_networks();
        $do = p('do');
        if ($do === 'add') {
            $ip = trim(p('ip')); $label = mb_substr(trim(p('label')), 0, 60);
            if (!ip_rule_valid($ip)) flash('Enter a valid IP address (for example 203.0.113.7) or a range (for example 203.0.113.0/24).', 'bad');
            elseif (in_array($ip, array_column($nets, 'ip'), true)) flash('This address is already in the list.', 'warn');
            else { $nets[] = ['ip' => $ip, 'label' => $label ?: 'Office', 'by' => me()['name'], 'at' => now()]; set_setting('office_networks', json_encode($nets)); audit('office-network-add', 'settings', null, null, ['ip' => $ip, 'label' => $label]); flash('Office network added: ' . e($label ?: $ip) . '.'); }
        } elseif ($do === 'remove') {
            $ip = p('ip'); $left = array_values(array_filter($nets, function ($n) use ($ip) { return $n['ip'] !== $ip; }));
            set_setting('office_networks', json_encode($left)); audit('office-network-remove', 'settings', null, ['ip' => $ip], null);
            if (!$left && setting('office_lock') === '1') { set_setting('office_lock', '0'); flash('Network removed. The list is empty, so the office Wi-Fi rule was switched off.', 'warn'); } else flash('Network removed.');
        } elseif ($do === 'lock') {
            $on = p('on') === '1';
            if ($on && !$nets) flash('Add the office network first, then switch the rule on.', 'bad');
            else { set_setting('office_lock', $on ? '1' : '0'); audit('office-lock', 'settings', null, null, ['on' => $on]); flash($on ? 'Done. Staff can now check in and out only on the office Wi-Fi.' : 'The office Wi-Fi rule is off. Staff can check in from anywhere.'); }
        } elseif ($do === 'anywhere') {
            $ids = array_values(array_unique(array_map('intval', (array)($_POST['uid'] ?? []))));
            set_setting('office_anywhere', json_encode($ids)); audit('office-anywhere', 'settings', null, null, ['users' => $ids]); flash('Saved.');
        } elseif ($do === 'header') {
            $h = p('ip_header'); if (!in_array($h, ['', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'], true)) $h = '';
            set_setting('ip_header', $h); audit('ip-header', 'settings', null, null, ['header' => $h]); flash('Saved.');
        }
        redirect('admin.php?t=office');
    }
    if ($t === 'chat') {
        if (p('revoke')) { q('UPDATE chat_access SET archived=1, updated_at=? WHERE id=?', [now(), (int)p('revoke')]); audit('chat-access-revoke', 'chat_access', (int)p('revoke')); flash('Chat access removed.'); }
        elseif ((int)p('user_id')) {
            $cand = (int)p('candidate_id') ?: null;
            $id = ins('chat_access', ['user_id' => (int)p('user_id'), 'candidate_id' => $cand, 'note' => p('note'), 'granted_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
            audit('chat-access-grant', 'chat_access', $id, null, ['user' => user_name((int)p('user_id')), 'candidate' => $cand ? ent_title('candidates', $cand) : 'ALL chats'], p('note'));
            flash('Chat access granted to ' . e(user_name((int)p('user_id'))) . '.');
        }
        redirect('admin.php?t=chat');
    }
    if ($t === 'archive') {
        $d = entdef(p('ent'));
        if ($d) { q('UPDATE ' . $d['table'] . ' SET archived=0, updated_at=? WHERE id=?', [now(), (int)p('id')]); audit('restore', $d['table'], (int)p('id')); flash($d['one'] . ' restored.'); }
        redirect('admin.php?t=archive&ent=' . urlencode(p('ent')));
    }
}

page_start('Admin Control Centre', 'admin');
page_head('Admin Control Centre', 'settings', 'Configure lists, custom fields, archive and restore, roles, settings and backups — no developer needed.');
$tabs = ['options' => 'Custom options', 'office' => 'Office network', 'fields' => 'Custom fields', 'archive' => 'Archive & restore', 'chat' => 'Chat access', 'sounds' => 'Notification sounds', 'roles' => 'Roles & permissions', 'settings' => 'System settings', 'backup' => 'Backup & health'];
$tabIcons = ['options' => 'list', 'office' => 'wifi', 'fields' => 'edit', 'archive' => 'archive', 'chat' => 'lock', 'sounds' => 'volume', 'roles' => 'shield', 'settings' => 'settings', 'backup' => 'download'];
echo '<nav class="tabs pro">';
foreach ($tabs as $k => $l) echo '<a class="' . ($k === $t ? 'on' : '') . '" href="' . ($k === 'roles' ? 'staff.php?t=roles' : ($k === 'settings' ? 'settings.php' : '?t=' . $k)) . '">' . icon($tabIcons[$k] ?? 'grid', 16) . '<span>' . e($l) . '</span></a>';
echo '</nav>';

if ($t === 'options') {
    $lists = array_unique(array_merge(array_keys($GLOBALS['OPT_DEFAULTS']), array_column(rows('SELECT DISTINCT list FROM options'), 'list')));
    sort($lists);
    $cur = g('list', 'positions');
    options($cur);
    echo '<div class="grid g3"><div class="card"><div class="card-h"><h3>Lists</h3></div><ul class="list-plain">';
    foreach ($lists as $l) echo '<li><a href="?t=options&list=' . e($l) . '"' . ($l === $cur ? ' style="font-weight:800"' : '') . '>' . e(ucwords(str_replace('_', ' ', $l))) . '</a><span class="count">' . (int)val('SELECT COUNT(*) FROM options WHERE list=? AND active=1', [$l]) . '</span></li>';
    echo '</ul></div><div class="card span2"><div class="card-h"><h3>' . e(ucwords(str_replace('_', ' ', $cur))) . '</h3><span class="muted small">Every dropdown also offers "Other" with a required detail field.</span></div><form method="post" action="?t=options&list=' . e($cur) . '">' . csrf_field() . '<table class="table compact"><thead><tr><th>Order</th><th>Value</th><th>Active</th></tr></thead><tbody>';
    foreach (rows('SELECT * FROM options WHERE list=? ORDER BY sort, value', [$cur]) as $o) echo '<tr><td style="width:80px"><input type="number" name="sort[' . $o['id'] . ']" value="' . (int)$o['sort'] . '"></td><td><input name="val[' . $o['id'] . ']" value="' . e($o['value']) . '"></td><td><input type="checkbox" name="on[' . $o['id'] . ']" value="1"' . ($o['active'] ? ' checked' : '') . '></td></tr>';
    echo '<tr><td></td><td><input name="add" placeholder="Add a new value…"></td><td></td></tr></tbody></table><div class="form-actions"><button class="btn">Save list</button></div></form></div></div>';
}
if ($t === 'fields') {
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>Define a custom field</h3></div><form method="post" action="?t=fields">' . csrf_field() . '<div class="grid-form"><div class="fld"><label>Record type *</label><select name="entity" required>';
    $ents = $GLOBALS['ENT'];
    uasort($ents, function ($a, $b) { return strcmp($a['label'], $b['label']); });
    foreach ($ents as $k => $d) echo '<option value="' . e($k) . '">' . e($d['label']) . '</option>';
    echo '</select></div><div class="fld"><label>Label *</label><input name="label" required></div><div class="fld"><label>Type</label><select name="type"><option>Text</option><option>Number</option><option>Date</option><option>Dropdown</option><option>Yes / No</option></select></div><div class="fld"><label>Options (for dropdown, comma separated)</label><input name="options"></div><div class="fld fld-check"><label class="check"><input type="checkbox" name="required" value="1"> Required</label></div></div><div class="form-actions"><button class="btn">Add field</button></div></form>'
        . '<p class="muted small">Custom fields are defined and stored here. Automatic rendering of custom fields inside every form is a listed future extension (spec Section 23); the "Other" option covers unclassified values today.</p></div>';
    $cf = rows('SELECT * FROM custom_fields ORDER BY entity, label');
    echo '<div class="card"><div class="card-h"><h3>Defined fields</h3></div>' . simple_table(['Record type', 'Label', 'Type', 'Required', ''], array_map(function ($f) { return [e(entdef($f['entity'])['label'] ?? $f['entity']), '<b>' . e($f['label']) . '</b>', e($f['type']), $f['required'] ? 'Yes' : 'No', '<form method="post" action="?t=fields">' . csrf_field() . '<input type="hidden" name="toggle" value="' . $f['id'] . '"><button class="btn ghost sm">' . ($f['active'] ? 'Disable' : 'Enable') . '</button></form>']; }, $cf), 'No custom fields yet.') . '</div></div>';
}
if ($t === 'archive') {
    $ek = g('ent');
    echo '<form class="toolbar filters" method="get"><input type="hidden" name="t" value="archive"><select name="ent" data-autosubmit><option value="">— Choose a record type —</option>';
    $ents = $GLOBALS['ENT'];
    uasort($ents, function ($a, $b) { return strcmp($a['label'], $b['label']); });
    foreach ($ents as $k => $d) { $n = (int)val('SELECT COUNT(*) FROM ' . $d['table'] . ' WHERE archived=1'); echo '<option value="' . e($k) . '"' . ($k === $ek ? ' selected' : '') . '>' . e($d['label']) . ($n ? ' (' . $n . ' archived)' : '') . '</option>'; }
    echo '</select></form>';
    if ($ek && ($d = entdef($ek))) {
        $list = rows('SELECT * FROM ' . $d['table'] . ' WHERE archived=1 ORDER BY updated_at DESC LIMIT 200');
        card_open(icon('archive', 17) . ' Archived ' . e(strtolower($d['label'])));
        echo simple_table(['Record', 'Archived', 'By', ''], array_map(function ($r) use ($d, $ek) { return ['<b>' . e(ent_row_title($d, $r)) . '</b>', e(dtfmt($r['updated_at'])), e(user_name($r['updated_by'])), '<form method="post" action="?t=archive">' . csrf_field() . '<input type="hidden" name="ent" value="' . e($ek) . '"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn ghost sm">' . icon('refresh', 14) . ' Restore</button></form>']; }, $list), 'Nothing archived.');
        card_close();
    } else {
        $sum = [];
        foreach ($GLOBALS['ENT'] as $k => $d) { $n = (int)val('SELECT COUNT(*) FROM ' . $d['table'] . ' WHERE archived=1'); if ($n) $sum[] = ['<a href="?t=archive&ent=' . e($k) . '"><b>' . e($d['label']) . '</b></a>', $n]; }
        card_open(icon('archive', 17) . ' Archived records by type');
        echo simple_table(['Record type', 'Archived'], $sum, 'Nothing is archived.');
        card_close();
    }
}
if ($t === 'sounds') {
    $cfg = notif_config();
    $icons = ['bell', 'user-plus', 'folder', 'send', 'chat', 'target', 'check-circle', 'dollar', 'alert', 'activity', 'star', 'zap', 'heart', 'plane', 'briefcase', 'mail', 'clipboard', 'award'];
    echo '<div class="alert info">' . icon('volume', 15) . ' Choose which alerts ring, which are read aloud by a voice, the sound and the icon for each type. Staff can still mute their own computer with the speaker button in the top bar.</div>';
    echo '<form method="post" action="?t=sounds" class="card flush">' . csrf_field() . '<div class="table-wrap"><table class="table compact"><thead><tr><th>Alert type</th><th>On</th><th>Voice</th><th>Sound</th><th></th><th>Icon</th></tr></thead><tbody>';
    foreach ($cfg as $k => $c) {
        echo '<tr><td><span class="notif-ico">' . icon($c['icon'], 18) . '</span> <b>' . e($c['label']) . '</b></td><td><label class="switch"><input type="checkbox" name="on[' . $k . ']" value="1"' . ($c['on'] ? ' checked' : '') . '><i></i></label></td><td><label class="switch" title="Read the alert aloud"><input type="checkbox" name="voice[' . $k . ']" value="1"' . ($c['voice'] ? ' checked' : '') . '><i></i></label></td><td><select id="snd_' . $k . '" name="sound[' . $k . ']">';
        foreach (NOTIF_SOUNDS as $sv => $sl) echo '<option value="' . $sv . '"' . ($c['sound'] === $sv ? ' selected' : '') . '>' . e($sl) . '</option>';
        echo '</select></td><td><button type="button" class="btn ghost sm" data-test-sound="snd_' . $k . '">' . icon('volume', 14) . ' Test</button></td><td><select name="icon[' . $k . ']">';
        foreach ($icons as $ic) echo '<option value="' . $ic . '"' . ($c['icon'] === $ic ? ' selected' : '') . '>' . e(ucwords(str_replace('-', ' ', $ic))) . '</option>';
        echo '</select></td></tr>';
    }
    echo '</tbody></table></div>';
    $vm = json_decode((string)setting('voice_msgs', ''), true) ?: [];
    echo '<div class="card-h" style="padding:16px 18px 0"><h3>' . icon('volume', 17) . ' Voice style</h3></div><p class="muted small" style="padding:0 18px">Press <b>Listen</b> to hear each voice, choose one, then press Save. Everyone hears the chosen voice. The exact voice depends on the computer or phone; the name under each style shows the voice this device will use.</p><div class="voice-grid">';
    foreach (VOICE_STYLES as $n => $vs) echo '<label class="voice-opt' . ($n ? '' : ' rec') . '"><input type="radio" name="voice_style" value="' . $n . '"' . ($n === voice_style() ? ' checked' : '') . '><span class="vo-n">' . ($n ?: '★') . '</span><span class="vo-t"><b>' . e($vs[0]) . '</b><small>' . e($vs[1]) . '</small>' . ($n ? '<small class="vo-real" data-voice-name="' . $n . '"></small>' : '<small class="vo-real">Same sound on every computer and phone</small>') . '</span><button type="button" class="btn ghost sm" data-voice-test="' . $n . '">' . icon('volume', 14) . ' Listen</button></label>';
    echo '</div><label class="check" style="padding:0 18px 6px;display:flex"><input type="checkbox" name="voice_tail" value="1"' . (setting('voice_tail', '1') === '1' ? ' checked' : '') . '> With the recorded voice, also read the name and position after the recording (device voice)</label>';
    echo '<div class="card-h" style="padding:16px 18px 0"><h3>' . icon('volume', 17) . ' Spoken messages</h3></div><p class="muted small" style="padding:0 18px">The words shown in the alert and read aloud. Keep the words in curly brackets — they are replaced with the real name, position or amount. Leave a box empty to use the standard text.</p>'
        . '<div class="table-wrap"><table class="table compact"><thead><tr><th>Who hears it / when</th><th>Message</th><th></th></tr></thead><tbody>';
    foreach (VOICE_MSGS as $k => $d) {
        preg_match_all('/\{\w+\}/', $d[2], $ph);
        echo '<tr><td><b>' . e($d[0]) . '</b><br><small class="muted">Words: ' . e(implode(' ', $ph[0])) . '</small></td><td style="min-width:260px"><input id="vm_' . $k . '" name="msg[' . $k . ']" maxlength="300" value="' . e($vm[$k] ?? $d[2]) . '" placeholder="' . e($d[2]) . '"></td>'
            . '<td><button type="button" class="btn ghost sm" data-say-input="vm_' . $k . '" data-clip-src="' . (is_file(ROOT . '/assets/voice/' . $k . '.mp3') ? 'assets/voice/' . $k . '.mp3' : '') . '" data-tail-tpl="' . e(VOICE_TAILS[$k] ?? '') . '">' . icon('volume', 14) . ' Listen</button></td></tr>';
    }
    echo '</tbody></table></div><div class="form-actions" style="padding:0 18px 16px"><button class="btn">' . icon('check', 15) . ' Save alert settings</button></div></form>';
}
if ($t === 'office') {
    $nets = office_networks(); $on = office_lock_on(); $ip = client_ip(); $here = office_network_name($ip);
    echo '<div class="office-hero card ' . ($on ? 'on' : 'off') . '"><div class="oh-ico">' . icon('wifi', 30) . '</div><div class="oh-t"><small>Check-in / check-out rule</small><h2>' . ($on ? 'Office Wi-Fi only' : 'Allowed from anywhere') . '</h2><p class="muted">'
        . ($on ? 'Staff can check in and check out only when connected to one of the office networks below. Everything else in the system works from anywhere as normal.' : 'Switch this on to allow check-in and check-out only from the office Wi-Fi. Everything else keeps working from anywhere.') . '</p></div>'
        . '<form method="post" action="?t=office" data-confirm="' . ($on ? 'Allow check-in from anywhere again?' : 'Allow check-in only on the office Wi-Fi?') . '">' . csrf_field() . '<input type="hidden" name="do" value="lock"><input type="hidden" name="on" value="' . ($on ? '0' : '1') . '"><button class="btn big ' . ($on ? 'ghost' : '') . '"' . (!$nets && !$on ? ' disabled title="Add the office network first"' : '') . '>' . icon($on ? 'x' : 'lock', 16) . ' ' . ($on ? 'Switch off' : 'Switch on') . '</button></form></div>';
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('monitor', 17) . ' This computer right now</h3></div><div class="kv"><div class="hl"><span>Your internet address (IP)</span><b class="ref">' . e($ip ?: 'unknown') . '</b></div><div class="' . ($here ? 'good' : '') . '"><span>Office network?</span><b>' . ($here ? 'Yes · ' . e($here) : 'No') . '</b></div></div>';
    if (!$here && $ip) echo '<form method="post" action="?t=office" class="print-row">' . csrf_field() . '<input type="hidden" name="do" value="add"><input type="hidden" name="ip" value="' . e($ip) . '"><div class="fld" style="flex:2"><label>Name for this network</label><input name="label" maxlength="60" placeholder="For example: Main office Wi-Fi" required></div><button class="btn">' . icon('plus', 15) . ' Add this network</button></form>';
    echo '<p class="muted small" style="margin-top:12px">' . icon('help', 13) . ' Easiest way: open this page on a computer or phone that is connected to the office Wi-Fi, then press <b>Add this network</b>. The office internet address is saved automatically.</p></div>';
    echo '<div class="card"><div class="card-h"><h3>' . icon('plus', 17) . ' Add an address by hand</h3></div><form method="post" action="?t=office">' . csrf_field() . '<input type="hidden" name="do" value="add"><div class="grid-form"><div class="fld"><label>Name *</label><input name="label" maxlength="60" required placeholder="Branch office"></div><div class="fld"><label>IP address or range *</label><input name="ip" required placeholder="203.0.113.7 or 203.0.113.0/24"></div></div><div class="form-actions"><button class="btn">' . icon('plus', 15) . ' Add</button></div></form>'
        . '<p class="muted small">Use the office\'s public internet address (the one shown above when you are in the office). A range such as /24 covers a block of addresses, useful if the internet company changes the last number.</p></div></div>';
    $rows = [];
    foreach ($nets as $n) $rows[] = ['<b>' . e($n['label']) . '</b>' . (ip_match($ip, $n['ip']) ? ' ' . badge('You are here') : ''), '<span class="ref">' . e($n['ip']) . '</span>', '<small class="muted">' . e(($n['by'] ?? '') . ' · ' . dtfmt($n['at'] ?? '')) . '</small>',
        '<form method="post" action="?t=office" data-confirm="Remove ' . e($n['label']) . '? Staff on that network will no longer be able to check in (when the rule is on).">' . csrf_field() . '<input type="hidden" name="do" value="remove"><input type="hidden" name="ip" value="' . e($n['ip']) . '"><button class="btn ghost sm danger">' . icon('trash', 13) . ' Remove</button></form>'];
    card_open(icon('wifi', 17) . ' Office networks <span class="count">' . count($nets) . '</span>', '<span class="muted small">Change them any time, for example after moving office or changing the internet company.</span>');
    echo simple_table(['Name', 'Address / range', 'Added', ''], $rows, 'No office network yet. Add one above.');
    card_close();
    $any = office_anywhere_users();
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('users', 17) . ' Staff who may check in from anywhere</h3></div><p class="muted small">For field staff, drivers or people working outside. Everyone else follows the office Wi-Fi rule.</p><form method="post" action="?t=office">' . csrf_field() . '<input type="hidden" name="do" value="anywhere"><div class="check-grid">';
    foreach (attendance_users() as $s) echo '<label class="check"><input type="checkbox" name="uid[]" value="' . $s['id'] . '"' . (in_array((int)$s['id'], $any, true) ? ' checked' : '') . '> ' . e($s['name']) . '</label>';
    echo '</div><div class="form-actions"><button class="btn">' . icon('check', 15) . ' Save</button></div></form></div>';
    $blocked = rows("SELECT * FROM audit WHERE action='attendance-blocked' ORDER BY id DESC LIMIT 15");
    echo '<div class="card"><div class="card-h"><h3>' . icon('shield', 17) . ' Blocked attempts (outside the office)</h3></div>' . simple_table(['When', 'Staff', 'Address'], array_map(function ($b) { $v = json_decode((string)$b['new_v'], true) ?: []; return [e(dtfmt($b['at'])), e($b['actor']) . ' <small class="muted">' . e(($v['action'] ?? '') === 'out' ? 'check-out' : 'check-in') . '</small>', '<span class="ref">' . e($v['ip'] ?? $b['ip']) . '</span>']; }, $blocked), 'No blocked attempts.') . '</div></div>';
    $hdr = setting('ip_header', '');
    echo '<details class="card"><summary><b>' . icon('settings', 15) . ' Advanced: address detection</b> <span class="muted small">(only if the address above looks wrong)</span></summary><p class="muted small">Normally leave this on "Standard". If the website uses a protection/CDN service (for example Cloudflare), every visitor seems to come from the same address; then choose the header below that shows <i>your</i> real address.</p><form method="post" action="?t=office">' . csrf_field() . '<input type="hidden" name="do" value="header"><div class="check-grid">';
    foreach (['' => ['Standard (recommended)', $_SERVER['REMOTE_ADDR'] ?? ''], 'HTTP_CF_CONNECTING_IP' => ['Cloudflare', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''], 'HTTP_X_FORWARDED_FOR' => ['X-Forwarded-For', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''], 'HTTP_X_REAL_IP' => ['X-Real-IP', $_SERVER['HTTP_X_REAL_IP'] ?? '']] as $k => $v)
        echo '<label class="check"><input type="radio" name="ip_header" value="' . $k . '"' . ($hdr === $k ? ' checked' : '') . '> ' . e($v[0]) . ' — <span class="ref">' . e($v[1] ?: 'not sent') . '</span></label>';
    echo '</div><div class="form-actions"><button class="btn ghost">Save</button></div></form></details>';
}
if ($t === 'chat') {
    echo '<div class="alert info">' . icon('lock', 15) . ' Candidate chats are private. Only the assigned deployment officer and team can read them. You (Super Admin) can read any chat, and every view by someone other than the deployment team is written to the audit log. Give another staff member access below.</div><div class="grid g2">';
    echo '<div class="card"><div class="card-h"><h3>' . icon('plus', 17) . ' Give access</h3></div><form method="post" action="?t=chat">' . csrf_field() . '<div class="fld" style="margin-bottom:12px"><label>Staff member *</label><select name="user_id" required><option value="">— Select —</option>';
    foreach (staff_list() as $s) echo '<option value="' . $s['id'] . '">' . e($s['name']) . '</option>';
    echo '</select></div><div class="fld" style="margin-bottom:12px"><label>Which chat?</label><select name="candidate_id"><option value="">ALL candidate chats</option>';
    foreach (rows('SELECT id, ref_no, full_name FROM candidates WHERE archived=0 ORDER BY id DESC LIMIT 300') as $c) echo '<option value="' . $c['id'] . '">' . e($c['ref_no'] . ' · ' . $c['full_name']) . '</option>';
    echo '</select></div><div class="fld" style="margin-bottom:12px"><label>Reason</label><input name="note" placeholder="For example: supervising complaint"></div><button class="btn">Grant access</button></form></div>';
    $gr = rows('SELECT * FROM chat_access WHERE archived=0 ORDER BY id DESC');
    echo '<div class="card"><div class="card-h"><h3>' . icon('shield', 17) . ' Who has access <span class="count">' . count($gr) . '</span></h3></div>' . simple_table(['Staff member', 'Chat', 'Reason', 'Granted', ''], array_map(function ($g) { return ['<b>' . e(user_name($g['user_id'])) . '</b>', $g['candidate_id'] ? e(ent_title('candidates', $g['candidate_id'])) : '<span class="badge b-warn">ALL chats</span>', e($g['note']), e(dfmt($g['created_at'])) . '<br><small class="muted">by ' . e(user_name($g['granted_by'])) . '</small>', '<form method="post" action="?t=chat" data-confirm="Remove this access?">' . csrf_field() . '<input type="hidden" name="revoke" value="' . $g['id'] . '"><button class="btn ghost sm danger">Remove</button></form>']; }, $gr), 'Nobody has been given access. Only the deployment teams can read their chats.') . '</div></div>';
    $views = rows("SELECT * FROM audit WHERE action IN ('chat-view','chat-file-view','chat-denied') ORDER BY id DESC LIMIT 25");
    card_open(icon('eye', 17) . ' Recent chat views by people outside the deployment team');
    echo simple_table(['When', 'Who', 'What', 'Candidate'], array_map(function ($a) { return [e(dtfmt($a['at'])), e($a['actor']), badge($a['action'] === 'chat-denied' ? 'Denied' : 'Viewed'), e(ent_title('candidates', $a['rec_id']))]; }, $views), 'No such views yet.');
    card_close();
}
if ($t === 'backup') {
    $size = is_file(ROOT . '/data/rh.sqlite') ? filesize(ROOT . '/data/rh.sqlite') : 0;
    $up = 0;
    if (is_dir(ROOT . '/uploads')) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) $up += $f->getSize();
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('download', 17) . ' Backup</h3></div><p>A complete backup is <b>data/rh.sqlite</b> plus the <b>uploads/</b> folder. Nothing else holds business data.</p><div class="kv"><div><span>Database</span><b>' . round($size / 1024) . ' KB</b></div><div><span>Uploads</span><b>' . round($up / 1024) . ' KB</b></div></div><p style="margin-top:14px"><a class="btn" href="admin.php?a=backup">' . icon('download', 15) . ' Download database backup</a></p><p class="muted small">Download the uploads folder from the hosting File Manager. Backups are the owner\'s responsibility. Deleting rh.sqlite destroys all data and recreates a fresh demo database.</p></div>';
    $checks = ['PHP version ' . PHP_VERSION => version_compare(PHP_VERSION, '7.4', '>='), 'pdo_sqlite enabled' => extension_loaded('pdo_sqlite'), 'data/ writable' => is_writable(ROOT . '/data'), 'uploads/ writable' => is_writable(ROOT . '/uploads') || is_writable(ROOT), 'data/.htaccess present' => is_file(ROOT . '/data/.htaccess'), 'uploads/.htaccess present' => is_file(ROOT . '/uploads/.htaccess'), 'fileinfo extension' => extension_loaded('fileinfo')];
    echo '<div class="card"><div class="card-h"><h3>' . icon('activity', 17) . ' System health</h3></div><ul class="list-plain">';
    foreach ($checks as $l => $ok) echo '<li><span>' . e($l) . '</span>' . badge($ok ? 'OK' : 'Missing') . '</li>';
    echo '<li><span>Tables</span><b>' . db_table_count() . '</b></li><li><span>Seeded</span><b>' . e(dtfmt(setting('seeded'))) . '</b></li></ul></div></div>';
}
page_end();
