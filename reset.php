<?php
// Choose a new password with a link from the email.
require __DIR__ . '/inc/boot.php';
$token = (string)g('token');
$r = reset_lookup($token);
$a = $r ? reset_account($r['acct_type'], $r['acct_id']) : null;
if ($r && $a && is_post()) {
    $pw = (string)($_POST['password'] ?? '');
    if ($e = password_problem($pw, (string)($_POST['confirm'] ?? ''))) { flash($e, 'bad'); redirect('reset.php?token=' . urlencode($token)); }
    reset_apply($r, $pw);
    portal_start('Password changed', 'Account recovery');
    echo '<div class="card p-login" style="text-align:center">' . icon('check-circle', 44) . '<h2>Password changed</h2><p>You can now sign in with your new password.</p><a class="btn" href="' . e(reset_login_url($r['acct_type'])) . '">Go to sign in</a></div>';
    portal_end();
    exit;
}
portal_start('Reset password', 'Account recovery');
echo '<div class="card p-login"><h2>' . icon('lock', 22) . ' Choose a new password</h2>';
if (!$r || !$a) echo '<div class="alert bad">This reset link is not valid. It may have expired (links last 60 minutes) or already been used.</div><p><a class="btn" href="forgot.php">Request a new link</a></p>';
else {
    echo '<p>Account: <b>' . e($a['name']) . '</b> (' . e(RESET_TYPES[$r['acct_type']]) . ')</p><form method="post">' . csrf_field()
        . '<div class="fld" style="margin-bottom:12px"><label>New password *</label><input type="password" name="password" required minlength="10" autocomplete="new-password"><small class="muted">At least 10 characters, with letters and numbers.</small></div>'
        . '<div class="fld" style="margin-bottom:12px"><label>Confirm new password *</label><input type="password" name="confirm" required minlength="10" autocomplete="new-password"></div><button class="btn" style="width:100%">Save new password</button></form>';
}
echo '</div>';
portal_end();
