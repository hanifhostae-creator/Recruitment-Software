<?php
// Shared helpers used by every module. See docs/core-api.md.

/** Escape for HTML output. */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/** GET / POST value (trimmed). */
function g($k, $d = '') { return isset($_GET[$k]) ? (is_array($_GET[$k]) ? $_GET[$k] : trim($_GET[$k])) : $d; }
function p($k, $d = '') { return isset($_POST[$k]) ? (is_array($_POST[$k]) ? $_POST[$k] : trim($_POST[$k])) : $d; }
function is_post() { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function redirect($url) { header('Location: ' . $url); exit; }
function back($fallback = 'index.php') { redirect(!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $fallback); }
function now() { return date('Y-m-d H:i:s'); }
function today() { return date('Y-m-d'); }

/** Build a URL from a base ("page.php?t=x") and extra params. */
function u($base, array $params = [])
{
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    if (!$params) return $base;
    return $base . (strpos($base, '?') === false ? '?' : '&') . http_build_query($params);
}

/** Flash messages: flash('Saved') to set, flash() to read all. */
function flash($msg = null, $type = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check()
{
    if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;max-width:600px;margin:60px auto">'
           . '<h2>Your session has expired</h2><p>For your security the form could not be submitted. Please go back, reload the page and try again.</p>'
           . '<p><a href="javascript:history.back()">Go back</a></p></body>';
        exit;
    }
}

function setting($k, $d = null) { $v = val('SELECT v FROM settings WHERE k=?', [$k]); return $v === null ? $d : $v; }
function set_setting($k, $v) { db_upsert('settings', ['k' => $k, 'v' => (string)$v], ['v']); }
/** Stored secrets (SMTP password, WhatsApp token) are encrypted with APP_KEY from inc/config.php when it is set. */
function secret_put($k, $v)
{
    $v = (string)$v;
    if ($v !== '' && defined('APP_KEY') && APP_KEY !== '' && function_exists('openssl_encrypt')) {
        $iv = random_bytes(12);
        $c = openssl_encrypt($v, 'aes-256-gcm', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA, $iv, $tag);
        $v = 'enc:' . base64_encode($iv . $tag . $c);
    }
    set_setting($k, $v);
}
function secret_get($k)
{
    $v = (string)setting($k, '');
    if (strpos($v, 'enc:') !== 0) return $v;
    if (!defined('APP_KEY') || !function_exists('openssl_decrypt')) return '';
    $raw = base64_decode(substr($v, 4));
    $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $out === false ? '' : $out;
}

/** Next unique record number, e.g. next_number('CAN') => CAN-2026-00001. */
function next_number($prefix)
{
    $y = date('Y');
    $key = $prefix . '-' . $y;
    db_insert_ignore('counters', ['prefix' => $key, 'n' => 0]);
    q('UPDATE counters SET n=n+1 WHERE prefix=?', [$key]);
    return sprintf('%s-%s-%05d', $prefix, $y, (int)val('SELECT n FROM counters WHERE prefix=?', [$key]));
}

/** Audit trail entry. */
function audit($action, $tbl = null, $id = null, $old = null, $new = null, $reason = null)
{
    $u = function_exists('me') ? me() : null;
    if (!$u && !empty($_SESSION['uid'])) $u = row('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
    $actor = $u ? $u['name'] . ' (' . $u['username'] . ')' : (isset($_SESSION['portal']) ? $_SESSION['portal']['type'] . ' portal: ' . $_SESSION['portal']['name'] : 'system');
    ins('audit', [
        'at' => now(), 'actor' => $actor, 'actor_type' => $u ? 'staff' : (isset($_SESSION['portal']) ? $_SESSION['portal']['type'] : 'system'),
        'actor_id' => $u ? $u['id'] : ($_SESSION['portal']['id'] ?? null), 'action' => $action, 'tbl' => $tbl, 'rec_id' => $id,
        'old_v' => $old === null ? null : (is_string($old) ? $old : json_encode($old, JSON_UNESCAPED_UNICODE)),
        'new_v' => $new === null ? null : (is_string($new) ? $new : json_encode($new, JSON_UNESCAPED_UNICODE)),
        'reason' => $reason, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
    ]);
}

function money($v, $cur = null) { return ($cur ?: setting('currency', 'AED')) . ' ' . number_format((float)$v, 2); }
function num($v) { return number_format((float)$v, (float)$v == (int)$v ? 0 : 2); }
function dfmt($d) { return $d ? date('d M Y', strtotime($d)) : ''; }
function dtfmt($d) { return $d ? date('d M Y, H:i', strtotime($d)) : ''; }
function ago($d)
{
    if (!$d) return '';
    $s = time() - strtotime($d);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 86400 * 30) return floor($s / 86400) . ' d ago';
    return dfmt($d);
}

/** Staff name by id (memoised). */
function user_name($id)
{
    static $m = [];
    if (!$id) return '';
    if (!isset($m[$id])) $m[$id] = (string)val('SELECT name FROM users WHERE id=?', [$id]);
    return $m[$id];
}
function staff_list($rolePrefix = null)
{
    if ($rolePrefix) {
        $l = rows('SELECT u.id, u.name, u.username FROM users u JOIN roles r ON r.id=u.role_id WHERE u.active=1 AND r.name LIKE ? ORDER BY u.name', [$rolePrefix . '%']);
        if ($l) return $l;
    }
    return rows('SELECT id, name, username FROM users WHERE active=1 ORDER BY name');
}

/** Configurable dropdown values (Admin Control Centre > Custom options). */
$GLOBALS['OPT_DEFAULTS'] = [];
function opt_defaults($list, array $values) { $GLOBALS['OPT_DEFAULTS'][$list] = $values; }
function options($list)
{
    static $cache = [];
    if (isset($cache[$list])) return $cache[$list];
    $v = array_column(rows('SELECT value FROM options WHERE list=? AND active=1 ORDER BY sort, value', [$list]), 'value');
    if (!$v && !val('SELECT COUNT(*) FROM options WHERE list=?', [$list]) && !empty($GLOBALS['OPT_DEFAULTS'][$list])) {
        foreach ($GLOBALS['OPT_DEFAULTS'][$list] as $i => $x) ins('options', ['list' => $list, 'value' => $x, 'sort' => $i, 'active' => 1]);
        $v = $GLOBALS['OPT_DEFAULTS'][$list];
    }
    return $cache[$list] = $v;
}

/** Badge with a tone picked from the word. */
function badge($text)
{
    if ($text === null || $text === '') return '';
    $t = strtolower($text);
    $tone = 'muted';
    $map = [
        'ok' => ['active', 'approved', 'selected', 'paid', 'done', 'completed', 'verified', 'joined', 'deployed', 'won', 'present', 'pass', 'published', 'yes', 'travelled', 'resolved', 'received', 'accepted', 'placed', 'issued', 'confirmed', 'answered', 'open file'],
        'bad' => ['rejected', 'not selected', 'cancelled', 'closed', 'lost', 'absent', 'fail', 'declined', 'critical', 'overdue', 'did not travel', 'terminated', 'missing', 'expired', 'no show', 'suspended', 'reversed', 'no'],
        'warn' => ['pending', 'on hold', 'issue', 'high', 'urgent', 'draft', 'in progress', 'partially', 'needs review', 'rescheduled', 'late', 'documentation', 'visa', 'processing', 'requested', 'half'],
        'info' => ['new', 'hr assigned', 'interviewed', 'file opened', 'in deployment', 'submitted', 'open', 'scheduled', 'sent', 'assigned', 'prospect', 'proposal', 'qualified', 'contacted', 'to do', 'ready'],
    ];
    foreach ($map as $k => $words) foreach ($words as $w) if (strpos($t, $w) !== false) { $tone = $k; break 2; }
    return '<span class="badge b-' . $tone . '">' . e($text) . '</span>';
}

/** Validate and store an uploaded file. Returns [relative path or null, error or null]. */
function upload_file($field, $sub = 'files', $required = false, $types = null)
{
    $types = $types ?: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return [null, $required ? 'Please attach the file.' : null];
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'The file could not be uploaded (error ' . (int)$f['error'] . ').'];
    if ($f['size'] > 10 * 1024 * 1024) return [null, 'The file is larger than 10 MB.'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $types, true)) return [null, 'Allowed file types: ' . strtoupper(implode(', ', $types)) . '.'];
    // check the real content, not only the name (stops disguised files)
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string)finfo_file($fi, $f['tmp_name']);
        $okMime = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
            'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'], 'csv' => ['text/plain', 'text/csv']];
        if (isset($okMime[$ext]) && !in_array($mime, $okMime[$ext], true)) return [null, 'The file content does not match a real ' . strtoupper($ext) . ' file.'];
    }
    $dir = ROOT . '/uploads/' . $sub;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        if (!@rename($f['tmp_name'], $dir . '/' . $name)) return [null, 'The file could not be saved. Check that uploads/ is writable.'];
    }
    return [$sub . '/' . $name, null];
}
function file_link($path, $label = 'Download')
{
    if (!$path) return '';
    return '<a class="file-link" href="file.php?f=' . rawurlencode($path) . '">' . icon('download', 14) . ' ' . e($label) . '</a>';
}

