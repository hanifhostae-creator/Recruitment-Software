<?php
// Generic entity engine: schema, list, form, detail page, actions, archive, CSV export/import.
// A module defines entities with ent() and lists them as tabs in mod(); the engine renders the screens.

$GLOBALS['ENT'] = [];
$GLOBALS['ENT_HOME'] = [];

/**
 * Define an entity. Field spec: 'key' => [Label, type, flags...]
 * Types: text textarea email phone url int number money date datetime time bool password file
 *        opt:list (configurable dropdown + Other) enum:A|B|C ref:entity user users bl (business line)
 * Flags: req, req_new, hide (not in forms), new_only, full, other (enum gets Other), default => v, help => text
 * Hooks: before_save(&$data,$old) => error|null, create($data) => id, after_save($id,$data,$old),
 *        view($row) top panels, view_after($row), actions => [key => [label, perm, show, form, do]],
 *        can_edit($row) => bool, cols => [Label => fn($row)], scope => fn() => [sql, params]
 */
function ent($key, array $d)
{
    $d += ['table' => $key, 'label' => ucwords(str_replace('_', ' ', $key)), 'one' => null, 'prefix' => null, 'bl' => false,
        'fields' => [], 'list' => null, 'title' => null, 'filters' => [], 'order' => 'id DESC', 'dupe' => [], 'financial' => false,
        'immutable' => false, 'log_view' => false, 'related' => true, 'actions' => [], 'cols' => [], 'icon' => 'file', 'search' => null];
    if (!$d['one']) $d['one'] = preg_replace('/(ie)?s$/', '', $d['label']) . (preg_match('/ies$/', $d['label']) ? 'y' : '');
    $fields = $d['fields'];
    if ($d['bl']) $fields['business_line'] = ['Business line', 'bl', 'req', 'default' => is_string($d['bl']) ? $d['bl'] : null];
    $f = [];
    foreach ($fields as $k => $spec) $f[$k] = field_norm($spec);
    $d['fields'] = $f;
    if (!$d['title']) $d['title'] = $d['prefix'] ? 'ref_no' : array_keys($f)[0];
    if (!$d['list']) $d['list'] = array_slice(array_keys(array_filter($f, function ($x) { return $x['type'] !== 'textarea'; })), 0, 5);
    if ($d['prefix'] && !in_array('ref_no', $d['list'], true)) array_unshift($d['list'], 'ref_no');
    if ($d['bl'] && !in_array('business_line', $d['filters'], true)) $d['filters'][] = 'business_line';
    $d['key'] = $key;
    $GLOBALS['ENT'][$key] = $d;
}
function field_norm($s)
{
    $f = ['label' => $s[0], 'type' => $s[1] ?? 'text'];
    foreach ($s as $i => $v) {
        if (is_int($i) && $i < 2) continue;
        if (is_int($i)) $f[$v] = true; else $f[$i] = $v;
    }
    if (strpos($f['type'], ':') !== false) { list($t, $arg) = explode(':', $f['type'], 2); $f['type'] = $t; $f['arg'] = $arg; }
    if ($f['type'] === 'enum') {
        $f['choices'] = explode('|', $f['arg']);
        if (!empty($f['other']) && !in_array('Other', $f['choices'], true)) $f['choices'][] = 'Other';
        if (in_array('Other', $f['choices'], true)) $f['other'] = true;
    }
    if ($f['type'] === 'opt') $f['other'] = true;
    return $f;
}
function entdef($key) { return $GLOBALS['ENT'][$key] ?? null; }
function ent_home($key) { return $GLOBALS['ENT_HOME'][$key] ?? null; }
function ent_url($key, $id) { $h = ent_home($key); return $h ? u($h['url'], ['a' => 'view', 'id' => $id]) : null; }

/** Generated, data-preserving schema for every entity. */
function ent_migrate()
{
    foreach ($GLOBALS['ENT'] as $d) {
        $t = $d['table'];
        db_ddl("CREATE TABLE IF NOT EXISTS $t (id INTEGER PRIMARY KEY AUTOINCREMENT, ref_no TEXT, created_by INTEGER, created_at TEXT, updated_by INTEGER, updated_at TEXT, archived INTEGER DEFAULT 0)");
        $have = db_columns($t);
        foreach ($d['fields'] as $k => $f) foreach (field_cols($k, $f) as $c => $type) {
            if (!in_array($c, $have, true)) { db_ddl("ALTER TABLE $t ADD COLUMN $c $type"); $have[] = $c; }
        }
        foreach ($d['extra_cols'] ?? [] as $c => $type) if (!in_array($c, $have, true)) { db_ddl("ALTER TABLE $t ADD COLUMN $c $type"); $have[] = $c; }
        if ($d['prefix']) db_ddl("CREATE UNIQUE INDEX IF NOT EXISTS ux_{$t}_ref ON $t(ref_no)");
        foreach ($d['fields'] as $k => $f) if ($f['type'] === 'ref' || $f['type'] === 'user') db_ddl("CREATE INDEX IF NOT EXISTS ix_{$t}_$k ON $t($k)");
    }
}
function field_cols($k, $f)
{
    switch ($f['type']) {
        case 'int': case 'ref': case 'user': case 'bool': return [$k => 'INTEGER'];
        case 'number': case 'money': return [$k => 'REAL'];
        case 'password': return ['password_hash' => 'TEXT'];
        case 'textarea': case 'url': case 'users': return [$k => 'LONGTEXT'];
        default: return !empty($f['other']) ? [$k => 'TEXT', $k . '_other' => 'TEXT'] : [$k => 'TEXT'];
    }
}

// ---------------------------------------------------------------- display helpers

