<?php
// Database: one SQLite file (data/rh.sqlite), created and seeded on first visit.

function db()
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = ROOT . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . $dir . '/rh.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    return $pdo;
}

/** Run a prepared statement. */
function q($sql, array $p = [])
{
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
}
function rows($sql, array $p = []) { return q($sql, $p)->fetchAll(); }
function row($sql, array $p = []) { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val($sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }

/** Insert an associative array, return new id. */
function ins($table, array $data)
{
    $cols = array_keys($data);
    q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
    return (int)db()->lastInsertId();
}
/** Update row by id. */
function upd($table, $id, array $data)
{
    if (!$data) return;
    $set = implode(',', array_map(function ($c) { return $c . '=?'; }, array_keys($data)));
    $p = array_values($data);
    $p[] = $id;
    q('UPDATE ' . $table . ' SET ' . $set . ' WHERE id=?', $p);
}

/** Core tables (not generated from entity definitions). */
function core_schema()
{
    $sql = [
        'CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)',
        'CREATE TABLE IF NOT EXISTS roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, description TEXT, modules TEXT, actions TEXT, is_super INTEGER DEFAULT 0, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, name TEXT, email TEXT, phone TEXT, role_id INTEGER, password_hash TEXT, must_change INTEGER DEFAULT 1, active INTEGER DEFAULT 1, last_login TEXT, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT, actor TEXT, actor_type TEXT, actor_id INTEGER, action TEXT, tbl TEXT, rec_id INTEGER, old_v TEXT, new_v TEXT, reason TEXT, ip TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_audit_rec ON audit(tbl, rec_id)',
        'CREATE TABLE IF NOT EXISTS counters (prefix TEXT PRIMARY KEY, n INTEGER)',
        'CREATE TABLE IF NOT EXISTS options (id INTEGER PRIMARY KEY AUTOINCREMENT, list TEXT, value TEXT, sort INTEGER DEFAULT 0, active INTEGER DEFAULT 1)',
        'CREATE INDEX IF NOT EXISTS ix_options_list ON options(list)',
        'CREATE TABLE IF NOT EXISTS staff_attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, day TEXT, check_in TEXT, check_out TEXT, marked_by INTEGER, note TEXT, UNIQUE(user_id, day))',
        'CREATE TABLE IF NOT EXISTS payments (id INTEGER PRIMARY KEY AUTOINCREMENT, ref_no TEXT UNIQUE, entity TEXT, entity_id INTEGER, party TEXT, direction TEXT, amount REAL, method TEXT, pay_type TEXT, pay_date TEXT, note TEXT, reversal_of INTEGER, reversed INTEGER DEFAULT 0, reason TEXT, journal_id INTEGER, created_by INTEGER, created_at TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_pay_ent ON payments(entity, entity_id)',
        'CREATE TABLE IF NOT EXISTS stage_history (id INTEGER PRIMARY KEY AUTOINCREMENT, entity TEXT, entity_id INTEGER, from_stage TEXT, to_stage TEXT, reason TEXT, by_user INTEGER, by_name TEXT, at TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_stage_ent ON stage_history(entity, entity_id)',
        'CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, text TEXT, link TEXT, is_read INTEGER DEFAULT 0, at TEXT)',
        'CREATE TABLE IF NOT EXISTS saved_views (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, name TEXT, url TEXT, at TEXT)',
        'CREATE TABLE IF NOT EXISTS journal_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, ref_no TEXT UNIQUE, entry_date TEXT, memo TEXT, source TEXT, source_id INTEGER, reversal_of INTEGER, reversed INTEGER DEFAULT 0, created_by INTEGER, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS journal_lines (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, account_id INTEGER, debit REAL DEFAULT 0, credit REAL DEFAULT 0, note TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_jl_entry ON journal_lines(entry_id)',
        'CREATE TABLE IF NOT EXISTS staff_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, from_user INTEGER, to_user INTEGER, body TEXT, is_read INTEGER DEFAULT 0, at TEXT)',
        'CREATE TABLE IF NOT EXISTS wa_webhook_log (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT, method TEXT, body TEXT, result TEXT)',
        'CREATE TABLE IF NOT EXISTS candidate_logins (id INTEGER PRIMARY KEY AUTOINCREMENT, candidate_id INTEGER UNIQUE, username TEXT, legacy_slug TEXT, password_hash TEXT, token TEXT, token_expires TEXT, last_login TEXT, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS password_resets (id INTEGER PRIMARY KEY AUTOINCREMENT, acct_type TEXT, acct_id INTEGER, token_hash TEXT, expires_at TEXT, used_at TEXT, created_at TEXT, ip TEXT, requested_by INTEGER)',
        'CREATE INDEX IF NOT EXISTS ix_pwr_token ON password_resets(token_hash)',
        'CREATE TABLE IF NOT EXISTS custom_fields (id INTEGER PRIMARY KEY AUTOINCREMENT, entity TEXT, label TEXT, type TEXT, options TEXT, required INTEGER DEFAULT 0, active INTEGER DEFAULT 1)',
        'CREATE TABLE IF NOT EXISTS custom_values (id INTEGER PRIMARY KEY AUTOINCREMENT, field_id INTEGER, entity TEXT, rec_id INTEGER, value TEXT)',
    ];
    foreach ($sql as $s) db()->exec($s);
}

/** Make sure the schema is current (data-preserving) and demo data exists. */
function db_ready()
{
    static $done = false;
    if ($done) return;
    $done = true;
    $hash = md5(APP_VERSION . serialize(array_map(function ($d) {
        return [$d['table'], array_map(function ($f) { return $f['type']; }, $d['fields'])];
    }, $GLOBALS['ENT'])));
    $cur = null;
    try { $cur = val("SELECT v FROM settings WHERE k='schema_hash'"); } catch (Exception $e) { $cur = null; }
    if ($cur !== $hash) {
        db()->beginTransaction();
        core_schema();
        ent_migrate();
        q("INSERT OR REPLACE INTO settings (k,v) VALUES ('schema_hash',?)", [$hash]);
        db()->commit();
    }
    if (!val("SELECT v FROM settings WHERE k='seeded'")) {
        db()->beginTransaction();
        run_seeders();
        q("INSERT OR REPLACE INTO settings (k,v) VALUES ('seeded',?)", [date('c')]);
        db()->commit();
    }
    if (function_exists('apply_upgrades')) apply_upgrades();
}
