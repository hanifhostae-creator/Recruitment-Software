<?php
// Bootstrap — every page starts with: require __DIR__ . '/inc/boot.php';
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
define('APP_NAME', 'RH Management');
define('APP_VERSION', '1.1.0');

if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Hosting setup needed</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#12204a">'
       . '<h1>One hosting setting is missing</h1><p>The system stores its data in an SQLite database, but the PHP '
       . '<b>pdo_sqlite</b> extension is not enabled on this hosting account.</p><ol>'
       . '<li>Open your hosting control panel (cPanel / DirectAdmin / Plesk).</li>'
       . '<li>Go to <b>Select PHP Version</b> (or PHP Settings) and choose PHP 8.1 or 8.2.</li>'
       . '<li>Tick the <b>pdo_sqlite</b> extension (and <b>sqlite3</b>) and save.</li>'
       . '<li>Reload this page.</li></ol></body>';
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/data/error.log');
date_default_timezone_set('Asia/Dubai');

set_exception_handler(function ($ex) {
    error_log('[' . date('c') . '] ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#12204a">'
       . '<h1>Something went wrong</h1><p>The request could not be completed. The error has been logged for the administrator.</p>'
       . (getenv('RH_DEBUG') ? '<pre>' . htmlspecialchars($ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine()) . '</pre>' : '')
       . '<p><a href="index.php">Back to dashboard</a></p></body>';
});

if (session_status() === PHP_SESSION_NONE) {
    session_name('RHMS');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/reset.php';
require_once __DIR__ . '/entity.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/registry.php';

registry_load();
db_ready();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('NO_CSRF')) csrf_check();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_att'])) attendance_handle();
