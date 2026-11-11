<?php
require __DIR__ . '/inc/boot.php';
require_module('staff');
$t = g('t', 'users');
$a = g('a');
$mods = array_filter($GLOBALS['MODS'], function ($m, $k) { return !in_array($k, PERSONAL_MODULES, true) && !in_array($k, SUPER_ONLY, true); }, ARRAY_FILTER_USE_BOTH);

if (is_post() && g('a') === 'resetlink') {
    $rid = (int)g('id');
    if (row('SELECT id FROM users WHERE id=?', [$rid])) {
        list($url, $em, $addr) = reset_send('staff', $rid, me()['id'], 'An administrator has sent you a password reset link for your RH Management account.');
        flash($em ? 'Reset link emailed to ' . e($addr) . '.' : 'No email was sent (' . ($addr ? e($GLOBALS['mail_error'] ?? 'email failed') : 'this staff member has no email address') . ').', $em ? 'ok' : 'warn');
        flash('Reset link (valid 60 minutes, you can share it): <a href="' . e($url) . '">' . e($url) . '</a>', 'info');
    }
    redirect('staff.php');
}
if (is_post()) {
    if ($t === 'users') {
        $id = (int)g('id');
        $d = ['name' => p('name'), 'username' => strtolower(p('username')), 'email' => p('email'), 'phone' => p('phone'), 'role_id' => (int)p('role_id'), 'active' => p('active') ? 1 : 0];
        if (!$d['name'] || !$d['username'] || !$d['role_id']) { flash('Name, username and role are required.', 'bad'); redirect('staff.php?a=' . ($id ? 'edit&id=' . $id : 'new')); }
        if (val('SELECT id FROM users WHERE lower(username)=? AND id<>?', [$d['username'], $id])) { flash('This username is already used.', 'bad'); redirect('staff.php?a=' . ($id ? 'edit&id=' . $id : 'new')); }
        $pw = (string)($_POST['password'] ?? '');
        if (!$id && $pw === '') { flash('Set a temporary password.', 'bad'); redirect('staff.php?a=new'); }
        if ($pw !== '') { if ($e = password_problem($pw)) { flash($e, 'bad'); redirect('staff.php?a=' . ($id ? 'edit&id=' . $id : 'new')); } $d['password_hash'] = password_hash($pw, PASSWORD_DEFAULT); $d['must_change'] = 1; }
        if ($id) { $old = row('SELECT * FROM users WHERE id=?', [$id]); if ((int)$id === (int)me()['id'] && !$d['active']) { flash('You cannot deactivate your own account.', 'bad'); redirect('staff.php'); } upd('users', $id, $d); unset($old['password_hash'], $d['password_hash']); audit('update', 'users', $id, $old, $d + ($pw !== '' ? ['password' => 'reset'] : [])); flash('Staff member updated.' . ($pw !== '' ? ' They must change the temporary password at next login.' : '')); }
        else { $d['created_at'] = now(); $id = ins('users', $d); unset($d['password_hash']); audit('create', 'users', $id, null, $d); flash('Staff member added. They must change the temporary password at first login.'); }
        redirect('staff.php');
    }
    if ($t === 'roles') {
        $id = (int)g('id');
        if ($a === 'duplicate') {
            $r = row('SELECT * FROM roles WHERE id=?', [$id]);
            $name = $r['name'] . ' (copy)';
            $n = 2;
            while (val('SELECT id FROM roles WHERE name=?', [$name])) $name = $r['name'] . ' (copy ' . $n++ . ')';
            $nid = ins('roles', ['name' => $name, 'description' => $r['description'], 'modules' => $r['modules'], 'actions' => $r['actions'], 'is_super' => 0, 'created_at' => now()]);
            audit('duplicate', 'roles', $nid, null, ['from' => $r['name']]);
            flash('Role duplicated with all its modules and actions. Adjust it below.');
            redirect('staff.php?t=roles&a=edit&id=' . $nid);
        }
        $name = p('name');
        if (!$name) { flash('Role name is required.', 'bad'); redirect('staff.php?t=roles'); }
        if (val('SELECT id FROM roles WHERE name=? AND id<>?', [$name, $id])) { flash('A role with this name exists.', 'bad'); redirect('staff.php?t=roles&a=edit&id=' . $id); }
        $mm = array_values(array_intersect(array_keys($mods), (array)($_POST['mods'] ?? [])));
        $acts = [];
        foreach ($mm as $m) $acts[$m] = array_values(array_intersect(array_keys(ACTIONS), (array)($_POST['acts'][$m] ?? [])));
        $d = ['name' => $name, 'description' => p('description'), 'modules' => json_encode($mm), 'actions' => json_encode((object)$acts)];
        if ($id) { $old = row('SELECT * FROM roles WHERE id=?', [$id]); if ($old['is_super']) { flash('The Super Admin role always has every module.', 'warn'); redirect('staff.php?t=roles'); } upd('roles', $id, $d); audit('permissions', 'roles', $id, ['modules' => $old['modules'], 'actions' => $old['actions']], $d); }
        else { $d['created_at'] = now(); $id = ins('roles', $d); audit('create', 'roles', $id, null, $d); }
        flash('Role saved. Permission changes are written to the audit log.');
        redirect('staff.php?t=roles');
    }
}

