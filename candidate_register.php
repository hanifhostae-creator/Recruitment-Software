<?php
// Public candidate self-registration (CV required). Creates a candidate in the HR-assign queue.
require __DIR__ . '/inc/boot.php';
$done = g('done');
if (is_post()) {
    $d = ['full_name' => p('full_name'), 'phone' => p('phone'), 'email' => strtolower(p('email')), 'nationality' => p('nationality'), 'applied_position' => p('applied_position'), 'visa_type' => p('visa_type'), 'experience' => p('experience')];
    $err = [];
    foreach (['full_name' => 'Full name', 'phone' => 'Phone', 'nationality' => 'Nationality', 'applied_position' => 'Position'] as $k => $l) if (!$d[$k]) $err[] = $l . ' is required.';
    if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'The email address is not valid.';
    if (!in_array($d['visa_type'], ['Visit', 'Cancel', 'Employment', 'Family', 'Sponsor', 'Other', ''], true)) $err[] = 'Choose a visa type.';
    if (!$err && (val('SELECT id FROM candidates WHERE phone=? AND archived=0', [$d['phone']]) || ($d['email'] && val('SELECT id FROM candidates WHERE email=? AND archived=0', [$d['email']])))) $err[] = 'You are already registered with us. Please contact Reception or WhatsApp us.';
    list($cv, $e) = upload_file('cv', 'cv', true);
    if ($e) $err[] = 'CV: ' . $e;
    if ($err) { foreach ($err as $x) flash($x, 'bad'); $_SESSION['reg_old'] = $_POST; redirect('candidate_register.php'); }
    $id = ins('candidates', $d + ['ref_no' => next_number('CAN'), 'cv_file' => $cv, 'source' => 'Website', 'through_whom' => 'Self-registration', 'stage' => 'New', 'created_at' => now(), 'archived' => 0]);
    ins('stage_history', ['entity' => 'candidates', 'entity_id' => $id, 'to_stage' => 'New', 'reason' => 'Online self-registration', 'by_name' => 'Candidate', 'at' => now()]);
    audit('self-register', 'candidates', $id, null, $d);
    foreach (rows("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.active=1 AND r.name IN ('Reception','Super Admin')") as $r) notify($r['id'], 'New online registration: ' . $d['full_name'], ent_url('candidates', $id));
    redirect('candidate_register.php?done=' . urlencode(val('SELECT ref_no FROM candidates WHERE id=?', [$id])));
}
$o = $_SESSION['reg_old'] ?? [];
unset($_SESSION['reg_old']);
portal_start('Candidate registration', 'Candidate Registration');
if ($done) echo '<div class="card p-login" style="text-align:center">' . icon('check-circle', 44) . '<h2>Thank you!</h2><p>Your registration number is <b class="ref">' . e($done) . '</b>.</p><p>Our Reception team will contact you to arrange your HR interview. Please bring your passport and CV when you visit.</p><a class="btn" href="candidate_register.php">Register another person</a></div>';
else {
    echo '<div class="card" style="max-width:760px;margin:10px auto"><h2>' . icon('user-plus', 22) . ' Register as a candidate</h2><p class="muted">RH Business Solutions &amp; Marketing Management — Dubai. Fields marked * are required. A CV is required.</p><form method="post" enctype="multipart/form-data">' . csrf_field() . '<div class="grid-form">'
        . '<div class="fld"><label>Full name *</label><input name="full_name" required value="' . e($o['full_name'] ?? '') . '"></div><div class="fld"><label>Phone *</label><input type="tel" name="phone" required value="' . e($o['phone'] ?? '') . '"></div>'
        . '<div class="fld"><label>Email</label><input type="email" name="email" value="' . e($o['email'] ?? '') . '"></div><div class="fld"><label>Nationality *</label><select name="nationality" required><option value="">— Select —</option>';
    foreach (options('nationalities') as $n) echo '<option' . (($o['nationality'] ?? '') === $n ? ' selected' : '') . '>' . e($n) . '</option>';
    echo '</select></div><div class="fld"><label>Position you are applying for *</label><select name="applied_position" required><option value="">— Select —</option>';
    foreach (options('positions') as $n) echo '<option' . (($o['applied_position'] ?? '') === $n ? ' selected' : '') . '>' . e($n) . '</option>';
    echo '</select></div><div class="fld"><label>Visa type</label><select name="visa_type"><option value="">— Select —</option>';
    foreach (['Visit', 'Cancel', 'Employment', 'Family', 'Sponsor', 'Other'] as $n) echo '<option' . (($o['visa_type'] ?? '') === $n ? ' selected' : '') . '>' . $n . '</option>';
    echo '</select></div><div class="fld wide"><label>Experience</label><textarea name="experience" rows="3">' . e($o['experience'] ?? '') . '</textarea></div><div class="fld wide"><label>CV *</label><input type="file" name="cv" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"><small class="muted">PDF, DOC, DOCX, JPG or PNG · max 10 MB</small></div></div>'
        . '<div class="form-actions"><button class="btn">' . icon('check', 15) . ' Submit registration</button></div></form></div>';
}
portal_end();