/** Display title of a record (memoised). */
function ent_title($key, $id)
{
    static $m = [];
    if (!$id) return '';
    $k = $key . ':' . $id;
    if (isset($m[$k])) return $m[$k];
    $d = entdef($key);
    if (!$d) return '#' . $id;
    $r = row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]);
    return $m[$k] = $r ? ent_row_title($d, $r) : '#' . $id;
}
function ent_row_title($d, $r)
{
    $t = $r[$d['title']] ?? '';
    $f = $d['fields'][$d['title']] ?? null;
    if ($f && $f['type'] === 'ref') $t = ent_title($f['arg'], $t);
    if ($f && $f['type'] === 'user') $t = user_name($t);
    if ($t === '' || $t === null) $t = $r['ref_no'] ?: '#' . $r['id'];
    if ($d['title'] !== 'ref_no' && !empty($r['ref_no']) && !empty($d['title_ref'])) $t = $r['ref_no'] . ' · ' . $t;
    return (string)$t;
}
function ref_link($key, $id)
{
    if (!$id) return '';
    $url = ent_url($key, $id);
    $h = ent_home($key);
    $t = e(ent_title($key, $id));
    return $url && $h && can($h['mod']) ? '<a href="' . e($url) . '">' . $t . '</a>' : $t;
}
/** Display value of a field. */
function field_show($k, $f, $r, $plain = false)
{
    $v = $r[$k] ?? null;
    if ($f['type'] === 'password') return $plain ? '' : '<span class="muted">••••••</span>';
    if ($v === null || $v === '') return '';
    switch ($f['type']) {
        case 'money': return money($v);
        case 'number': return num($v);
        case 'bool': return $plain ? ($v ? 'Yes' : 'No') : ($v ? badge('Yes') : '<span class="muted">No</span>');
        case 'date': return dfmt($v);
        case 'datetime': return dtfmt($v);
        case 'ref': return $plain ? ent_title($f['arg'], $v) : ref_link($f['arg'], $v);
        case 'user': return e(user_name($v));
        case 'users': return $plain ? implode(', ', array_map('user_name', explode(',', $v))) : e(implode(', ', array_map('user_name', explode(',', $v))));
        case 'file': return $plain ? $v : file_link($v, 'Download');
        case 'url': return $plain ? $v : '<a href="' . e($v) . '" target="_blank" rel="noopener">' . e(strlen($v) > 40 ? substr($v, 0, 40) . '…' : $v) . '</a>';
        case 'email': return $plain ? $v : '<a href="mailto:' . e($v) . '">' . e($v) . '</a>';
        case 'textarea': return $plain ? $v : nl2br(e($v));
        case 'enum': case 'opt': case 'bl':
            $txt = $v === 'Other' && !empty($r[$k . '_other']) ? 'Other: ' . $r[$k . '_other'] : $v;
            if ($plain) return $txt;
            return preg_match('/(status|stage|result|response|outcome|priority|severity|decision)/', $k) ? badge($txt) : e($txt);
        default: return $plain ? $v : e($v);
    }
}

/** Form input for a field. */
function field_input($k, $f, $v, $name = null)
{
    $name = $name ?: $k;
    $req = !empty($f['req']) ? ' required' : '';
    $id = 'f_' . $name;
    $type = $f['type'];
    $other = '';
    switch ($type) {
        case 'textarea': $in = '<textarea id="' . $id . '" name="' . $name . '" rows="3"' . $req . '>' . e($v) . '</textarea>'; break;
        case 'int': $in = '<input id="' . $id . '" type="number" step="1" name="' . $name . '" value="' . e($v) . '"' . $req . '>'; break;
        case 'number': case 'money': $in = '<input id="' . $id . '" type="number" step="0.01" name="' . $name . '" value="' . e($v) . '"' . $req . '>'; break;
        case 'date': $in = '<input id="' . $id . '" type="date" name="' . $name . '" value="' . e($v ? substr($v, 0, 10) : '') . '"' . $req . '>'; break;
        case 'datetime': $in = '<input id="' . $id . '" type="datetime-local" name="' . $name . '" value="' . e($v ? str_replace(' ', 'T', substr($v, 0, 16)) : '') . '"' . $req . '>'; break;
        case 'time': $in = '<input id="' . $id . '" type="time" name="' . $name . '" value="' . e($v) . '"' . $req . '>'; break;
        case 'email': $in = '<input id="' . $id . '" type="email" name="' . $name . '" value="' . e($v) . '"' . $req . '>'; break;
        case 'phone': $in = '<input id="' . $id . '" type="tel" name="' . $name . '" value="' . e($v) . '"' . $req . '>'; break;
        case 'url': $in = '<input id="' . $id . '" type="url" name="' . $name . '" value="' . e($v) . '" placeholder="https://"' . $req . '>'; break;
        case 'password': $in = '<input id="' . $id . '" type="password" name="' . $name . '" autocomplete="new-password" placeholder="' . ($v ? 'Leave blank to keep the current password' : 'At least 10 characters') . '">'; break;
        case 'bool': return '<label class="check"><input type="hidden" name="' . $name . '" value="0"><input type="checkbox" name="' . $name . '" value="1"' . ($v ? ' checked' : '') . '> ' . e($f['label']) . '</label>';
        case 'file': $in = '<input id="' . $id . '" type="file" name="' . $name . '"' . (!empty($f['accept']) ? ' accept="' . e($f['accept']) . '"' : ' accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"') . ((!empty($f['req']) || !empty($f['req_new'])) && !$v ? ' required' : '') . '>' . ($v ? '<small class="muted">Current: ' . file_link($v, basename($v)) . '</small>' : '<small class="muted">PDF, DOC, DOCX, JPG or PNG · max 10 MB</small>'); break;
        case 'user':
            $ulist = staff_list($f['role'] ?? null);
            if ($v && !in_array((string)$v, array_map('strval', array_column($ulist, 'id')), true)) $ulist[] = ['id' => $v, 'name' => user_name($v)];
            $in = '<select id="' . $id . '" name="' . $name . '"' . $req . '><option value="">— Select —</option>';
            foreach ($ulist as $s) $in .= '<option value="' . $s['id'] . '"' . ((string)$v === (string)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
            $in .= '</select>'; break;
        case 'users':
            $sel = $v ? explode(',', $v) : [];
            $in = '<select id="' . $id . '" name="' . $name . '[]" multiple size="4">';
            foreach (staff_list() as $s) $in .= '<option value="' . $s['id'] . '"' . (in_array((string)$s['id'], $sel, true) ? ' selected' : '') . '>' . e($s['name']) . '</option>';
            $in .= '</select><small class="muted">Hold Ctrl / Cmd to select several.</small>'; break;
        case 'ref':
            $rd = entdef($f['arg']);
            $opts = rows('SELECT * FROM ' . $rd['table'] . ' WHERE archived=0 OR id=? ORDER BY id DESC LIMIT 500', [(int)$v]);
            $in = '<select id="' . $id . '" name="' . $name . '"' . $req . '><option value="">— Select ' . e($rd['one']) . ' —</option>';
            foreach ($opts as $o) $in .= '<option value="' . $o['id'] . '"' . ((string)$v === (string)$o['id'] ? ' selected' : '') . '>' . e(($o['ref_no'] && $rd['title'] !== 'ref_no' ? $o['ref_no'] . ' · ' : '') . ent_row_title($rd, $o)) . '</option>';
            $in .= '</select>'; break;
        case 'bl': case 'enum': case 'opt':
            $choices = $type === 'bl' ? options('business_lines') : ($type === 'enum' ? $f['choices'] : options($f['arg']));
            if ($type === 'opt' && !in_array('Other', $choices, true)) $choices[] = 'Other';
            if ($v !== null && $v !== '' && !in_array($v, $choices, true)) array_unshift($choices, $v);
            $in = '<select id="' . $id . '" name="' . $name . '"' . $req . (!empty($f['other']) ? ' data-other="' . $id . '_o"' : '') . '><option value="">— Select —</option>';
            foreach ($choices as $c) $in .= '<option' . ((string)$v === (string)$c ? ' selected' : '') . '>' . e($c) . '</option>';
            $in .= '</select>';
            if (!empty($f['other'])) {
                $ov = $GLOBALS['_form_row'][$k . '_other'] ?? '';
                $other = '<input class="other-detail' . ($v === 'Other' ? ' show' : '') . '" id="' . $id . '_o" name="' . $name . '_other" value="' . e($ov) . '" placeholder="Please specify (required for Other)">';
            }
            break;
        default: $in = '<input id="' . $id . '" type="text" name="' . $name . '" value="' . e($v) . '"' . $req . '>';
    }
    return '<label for="' . $id . '">' . e($f['label']) . (!empty($f['req']) || !empty($f['req_new']) ? ' <em>*</em>' : '') . '</label>' . $in . $other . (!empty($f['help']) ? '<small class="muted">' . e($f['help']) . '</small>' : '');
}

/** Read a field from POST into $data; adds messages to $errors. */
function field_from_post($k, $f, $old, array &$data, array &$errors, $isNew)
{
    $label = $f['label'];
    $type = $f['type'];
    if ($type === 'file') {
        list($path, $err) = upload_file($k, $f['dir'] ?? 'files', ($isNew && (!empty($f['req']) || !empty($f['req_new']))) || (!empty($f['req']) && empty($old[$k])));
        if ($err) $errors[] = $label . ': ' . $err;
        if ($path) $data[$k] = $path;
        return;
    }
    if ($type === 'password') {
        $pw = (string)($_POST[$k] ?? '');
        if ($pw === '' && $isNew) { $errors[] = $label . ' is required.'; return; }
        if ($pw !== '') { if ($pr = password_problem($pw)) $errors[] = $pr; else $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT); }
        return;
    }
    if ($type === 'users') { $v = isset($_POST[$k]) && is_array($_POST[$k]) ? implode(',', array_map('intval', $_POST[$k])) : ''; $data[$k] = $v; return; }
    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : null;
    if ($v === null && $type !== 'bool') return;
    switch ($type) {
        case 'bool': $v = !empty($_POST[$k]) ? 1 : 0; break;
        case 'int': case 'ref': case 'user': $v = $v === '' ? null : (int)$v; break;
        case 'number': case 'money': $v = $v === '' ? null : round((float)str_replace(',', '', $v), 2); break;
        case 'datetime': $v = $v === '' ? null : str_replace('T', ' ', $v) . (strlen($v) === 16 ? ':00' : ''); break;
        case 'email': if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[] = $label . ' is not a valid email address.'; break;
        case 'enum': if ($v !== '' && !in_array($v, $f['choices'], true)) $errors[] = $label . ' has an invalid value.'; break;
    }
    if (($v === null || $v === '') && (!empty($f['req']) || ($isNew && !empty($f['req_new'])))) $errors[] = $label . ' is required.';
    $data[$k] = $v === '' ? null : $v;
    if (!empty($f['other'])) {
        $o = trim((string)($_POST[$k . '_other'] ?? ''));
        if ($v === 'Other' && $o === '') $errors[] = $label . ': please specify the "Other" detail.';
        $data[$k . '_other'] = $v === 'Other' ? $o : null;
    }
}

