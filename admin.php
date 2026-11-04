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
    if ($t === 'archive') {
        $d = entdef(p('ent'));
        if ($d) { q('UPDATE ' . $d['table'] . ' SET archived=0, updated_at=? WHERE id=?', [now(), (int)p('id')]); audit('restore', $d['table'], (int)p('id')); flash($d['one'] . ' restored.'); }
        redirect('admin.php?t=archive&ent=' . urlencode(p('ent')));
    }
}

page_start('Admin Control Centre', 'admin');
page_head('Admin Control Centre', 'settings', 'Configure lists, custom fields, archive and restore, roles, settings and backups — no developer needed.');
$tabs = ['options' => 'Custom options', 'fields' => 'Custom fields', 'archive' => 'Archive & restore', 'roles' => 'Roles & permissions', 'settings' => 'System settings', 'backup' => 'Backup & health'];
echo '<nav class="tabs">';
foreach ($tabs as $k => $l) echo '<a class="' . ($k === $t ? 'on' : '') . '" href="' . ($k === 'roles' ? 'staff.php?t=roles' : ($k === 'settings' ? 'settings.php' : '?t=' . $k)) . '">' . e($l) . '</a>';
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
if ($t === 'backup') {
    $size = is_file(ROOT . '/data/rh.sqlite') ? filesize(ROOT . '/data/rh.sqlite') : 0;
    $up = 0;
    if (is_dir(ROOT . '/uploads')) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) $up += $f->getSize();
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('download', 17) . ' Backup</h3></div><p>A complete backup is <b>data/rh.sqlite</b> plus the <b>uploads/</b> folder. Nothing else holds business data.</p><div class="kv"><div><span>Database</span><b>' . round($size / 1024) . ' KB</b></div><div><span>Uploads</span><b>' . round($up / 1024) . ' KB</b></div></div><p style="margin-top:14px"><a class="btn" href="admin.php?a=backup">' . icon('download', 15) . ' Download database backup</a></p><p class="muted small">Download the uploads folder from the hosting File Manager. Backups are the owner\'s responsibility. Deleting rh.sqlite destroys all data and recreates a fresh demo database.</p></div>';
    $checks = ['PHP version ' . PHP_VERSION => version_compare(PHP_VERSION, '7.4', '>='), 'pdo_sqlite enabled' => extension_loaded('pdo_sqlite'), 'data/ writable' => is_writable(ROOT . '/data'), 'uploads/ writable' => is_writable(ROOT . '/uploads') || is_writable(ROOT), 'data/.htaccess present' => is_file(ROOT . '/data/.htaccess'), 'uploads/.htaccess present' => is_file(ROOT . '/uploads/.htaccess'), 'fileinfo extension' => extension_loaded('fileinfo')];
    echo '<div class="card"><div class="card-h"><h3>' . icon('activity', 17) . ' System health</h3></div><ul class="list-plain">';
    foreach ($checks as $l => $ok) echo '<li><span>' . e($l) . '</span>' . badge($ok ? 'OK' : 'Missing') . '</li>';
    echo '<li><span>Tables</span><b>' . (int)val("SELECT COUNT(*) FROM sqlite_master WHERE type='table'") . '</b></li><li><span>Seeded</span><b>' . e(dtfmt(setting('seeded'))) . '</b></li></ul></div></div>';
}
page_end();