page_start('Staff & Roles', 'staff');
page_head('Staff & Roles', 'shield', 'Add staff, create roles, tick which modules each role may open and which actions it may perform.');
echo '<nav class="tabs"><a class="' . ($t === 'users' ? 'on' : '') . '" href="?t=users">Staff</a><a class="' . ($t === 'roles' ? 'on' : '') . '" href="?t=roles">Roles &amp; permissions</a></nav>';
$roles = rows('SELECT * FROM roles ORDER BY is_super DESC, name');

if ($t === 'users') {
    if ($a === 'new' || $a === 'edit') {
        $u = $a === 'edit' ? row('SELECT * FROM users WHERE id=?', [(int)g('id')]) : ['name' => '', 'username' => '', 'email' => '', 'phone' => '', 'role_id' => '', 'active' => 1];
        echo '<div class="card form-card"><div class="card-h"><h3>' . ($a === 'new' ? 'New staff member' : 'Edit ' . e($u['name'])) . '</h3><a class="btn ghost sm" href="staff.php">Cancel</a></div><form method="post" action="staff.php?t=users&id=' . (int)($u['id'] ?? 0) . '">' . csrf_field() . '<div class="grid-form">'
            . '<div class="fld"><label>Full name *</label><input name="name" required value="' . e($u['name']) . '"></div><div class="fld"><label>Username *</label><input name="username" required value="' . e($u['username']) . '"></div>'
            . '<div class="fld"><label>Email</label><input type="email" name="email" value="' . e($u['email']) . '"></div><div class="fld"><label>Phone</label><input name="phone" value="' . e($u['phone']) . '"></div>'
            . '<div class="fld"><label>Role *</label><select name="role_id" required><option value="">— Select —</option>';
        foreach ($roles as $r) echo '<option value="' . $r['id'] . '"' . ((string)$u['role_id'] === (string)$r['id'] ? ' selected' : '') . '>' . e($r['name']) . '</option>';
        echo '</select></div><div class="fld"><label>' . ($a === 'new' ? 'Temporary password *' : 'Reset password (optional)') . '</label><input type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters, letters and numbers"><small class="muted">The user must change it at next login. Passwords are never shown.</small></div>'
            . '<div class="fld fld-check"><label class="check"><input type="checkbox" name="active" value="1"' . ($u['active'] ? ' checked' : '') . '> Active</label></div></div><div class="form-actions"><button class="btn">Save</button></div></form>' . ($a === 'edit' ? '<form method="post" action="staff.php?a=resetlink&id=' . (int)$u['id'] . '" style="margin-top:10px">' . csrf_field() . '<button class="btn ghost">' . icon('mail', 15) . ' Email a password reset link</button></form>' : '') . '</div>';
    } else {
        echo '<div class="toolbar"><span></span><a class="btn" href="staff.php?a=new">' . icon('plus', 16) . ' Add New staff</a></div>';
        $us = rows('SELECT u.*, r.name role FROM users u LEFT JOIN roles r ON r.id=u.role_id ORDER BY u.active DESC, u.name');
        card_open('', '', 'flush');
        echo simple_table(['Name', 'Username', 'Role', 'Email', 'Last login', 'Status', 'Password'], array_map(function ($u) { return ['<b>' . e($u['name']) . '</b>', '@' . e($u['username']), e($u['role']), e($u['email']), e($u['last_login'] ? ago($u['last_login']) : 'Never'), ($u['active'] ? badge('Active') : badge('Inactive')) . ($u['must_change'] ? ' <span class="badge b-warn">Must change password</span>' : ''), '<form method="post" action="staff.php?a=resetlink&id=' . $u['id'] . '" data-confirm="Email a password reset link to ' . e($u['name']) . '?">' . csrf_field() . '<button class="btn ghost sm">' . icon('mail', 13) . ' Send reset link</button></form>']; }, $us), 'No staff.', array_map(function ($u) { return 'staff.php?a=edit&id=' . $u['id']; }, $us));
        card_close();
    }
} else {
    if ($a === 'edit' || $a === 'new') {
        $r = $a === 'edit' ? row('SELECT * FROM roles WHERE id=?', [(int)g('id')]) : ['id' => 0, 'name' => '', 'description' => '', 'modules' => '[]', 'actions' => '{}', 'is_super' => 0];
        $rm = json_decode($r['modules'], true) ?: [];
        $ra = json_decode($r['actions'], true) ?: [];
        echo '<form method="post" action="staff.php?t=roles&id=' . (int)$r['id'] . '" class="card">' . csrf_field() . '<div class="card-h"><h3>' . ($r['id'] ? 'Edit role: ' . e($r['name']) : 'New role') . '</h3><a class="btn ghost sm" href="staff.php?t=roles">Cancel</a></div>';
        if ($r['is_super']) echo '<div class="alert info">The Super Admin role always has every module, the Admin Control Centre and the full audit trail.</div>';
        echo '<div class="grid-form"><div class="fld"><label>Role name *</label><input name="name" required value="' . e($r['name']) . '"></div><div class="fld"><label>Description</label><input name="description" value="' . e($r['description']) . '"></div></div>'
            . '<p class="muted small" style="margin-top:14px">Personal screens (Dashboard, My Dashboard, Search, Tasks, Notifications, Messenger, Calendar, My HR) are available to every staff member. Admin Control Centre, Audit Report, Settings and Staff &amp; Roles are Super Admin only.</p>'
            . '<div class="table-wrap"><table class="table compact"><thead><tr><th>Module (screen access)</th>';
        foreach (ACTIONS as $k => $l) echo '<th style="text-align:center">' . e($l) . '</th>';
        echo '</tr></thead><tbody>';
        foreach (AREAS as $area) {
            $in = array_filter($mods, function ($m) use ($area) { return $m['area'] === $area; });
            if (!$in) continue;
            echo '<tr><td colspan="' . (count(ACTIONS) + 1) . '" style="background:var(--surface-2)"><b class="small">' . e(strtoupper($area)) . '</b></td></tr>';
            foreach ($in as $k => $m) {
                echo '<tr><td><label class="check"><input type="checkbox" name="mods[]" value="' . e($k) . '"' . (in_array($k, $rm, true) || $r['is_super'] ? ' checked' : '') . '> ' . icon($m['icon'], 15) . ' ' . e($m['label']) . '</label></td>';
                foreach (ACTIONS as $ak => $al) echo '<td style="text-align:center"><input type="checkbox" aria-label="' . e($m['label'] . ' ' . $al) . '" name="acts[' . e($k) . '][]" value="' . $ak . '"' . (in_array($ak, $ra[$k] ?? [], true) || $r['is_super'] ? ' checked' : '') . '></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div><div class="form-actions"><button class="btn">' . icon('check', 15) . ' Save role</button></div></form>';
    } else {
        echo '<div class="toolbar"><span class="muted small">Permissions are stored per role. Duplicate a role to create a variant.</span><a class="btn" href="staff.php?t=roles&a=new">' . icon('plus', 16) . ' Add New role</a></div><div class="grid g3">';
        foreach ($roles as $r) {
            $rm = json_decode($r['modules'], true) ?: [];
            $cnt = (int)val('SELECT COUNT(*) FROM users WHERE role_id=? AND active=1', [$r['id']]);
            echo '<div class="card"><div class="card-h"><h3>' . icon('shield', 16) . ' ' . e($r['name']) . '</h3>' . ($r['is_super'] ? badge('All access') : '<span class="count">' . count($rm) . ' modules</span>') . '</div><p class="small muted">' . e($r['description']) . '</p><p class="small">' . ($r['is_super'] ? 'Every module' : e(implode(', ', array_map('module_label', $rm)))) . '</p><p class="small muted">' . $cnt . ' active user' . ($cnt === 1 ? '' : 's') . '</p>'
                . '<div class="action-bar"><a class="btn ghost sm" href="staff.php?t=roles&a=edit&id=' . $r['id'] . '">' . icon('edit', 14) . ' Edit permissions</a><form method="post" action="staff.php?t=roles&a=duplicate&id=' . $r['id'] . '">' . csrf_field() . '<button class="btn ghost sm">' . icon('copy', 14) . ' Duplicate</button></form></div></div>';
        }
        echo '</div>';
    }
}
page_end();
