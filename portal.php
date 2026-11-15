<?php
// Candidate Portal — separate login, candidate sees only their own file.
require __DIR__ . '/inc/boot.php';

if (g('logout')) { portal_logout(); flash('You have logged out.'); redirect('portal.php'); }

// ---- registration link (32-character token, valid 7 days)
if (g('register') !== '') {
    $l = row('SELECT * FROM candidate_logins WHERE token=?', [g('register')]);
    $valid = $l && $l['token_expires'] >= now();
    $c = $valid ? row('SELECT * FROM candidates WHERE id=?', [$l['candidate_id']]) : null;
    if (is_post() && $valid && $c) {
        $pw = (string)($_POST['password'] ?? '');
        if ($e = password_problem($pw, (string)($_POST['confirm'] ?? ''))) { flash($e, 'bad'); redirect('portal.php?register=' . urlencode(g('register'))); }
        if (!$l['username']) { flash('Your email or phone number is missing on your file. Please ask the deployment team to add it, then use the link again.', 'bad'); redirect('portal.php?register=' . urlencode(g('register'))); }
        q('UPDATE candidate_logins SET password_hash=?, token=NULL, token_expires=NULL WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $l['id']]);
        portal_login('candidate', $c['id'], $c['full_name']);
        audit('portal-registered', 'candidates', $c['id']);
        foreach (chat_participants($c['id']) as $pid) notify_msg($pid, 'deploy_portal', ['name' => $c['full_name'], 'position' => $c['applied_position']], 'chat.php?c=' . $c['id']);
        flash('Your password is set. Welcome to your portal!');
        redirect('portal.php');
    }
    portal_start('Complete registration', 'Candidate Portal');
    echo '<div class="card p-login"><h2>' . icon('user-plus', 22) . ' Complete your registration</h2>';
    if (!$valid || !$c) echo '<div class="alert bad">This registration link is not valid or has expired (links last 7 days). Please ask the RH Management deployment team for a new link.</div>';
    else {
        echo '<p>Hello <b>' . e($c['full_name']) . '</b> (' . e($c['ref_no']) . '). Choose a password for your candidate portal.</p><form method="post">' . csrf_field()
            . '<div class="fld" style="margin-bottom:12px"><label>Your username</label><input value="' . e($l['username'] ?: 'Missing — contact the deployment team') . '" readonly><small class="muted">Use it with your password to sign in next time.</small></div>'
            . '<div class="fld" style="margin-bottom:12px"><label>Password *</label><input type="password" name="password" required minlength="10" autocomplete="new-password"></div><div class="fld" style="margin-bottom:12px"><label>Confirm password *</label><input type="password" name="confirm" required minlength="10" autocomplete="new-password"></div>'
            . '<button class="btn" style="width:100%">Set password and sign in</button></form><p class="muted small" style="margin-top:10px">Link valid until ' . e(dtfmt($l['token_expires'])) . '.</p>';
    }
    echo '</div>';
    portal_end();
    exit;
}

// ---- login
if (empty($_SESSION['portal']) || $_SESSION['portal']['type'] !== 'candidate') {
    if (is_post()) {
        $key = 'pfail_' . md5(p('mode') . p('username') . p('file_no'));
        if (too_many_attempts() || ($_SESSION[$key] ?? 0) >= 5) { flash('Too many attempts. Please wait a few minutes.', 'bad'); redirect('portal.php'); }
        $cid = null;
        if (p('mode') === 'legacy') {
            $f = row('SELECT f.candidate_id, c.phone FROM inside_files f JOIN candidates c ON c.id=f.candidate_id WHERE upper(f.ref_no)=upper(?)', [p('file_no')]);
            $norm = function ($x) { return substr(preg_replace('/\D/', '', (string)$x), -9); };
            if ($f && $norm($f['phone']) !== '' && $norm($f['phone']) === $norm(p('phone'))) $cid = (int)$f['candidate_id'];
        } else {
            $user = strtolower(trim(p('username')));
            $digits = preg_replace('/\D/', '', $user);
            $l = row('SELECT * FROM candidate_logins WHERE lower(username)=? OR lower(legacy_slug)=?' . (strlen($digits) >= 7 ? ' OR username=?' : ''), strlen($digits) >= 7 ? [$user, $user, $digits] : [$user, $user]);
            if ($l && $l['password_hash'] && password_verify((string)($_POST['password'] ?? ''), $l['password_hash'])) $cid = (int)$l['candidate_id'];
        }
        if ($cid) { $c = row('SELECT * FROM candidates WHERE id=?', [$cid]); unset($_SESSION[$key]); portal_login('candidate', $cid, $c['full_name']); q('UPDATE candidate_logins SET last_login=? WHERE candidate_id=?', [now(), $cid]); redirect('portal.php'); }
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        audit('portal-login-failed', 'candidates', null, null, ['mode' => p('mode')]);
        flash('The login details are not correct.', 'bad');
        redirect('portal.php' . (p('mode') === 'legacy' ? '?legacy=1' : ''));
    }
    portal_start('Candidate login', 'Candidate Portal');
    $legacy = g('legacy') === '1';
    echo '<div class="card p-login"><h2>' . icon('user', 22) . ' Candidate portal</h2><nav class="tabs"><a class="' . (!$legacy ? 'on' : '') . '" href="portal.php">Password</a><a class="' . ($legacy ? 'on' : '') . '" href="portal.php?legacy=1">File number &amp; phone</a></nav><form method="post">' . csrf_field();
    if ($legacy) echo '<input type="hidden" name="mode" value="legacy"><div class="fld" style="margin-bottom:12px"><label>File number</label><input name="file_no" required placeholder="IN-FILE-2026-00001"></div><div class="fld" style="margin-bottom:12px"><label>Phone number on your file</label><input name="phone" required type="tel"></div>';
    else echo '<input type="hidden" name="mode" value="email"><div class="fld" style="margin-bottom:12px"><label>Email or phone number</label><input name="username" required autocomplete="username" inputmode="email"></div><div class="fld" style="margin-bottom:12px"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>';
    echo '<button class="btn" style="width:100%">' . icon('login', 16) . ' Sign in</button></form><p class="small" style="margin-top:14px"><a href="forgot.php?t=candidate">Forgot your password?</a></p><p class="muted small">New? Your deployment officer gives you a registration link. Not registered yet? <a href="candidate_register.php">Register as a candidate</a>.</p><p class="small"><a href="login.php">Staff login</a></p></div>';
    portal_end();
    exit;
}

// ---- signed-in candidate
$cid = require_portal('candidate', 'portal.php');
$c = row('SELECT * FROM candidates WHERE id=?', [$cid]);
$f = candidate_file($cid);
$m = file_money($f);
$pg = g('p', 'home');
if ($pg === 'help') $pg = 'chat';
if ($pg === 'chat' && g('frag')) { q("UPDATE chat_messages SET read_at=? WHERE candidate_id=? AND sender_type='Staff' AND read_at IS NULL", [now(), $cid]); echo chat_messages_html($cid, 'candidate'); exit; }

if (is_post()) {
    if ($pg === 'locations') {
        $loc = row('SELECT * FROM interview_locations WHERE id=? AND candidate_id=?', [(int)p('loc'), $cid]);
        $resp = p('response');
        if (!$loc || !in_array($resp, ['Selected', 'Not Selected', 'Issue'], true)) flash('Choose a response.', 'bad');
        elseif ($resp !== 'Selected' && !p('note')) flash('Please add a note for Not Selected or Issue.', 'bad');
        else {
            upd('interview_locations', $loc['id'], ['response' => $resp, 'response_note' => p('note'), 'responded_at' => now(), 'updated_at' => now()]);
            audit('candidate-response', 'interview_locations', $loc['id'], ['response' => $loc['response']], ['response' => $resp], p('note'));
            foreach (chat_participants($cid) ?: array_filter([$c['deploy_officer']]) as $pid) notify_msg($pid, 'deploy_response', ['name' => $c['full_name'], 'seq' => ordinal($loc['seq']), 'response' => $resp], 'chat.php?c=' . $cid);
            flash('Thank you — your response was sent to the deployment team.');
        }
        redirect(p('back') === 'home' ? 'portal.php' : 'portal.php?p=locations');
    }
    if ($pg === 'chat') {
        $parts = chat_participants($cid);
        $body = mb_substr(p('body'), 0, 4000);
        list($path, $err) = upload_file('file', 'chat');
        if (!$parts) flash('The chat opens when your file is assigned to the deployment team.', 'bad');
        elseif ($err) flash($err, 'bad');
        elseif ($body === '' && !$path) flash('Write a message or attach a file.', 'bad');
        else {
            chat_add($cid, 'Candidate', $cid, $body, $path);
            if ($path) ins('documents', ['ref_no' => next_number('DOC'), 'doc_type' => 'Other', 'doc_type_other' => 'Sent in chat', 'candidate_id' => $cid, 'status' => 'Received', 'file' => $path, 'created_at' => now(), 'archived' => 0]);
            foreach ($parts as $pid) notify_msg($pid, 'deploy_chat', ['name' => $c['full_name']], 'chat.php?c=' . $cid);
        }
        redirect('portal.php#chat');
    }
    if ($pg === 'profile') {
        $d = ['whatsapp' => p('whatsapp'), 'skills' => p('skills'), 'experience' => p('experience'), 'preferred_positions' => p('preferred_positions'), 'qualification' => p('qualification'), 'updated_at' => now()];
        $old = array_intersect_key($c, $d);
        upd('candidates', $cid, $d);
        audit('portal-profile', 'candidates', $cid, $old, $d);
        flash('Your profile was updated.');
        redirect('portal.php?p=profile');
    }
}

if ($pg === 'chat') $pg = 'home';
if ($pg === 'home') q("UPDATE chat_messages SET read_at=? WHERE candidate_id=? AND sender_type='Staff' AND read_at IS NULL", [now(), $cid]);
$pending = rows("SELECT * FROM interview_locations WHERE candidate_id=? AND archived=0 AND response='Pending' ORDER BY seq", [$cid]);
portal_start('My portal', 'Candidate Portal', ['home' => ['Chat', 'chat'], 'locations' => ['Interviews', 'map-pin', count($pending)], 'payments' => ['Payments', 'dollar'], 'profile' => ['Profile', 'user']], $pg, 'portal.php?logout=1');
$ci = (int)array_search($c['stage'], CAND_STAGES, true);
$stepLabel = ['New' => 'Registered', 'HR Assigned' => 'Waiting for HR interview', 'Interviewed' => 'Interview done', 'File Opened' => 'File opened', 'In Deployment' => 'Finding your job', 'Selected' => 'Selected by employer', 'Placed' => 'Joined the job', 'Closed' => 'File closed'];
/** One interview card with Map, Call and Respond buttons. */
$locCard = function ($l, $back) use ($c) {
    $h = '<div class="iv-card"><div class="iv-top"><span class="chip ref">' . ordinal($l['seq']) . ' interview</span>' . badge($l['response']) . '</div><h4>' . e($l['company']) . '</h4>' . ($l['position'] ? '<p class="muted">' . e($l['position']) . '</p>' : '')
        . '<p>' . icon('calendar', 15) . ' <b>' . e(dtfmt($l['interview_at'])) . '</b></p><p>' . icon('map-pin', 15) . ' ' . e($l['location']) . '</p>' . ($l['contact'] ? '<p>' . icon('phone', 15) . ' ' . e($l['contact']) . '</p>' : '') . ($l['instructions'] ? '<p class="muted small">' . e($l['instructions']) . '</p>' : '')
        . '<div class="iv-btns">' . ($l['map_link'] ? '<a class="btn sm" href="' . e($l['map_link']) . '" target="_blank" rel="noopener">' . icon('map-pin', 14) . ' Open map</a>' : '<a class="btn sm" href="https://www.google.com/maps/search/?api=1&amp;query=' . e(rawurlencode($l['location'])) . '" target="_blank" rel="noopener">' . icon('map-pin', 14) . ' Find on map</a>') . '</div>';
    if ($l['response'] === 'Pending' && $c['stage'] !== 'Closed') $h .= '<details class="iv-reply"><summary class="btn ghost sm">' . icon('check', 14) . ' After the interview: tell us the result</summary><form method="post" action="portal.php?p=locations">' . csrf_field() . '<input type="hidden" name="loc" value="' . $l['id'] . '"><input type="hidden" name="back" value="' . $back . '">'
        . '<div class="seg"><label><input type="radio" name="response" value="Selected" required> Selected</label><label><input type="radio" name="response" value="Not Selected"> Not selected</label><label><input type="radio" name="response" value="Issue"> Problem</label></div>'
        . '<input name="note" placeholder="Note (needed for Not selected or Problem)"><button class="btn sm">' . icon('send', 14) . ' Send result</button></form></details>';
    elseif ($l['response_note']) $h .= '<p class="small">Your note: ' . e($l['response_note']) . '</p>';
    return $h . '</div>';
};

if ($pg === 'home') {
    echo '<section class="p-status"><div><small>Hello</small><h1>' . e(explode(' ', $c['full_name'])[0]) . '</h1><p>' . e($c['applied_position']) . ' · <span class="ref">' . e($c['ref_no']) . '</span></p></div>'
        . '<div class="p-stage"><span>' . e($stepLabel[$c['stage']] ?? $c['stage']) . '</span><div class="p-bar"><i style="width:' . round(($ci + 1) / count(CAND_STAGES) * 100) . '%"></i></div>'
        . ($f ? '<small>' . ($m['balance'] > 0 ? 'Balance ' . money($m['balance'], $f['currency']) . ($m['next'] ? ' · due ' . e(dfmt($m['next'])) : '') : 'Fully paid') . '</small>' : '') . '</div></section>';
    if ($c['stage'] === 'Closed') echo '<div class="banner">' . icon('lock', 20) . ' Your file is closed.</div>';
    foreach ($pending as $l) echo $locCard($l, 'home');
    $parts = chat_participants($cid);
    echo '<section class="p-chat" id="chat"><div class="p-chat-h"><span class="avatar">' . icon('users', 16) . '</span><div><b>Your deployment team</b><small>' . icon('lock', 11) . ' ' . e($parts ? implode(', ', array_map('user_name', $parts)) : 'Private chat') . '</small></div></div>';
    if (!$parts) echo '<div class="chat-start">' . icon('chat', 30) . '<b>Your chat opens soon</b><span>As soon as your file is given to our deployment team, you can talk to them here.</span></div>';
    else echo '<div class="msgs" id="msgs">' . chat_messages_html($cid, 'candidate') . '</div>' . chat_composer('portal.php?p=chat', 'Type your message…')
        . '<script nonce="' . csp_nonce() . '">(function(){var m=document.getElementById("msgs"),last=m.innerHTML;m.scrollTop=m.scrollHeight;setInterval(function(){if(document.hidden)return;fetch("portal.php?p=chat&frag=1",{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.ok?r.text():null}).then(function(h){if(h!==null&&h!==last){var atEnd=m.scrollHeight-m.scrollTop-m.clientHeight<80;m.innerHTML=h;last=h;if(atEnd)m.scrollTop=m.scrollHeight}}).catch(function(){})},8000)})();</script>';
    echo '</section>';
}
if ($pg === 'locations') {
    $locs = rows('SELECT * FROM interview_locations WHERE candidate_id=? AND archived=0 ORDER BY seq DESC', [$cid]);
    echo '<h2 class="p-title">' . icon('map-pin', 20) . ' My interviews</h2>';
    if (!$locs) echo '<div class="card chat-start">' . icon('map-pin', 30) . '<b>No interviews yet</b><span>When the deployment team arranges an interview, it appears here and in your chat.</span></div>';
    foreach ($locs as $l) echo $locCard($l, 'locations');
}
if ($pg === 'payments') {
    echo '<h2 class="p-title">' . icon('dollar', 20) . ' My payments</h2>';
    if (!$f) echo '<div class="card chat-start">' . icon('dollar', 30) . '<b>No file yet</b><span>Your payments appear here after your file is opened.</span></div>';
    else {
        echo '<div class="p-money"><div><small>Agreed total</small><b>' . money($m['agreed'], $f['currency']) . '</b></div><div class="good"><small>Paid</small><b>' . money($m['paid'], $f['currency']) . '</b></div><div class="' . ($m['balance'] > 0 ? 'bad' : 'good') . '"><small>Remaining</small><b>' . money(max(0, $m['balance']), $f['currency']) . '</b></div><div><small>Next payment date</small><b>' . e($m['next'] && $m['balance'] > 0 ? dfmt($m['next']) : '—') . '</b></div></div>';
        if ($m['agreed'] > 0) echo '<div class="card">' . progress($m['paid'] / $m['agreed'] * 100, round($m['paid'] / $m['agreed'] * 100) . '% paid') . '</div>';
        $pays = payments_for('inside_files', $f['id']);
        echo '<div class="card"><h3 style="margin-top:0">Receipts</h3>';
        if (!$pays) echo '<p class="muted">No receipts yet.</p>';
        foreach ($pays as $p) echo '<div class="rc-row"><div><b class="ref">' . e($p['ref_no']) . '</b><small>' . e(dfmt($p['pay_date'])) . ' · ' . e($p['pay_type']) . '</small></div><b>' . money($p['amount']) . '</b></div>';
        echo '</div>';
    }
}
if ($pg === 'profile') {
    echo '<h2 class="p-title">' . icon('user', 20) . ' My profile</h2><div class="card"><div class="kv kv-list"><div><span>Name</span><b>' . e($c['full_name']) . '</b></div><div><span>Phone</span><b>' . e($c['phone']) . '</b></div><div><span>Email</span><b>' . e($c['email'] ?: '—') . '</b></div><div><span>Nationality</span><b>' . e($c['nationality']) . '</b></div><div><span>File</span><b>' . e($f['ref_no'] ?? 'Not opened yet') . '</b></div></div>'
        . ($c['cv_file'] ? '<p>' . file_link($c['cv_file'], 'My CV') . '</p>' : '') . '</div>'
        . '<div class="card"><h3 style="margin-top:0">Update my details</h3><form method="post">' . csrf_field() . '<div class="grid-form"><div class="fld"><label>WhatsApp number</label><input name="whatsapp" type="tel" value="' . e($c['whatsapp']) . '"></div><div class="fld"><label>Qualification</label><input name="qualification" value="' . e($c['qualification']) . '"></div><div class="fld wide"><label>Preferred positions</label><input name="preferred_positions" value="' . e($c['preferred_positions']) . '"></div><div class="fld wide"><label>Skills</label><textarea name="skills" rows="2">' . e($c['skills']) . '</textarea></div><div class="fld wide"><label>Experience</label><textarea name="experience" rows="3">' . e($c['experience']) . '</textarea></div></div><div class="form-actions"><button class="btn">Save</button></div></form></div>';
    $docs = rows('SELECT * FROM documents WHERE candidate_id=? AND archived=0', [$cid]);
    if ($docs) echo '<div class="card"><h3 style="margin-top:0">Documents</h3>' . simple_table(['Document', 'Status'], array_map(function ($d) { return [e($d['doc_type'] === 'Other' ? $d['doc_type_other'] : $d['doc_type']), badge($d['status'])]; }, $docs)) . '</div>';
    $faq = rows("SELECT * FROM faqs WHERE show_in='Candidate portal' AND archived=0 ORDER BY sort");
    if ($faq) { echo '<div class="card"><h3 style="margin-top:0">' . icon('help', 17) . ' Questions</h3>'; foreach ($faq as $q) echo '<details style="margin-bottom:8px"><summary><b>' . e($q['question']) . '</b></summary><p class="muted" style="margin:6px 0 0">' . nl2br(e($q['answer'])) . '</p></details>'; echo '</div>'; }
    echo '<p class="center"><a class="btn ghost" href="portal.php?logout=1">' . icon('logout', 15) . ' Log out</a></p>';
}
portal_end();