// ---------------------------------------------------------------- controller

/** POST / export handling before any output. Returns true if handled. */
function crud_handle($key, $base, $mod, array $opt = [])
{
    $d = entdef($key);
    $a = g('a');
    if ($a === 'export') { require_action($mod, 'export'); crud_export($d, $mod, $opt); }
    if ($a === 'template') { require_action($mod, 'add'); csv_out($key . '-template.csv', crud_import_cols($d), [], $d['table']); }
    if (!is_post()) return false;
    $id = (int)g('id');
    switch ($a) {
        case 'save': crud_save($d, $base, $mod, $opt); return true;
        case 'act':
            $row = $id ? row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]) : null;
            $act = $d['actions'][g('act')] ?? null;
            if (!$row || !$act) { flash('Action not available.', 'bad'); redirect($base); }
            require_action($mod, $act['perm'] ?? 'edit');
            if (isset($act['show']) && !$act['show']($row)) { flash('This action is not available for this record.', 'bad'); redirect(u($base, ['a' => 'view', 'id' => $id])); }
            $res = $act['do']($row, $mod);
            if (is_string($res)) { flash($res, 'bad'); $_SESSION['form_old'] = $_POST; redirect(u($base, ['a' => 'view', 'id' => $id, 'act' => g('act')])); }
            flash(is_array($res) && isset($res['msg']) ? $res['msg'] : ($act['done'] ?? 'Saved.'));
            $back = (string)p('_back');
            if ($back !== '' && preg_match('#^[a-z_]+\.php(\?[A-Za-z0-9_=&%.-]*)?$#', $back)) redirect($back);
            redirect(is_array($res) && isset($res['go']) ? $res['go'] : u($base, ['a' => 'view', 'id' => $id]));
        case 'archive':
            require_action($mod, 'delete');
            $old = row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]);
            q('UPDATE ' . $d['table'] . ' SET archived=1, updated_at=?, updated_by=? WHERE id=?', [now(), me()['id'], $id]);
            audit('archive', $d['table'], $id, null, null, p('reason'));
            flash($d['one'] . ' archived. An admin can restore it.');
            redirect($base);
        case 'restore':
            if (!is_super() && !can($mod, 'delete')) deny();
            q('UPDATE ' . $d['table'] . ' SET archived=0, updated_at=? WHERE id=?', [now(), $id]);
            audit('restore', $d['table'], $id);
            flash($d['one'] . ' restored.');
            redirect(u($base, ['a' => 'view', 'id' => $id]));
        case 'purge':
            if (!is_super()) deny('Only the Super Admin can permanently delete.');
            if ($why = crud_purge_block($d, $id)) { flash($why, 'bad'); redirect(u($base, ['a' => 'view', 'id' => $id])); }
            $old = row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]);
            q('DELETE FROM ' . $d['table'] . ' WHERE id=?', [$id]);
            audit('delete', $d['table'], $id, $old, null, p('reason'));
            flash($d['one'] . ' permanently deleted.');
            redirect($base);
        case 'import': require_action($mod, 'add'); crud_import_preview($d, $base); return true;
        case 'import_commit': require_action($mod, 'add'); crud_import_commit($d, $base); return true;
        case 'saveview':
            ins('saved_views', ['user_id' => me()['id'], 'name' => p('name') ?: $d['label'], 'url' => p('url'), 'at' => now()]);
            flash('View saved. Find it under Reports > Saved views.');
            redirect(p('url'));
    }
    return false;
}

