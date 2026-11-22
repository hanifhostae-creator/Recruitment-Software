<?php
// Public candidate self-registration (link sent with the interview invitation). The candidate appears in Reception's HR-assign queue.
require __DIR__ . '/inc/boot.php';
$done = g('done');
$F = entdef('candidates')['fields'];
$F['email']['label'] = 'Email';
$F['cv_file']['label'] = 'Your CV';
$keys = ['cv_file', 'full_name', 'phone', 'whatsapp', 'email', 'nationality', 'applied_position', 'visa_type', 'visa_expiry', 'passport_no', 'dob', 'qualification', 'experience', 'skills'];
if (is_post()) {
    if ((int)val("SELECT COUNT(*) FROM audit WHERE action='self-register' AND ip=? AND at>=?", [client_ip(), date('Y-m-d H:i:s', time() - 3600)]) >= 30) { flash('Too many registrations from this connection. Please try again later.', 'bad'); redirect('candidate_register.php'); }
    $d = [];
    foreach ($keys as $k) if ($k !== 'cv_file') $d[$k] = mb_substr(trim((string)p($k)), 0, $F[$k]['type'] === 'textarea' ? 2000 : 150);
    $d['email'] = strtolower($d['email']);
    $err = [];
    foreach (['full_name' => 'Full name', 'phone' => 'Phone', 'nationality' => 'Nationality', 'applied_position' => 'Position'] as $k => $l) if ($d[$k] === '') $err[] = $l . ' is required.';
    if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'The email address is not valid.';
    if ($d['visa_type'] && !in_array($d['visa_type'], ['Visit', 'Cancel', 'Employment', 'Family', 'Sponsor', 'Other'], true)) $err[] = 'Choose a visa type.';
    foreach (['visa_expiry', 'dob'] as $k) if ($d[$k] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d[$k])) $d[$k] = '';
    if (!$err && (val('SELECT id FROM candidates WHERE phone=? AND archived=0', [$d['phone']]) || ($d['email'] && val('SELECT id FROM candidates WHERE email=? AND archived=0', [$d['email']])) || ($d['passport_no'] && val('SELECT id FROM candidates WHERE passport_no=? AND archived=0', [$d['passport_no']]))))
        $err[] = 'You are already registered with us. Please contact Reception or send us a WhatsApp message.';
    list($cv, $e) = upload_file('cv_file', 'cv');
    if ($e) $err[] = 'CV: ' . $e;
    if ($err) { foreach ($err as $x) flash(e($x), 'bad'); $_SESSION['reg_old'] = $_POST; redirect('candidate_register.php'); }
    $d = array_filter($d, function ($v) { return $v !== ''; });
    $id = ins('candidates', $d + ['ref_no' => next_number('CAN'), 'cv_file' => $cv, 'source' => 'Website', 'through_whom' => 'Online registration link', 'stage' => 'New', 'created_at' => now(), 'archived' => 0]);
    ins('stage_history', ['entity' => 'candidates', 'entity_id' => $id, 'to_stage' => 'New', 'reason' => 'Online self-registration', 'by_name' => 'Candidate', 'at' => now()]);
    audit('self-register', 'candidates', $id, null, $d);
    $v = ['name' => $d['full_name'], 'position' => $d['applied_position']];
    notify_role('Reception', voice_text('reception_new', $v), ent_url('candidates', $id), 'lead', 'reception_new', $v);
    notify_super(voice_text('reception_new', $v), ent_url('candidates', $id), 'entry', 'reception_new', $v);
    $_SESSION['reg_done'] = val('SELECT ref_no FROM candidates WHERE id=?', [$id]);
    redirect('candidate_register.php?done=1');
}
$o = $_SESSION['reg_old'] ?? [];
unset($_SESSION['reg_old']);
if (!$o && g('pos')) $o['applied_position'] = g('pos');
portal_start('Candidate registration', 'Candidate Registration');
if ($done && !empty($_SESSION['reg_done'])) {
    $ref = $_SESSION['reg_done'];
    echo '<div class="card reg-done"><div class="ap-ico">&#10003;</div><h2>Thank you, your registration is complete</h2><p>Your registration number is <b class="ref">' . e($ref) . '</b>. Please keep it.</p><p class="muted">Our Reception team will contact you about your interview. Please bring your passport and CV when you come.</p><a class="btn ghost" href="candidate_register.php">Register another person</a></div>';
} else {
    $val = function ($k) use ($o) { return $o[$k] ?? null; };
    $fld = function ($k, $wide = false, $req = null) use ($F, $val) { $f = $F[$k]; if ($req !== null) { if ($req) $f['req'] = true; else unset($f['req']); } return '<div class="fld' . ($wide ? ' wide' : '') . '">' . field_input($k, $f, $val($k)) . '</div>'; };
    echo '<div class="reg-wrap"><div class="reg-hero"><span class="reg-ico">' . icon('user-plus', 26) . '</span><div><h1>Register for your interview</h1><p>RH Business Solutions &amp; Marketing Management · Dubai. It takes about 2 minutes. Fields marked * are required.</p></div></div>'
        . '<form method="post" enctype="multipart/form-data" class="card reg-form">' . csrf_field()
        . '<div class="reg-step"><span>1</span><b>Your CV</b><small>Optional — add it and most of the form fills itself.</small></div><div class="grid-form">' . $fld('cv_file', true) . '</div>'
        . '<div class="reg-step"><span>2</span><b>Your details</b></div><div class="grid-form">' . $fld('full_name') . $fld('phone') . $fld('whatsapp') . $fld('email') . $fld('nationality') . $fld('applied_position') . '</div>'
        . '<div class="reg-step"><span>3</span><b>Visa &amp; passport</b><small>Optional</small></div><div class="grid-form">' . $fld('visa_type') . $fld('visa_expiry') . $fld('passport_no') . $fld('dob') . '</div>'
        . '<div class="reg-step"><span>4</span><b>Experience</b><small>Optional</small></div><div class="grid-form">' . $fld('qualification', true) . $fld('experience', true) . $fld('skills', true) . '</div>'
        . '<div class="form-actions"><button class="btn big">' . icon('check', 16) . ' Submit my registration</button></div><p class="muted small center">Your details are used only for your job application with RH Management.</p></form></div>';
}
portal_end();