/** Notification for a staff user. */
function notify($uid, $text, $link = '', $kind = 'system')
{
    if ($uid) ins('notifications', ['user_id' => $uid, 'kind' => $kind, 'text' => $text, 'link' => $link, 'is_read' => 0, 'at' => now()]);
}
/** Notification types: label, default sound and icon (the Super Admin changes them in Admin → Notification sounds). */
const NOTIF_KINDS = [
    'assigned' => ['Candidate assigned to me', 'chime', 'user-plus'], 'file' => ['File confirmed — ready for deployment', 'ding', 'folder'],
    'deploy' => ['Candidate sent to my deployment team', 'bell', 'send'], 'chat' => ['New chat message', 'pop', 'chat'],
    'lead' => ['New enquiry, lead or WhatsApp message', 'soft', 'target'], 'approval' => ['Approval needed', 'alert', 'check-circle'],
    'payment' => ['Payment received', 'cash', 'dollar'], 'task' => ['Task, issue or ticket', 'alert', 'alert'],
    'entry' => ['New entry anywhere (Super Admin)', 'soft', 'activity'], 'system' => ['Other notifications', 'soft', 'bell']];
const NOTIF_SOUNDS = ['chime' => 'Chime', 'bell' => 'Bell', 'ding' => 'Ding', 'pop' => 'Pop', 'soft' => 'Soft', 'alert' => 'Alert', 'cash' => 'Cash register', 'none' => 'Silent'];
function notif_config()
{
    static $c = null;
    if ($c !== null) return $c;
    $saved = json_decode((string)setting('notif_config', ''), true) ?: [];
    $c = [];
    foreach (NOTIF_KINDS as $k => $d) $c[$k] = ['on' => (bool)($saved[$k]['on'] ?? true), 'sound' => $saved[$k]['sound'] ?? $d[1], 'icon' => $saved[$k]['icon'] ?? $d[2], 'label' => $d[0]];
    return $c;
}
/** System task (Tasks & Alerts). $key avoids duplicates for the same auto task. */
function add_task($title, $severity = 'Medium', $due = null, $owner = null, $link = '', $key = null)
{
    if ($key && val('SELECT id FROM tasks WHERE auto_key=? AND status<>?', [$key, 'Done'])) return;
    ins('tasks', ['ref_no' => next_number('TSK'), 'title' => $title, 'severity' => $severity, 'due_date' => $due, 'owner' => $owner,
        'status' => 'Open', 'source' => 'System', 'link' => $link, 'auto_key' => $key, 'created_at' => now(), 'archived' => 0]);
    if ($owner) notify($owner, 'New task: ' . $title, 'tasks.php', 'task');
}

