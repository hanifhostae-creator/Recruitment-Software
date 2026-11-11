<?php
// Staff authentication, role permissions and portal sessions.

const ACTIONS = ['add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete / archive', 'approve' => 'Approve / decide', 'assign' => 'Assign / deploy',
    'export' => 'Export', 'finance' => 'Finance posting', 'config' => 'Configuration'];
/** Screens every staff user may open. */
const PERSONAL_MODULES = ['dashboard', 'mydash', 'search', 'tasks', 'notifications', 'messenger', 'calendar', 'selfservice', 'chat'];
/** Screens only the Super Admin role may open. */
const SUPER_ONLY = ['admin', 'audit', 'settings', 'staff'];

/** Logged-in staff user (with role, modules, actions) or null. */
function me()
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT u.*, r.name AS role_name, r.modules, r.actions, r.is_super FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1', [$_SESSION['uid']]);
        if ($u) {
            $u['mods'] = json_decode($u['modules'] ?: '[]', true) ?: [];
            $u['acts'] = json_decode($u['actions'] ?: '{}', true) ?: [];
        } else unset($_SESSION['uid']);
    }
    return $u;
}
function is_super() { $u = me(); return $u && (int)$u['is_super'] === 1; }

/** Can the current user open a module (and optionally perform an action in it)? */
function can($mod, $act = null)
{
    $u = me();
    if (!$u) return false;
    if (is_super()) return true;
    if (in_array($mod, SUPER_ONLY, true)) return false;
    if ($act === null && in_array($mod, PERSONAL_MODULES, true)) return true;
    if (!in_array($mod, $u['mods'], true) && !in_array($mod, PERSONAL_MODULES, true)) return false;
    if ($act === null) return true;
    if (in_array($mod, PERSONAL_MODULES, true) && in_array($act, ['add', 'edit'], true)) return true;
    return in_array($act, $u['acts'][$mod] ?? [], true);
}

function require_login()
{
    if (!me()) redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    if ((int)me()['must_change'] === 1 && basename($_SERVER['SCRIPT_NAME']) !== 'login.php') redirect('login.php?a=change');
}
function require_module($mod) { require_login(); if (!can($mod)) deny('Your role does not include the ' . module_label($mod) . ' module.'); }
function require_action($mod, $act) { require_login(); if (!can($mod, $act)) deny('Your role is not allowed to ' . strtolower(ACTIONS[$act] ?? $act) . ' in ' . module_label($mod) . '.'); }

function deny($msg = 'You do not have permission to open this page.')
{
    http_response_code(403);
    audit('denied', null, null, null, ['url' => $_SERVER['REQUEST_URI'] ?? '', 'msg' => $msg]);
    if (me() && function_exists('page_start')) {
        page_start('Access denied');
        echo '<div class="empty big">' . icon('lock', 40) . '<h2>Access denied</h2><p>' . e($msg) . '</p><p class="muted">Ask the Super Admin to tick this module or action for your role in Staff &amp; Roles.</p><a class="btn" href="index.php">Back to dashboard</a></div>';
        page_end();
    } else echo '<h1>Access denied</h1><p>' . e($msg) . '</p>';
    exit;
}

/** Too many failed sign-ins from this network address in the last 15 minutes (staff and portals). */
function too_many_attempts()
{
    return (int)val("SELECT COUNT(*) FROM audit WHERE action IN ('login-failed','portal-login-failed') AND ip=? AND at>=?", [$_SERVER['REMOTE_ADDR'] ?? 'cli', date('Y-m-d H:i:s', time() - 900)]) >= 10;
}
function login_attempt($username, $password)
{
    if (too_many_attempts()) return 'Too many failed attempts from your network. Please wait 15 minutes and try again.';
    $key = 'fail_' . md5(strtolower($username));
    if (($_SESSION[$key] ?? 0) >= 5 && (time() - ($_SESSION[$key . '_t'] ?? 0)) < 300) return 'Too many failed attempts. Please wait 5 minutes and try again.';
    $u = row('SELECT * FROM users WHERE lower(username)=lower(?)', [$username]);
    if (!$u || !password_verify($password, (string)$u['password_hash'])) {
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        $_SESSION[$key . '_t'] = time();
        audit('login-failed', 'users', $u['id'] ?? null, null, ['username' => $username]);
        return 'The username or password is not correct.';
    }
    if (!(int)$u['active']) return 'This account is inactive. Contact the administrator.';
    unset($_SESSION[$key]);
    unset($_SESSION['portal']);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    q('UPDATE users SET last_login=? WHERE id=?', [now(), $u['id']]);
    audit('login', 'users', $u['id']);
    return null;
}

/** Password rules used for staff and portal accounts. */
function password_problem($pw, $confirm = null)
{
    if (strlen($pw) < 10) return 'The password must be at least 10 characters.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'The password must contain letters and numbers.';
    if ($confirm !== null && $pw !== $confirm) return 'The two passwords do not match.';
    return null;
}

// ---------------------------------------------------------------- portals (separate logins, never staff access)

function portal_login($type, $id, $name)
{
    session_regenerate_id(true);
    unset($_SESSION['uid']);
    $_SESSION['portal'] = ['type' => $type, 'id' => (int)$id, 'name' => $name];
    audit('portal-login', $type, $id);
}
/** Returns the portal account id for $type or redirects to that portal's login. */
function require_portal($type, $loginUrl)
{
    if (empty($_SESSION['portal']) || $_SESSION['portal']['type'] !== $type) redirect($loginUrl);
    return (int)$_SESSION['portal']['id'];
}
function portal_logout() { unset($_SESSION['portal']); session_regenerate_id(true); }
