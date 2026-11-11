<?php
// First run on a live (empty) install: create the Super Admin account. Disabled as soon as any user exists.
require __DIR__ . '/inc/boot.php';
if ((int)val('SELECT COUNT(*) FROM users')) redirect('login.php');
if (is_post()) {
    $d = ['name' => p('name'), 'username' => strtolower(p('username')), 'email' => strtolower(p('email'))];
    $err = [];
    if (!$d['name'] || !$d['username'] || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Name, username and a valid email are required.';
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $d['username'])) $err[] = 'The username may use letters, numbers, dot, dash and underscore (3–40 characters).';
    if ($e = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''))) $err[] = $e;
    $site = rtrim(trim(p('site_url')), '/');
    if ($site !== '' && !preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?(/[^\s]*)?$#', $site)) $err[] = 'The System URL must look like https://crm.yourdomain.com';
    if ($err) { foreach ($err as $x) flash($x, 'bad'); redirect('setup.php'); }
    $rid = (int)val('SELECT id FROM roles WHERE is_super=1 ORDER BY id LIMIT 1');
    $id = ins('users', $d + ['role_id' => $rid, 'team' => 'Management', 'password_hash' => password_hash((string)$_POST['password'], PASSWORD_DEFAULT), 'must_change' => 0, 'active' => 1, 'created_at' => now()]);
    if (p('company')) set_setting('company_name', p('company'));
    if ($site !== '') set_setting('site_url', $site);
    audit('setup', 'users', $id, null, ['username' => $d['username']], 'First Super Admin created');
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    flash('Welcome! Your system is ready. Next: Settings → Email, then Staff & Roles to add your team.');
    redirect('index.php');
}
portal_start('Set up', 'First-time setup');
echo '<div class="card" style="max-width:560px;margin:10px auto"><h2>' . icon('shield', 22) . ' Create the Super Admin</h2><p class="muted">This screen appears only once, on a new empty system. The account you create here controls everything.</p><form method="post">' . csrf_field() . '<div class="grid-form">'
    . '<div class="fld wide"><label>Company name</label><input name="company" value="' . e(setting('company_name')) . '"></div>'
    . '<div class="fld"><label>Your full name *</label><input name="name" required></div><div class="fld"><label>Email *</label><input type="email" name="email" required></div>'
    . '<div class="fld"><label>Username *</label><input name="username" required autocomplete="username"></div><div class="fld"><label>System URL</label><input name="site_url" value="' . e(rtrim(app_url(), '/')) . '"></div>'
    . '<div class="fld"><label>Password *</label><input type="password" name="password" required minlength="10" autocomplete="new-password"><small class="muted">At least 10 characters with letters and numbers.</small></div><div class="fld"><label>Confirm password *</label><input type="password" name="confirm" required minlength="10" autocomplete="new-password"></div>'
    . '</div><div class="form-actions"><button class="btn">' . icon('check', 15) . ' Create and sign in</button></div></form></div>';
portal_end();