/** Forward-only stage change through one function. $stages is the ordered list. */
function stage_advance($table, $id, $to, $reason = '', $field = 'stage')
{
    $d = entdef($table);
    $stages = $d['fields'][$field]['choices'] ?? [];
    $cur = val("SELECT $field FROM $table WHERE id=?", [$id]);
    $ci = array_search($cur, $stages, true);
    $ti = array_search($to, $stages, true);
    if ($ti === false) return 'Unknown stage.';
    if ($cur === $to) return null;
    if ($ci !== false && $ti < $ci) return 'Stages only move forward. Use an authorised edit with a reason to correct a record.';
    q("UPDATE $table SET $field=?, updated_at=? WHERE id=?", [$to, now(), $id]);
    $u = me();
    ins('stage_history', ['entity' => $table, 'entity_id' => $id, 'from_stage' => $cur, 'to_stage' => $to, 'reason' => $reason,
        'by_user' => $u['id'] ?? null, 'by_name' => $u['name'] ?? 'system', 'at' => now()]);
    audit('stage', $table, $id, [$field => $cur], [$field => $to], $reason);
    return null;
}

// ---------------------------------------------------------------- money (one shared payment logic)

/** GL account id by code. */
function acct($code) { return (int)val('SELECT id FROM accounts WHERE code=?', [$code]); }