/** Why a record may not be hard-deleted (financial or legal links). */
function crud_purge_block($d, $id)
{
    if ($d['financial']) return 'Records with financial history cannot be permanently deleted. Archive it instead.';
    if (val('SELECT COUNT(*) FROM payments WHERE entity=? AND entity_id=?', [$d['table'], $id])) return 'This record has payments. Archive it instead.';
    foreach ($GLOBALS['ENT'] as $o) foreach ($o['fields'] as $k => $f) {
        if ($f['type'] === 'ref' && $f['arg'] === $d['key'] && val('SELECT COUNT(*) FROM ' . $o['table'] . " WHERE $k=?", [$id])) return 'Other records (' . $o['label'] . ') are linked to this one. Archive it instead.';
    }
    return null;
}

/** GET rendering. */
function crud_render($key, $base, $mod, array $opt = [])
{
    $d = entdef($key);
    $a = g('a', 'list');
    $id = (int)g('id');
    if ($a === 'new') { require_action($mod, 'add'); crud_form($d, $base, $mod, null, $opt); return; }
    if ($a === 'edit') {
        require_action($mod, 'edit');
        $r = row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]);
        if (!$r) { echo '<div class="empty">Record not found.</div>'; return; }
        if ($d['immutable'] || (isset($d['can_edit']) && !$d['can_edit']($r))) { echo '<div class="alert bad">This record is locked and cannot be edited. Use the actions on the record (for example a reversal) instead.</div>'; return; }
        crud_form($d, $base, $mod, $r, $opt);
        return;
    }
    if ($a === 'view') { crud_view($d, $base, $mod, $id, $opt); return; }
    if ($a === 'import') { require_action($mod, 'add'); crud_import_form($d, $base); return; }
    if ($a === 'preview') { require_action($mod, 'add'); crud_import_show($d, $base); return; }
    crud_list($d, $base, $mod, $opt);
}

function crud_where($d, $mod, array $opt)
{
    $w = ['archived=?'];
    $p = [g('archived') === '1' ? 1 : 0];
    foreach (['scope' => $opt['scope'] ?? null, 'escope' => $d['scope'] ?? null] as $sc) if ($sc) { list($sw, $sp) = $sc(); if ($sw) { $w[] = $sw; $p = array_merge($p, $sp); } }
    $qq = g('q');
    if ($qq !== '') {
        $cols = ['ref_no'];
        foreach ($d['fields'] as $k => $f) if (in_array($f['type'], ['text', 'email', 'phone', 'textarea', 'opt', 'enum'], true)) $cols[] = $k;
        $w[] = '(' . implode(' OR ', array_map(function ($c) { return "$c LIKE ?"; }, $cols)) . ')';
        foreach ($cols as $c) $p[] = '%' . $qq . '%';
    }
    foreach ($d['filters'] as $k) {
        $v = g('f_' . $k);
        if ($v !== '' && isset($d['fields'][$k])) { $w[] = "$k=?"; $p[] = $v; }
    }
    $dc = g('datecol');
    if (g('from') && ($dc === 'created_at' || (isset($d['fields'][$dc]) && in_array($d['fields'][$dc]['type'], ['date', 'datetime'], true)))) { $w[] = $dc . '>=?'; $p[] = g('from'); }
    return [implode(' AND ', $w), $p];
}

