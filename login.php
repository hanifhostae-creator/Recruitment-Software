<?php
require __DIR__ . '/inc/boot.php';

// ---- change password (forced on first login)
if (g('a') === 'change') {
    require_login();
    $u = me();
    if (is_post()) {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $u['password_hash'])) flash('Your current password is not correct.', 'bad');
        elseif ($err = password_problem($new, (string)($_POST['confirm'] ?? ''))) flash($err, 'bad');
        elseif (password_verify($new, $u['password_hash'])) flash('Choose a new password that is different from the current one.', 'bad');
        else {
            q('UPDATE users SET password_hash=?, must_change=0 WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            audit('password-change', 'users', $u['id']);
            flash('Password changed. Welcome, ' . e($u['name']) . '!');
            redirect('index.php');
        }
        redirect('login.php?a=change');
    }
    html_head('Change password');
    echo '<body><div class="auth"><div class="auth-art"><a class="brand" href="index.php"><img src="assets/img/rh-mark.png" alt=""><span><b>RH Management</b><small>Business Management System</small></span></a><div><h1>Secure your <span>account</span></h1><p>' . ((int)$u['must_change'] ? 'This is your first login (or your password was reset). Please set your own password before continuing.' : 'Choose a strong password you do not use anywhere else.') . '</p></div><p class="small">Passwords need at least 8 characters with letters and numbers.</p></div>'
        . '<div class="auth-form"><div class="auth-box"><h2>Change password</h2><p class="muted">Signed in as <b>' . e($u['username']) . '</b></p>';
    foreach (flash() as $f) echo '<div class="alert ' . e($f[0]) . '">' . $f[1] . '</div>';
    echo '<form method="post">' . csrf_field() . '<div><label>Current password</label><input type="password" name="current" required autocomplete="current-password"></div><div><label>New password</label><input type="password" name="new" required minlength="8" autocomplete="new-password"></div><div><label>Confirm new password</label><input type="password" name="confirm" required minlength="8" autocomplete="new-password"></div><button class="btn">' . icon('lock', 16) . ' Save new password</button></form>'
        . ((int)$u['must_change'] ? '<p class="small muted" style="margin-top:14px"><a href="logout.php">Log out</a></p>' : '<p class="small" style="margin-top:14px"><a href="index.php">Back to dashboard</a></p>') . '</div></div></div><script src="assets/app.js"></script></body></html>';
    exit;
}

if (me()) redirect('index.php');
if (is_post()) {
    $err = login_attempt(p('username'), (string)($_POST['password'] ?? ''));
    if ($err) { flash($err, 'bad'); redirect('login.php'); }
    $next = g('next');
    redirect($next && strpos($next, '//') === false && $next[0] === '/' ? $next : 'index.php');
}
html_head('Sign in');
echo '<body><div class="auth"><div class="auth-art"><a class="brand" href="login.php"><img src="assets/img/rh-mark.png" alt=""><span><b>RH Management</b><small>RH Business Solutions &amp; Marketing Management</small></span></a>'
    . '<div><h1>One system for <span>recruitment, people and money.</span></h1><p>Reception, HR, deployment, outside hiring, finance, CRM, HRM, travel and more — with every candidate, file, demand and payment traceable end to end.</p></div>'
    . '<div class="auth-feats"><div>' . icon('users', 18) . ' Inside &amp; outside hiring</div><div>' . icon('dollar', 18) . ' Fees, receipts &amp; accounting</div><div>' . icon('shield', 18) . ' Role permissions &amp; audit</div><div>' . icon('globe', 18) . ' Candidate, client &amp; agent portals</div></div></div>'
    . '<div class="auth-form"><div class="auth-box"><h2>Staff sign in</h2><p class="muted">Use your staff username and password.</p>';
foreach (flash() as $f) echo '<div class="alert ' . e($f[0]) . '">' . $f[1] . '</div>';
echo '<form method="post">' . csrf_field() . '<div><label for="u">Username</label><input id="u" name="username" required autofocus autocomplete="username"></div><div><label for="pw">Password</label><input id="pw" type="password" name="password" required autocomplete="current-password"></div><button class="btn">' . icon('login', 16) . ' Sign in</button></form>'
    . '<div class="portal-links"><span class="muted small">Portals:</span><a href="portal.php">Candidate portal</a><a href="client_portal.php">Client portal</a><a href="agent_portal.php">Agent portal</a><a href="candidate_register.php">Register as a candidate</a></div></div></div></div><script src="assets/app.js"></script></body></html>';