/** Post a double-entry journal. $lines = [[account code or id, debit, credit, note], ...]. Returns entry id. */
function post_journal($date, $memo, array $lines, $source = 'manual', $sourceId = null, $reversalOf = null)
{
    $dr = 0; $cr = 0;
    foreach ($lines as $l) { $dr += round((float)$l[1], 2); $cr += round((float)$l[2], 2); }
    if (abs($dr - $cr) > 0.005 || $dr <= 0) throw new RuntimeException('Journal does not balance (debit ' . $dr . ' / credit ' . $cr . ').');
    $u = function_exists('me') ? me() : null;
    $id = ins('journal_entries', ['ref_no' => next_number('JE'), 'entry_date' => $date ?: today(), 'memo' => $memo, 'source' => $source,
        'source_id' => $sourceId, 'reversal_of' => $reversalOf, 'created_by' => $u['id'] ?? null, 'created_at' => now()]);
    foreach ($lines as $l) {
        // an int is an account id; a string is an account code
        $acc = is_int($l[0]) ? $l[0] : (int)val('SELECT id FROM accounts WHERE code=?', [(string)$l[0]]);
        if (!$acc) throw new RuntimeException('Unknown account ' . $l[0] . '.');
        ins('journal_lines', ['entry_id' => $id, 'account_id' => $acc, 'debit' => round((float)$l[1], 2), 'credit' => round((float)$l[2], 2), 'note' => $l[3] ?? null]);
    }
    return $id;
}
/** Reverse a journal entry (never edited in place). */
function reverse_journal($entryId, $reason)
{
    $lines = rows('SELECT account_id, debit, credit FROM journal_lines WHERE entry_id=?', [$entryId]);
    $je = row('SELECT * FROM journal_entries WHERE id=?', [$entryId]);
    if (!$je || $je['reversed']) return null;
    $rev = [];
    foreach ($lines as $l) $rev[] = [(int)$l['account_id'], $l['credit'], $l['debit'], 'Reversal'];
    $id = post_journal(today(), 'Reversal of ' . $je['ref_no'] . ': ' . $reason, $rev, 'reversal', $entryId, $entryId);
    q('UPDATE journal_entries SET reversed=1 WHERE id=?', [$entryId]);
    audit('reverse', 'journal_entries', $entryId, null, null, $reason);
    return $id;
}

