<?php
require __DIR__ . '/inc/boot.php';
require_module('settings');
$keys = ['company_name' => 'Company name', 'company_short' => 'Short name', 'company_phone' => 'Phone', 'company_email' => 'Email', 'company_address' => 'Address',
    'site_url' => 'System URL (for example https://crm.yourdomain.com) — used in every email link', 'currency' => 'Default currency', 'leave_entitlement' => 'Annual leave entitlement (days)'];
$mailKeys = ['smtp_host' => 'SMTP server', 'smtp_port' => 'Port', 'smtp_sec' => 'Security', 'smtp_user' => 'SMTP username (the full mailbox address)', 'smtp_from' => 'Send emails from (address)', 'smtp_name' => 'Sender name'];

if (is_post()) {
    if (p('do') === 'testmail') {
        $to = p('test_to');
        $ok = send_email($to, 'RH Management test email', "This is a test email from your RH Management system.\n\nIf you can read this, email sending works and password reset emails will be delivered.", 'Test email');
        flash($ok ? 'Test email sent to ' . e($to) . '. Check the inbox (and the spam folder).' : 'The test email failed: ' . e($GLOBALS['mail_error'] ?? 'unknown error'), $ok ? 'ok' : 'bad');
        redirect('settings.php');
    }
    $old = [];
    foreach ($keys + $mailKeys as $k => $l) { $old[$k] = setting($k); }
    $site = trim(p('site_url'));
    if ($site !== '' && !preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?(/[^\s]*)?$#', $site)) { flash('The System URL must start with https:// and contain only the web address.', 'bad'); redirect('settings.php'); }
    foreach ($keys as $k => $l) set_setting($k, $k === 'site_url' ? rtrim($site, '/') : p($k));
    foreach ($mailKeys as $k => $l) set_setting($k, p($k));
    if ((string)($_POST['smtp_pass'] ?? '') !== '') set_setting('smtp_pass', (string)$_POST['smtp_pass']);
    audit('settings', 'settings', null, $old, array_intersect_key($_POST, $keys + $mailKeys) + (isset($_POST['smtp_pass']) && $_POST['smtp_pass'] !== '' ? ['smtp_pass' => 'changed'] : []));
    flash('Settings saved.');
    redirect('settings.php');
}
page_start('Settings', 'settings');
page_head('Settings', 'settings', 'Company details, email sending and system defaults. WhatsApp is set up under WhatsApp → Integration settings.');
echo '<nav class="tabs"><a href="admin.php?t=options">Custom options</a><a href="admin.php?t=fields">Custom fields</a><a href="admin.php?t=archive">Archive &amp; restore</a><a href="admin.php?t=chat">Chat access</a><a href="staff.php?t=roles">Roles &amp; permissions</a><a class="on" href="settings.php">System settings</a><a href="admin.php?t=backup">Backup &amp; health</a></nav>';
echo '<form method="post" class="card form-card">' . csrf_field() . '<div class="card-h"><h3>' . icon('settings', 17) . ' Company</h3></div><div class="grid-form">';
foreach ($keys as $k => $l) echo '<div class="fld' . ($k === 'site_url' ? ' wide' : '') . '"><label>' . e($l) . '</label><input name="' . $k . '" value="' . e(setting($k)) . '"></div>';
echo '</div><div class="card-h" style="margin-top:22px"><h3>' . icon('mail', 17) . ' Email (password resets, candidate portal links)</h3>' . (setting('smtp_host') ? badge('SMTP configured') : badge('Not configured')) . '</div>'
    . '<p class="muted small">Create a mailbox in hPanel (Emails → Email Accounts), for example <b>no-reply@yourdomain.com</b>, then enter its details. Hostinger: server <code>smtp.hostinger.com</code>, port <code>465</code>, security <b>SSL</b>. Without SMTP the system uses the basic server mail, which often lands in spam.</p><div class="grid-form">';
foreach ($mailKeys as $k => $l) {
    echo '<div class="fld"><label>' . e($l) . '</label>';
    if ($k === 'smtp_sec') { echo '<select name="smtp_sec">'; foreach (['ssl' => 'SSL (port 465)', 'tls' => 'STARTTLS (port 587)'] as $v => $t) echo '<option value="' . $v . '"' . (setting('smtp_sec', 'ssl') === $v ? ' selected' : '') . '>' . $t . '</option>'; echo '</select>'; }
    else echo '<input name="' . $k . '" value="' . e(setting($k)) . '">';
    echo '</div>';
}
echo '<div class="fld"><label>SMTP password</label><input type="password" name="smtp_pass" autocomplete="new-password" placeholder="' . (setting('smtp_pass') ? '•••••••• saved — leave blank to keep' : 'Mailbox password') . '"></div></div>'
    . '<div class="form-actions"><a class="btn ghost" href="whatsapp.php?t=settings">' . icon('chat', 15) . ' WhatsApp settings</a><button class="btn">Save settings</button></div></form>';
echo '<form method="post" class="card form-card">' . csrf_field() . '<input type="hidden" name="do" value="testmail"><div class="card-h"><h3>' . icon('send', 17) . ' Send a test email</h3></div><div class="inline-form"><div><label>Send to</label><input type="email" name="test_to" required value="' . e(me()['email']) . '"></div><button class="btn">Send test email</button></div><p class="muted small" style="margin-top:8px">Save the settings first. Every email is also listed in Email &amp; Notifications → Email log (links inside secure emails are hidden there).</p></form>';
page_end();
