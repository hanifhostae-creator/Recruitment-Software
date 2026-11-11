<?php
// "Forgot password" for staff and the Candidate, Client and Agent portals. The answer is always the same,
// so nobody can find out which usernames or emails exist.
require __DIR__ . '/inc/boot.php';
$t = g('t', 'staff');
if (!isset(RESET_TYPES[$t])) $t = 'staff';
$done = false;
$job = null;
if (is_post()) {
    $done = true;
    $hour = date('Y-m-d H:i:s', time() - 3600);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $attempts = (int)val("SELECT COUNT(*) FROM audit WHERE action='password-forgot' AND ip=? AND at>=?", [$ip, $hour]);
    if ($attempts < 15) {
        $a = reset_find($t, p('ident'));
        if ($a && !empty($a['email']) && (int)val('SELECT COUNT(*) FROM password_resets WHERE acct_type=? AND acct_id=? AND created_at>=?', [$t, $a['id'], $hour]) < 3) $job = $a['id'];
    }
    audit('password-forgot', null, null, null, ['type' => $t]);
}
portal_start('Forgot password', 'Account recovery');
echo '<div class="card p-login"><h2>' . icon('lock', 22) . ' Forgot your password?</h2><p class="muted">' . e(RESET_TYPES[$t]) . ' account</p>';
if ($done) {
    echo '<div class="alert ok">If an account with that username or email exists and has an email address, we have sent a link to reset the password. The link works once and expires in 60 minutes. Please also check your spam folder.</div>'
        . '<p class="small">No email? Ask an administrator to send you a new link.</p><p><a class="btn ghost" href="' . e(reset_login_url($t)) . '">Back to sign in</a></p>';
} else {
    echo '<p>Enter your ' . ($t === 'staff' ? 'username or ' : '') . 'email address and we will email you a link to choose a new password.</p><form method="post">' . csrf_field()
        . '<div class="fld" style="margin-bottom:12px"><label>' . ($t === 'staff' ? 'Username or email' : 'Username or email address') . '</label><input name="ident" required autofocus autocomplete="username"></div><button class="btn" style="width:100%">' . icon('mail', 16) . ' Email me a reset link</button></form>'
        . '<p class="small" style="margin-top:14px"><a href="' . e(reset_login_url($t)) . '">Back to sign in</a></p>';
    $others = array_diff_key(RESET_TYPES, [$t => 1]);
    echo '<p class="small muted">Other accounts: ';
    $links = [];
    foreach ($others as $k => $l) $links[] = '<a href="forgot.php?t=' . $k . '">' . e($l) . '</a>';
    echo implode(' · ', $links) . '</p>';
}
echo '</div>';
portal_end();
if ($job) {
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    else @flush();
    reset_send($t, $job);
}