/** Income / expense GL account used when a payment is recorded for an entity. */
function payment_account($entity, $direction)
{
    $map = setting('pay_accounts') ? json_decode(setting('pay_accounts'), true) : [];
    if (isset($map[$entity . ':' . $direction])) return $map[$entity . ':' . $direction];
    $in = ['inside_files' => '4000', 'invoices' => '1100', 'companies' => '4100', 'travel_bookings' => '4200', 'paybook_files' => '4300', 'pos_sales' => '4300'];
    $out = ['agents' => '5000', 'wages' => '5100', 'payroll' => '5100', 'travel_bookings' => '5500', 'vendor_bills' => '5200', 'inside_files' => '5400', 'paybook_files' => '5400'];
    return $direction === 'in' ? ($in[$entity] ?? '4300') : ($out[$entity] ?? '5200');
}

/**
 * Record a payment against any record (file, invoice, agent, booking...).
 * $o: direction (in|out), method, pay_type, pay_date, note, party.
 * Returns payment id. Posts a balanced journal entry.
 */
function record_payment($entity, $id, $amount, array $o = [])
{
    $amount = round((float)$amount, 2);
    if ($amount <= 0) throw new RuntimeException('Amount must be greater than zero.');
    $dir = $o['direction'] ?? 'in';
    $method = $o['method'] ?? 'Cash';
    $cash = in_array($method, ['Cash'], true) ? '1000' : '1010';
    $gl = payment_account($entity, $dir);
    $u = function_exists('me') ? me() : null;
    $ref = next_number($dir === 'in' ? 'RCT' : 'PAY');
    $pid = ins('payments', ['ref_no' => $ref, 'entity' => $entity, 'entity_id' => $id, 'section' => $o['section'] ?? section_of_entity($entity, $id), 'party' => $o['party'] ?? null, 'direction' => $dir,
        'amount' => $amount, 'method' => $method, 'pay_type' => $o['pay_type'] ?? null, 'pay_date' => ($o['pay_date'] ?? '') ?: today(),
        'note' => $o['note'] ?? null, 'reversed' => 0, 'created_by' => $u['id'] ?? null, 'created_at' => now()]);
    $lines = $dir === 'in' ? [[$cash, $amount, 0], [$gl, 0, $amount]] : [[$gl, $amount, 0], [$cash, 0, $amount]];
    $jid = post_journal($o['pay_date'] ?? today(), ($dir === 'in' ? 'Receipt ' : 'Payment ') . $ref . ' - ' . $entity . ' #' . $id, $lines, 'payment', $pid);
    q('UPDATE payments SET journal_id=? WHERE id=?', [$jid, $pid]);
    audit('payment', $entity, $id, null, ['ref' => $ref, 'amount' => $amount, 'direction' => $dir, 'type' => $o['pay_type'] ?? null]);
    if ($dir === 'in' && function_exists('notify_super') && function_exists('me') && me()) notify_super('Payment received ' . money($amount) . ' (' . $ref . ')', 'finance.php?t=ledger', 'payment');
    return $pid;
}
/** Reverse a payment: new negative entry with reason; original kept and flagged. */
function reverse_payment($pid, $reason)
{
    $pmt = row('SELECT * FROM payments WHERE id=?', [$pid]);
    if (!$pmt) return 'Payment not found.';
    if ($pmt['reversed'] || $pmt['reversal_of']) return 'This entry has already been reversed or is itself a reversal.';
    if (trim($reason) === '') return 'A reason is required for a reversal.';
    $u = me();
    $rid = ins('payments', ['ref_no' => next_number('REV'), 'entity' => $pmt['entity'], 'entity_id' => $pmt['entity_id'], 'section' => $pmt['section'], 'party' => $pmt['party'],
        'direction' => $pmt['direction'], 'amount' => -$pmt['amount'], 'method' => $pmt['method'], 'pay_type' => 'Reversal',
        'pay_date' => today(), 'note' => 'Reversal of ' . $pmt['ref_no'], 'reversal_of' => $pid, 'reason' => $reason, 'reversed' => 0,
        'created_by' => $u['id'] ?? null, 'created_at' => now()]);
    q('UPDATE payments SET reversed=1 WHERE id=?', [$pid]);
    if ($pmt['journal_id']) { $j = reverse_journal($pmt['journal_id'], $reason); q('UPDATE payments SET journal_id=? WHERE id=?', [$j, $rid]); }
    audit('reverse', 'payments', $pid, ['amount' => $pmt['amount']], ['reversal' => $rid], $reason);
    return null;
}
/** Business sections used for accounts, dashboards and staff teams. */
const SECTIONS = ['Inside Hiring', 'Outside Hiring', 'Travel & Tour', 'Marriage Bureau', 'Marketing', 'IT & Business Development', 'General'];
/** Which business section a payment belongs to (set when the payment is recorded). */
function section_of_entity($entity, $id)
{
    switch ($entity) {
        case 'inside_files': return 'Inside Hiring';
        case 'agents': return 'Outside Hiring';
        case 'travel_bookings': return 'Travel & Tour';
        case 'paybook_files': return 'Marriage Bureau';
        case 'wages': return val('SELECT worker_id FROM wages WHERE id=?', [$id]) ? 'Outside Hiring' : 'General';
        case 'invoices': case 'pos_sales':
            $bl = (string)val("SELECT business_line FROM $entity WHERE id=?", [$id]);
            return $bl === 'Chairman Services' || $bl === '' ? 'General' : $bl;
    }
    return 'General';
}
/** Net amount paid (reversals included) for a record. */
function paid_total($entity, $id, $dir = 'in')
{
    return (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE entity=? AND entity_id=? AND direction=?', [$entity, $id, $dir]);
}
function payments_for($entity, $id) { return rows('SELECT * FROM payments WHERE entity=? AND entity_id=? ORDER BY id', [$entity, $id]); }

/** CSV download (logged). */
function csv_out($filename, array $headers, array $data, $tbl = null)
{
    audit('export', $tbl, null, null, ['file' => $filename, 'rows' => count($data)]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($data as $r) fputcsv($out, array_values($r));
    fclose($out);
    exit;
}

// ---------------------------------------------------------------- staff attendance (check-in / check-out)

function attendance_today($uid) { return row('SELECT * FROM staff_attendance WHERE user_id=? AND day=?', [$uid, today()]); }
/** Handles the check-in box and the Reception manual box (posted with _att). */
function attendance_handle()
{
    $u = me();
    if (!$u) return;
    $target = (int)p('att_user', $u['id']);
    if ($target !== (int)$u['id'] && !can('reception', 'edit') && !can('hrm', 'edit')) { flash('You cannot mark attendance for other staff.', 'bad'); back(); }
    $a = p('_att');
    $rec = attendance_today($target);
    if ($a === 'in') {
        if ($rec) flash('Already checked in today at ' . substr($rec['check_in'], 11, 5) . '.', 'warn');
        else { ins('staff_attendance', ['user_id' => $target, 'day' => today(), 'check_in' => now(), 'marked_by' => $u['id']]); audit('check-in', 'staff_attendance', $target); flash(($target === (int)$u['id'] ? 'Checked in' : user_name($target) . ' checked in') . ' at ' . date('H:i') . '.'); }
    } elseif ($a === 'out') {
        if (!$rec) flash('Check in first.', 'warn');
        elseif ($rec['check_out']) flash('Already checked out today.', 'warn');
        else { q('UPDATE staff_attendance SET check_out=? WHERE id=?', [now(), $rec['id']]); audit('check-out', 'staff_attendance', $target); flash('Checked out at ' . date('H:i') . '.'); }
    }
    back();
}

// ---------------------------------------------------------------- charts (SVG, no library)

/** Horizontal bar chart. $data = [label => value]. */
function chart_bars(array $data, $fmt = null)
{
    if (!$data) return '<p class="muted small">No data yet.</p>';
    $max = max(array_map('floatval', $data)) ?: 1;
    $h = '<div class="hbars">';
    $i = 0;
    foreach ($data as $k => $v) {
        $w = max(1.5, round($v / $max * 100, 1));
        $h .= '<div class="hbar"><span class="hb-l">' . e($k) . '</span><span class="hb-t"><i style="--w:' . $w . '%;--d:' . ($i++ * 60) . 'ms"></i></span><b>' . e($fmt ? $fmt($v) : num($v)) . '</b></div>';
    }
    return $h . '</div>';
}
/** Vertical column chart (SVG). */
function chart_cols(array $data, $height = 160)
{
    if (!$data) return '<p class="muted small">No data yet.</p>';
    $max = max(array_map('floatval', $data)) ?: 1;
    $n = count($data);
    $w = 100 / $n;
    $s = '<svg class="cols" viewBox="0 0 100 ' . ($height / 2) . '" preserveAspectRatio="none" role="img">';
    $i = 0;
    $labels = '';
    foreach ($data as $k => $v) {
        $bh = $v / $max * ($height / 2 - 4);
        $x = $i * $w + $w * .18;
        $s .= '<rect x="' . round($x, 2) . '" y="' . round($height / 2 - $bh, 2) . '" width="' . round($w * .64, 2) . '" height="' . round($bh, 2) . '" rx="1.2" style="--d:' . ($i * 50) . 'ms"><title>' . e($k . ': ' . num($v)) . '</title></rect>';
        $labels .= '<span>' . e($k) . '</span>';
        $i++;
    }
    return '<div class="colchart">' . $s . '</svg><div class="col-labels" style="grid-template-columns:repeat(' . $n . ',1fr)">' . $labels . '</div></div>';
}
/** Donut chart. */
function chart_donut(array $data)
{
    $data = array_filter($data, function ($v) { return $v > 0; });
    $total = array_sum($data);
    if (!$total) return '<p class="muted small">No data yet.</p>';
    $colors = ['#3d6bff', '#dcaa48', '#22c08a', '#e5566f', '#8b5cf6', '#14b8c4', '#f08a3c', '#64748b'];
    $r = 15.915; $off = 25; $i = 0; $seg = ''; $leg = '';
    foreach ($data as $k => $v) {
        $pct = $v / $total * 100;
        $c = $colors[$i % count($colors)];
        $seg .= '<circle r="' . $r . '" cx="21" cy="21" fill="none" stroke="' . $c . '" stroke-width="6" stroke-dasharray="' . round($pct, 3) . ' ' . round(100 - $pct, 3) . '" stroke-dashoffset="' . round($off, 3) . '"><title>' . e($k . ': ' . num($v)) . '</title></circle>';
        $off -= $pct;
        $leg .= '<li><i style="background:' . $c . '"></i>' . e($k) . ' <b>' . num($v) . '</b></li>';
        $i++;
    }
    return '<div class="donut"><svg viewBox="0 0 42 42">' . $seg . '<text x="21" y="22.5" text-anchor="middle">' . num($total) . '</text></svg><ul>' . $leg . '</ul></div>';
}
function progress($pct, $label = '')
{
    $pct = max(0, min(100, round($pct)));
    return '<div class="progress" title="' . $pct . '%"><i style="--w:' . $pct . '%"></i></div>' . ($label ? '<small class="muted">' . e($label) . '</small>' : '');
}

/** Code 39 barcode as SVG (for POS labels). */
function barcode39($text)
{
    $codes = ['0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn'];
    $t = '*' . preg_replace('/[^0-9A-Z\-. ]/', '', strtoupper($text)) . '*';
    $x = 0; $bars = '';
    foreach (str_split($t) as $ch) {
        $p = $codes[$ch];
        for ($i = 0; $i < 9; $i++) {
            $w = $p[$i] === 'w' ? 3 : 1;
            if ($i % 2 === 0) $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="40"/>';
            $x += $w;
        }
        $x += 1;
    }
    return '<svg class="barcode" viewBox="0 0 ' . $x . ' 40" preserveAspectRatio="none">' . $bars . '</svg>';
}