function crud_list($d, $base, $mod, array $opt)
{
    list($w, $p) = crud_where($d, $mod, $opt);
    $sort = g('sort');
    $order = $d['order'];
    if ($sort && ($sort === 'ref_no' || $sort === 'created_at' || isset($d['fields'][$sort]))) $order = $sort . (g('dir') === 'asc' ? ' ASC' : ' DESC');
    $total = (int)val('SELECT COUNT(*) FROM ' . $d['table'] . ' WHERE ' . $w, $p);
    $per = 25;
    $page = max(1, (int)g('page', 1));
    $data = rows('SELECT * FROM ' . $d['table'] . ' WHERE ' . $w . ' ORDER BY ' . $order . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $p);
    $cols = $opt['list'] ?? $d['list'];
    $keep = array_filter(['t' => g('t'), 'q' => g('q'), 'archived' => g('archived')] + array_combine(array_map(function ($k) { return 'f_' . $k; }, $d['filters']), array_map(function ($k) { return g('f_' . $k); }, $d['filters'])));

    if (!empty($opt['intro'])) echo $opt['intro'];
    if (isset($d['list_top'])) ($d['list_top'])($opt);
    echo '<div class="toolbar"><form class="filters" method="get">';
    foreach (array_filter(['t' => g('t')] + ($opt['keep'] ?? [])) as $hk => $hv) echo '<input type="hidden" name="' . e($hk) . '" value="' . e($hv) . '">';
    echo '<div class="search-in">' . icon('search', 16) . '<input name="q" value="' . e(g('q')) . '" placeholder="Search ' . e(strtolower($d['label'])) . '…"></div>';
    foreach ($d['filters'] as $k) {
        $f = $d['fields'][$k] ?? null;
        if (!$f) continue;
        $choices = [];
        if ($f['type'] === 'enum') $choices = array_combine($f['choices'], $f['choices']);
        elseif ($f['type'] === 'opt') $choices = array_combine(options($f['arg']), options($f['arg']));
        elseif ($f['type'] === 'bl') $choices = array_combine(options('business_lines'), options('business_lines'));
        elseif ($f['type'] === 'user') foreach (staff_list() as $s) $choices[$s['id']] = $s['name'];
        elseif ($f['type'] === 'ref') foreach (rows('SELECT * FROM ' . entdef($f['arg'])['table'] . ' WHERE archived=0 ORDER BY id DESC LIMIT 200') as $o) $choices[$o['id']] = ent_row_title(entdef($f['arg']), $o);
        elseif ($f['type'] === 'bool') $choices = ['1' => 'Yes', '0' => 'No'];
        echo '<select name="f_' . $k . '" data-autosubmit><option value="">' . e($f['label']) . ': all</option>';
        foreach ($choices as $cv => $cl) echo '<option value="' . e($cv) . '"' . ((string)g('f_' . $k) === (string)$cv ? ' selected' : '') . '>' . e($cl) . '</option>';
        echo '</select>';
    }
    if (can($mod, 'delete')) echo '<label class="check small"><input type="checkbox" name="archived" value="1" data-autosubmit' . (g('archived') === '1' ? ' checked' : '') . '> Archived</label>';
    echo '<button class="btn ghost sm">Filter</button></form><div class="tool-actions">';
    if (can($mod, 'export')) echo '<a class="btn ghost sm" href="' . e(u($base, ['a' => 'export'] + $keep)) . '">' . icon('download', 15) . ' Export</a>';
    if (can($mod, 'add') && empty($opt['noimport'])) echo '<a class="btn ghost sm" href="' . e(u($base, ['a' => 'import'])) . '">' . icon('upload', 15) . ' Import</a>';
    if (can($mod, 'add') && empty($opt['noadd'])) echo '<a class="btn" href="' . e(u($base, ['a' => 'new'] + ($opt['newparams'] ?? []))) . '">' . icon('plus', 16) . ' Add New</a>';
    echo '</div></div>';

    echo '<div class="card flush"><div class="table-wrap"><table class="table"><thead><tr>';
    foreach ($cols as $c) {
        $lbl = $c === 'ref_no' ? 'No.' : ($c === 'created_at' ? 'Created' : ($d['fields'][$c]['label'] ?? $c));
        $dir = g('sort') === $c && g('dir') !== 'asc' ? 'asc' : 'desc';
        echo '<th><a href="' . e(u($base, ['sort' => $c, 'dir' => $dir] + $keep)) . '">' . e($lbl) . (g('sort') === $c ? (g('dir') === 'asc' ? ' ↑' : ' ↓') : '') . '</a></th>';
    }
    foreach (($opt['cols'] ?? []) + $d['cols'] as $lbl => $fn) echo '<th>' . e($lbl) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($data as $r) {
        echo '<tr data-href="' . e(u($base, ['a' => 'view', 'id' => $r['id']])) . '">';
        foreach ($cols as $i => $c) {
            if ($c === 'ref_no') $v = '<b class="ref">' . e($r['ref_no']) . '</b>';
            elseif ($c === 'created_at') $v = '<span class="muted">' . e(dfmt($r['created_at'])) . '</span>';
            else $v = field_show($c, $d['fields'][$c], $r);
            if ($i === 0 || $c === $d['title']) $v = '<a class="row-link" href="' . e(u($base, ['a' => 'view', 'id' => $r['id']])) . '">' . ($v === '' ? '#' . $r['id'] : $v) . '</a>';
            echo '<td data-label="' . e($c === 'ref_no' ? 'No.' : ($d['fields'][$c]['label'] ?? $c)) . '">' . $v . '</td>';
        }
        foreach (($opt['cols'] ?? []) + $d['cols'] as $lbl => $fn) echo '<td data-label="' . e($lbl) . '">' . $fn($r) . '</td>';
        echo '</tr>';
    }
    if (!$data) echo '<tr><td colspan="' . (count($cols) + count(($opt['cols'] ?? []) + $d['cols'])) . '"><div class="empty">' . icon('inbox', 28) . '<p>No ' . e(strtolower($d['label'])) . ' found.</p>' . (can($mod, 'add') && empty($opt['noadd']) ? '<a class="btn sm" href="' . e(u($base, ['a' => 'new'])) . '">' . icon('plus', 14) . ' Add New</a>' : '') . '</div></td></tr>';
    echo '</tbody></table></div>';
    $pages = (int)ceil($total / $per);
    echo '<div class="table-foot"><span class="muted small">' . $total . ' record' . ($total === 1 ? '' : 's') . '</span>';
    if ($pages > 1) {
        echo '<div class="pager">';
        for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++) echo '<a class="' . ($i === $page ? 'on' : '') . '" href="' . e(u($base, ['page' => $i, 'sort' => g('sort'), 'dir' => g('dir')] + $keep)) . '">' . $i . '</a>';
        echo '</div>';
    }
    echo '<form method="post" action="' . e(u($base, ['a' => 'saveview'])) . '" class="saveview">' . csrf_field() . '<input type="hidden" name="url" value="' . e($_SERVER['REQUEST_URI'] ?? '') . '"><input name="name" placeholder="Name this view" aria-label="View name"><button class="btn ghost sm">Save view</button></form>';
    echo '</div></div>';
}

function crud_form($d, $base, $mod, $r, array $opt)
{
    $isNew = !$r;
    $old = $_SESSION['form_old'] ?? null;
    unset($_SESSION['form_old']);
    $vals = $old ?: ($r ?: []);
    $GLOBALS['_form_row'] = $vals;
    echo '<div class="card form-card"><div class="card-h"><h3>' . ($isNew ? 'New ' . e(strtolower($d['one'])) : 'Edit ' . e(ent_row_title($d, $r))) . '</h3>'
        . '<a class="btn ghost sm" href="' . e($isNew ? $base : u($base, ['a' => 'view', 'id' => $r['id']])) . '">Cancel</a></div>';
    echo '<form method="post" enctype="multipart/form-data" action="' . e(u($base, ['a' => 'save', 'id' => $r['id'] ?? null])) . '">' . csrf_field() . '<div class="grid-form">';
    foreach ($d['fields'] as $k => $f) {
        if (!empty($f['hide'])) continue;
        $v = array_key_exists($k, $vals) ? $vals[$k] : ($isNew ? (g($k) !== '' ? g($k) : ($f['default'] ?? null)) : null);
        if ($f['type'] === 'password') $v = $r ? 'x' : '';
        if (!$isNew && !empty($f['new_only'])) { echo '<div class="fld"><label>' . e($f['label']) . '</label><div class="ro">' . (field_show($k, $f, $r) ?: '—') . '</div></div>'; continue; }
        $wide = in_array($f['type'], ['textarea', 'users'], true) || !empty($f['full']);
        echo '<div class="fld' . ($wide ? ' wide' : '') . ($f['type'] === 'bool' ? ' fld-check' : '') . '">' . field_input($k, $f, $v) . '</div>';
    }
    if (isset($d['form_extra'])) ($d['form_extra'])($r);
    if ($isNew && !empty($_SESSION['dupe_warn'])) {
        echo '<div class="fld wide"><div class="alert warn">' . $_SESSION['dupe_warn'] . '<label class="check"><input type="checkbox" name="_dupe_ok" value="1"> I have checked — this is a different person / record. Create it anyway.</label></div></div>';
        unset($_SESSION['dupe_warn']);
    }
    echo '</div><div class="form-actions"><button class="btn">' . icon('check', 16) . ' ' . ($isNew ? 'Save ' . e(strtolower($d['one'])) : 'Save changes') . '</button></div></form></div>';
}

