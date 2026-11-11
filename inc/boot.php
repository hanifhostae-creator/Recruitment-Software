<?php
// Bootstrap — every page starts with: require __DIR__ . '/inc/boot.php';
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
define('APP_NAME', 'RH Management');
define('APP_VERSION', '1.2.0');

// Site configuration (database, demo mode, secret key). inc/config.php is never committed — copy inc/config.sample.php.
if (is_file(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
foreach (['DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_KEY'] as $c) if (!defined($c) && getenv('RH_' . $c) !== false) define($c, getenv('RH_' . $c));
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
// Without a config file the system runs as a demo (sample data). With a config file it starts empty unless DEMO_DATA is true.
if (!defined('DEMO_DATA')) define('DEMO_DATA', getenv('RH_DEMO_DATA') !== false ? getenv('RH_DEMO_DATA') === '1' : !is_file(__DIR__ . '/config.php'));
if (!defined('SESSION_IDLE_MINUTES')) define('SESSION_IDLE_MINUTES', 120);

$need = DB_DRIVER === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite';
if (!extension_loaded($need)) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Hosting setup needed</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#12204a">'
       . '<h1>One hosting setting is missing</h1><p>The PHP extension <b>' . $need . '</b> is not enabled on this hosting account.</p><ol>'
       . '<li>Open the hosting panel (hPanel: Websites → Manage → Advanced → PHP Configuration).</li>'
       . '<li>Choose PHP 8.2 and open the <b>PHP extensions</b> tab.</li>'
       . '<li>Tick <b>' . $need . '</b> and save, then reload this page.</li></ol></body>';
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/data/error.log');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
date_default_timezone_set('Asia/Dubai');

set_exception_handler(function ($ex) {
    error_log('[' . date('c') . '] ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#12204a">'
       . '<h1>Something went wrong</h1><p>The request could not be completed. The error has been recorded for the administrator.</p>'
       . (getenv('RH_DEBUG') ? '<pre>' . htmlspecialchars($ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine()) . '</pre>' : '')
       . '<p><a href="index.php">Back to dashboard</a></p></body>';
});

/** Per-request nonce for the few inline scripts (Content-Security-Policy). */
function csp_nonce()
{
    static $n = null;
    if ($n === null) $n = base64_encode(random_bytes(16));
    return $n;
}

// Security headers. No header reveals the software or language behind the system.
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('sid');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
    // sign out after a period without activity (the live-notification check does not count as activity)
    if (!empty($_SESSION['uid']) || !empty($_SESSION['portal'])) {
        if (isset($_SESSION['_last']) && time() - $_SESSION['_last'] > SESSION_IDLE_MINUTES * 60) {
            $_SESSION = ['flash' => [['warn', 'You were signed out after a period of inactivity. Please sign in again.']]];
            session_regenerate_id(true);
        } elseif (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'poll.php') $_SESSION['_last'] = time();
    }
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
