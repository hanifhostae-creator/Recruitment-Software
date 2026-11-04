<?php
require __DIR__ . '/inc/boot.php';
require_module('settings');
$keys = ['company_name' => 'Company name', 'company_short' => 'Short name', 'company_phone' => 'Phone', 'company_email' => 'Email', 'company_address' => 'Address', 'site_url' => 'System URL (e.g. https://crm.rhmanagements.com)', 'currency' => 'Default currency', 'leave_entitlement' => 'Annual leave entitlement (days)'];
if (is_post()) {
    $old = [];
    foreach ($keys as $k => $l) { $old[$k] = setting($k); set_setting($k, p($k)); }
    audit('settings', 'settings', null, $old, array_intersect_key($_POST, $keys));
    flash('Settings saved.');
    redirect('settings.php');
}
page_start('Settings', 'settings');
page_head('Settings', 'settings', 'Company details and system defaults. WhatsApp is set up under WhatsApp → Integration settings.');
echo '<nav class="tabs"><a href="admin.php?t=options">Custom options</a><a href="admin.php?t=fields">Custom fields</a><a href="admin.php?t=archive">Archive &amp; restore</a><a href="staff.php?t=roles">Roles &amp; permissions</a><a class="on" href="settings.php">System settings</a><a href="admin.php?t=backup">Backup &amp; health</a></nav>';
echo '<form method="post" class="card form-card">' . csrf_field() . '<div class="grid-form">';
foreach ($keys as $k => $l) echo '<div class="fld"><label>' . e($l) . '</label><input name="' . $k . '" value="' . e(setting($k)) . '"></div>';
echo '</div><div class="form-actions"><a class="btn ghost" href="whatsapp.php?t=settings">' . icon('chat', 15) . ' WhatsApp settings</a><button class="btn">Save settings</button></div></form>';
page_end();