function crud_save($d, $base, $mod, array $opt)
{
    $id = (int)g('id');
    $isNew = !$id;
    require_action($mod, $isNew ? 'add' : 'edit');
    $old = $id ? row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]) : null;
    if ($id && !$old) { flash('Record not found.', 'bad'); redirect($base); }
    if ($old && ($d['immutable'] || (isset($d['can_edit']) && !$d['can_edit']($old)))) { flash('This record is locked.', 'bad'); redirect(u($base, ['a' => 'view', 'id' => $id])); }
    $data = [];
    $errors = [];
    foreach ($d['fields'] as $k => $f) {
        if (!empty($f['hide']) || (!$isNew && !empty($f['new_only']))) continue;
        field_from_post($k, $f, $old, $data, $errors, $isNew);
    }
    if ($isNew && !$errors && $d['dupe'] && empty($_POST['_dupe_ok'])) {
        $hits = [];
        foreach ($d['dupe'] as $k) if (!empty($data[$k])) {
            $m = row('SELECT * FROM ' . $d['table'] . " WHERE $k=? AND archived=0", [$data[$k]]);
            if ($m) $hits[] = e($d['fields'][$k]['label']) . ' matches <a href="' . e(u($base, ['a' => 'view', 'id' => $m['id']])) . '" target="_blank">' . e(($m['ref_no'] ? $m['ref_no'] . ' · ' : '') . ent_row_title($d, $m)) . '</a>';
        }
        if ($hits) { $_SESSION['dupe_warn'] = '<b>Possible duplicate found.</b> ' . implode('; ', $hits) . '.'; $errors[] = 'Duplicate check: please review the possible duplicate below.'; }
    }
    if (!$errors && isset($d['before_save'])) { $err = $d['before_save']($data, $old); if ($err) $errors[] = $err; }
    if ($errors) {
        foreach ($errors as $er) flash($er, 'bad');
        $_SESSION['form_old'] = $_POST;
        redirect(u($base, $isNew ? ['a' => 'new'] : ['a' => 'edit', 'id' => $id]));
    }
    $uid = me()['id'];
    if ($isNew) {
        if (isset($d['create'])) {
            $res = $d['create']($data);
            if (is_string($res)) { flash($res, 'bad'); $_SESSION['form_old'] = $_POST; redirect(u($base, ['a' => 'new'])); }
            $id = $res;
        } else {
            $data += ['created_by' => $uid, 'created_at' => now(), 'archived' => 0];
            if ($d['prefix']) $data['ref_no'] = next_number($d['prefix']);
            $id = ins($d['table'], $data);
            audit('create', $d['table'], $id, null, $data);
        }
    } else {
        $diffOld = []; $diffNew = [];
        foreach ($data as $k => $v) if ((string)($old[$k] ?? '') !== (string)$v) { $diffOld[$k] = $k === 'password_hash' ? '***' : ($old[$k] ?? null); $diffNew[$k] = $k === 'password_hash' ? '***' : $v; }
        $data['updated_by'] = $uid;
        $data['updated_at'] = now();
        upd($d['table'], $id, $data);
        if ($diffNew) audit('update', $d['table'], $id, $diffOld, $diffNew, p('_reason') ?: null);
    }
    if (isset($d['after_save'])) $d['after_save']($id, $data, $old);
    if ($isNew && isset($d['notify_new']) && function_exists('notify_super')) {
        $rec = row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]);
        $title = 'New ' . strtolower($d['one']) . ': ' . ($rec ? ent_row_title($d, $rec) : '#' . $id);
        if (!empty($d['notify_new'][0])) notify_role($d['notify_new'][0], $title, ent_url($d['key'], $id), $d['notify_new'][1] ?? 'lead');
        notify_super($title, ent_url($d['key'], $id), 'entry');
    }
    flash($d['one'] . ($isNew ? ' created.' : ' updated.'));
    redirect(u($base, ['a' => 'view', 'id' => $id]));
}

