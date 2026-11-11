<?php
// Database layer. Works with MySQL / MariaDB (recommended for the live site) and SQLite (local / quick start).
// The driver and credentials come from inc/config.php (never committed) — see inc/config.sample.php.

function db_driver() { return defined('DB_DRIVER') && DB_DRIVER === 'mysql' ? 'mysql' : 'sqlite'; }
function db_is_mysql() { return db_driver() === 'mysql'; }

function db()
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    try {
        if (db_is_mysql()) {
            $opt[PDO::ATTR_EMULATE_PREPARES] = false;
            $opt[PDO::ATTR_STRINGIFY_FETCHES] = false;
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306) . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
            // PIPES_AS_CONCAT keeps "||" string joins portable; no ONLY_FULL_GROUP_BY / strict surprises.
            $pdo->exec("SET SESSION sql_mode='PIPES_AS_CONCAT,NO_ENGINE_SUBSTITUTION'");
        } else {
            $dir = ROOT . '/data';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $pdo = new PDO('sqlite:' . $dir . '/rh.sqlite', null, null, $opt);
            $pdo->exec('PRAGMA busy_timeout=5000');
        }
    } catch (PDOException $ex) {
        error_log('[' . date('c') . '] database connection failed: ' . $ex->getMessage());
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>Service unavailable</title><body style="font-family:system-ui,sans-serif;max-width:600px;margin:60px auto;padding:0 20px;color:#12204a">'
           . '<h1>The system is temporarily unavailable</h1><p>The database could not be reached. Please try again in a few minutes.</p>'
           . '<p style="color:#78819c;font-size:14px">Administrator: check the database name, user and password in <code>inc/config.php</code> and that the database user has all privileges.</p></body>';
        exit;
    }
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
/** Insert or replace by primary / unique key (portable). */
function db_upsert($table, array $data, array $updateCols)
{
    $cols = array_keys($data);
    $ph = implode(',', array_fill(0, count($cols), '?'));
    if (db_is_mysql()) q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ") VALUES ($ph) ON DUPLICATE KEY UPDATE " . implode(',', array_map(function ($c) { return "$c=VALUES($c)"; }, $updateCols)), array_values($data));
    else q('INSERT OR REPLACE INTO ' . $table . ' (' . implode(',', $cols) . ") VALUES ($ph)", array_values($data));
}
/** Insert unless the unique key already exists (portable). */
function db_insert_ignore($table, array $data)
{
    $cols = array_keys($data);
    q((db_is_mysql() ? 'INSERT IGNORE INTO ' : 'INSERT OR IGNORE INTO ') . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
}
/** SQL expression: days between two datetime columns. */
function sql_days($a, $b) { return db_is_mysql() ? "TIMESTAMPDIFF(SECOND, $a, $b)/86400" : "(julianday($b)-julianday($a))"; }
function db_table_count() { return (int)(db_is_mysql() ? val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()') : val("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")); }

/** Column names of a table. */
function db_columns($t)
{
    if (db_is_mysql()) return array_column(rows('SHOW COLUMNS FROM ' . $t), 'Field');
    return array_column(rows("PRAGMA table_info($t)"), 'name');
}
/**
 * Run a schema statement written in SQLite style. For MySQL it is translated:
 * INTEGER PK AUTOINCREMENT → INT AUTO_INCREMENT, TEXT → VARCHAR(191), LONGTEXT → MEDIUMTEXT, REAL → DOUBLE,
 * and "CREATE INDEX IF NOT EXISTS" becomes a create that ignores an existing index.
 */
function db_ddl($sql)
{
    if (!db_is_mysql()) { db()->exec($sql); return; }
    $isIndex = (bool)preg_match('/^CREATE (UNIQUE )?INDEX IF NOT EXISTS/i', $sql);
    $sql = preg_replace(['/INTEGER PRIMARY KEY AUTOINCREMENT/i', '/\bLONGTEXT\b/', '/\bTEXT\b/', '/\bREAL\b/', '/\bINTEGER\b/', '/INDEX IF NOT EXISTS/i'],
        ['INT AUTO_INCREMENT PRIMARY KEY', 'MEDIUMTEXT', 'VARCHAR(191)', 'DOUBLE', 'INT', 'INDEX'], $sql);
    if (preg_match('/^CREATE TABLE/i', $sql)) $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    try { db()->exec($sql); }
    catch (PDOException $ex) { if (!$isIndex || strpos($ex->getMessage(), '1061') === false) throw $ex; }
}
/** Add columns that an older install does not have yet (data-preserving). */
function db_add_missing($table, array $cols)
{
    $have = db_columns($table);
    foreach ($cols as $c => $type) if (!in_array($c, $have, true)) db_ddl("ALTER TABLE $table ADD COLUMN $c $type");
}

/** Core tables (not generated from entity definitions). */
function core_schema()
{
    $sql = [
        'CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v LONGTEXT)',
        'CREATE TABLE IF NOT EXISTS roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, description LONGTEXT, modules LONGTEXT, actions LONGTEXT, is_super INTEGER DEFAULT 0, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, name TEXT, email TEXT, phone TEXT, role_id INTEGER, team TEXT, password_hash TEXT, must_change INTEGER DEFAULT 1, active INTEGER DEFAULT 1, last_login TEXT, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT, actor TEXT, actor_type TEXT, actor_id INTEGER, action TEXT, tbl TEXT, rec_id INTEGER, old_v LONGTEXT, new_v LONGTEXT, reason LONGTEXT, ip TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_audit_rec ON audit(tbl, rec_id)',
        'CREATE INDEX IF NOT EXISTS ix_audit_act ON audit(action, ip, at)',
        'CREATE TABLE IF NOT EXISTS counters (prefix TEXT PRIMARY KEY, n INTEGER)',
        'CREATE TABLE IF NOT EXISTS options (id INTEGER PRIMARY KEY AUTOINCREMENT, list TEXT, value TEXT, sort INTEGER DEFAULT 0, active INTEGER DEFAULT 1)',
        'CREATE INDEX IF NOT EXISTS ix_options_list ON options(list)',
        'CREATE TABLE IF NOT EXISTS staff_attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, day TEXT, check_in TEXT, check_out TEXT, marked_by INTEGER, note TEXT, UNIQUE(user_id, day))',
        'CREATE TABLE IF NOT EXISTS payments (id INTEGER PRIMARY KEY AUTOINCREMENT, ref_no TEXT UNIQUE, entity TEXT, entity_id INTEGER, section TEXT, party TEXT, direction TEXT, amount REAL, method TEXT, pay_type TEXT, pay_date TEXT, note LONGTEXT, reversal_of INTEGER, reversed INTEGER DEFAULT 0, reason LONGTEXT, journal_id INTEGER, created_by INTEGER, created_at TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_pay_ent ON payments(entity, entity_id)',
        'CREATE TABLE IF NOT EXISTS stage_history (id INTEGER PRIMARY KEY AUTOINCREMENT, entity TEXT, entity_id INTEGER, from_stage TEXT, to_stage TEXT, reason LONGTEXT, by_user INTEGER, by_name TEXT, at TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_stage_ent ON stage_history(entity, entity_id)',
        'CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, kind TEXT, text LONGTEXT, link LONGTEXT, is_read INTEGER DEFAULT 0, at TEXT)',
        'CREATE INDEX IF NOT EXISTS ix_notif_user ON notifications(user_id, is_read)',
        'CREATE TABLE IF NOT EXISTS saved_views (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, name TEXT, url LONGTEXT, at TEXT)',
        'CREATE TABLE IF NOT EXISTS journal_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, ref_no TEXT UNIQUE, entry_date TEXT, memo LONGTEXT, source TEXT, source_id INTEGER, reversal_of INTEGER, reversed INTEGER DEFAULT 0, created_by INTEGER, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS journal_lines (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, account_id INTEGER, debit REAL DEFAULT 0, credit REAL DEFAULT 0, note LONGTEXT)',
        'CREATE INDEX IF NOT EXISTS ix_jl_entry ON journal_lines(entry_id)',
        'CREATE TABLE IF NOT EXISTS staff_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, from_user INTEGER, to_user INTEGER, body LONGTEXT, is_read INTEGER DEFAULT 0, at TEXT)',
        'CREATE TABLE IF NOT EXISTS wa_webhook_log (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT, method TEXT, body LONGTEXT, result LONGTEXT)',
        'CREATE TABLE IF NOT EXISTS candidate_logins (id INTEGER PRIMARY KEY AUTOINCREMENT, candidate_id INTEGER UNIQUE, username TEXT, legacy_slug TEXT, password_hash TEXT, token TEXT, token_expires TEXT, last_login TEXT, created_at TEXT)',
        'CREATE TABLE IF NOT EXISTS password_resets (id INTEGER PRIMARY KEY AUTOINCREMENT, acct_type TEXT, acct_id INTEGER, token_hash TEXT, expires_at TEXT, used_at TEXT, created_at TEXT, ip TEXT, requested_by INTEGER)',
        'CREATE INDEX IF NOT EXISTS ix_pwr_token ON password_resets(token_hash)',
        'CREATE TABLE IF NOT EXISTS custom_fields (id INTEGER PRIMARY KEY AUTOINCREMENT, entity TEXT, label TEXT, type TEXT, options LONGTEXT, required INTEGER DEFAULT 0, active INTEGER DEFAULT 1)',
        'CREATE TABLE IF NOT EXISTS custom_values (id INTEGER PRIMARY KEY AUTOINCREMENT, field_id INTEGER, entity TEXT, rec_id INTEGER, value LONGTEXT)',
    ];
    foreach ($sql as $s) db_ddl($s);
    // columns added after v1.0 (older installs get them here)
    db_add_missing('users', ['team' => 'TEXT']);
    db_add_missing('payments', ['section' => 'TEXT']);
    db_add_missing('notifications', ['kind' => 'TEXT']);
}

/** Make sure the schema is current (data-preserving) and the starting data exists. */
function db_ready()
{
    static $done = false;
    if ($done) return;
    $done = true;
    $hash = md5(APP_VERSION . db_driver() . serialize(array_map(function ($d) {
        return [$d['table'], array_map(function ($f) { return $f['type']; }, $d['fields'])];
    }, $GLOBALS['ENT'])));
    $cur = null;
    try { $cur = val("SELECT v FROM settings WHERE k='schema_hash'"); } catch (Exception $e) { $cur = null; }
    if ($cur !== $hash) {
        // MySQL commits schema changes immediately, so no transaction around DDL there
        if (!db_is_mysql()) db()->beginTransaction();
        core_schema();
        ent_migrate();
        db_upsert('settings', ['k' => 'schema_hash', 'v' => $hash], ['v']);
        if (!db_is_mysql()) db()->commit();
    }
    if (!val("SELECT v FROM settings WHERE k='seeded'")) {
        db()->beginTransaction();
        run_seeders();
        db_upsert('settings', ['k' => 'seeded', 'v' => date('c')], ['v']);
        db()->commit();
    }
    if (function_exists('apply_upgrades')) apply_upgrades();
}