function crud_view($d, $base, $mod, $id, array $opt)
{
    $r = row('SELECT * FROM ' . $d['table'] . ' WHERE id=?', [$id]);
    if (!$r) { echo '<div class="empty">Record not found. <a href="' . e($base) . '">Back to list</a></div>'; return; }
    if ($d['log_view']) audit('view', $d['table'], $id);
    $locked = $d['immutable'] || (isset($d['can_edit']) && !$d['can_edit']($r));
    echo '<div class="detail-head"><div><a class="crumb" href="' . e($base) . '">' . icon('arrow-left', 14) . ' ' . e($d['label']) . '</a><h2>' . e(ent_row_title($d, $r)) . '</h2>'
        . '<div class="chips">' . ($r['ref_no'] ? '<span class="chip ref">' . e($r['ref_no']) . '</span>' : '') . ($r['archived'] ? badge('Archived') : '');
    foreach (['status', 'stage'] as $sk) if (!empty($r[$sk]) && isset($d['fields'][$sk])) echo badge($r[$sk]);
    if (!empty($r['business_line'])) echo '<span class="chip">' . e($r['business_line']) . '</span>';
    echo '</div></div><div class="action-bar">';
    if (!$locked && !$r['archived'] && can($mod, 'edit')) echo '<a class="btn ghost sm" href="' . e(u($base, ['a' => 'edit', 'id' => $id])) . '">' . icon('edit', 15) . ' Edit</a>';
    foreach ($d['actions'] as $ak => $act) {
        if (!empty($act['hidden']) || $r['archived'] || !can($mod, $act['perm'] ?? 'edit') || (isset($act['show']) && !$act['show']($r))) continue;
        echo '<a class="btn ' . (g('act') === $ak ? '' : 'ghost ') . 'sm" href="' . e(u($base, ['a' => 'view', 'id' => $id, 'act' => $ak])) . '">' . icon($act['icon'] ?? 'zap', 15) . ' ' . e($act['label']) . '</a>';
    }
    echo '</div></div>';

    $ak = g('act');
    if ($ak && isset($d['actions'][$ak]) && can($mod, $d['actions'][$ak]['perm'] ?? 'edit') && (!isset($d['actions'][$ak]['show']) || $d['actions'][$ak]['show']($r))) {
        $act = $d['actions'][$ak];
        $GLOBALS['_form_row'] = $_SESSION['form_old'] ?? [];
        unset($_SESSION['form_old']);
        echo '<div class="card action-card"><div class="card-h"><h3>' . icon($act['icon'] ?? 'zap', 18) . ' ' . e($act['label']) . '</h3><a class="btn ghost sm" href="' . e(u($base, ['a' => 'view', 'id' => $id])) . '">Close</a></div>'
            . '<form method="post" enctype="multipart/form-data" action="' . e(u($base, ['a' => 'act', 'id' => $id, 'act' => $ak])) . '"' . (!empty($act['confirm']) ? ' data-confirm="' . e($act['confirm']) . '"' : '') . '>' . csrf_field();
        $act['form']($r);
        echo '</form></div>';
    }

    if (isset($d['view'])) $d['view']($r, $mod, $base);
    echo '<div class="card"><div class="card-h"><h3>Details</h3></div><dl class="details">';
    foreach ($d['fields'] as $k => $f) {
        if ($f['type'] === 'password') continue;
        $v = field_show($k, $f, $r);
        echo '<div class="' . (in_array($f['type'], ['textarea'], true) ? 'wide' : '') . '"><dt>' . e($f['label']) . '</dt><dd>' . ($v === '' ? '<span class="muted">—</span>' : $v) . '</dd></div>';
    }
    echo '<div><dt>Created</dt><dd>' . e(dtfmt($r['created_at'])) . ($r['created_by'] ? ' · ' . e(user_name($r['created_by'])) : '') . '</dd></div>';
    if ($r['updated_at']) echo '<div><dt>Last updated</dt><dd>' . e(dtfmt($r['updated_at'])) . ($r['updated_by'] ? ' · ' . e(user_name($r['updated_by'])) : '') . '</dd></div>';
    echo '</dl></div>';
    if (isset($d['view_after'])) $d['view_after']($r, $mod, $base);

    if ($d['related']) crud_related($d, $r);

    $hist = rows('SELECT * FROM audit WHERE tbl=? AND rec_id=? ORDER BY id DESC LIMIT 30', [$d['table'], $id]);
    if ($hist) {
        echo '<details class="card history"><summary><h3>' . icon('clock', 16) . ' Change history (' . count($hist) . ')</h3></summary><ul class="timeline">';
        foreach ($hist as $h) {
            $chg = '';
            if ($h['new_v'] && $h['action'] === 'update') {
                $o = json_decode((string)$h['old_v'], true) ?: [];
                foreach ((json_decode($h['new_v'], true) ?: []) as $k => $v) $chg .= '<div class="small"><b>' . e($d['fields'][$k]['label'] ?? $k) . ':</b> <s class="muted">' . e(is_scalar($o[$k] ?? '') ? ($o[$k] ?? '') : '') . '</s> → ' . e(is_scalar($v) ? $v : json_encode($v)) . '</div>';
            }
            echo '<li><b>' . e(ucfirst($h['action'])) . '</b> <span class="muted">by ' . e($h['actor']) . ' · ' . e(dtfmt($h['at'])) . '</span>' . ($h['reason'] ? '<div class="small">Reason: ' . e($h['reason']) . '</div>' : '') . $chg . '</li>';
        }
        echo '</ul></details>';
    }

    if (can($mod, 'delete')) {
        echo '<div class="danger-zone">';
        if (!$r['archived']) echo '<form method="post" action="' . e(u($base, ['a' => 'archive', 'id' => $id])) . '" data-confirm="Archive this ' . e(strtolower($d['one'])) . '? It will be hidden from lists and can be restored by an admin.">' . csrf_field() . '<input name="reason" placeholder="Reason (optional)"><button class="btn ghost sm danger">' . icon('archive', 15) . ' Archive</button></form>';
        else echo '<form method="post" action="' . e(u($base, ['a' => 'restore', 'id' => $id])) . '">' . csrf_field() . '<button class="btn ghost sm">' . icon('refresh', 15) . ' Restore</button></form>';
        if (is_super() && !crud_purge_block($d, $id)) echo '<form method="post" action="' . e(u($base, ['a' => 'purge', 'id' => $id])) . '" data-confirm="Permanently delete this record? This cannot be undone.">' . csrf_field() . '<input name="reason" placeholder="Reason"><button class="btn ghost sm danger">' . icon('trash', 15) . ' Delete permanently</button></form>';
        echo '</div>';
    }
}

/** Lists of records that reference this one (e.g. a demand's workers). */
function crud_related($d, $r)
{
    foreach ($GLOBALS['ENT'] as $o) {
        if (!$o['related']) continue;
        foreach ($o['fields'] as $k => $f) {
            if ($f['type'] !== 'ref' || $f['arg'] !== $d['key'] || !empty($f['norel'])) continue;
            $h = ent_home($o['key']);
            if (!$h || !can($h['mod'])) continue;
            $cnt = (int)val('SELECT COUNT(*) FROM ' . $o['table'] . " WHERE $k=? AND archived=0", [$r['id']]);
            $list = rows('SELECT * FROM ' . $o['table'] . " WHERE $k=? AND archived=0 ORDER BY id DESC LIMIT 10", [$r['id']]);
            $cols = array_values(array_filter($o['list'], function ($c) use ($k) { return $c !== $k; }));
            $cols = array_slice($cols, 0, 5);
            echo '<div class="card related"><div class="card-h"><h3>' . e($o['label']) . ' <span class="count">' . $cnt . '</span></h3>';
            if (can($h['mod'], 'add')) echo '<a class="btn ghost sm" href="' . e(u($h['url'], ['a' => 'new', $k => $r['id']])) . '">' . icon('plus', 14) . ' Add</a>';
            echo '</div>';
            if ($list) {
                echo '<div class="table-wrap"><table class="table compact"><thead><tr>';
                foreach ($cols as $c) echo '<th>' . e($c === 'ref_no' ? 'No.' : ($c === 'created_at' ? 'Created' : ($o['fields'][$c]['label'] ?? $c))) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($list as $x) {
                    echo '<tr data-href="' . e(u($h['url'], ['a' => 'view', 'id' => $x['id']])) . '">';
                    foreach ($cols as $c) echo '<td>' . ($c === 'ref_no' ? '<b class="ref">' . e($x['ref_no']) . '</b>' : ($c === 'created_at' ? e(dfmt($x['created_at'])) : field_show($c, $o['fields'][$c], $x))) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            } else echo '<p class="muted small pad">None yet.</p>';
            echo '</div>';
        }
    }
}

// ---------------------------------------------------------------- CSV export / import

function crud_export($d, $mod, array $opt)
{
    list($w, $p) = crud_where($d, $mod, $opt);
    $data = rows('SELECT * FROM ' . $d['table'] . ' WHERE ' . $w . ' ORDER BY id', $p);
    $head = ['No.'];
    foreach ($d['fields'] as $k => $f) if ($f['type'] !== 'password') $head[] = $f['label'];
    $head[] = 'Created';
    $out = [];
    foreach ($data as $r) {
        $line = [$r['ref_no'] ?: $r['id']];
        foreach ($d['fields'] as $k => $f) if ($f['type'] !== 'password') $line[] = html_entity_decode(strip_tags((string)field_show($k, $f, $r, true)), ENT_QUOTES);
        $line[] = $r['created_at'];
        $out[] = $line;
    }
    csv_out($d['key'] . '-' . date('Ymd-His') . '.csv', $head, $out, $d['table']);
}
function crud_import_cols($d)
{
    $c = [];
    foreach ($d['fields'] as $k => $f) if (empty($f['hide']) && !in_array($f['type'], ['file', 'password', 'users'], true)) $c[] = $k;
    return $c;
}
function crud_import_form($d, $base)
{
    echo '<div class="card form-card"><div class="card-h"><h3>Import ' . e(strtolower($d['label'])) . ' from CSV</h3><a class="btn ghost sm" href="' . e($base) . '">Cancel</a></div>'
        . '<p>Upload a CSV file. The first row must contain column names (field keys or labels). The file is checked first — you see every error and possible duplicate <b>before</b> anything is saved, and nothing is imported until the file is clean.</p>'
        . '<p><a href="' . e(u($base, ['a' => 'template'])) . '">' . icon('download', 14) . ' Download a blank template</a> · Columns: <code>' . e(implode(', ', crud_import_cols($d))) . '</code></p>'
        . '<form method="post" enctype="multipart/form-data" action="' . e(u($base, ['a' => 'import'])) . '">' . csrf_field() . '<input type="file" name="csv" accept=".csv" required> <button class="btn">' . icon('upload', 15) . ' Check file</button></form></div>';
}
function crud_import_preview($d, $base)
{
    if (empty($_FILES['csv']['tmp_name'])) { flash('Choose a CSV file.', 'bad'); redirect(u($base, ['a' => 'import'])); }
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    $head = fgetcsv($fh);
    if (!$head) { flash('The file is empty.', 'bad'); redirect(u($base, ['a' => 'import'])); }
    $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
    $map = [];
    foreach ($head as $i => $h) {
        $h = strtolower(trim($h));
        foreach ($d['fields'] as $k => $f) if ($h === strtolower($k) || $h === strtolower($f['label'])) $map[$i] = $k;
    }
    $items = []; $errors = []; $seen = [];
    $n = 1;
    while (($line = fgetcsv($fh)) !== false) {
        $n++;
        if (count(array_filter($line, 'strlen')) === 0) continue;
        $rec = [];
        foreach ($map as $i => $k) $rec[$k] = trim($line[$i] ?? '');
        $rowErr = [];
        foreach ($d['fields'] as $k => $f) {
            if (!empty($f['hide']) || in_array($f['type'], ['file', 'password', 'users'], true)) continue;
            $v = $rec[$k] ?? '';
            if ($v === '' && (!empty($f['req']))) $rowErr[] = $f['label'] . ' is required';
            if ($v !== '' && $f['type'] === 'enum' && !in_array($v, $f['choices'], true)) $rowErr[] = $f['label'] . ' "' . $v . '" is not allowed';
            if ($v !== '' && in_array($f['type'], ['int', 'number', 'money'], true) && !is_numeric($v)) $rowErr[] = $f['label'] . ' must be a number';
            if ($v !== '' && $f['type'] === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $rowErr[] = $f['label'] . ' is not a valid email';
            if ($v !== '' && $f['type'] === 'date' && !strtotime($v)) $rowErr[] = $f['label'] . ' is not a valid date';
            if ($v !== '' && $f['type'] === 'ref') { $rid = is_numeric($v) ? (int)$v : (int)val('SELECT id FROM ' . entdef($f['arg'])['table'] . ' WHERE ref_no=?', [$v]); if (!$rid) $rowErr[] = $f['label'] . ' "' . $v . '" not found'; else $rec[$k] = $rid; }
        }
        foreach ($d['dupe'] as $k) {
            $v = $rec[$k] ?? '';
            if ($v === '') continue;
            if (val('SELECT id FROM ' . $d['table'] . " WHERE $k=? AND archived=0", [$v])) $rowErr[] = 'Duplicate: ' . $d['fields'][$k]['label'] . ' "' . $v . '" already exists';
            if (isset($seen[$k][$v])) $rowErr[] = 'Duplicate inside the file: ' . $d['fields'][$k]['label'] . ' "' . $v . '" (row ' . $seen[$k][$v] . ')';
            $seen[$k][$v] = $n;
        }
        if ($rowErr) $errors[$n] = $rowErr;
        $items[$n] = $rec;
    }
    fclose($fh);
    $_SESSION['import'] = ['ent' => $d['key'], 'items' => $items, 'errors' => $errors, 'unmapped' => array_values(array_diff_key($head, $map))];
    redirect(u($base, ['a' => 'preview']));
}
function crud_import_show($d, $base)
{
    $imp = $_SESSION['import'] ?? null;
    if (!$imp || $imp['ent'] !== $d['key']) { echo '<div class="empty">Nothing to preview. <a href="' . e(u($base, ['a' => 'import'])) . '">Upload a file</a></div>'; return; }
    $ok = !$imp['errors'] && $imp['items'];
    echo '<div class="card"><div class="card-h"><h3>Import check: ' . count($imp['items']) . ' rows, ' . count($imp['errors']) . ' with problems</h3><a class="btn ghost sm" href="' . e(u($base, ['a' => 'import'])) . '">Upload another file</a></div>';
    if ($imp['unmapped']) echo '<div class="alert warn">Ignored columns (not recognised): ' . e(implode(', ', $imp['unmapped'])) . '</div>';
    if ($imp['errors']) {
        echo '<div class="alert bad"><b>Nothing has been imported.</b> Fix these rows in your file and upload it again.</div><div class="table-wrap"><table class="table compact"><thead><tr><th>Row</th><th>Problems</th></tr></thead><tbody>';
        foreach ($imp['errors'] as $n => $er) echo '<tr><td>' . $n . '</td><td>' . e(implode('; ', $er)) . '</td></tr>';
        echo '</tbody></table></div>';
    } elseif ($ok) {
        echo '<div class="alert ok">All rows passed validation and duplicate checks.</div><form method="post" action="' . e(u($base, ['a' => 'import_commit'])) . '">' . csrf_field() . '<button class="btn">' . icon('check', 15) . ' Import ' . count($imp['items']) . ' rows</button></form>';
    }
    echo '</div>';
}
function crud_import_commit($d, $base)
{
    $imp = $_SESSION['import'] ?? null;
    if (!$imp || $imp['ent'] !== $d['key'] || $imp['errors']) { flash('Run the check again before importing.', 'bad'); redirect(u($base, ['a' => 'import'])); }
    db()->beginTransaction();
    foreach ($imp['items'] as $rec) {
        $data = [];
        foreach ($rec as $k => $v) if (isset($d['fields'][$k])) $data[$k] = $v === '' ? null : $v;
        $data += ['created_by' => me()['id'], 'created_at' => now(), 'archived' => 0];
        if ($d['prefix']) $data['ref_no'] = next_number($d['prefix']);
        $id = ins($d['table'], $data);
        audit('import', $d['table'], $id, null, $data);
    }
    db()->commit();
    unset($_SESSION['import']);
    flash(count($imp['items']) . ' records imported.');
    redirect($base);
}
