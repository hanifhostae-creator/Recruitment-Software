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
        if (!$l['username']) { flash('Your email address is missing on your file. Please ask the deployment team to add it, then use the link again.', 'bad'); redirect('portal.php?register=' . urlencode(g('register'))); }
        q('UPDATE candidate_logins SET password_hash=?, token=NULL, token_expires=NULL WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $l['id']]);
        portal_login('candidate', $c['id'], $c['full_name']);
        audit('portal-registered', 'candidates', $c['id']);
        flash('Your password is set. Welcome to your portal!');
        redirect('portal.php');
    }
    portal_start('Complete registration', 'Candidate Portal');
    echo '<div class="card p-login"><h2>' . icon('user-plus', 22) . ' Complete your registration</h2>';
    if (!$valid || !$c) echo '<div class="alert bad">This registration link is not valid or has expired (links last 7 days). Please ask the RH Management deployment team for a new link.</div>';
    else {
        echo '<p>Hello <b>' . e($c['full_name']) . '</b> (' . e($c['ref_no']) . '). Choose a password for your candidate portal.</p><form method="post">' . csrf_field()
            . '<div class="fld" style="margin-bottom:12px"><label>Username (your email)</label><input value="' . e($l['username'] ?: 'Email missing — contact the deployment team') . '" readonly></div>'
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
            $l = row('SELECT * FROM candidate_logins WHERE lower(username)=? OR lower(legacy_slug)=?', [$user, $user]);
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
    echo '<div class="card p-login"><h2>' . icon('user', 22) . ' Candidate portal</h2><nav class="tabs"><a class="' . (!$legacy ? 'on' : '') . '" href="portal.php">Email &amp; password</a><a class="' . ($legacy ? 'on' : '') . '" href="portal.php?legacy=1">File number &amp; phone</a></nav><form method="post">' . csrf_field();
    if ($legacy) echo '<input type="hidden" name="mode" value="legacy"><div class="fld" style="margin-bottom:12px"><label>File number</label><input name="file_no" required placeholder="IN-FILE-2026-00001"></div><div class="fld" style="margin-bottom:12px"><label>Phone number on your file</label><input name="phone" required type="tel"></div>';
    else echo '<input type="hidden" name="mode" value="email"><div class="fld" style="margin-bottom:12px"><label>Email address</label><input name="username" required autocomplete="username"></div><div class="fld" style="margin-bottom:12px"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>';
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
            if ($c['deploy_officer']) notify($c['deploy_officer'], $c['full_name'] . ' marked ' . ordinal($loc['seq']) . ' location: ' . $resp, ent_url('candidates', $cid));
            flash('Thank you — your response was sent to the deployment team.');
        }
        redirect('portal.php?p=locations');
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
            foreach ($parts as $pid) notify($pid, 'New chat message from ' . $c['full_name'], 'chat.php?c=' . $cid, 'chat');
        }
        redirect('portal.php?p=chat');
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

if ($pg === 'chat') q("UPDATE chat_messages SET read_at=? WHERE candidate_id=? AND sender_type='Staff' AND read_at IS NULL", [now(), $cid]);
$unreadChat = (int)val("SELECT COUNT(*) FROM chat_messages WHERE candidate_id=? AND sender_type='Staff' AND read_at IS NULL AND archived=0", [$cid]);
portal_start('My portal', 'Candidate Portal', ['home' => 'My file', 'locations' => 'My Interview Locations', 'payments' => 'Payments', 'chat' => 'Chat' . ($unreadChat ? ' (' . $unreadChat . ')' : ''), 'profile' => 'Profile'], $pg, 'portal.php?logout=1');
echo '<div class="p-hero"><h1>Hello, ' . e(explode(' ', $c['full_name'])[0]) . '</h1><p>' . e($c['ref_no']) . ($f ? ' · File ' . e($f['ref_no']) . ' opened ' . e(dfmt($f['created_at'])) : '') . ' · ' . e($c['applied_position']) . '</p></div>';
if ($c['stage'] === 'Closed') echo '<div class="banner">' . icon('lock', 20) . ' Your file is closed.</div>';

if ($pg === 'home') {
    card_open(icon('activity', 17) . ' My progress');
    $ci = array_search($c['stage'], CAND_STAGES, true);
    echo '<div class="journey">';
    foreach (CAND_STAGES as $i => $s) echo '<div class="jstep' . ($i <= $ci ? ' done' : '') . ($i === $ci ? ' cur' : '') . '"><i>' . ($i <= $ci ? '✓' : $i + 1) . '</i>' . e($s) . '</div>';
    echo '</div><div class="kv"><div class="hl"><span>Current stage</span><b>' . e($c['stage']) . '</b></div><div><span>Applied position</span><b>' . e($c['applied_position']) . '</b></div><div><span>File number</span><b>' . e($f['ref_no'] ?? 'Not opened yet') . '</b></div><div class="' . ($m['balance'] > 0 ? 'warn' : 'good') . '"><span>Balance</span><b>' . money(max(0, $m['balance'])) . '</b></div></div>';
    card_close();
    echo '<div class="grid g2">';
    $up = rows("SELECT * FROM interview_locations WHERE candidate_id=? AND archived=0 AND response='Pending' ORDER BY seq", [$cid]);
    card_open(icon('map-pin', 17) . ' Interview opportunities');
    if (!$up) echo '<p class="muted">No pending interviews right now. We will add them here.</p>';
    foreach ($up as $l) echo '<div class="vac" style="margin-bottom:10px"><div class="chips"><span class="chip ref">' . ordinal($l['seq']) . ' location</span>' . badge('Pending') . '</div><h4>' . e($l['company']) . '</h4><p>' . e($l['position']) . '</p><p>' . icon('calendar', 14) . ' ' . e(dtfmt($l['interview_at'])) . '</p><p>' . icon('map-pin', 14) . ' ' . e($l['location']) . ($l['map_link'] ? ' · <a href="' . e($l['map_link']) . '" target="_blank" rel="noopener">Open map</a>' : '') . '</p>' . ($l['contact'] ? '<p>' . icon('phone', 14) . ' ' . e($l['contact']) . '</p>' : '') . ($l['instructions'] ? '<p class="muted">' . e($l['instructions']) . '</p>' : '') . '<a class="btn sm" href="portal.php?p=locations">Respond</a></div>';
    card_close();
    card_open(icon('clock', 17) . ' Status updates');
    echo '<ul class="timeline">';
    foreach (array_reverse(rows("SELECT * FROM stage_history WHERE entity='candidates' AND entity_id=? ORDER BY id", [$cid])) as $h) echo '<li><b>' . e($h['to_stage']) . '</b> <span class="muted">· ' . e(dtfmt($h['at'])) . '</span></li>';
    echo '</ul>';
    $docs = rows('SELECT * FROM documents WHERE candidate_id=? AND archived=0', [$cid]);
    echo '<h4 style="margin-top:14px">Required documents</h4>' . simple_table(['Document', 'Status'], array_map(function ($d) { return [e($d['doc_type'] === 'Other' ? $d['doc_type_other'] : $d['doc_type']), badge($d['status'])]; }, $docs), 'No documents requested.');
    card_close();
    echo '</div>';
    $faq = rows("SELECT * FROM faqs WHERE show_in='Candidate portal' AND archived=0 ORDER BY sort");
    if ($faq) { card_open(icon('help', 17) . ' Frequently asked questions'); foreach ($faq as $q) echo '<details style="margin-bottom:8px"><summary><b>' . e($q['question']) . '</b></summary><p class="muted" style="margin:6px 0 0">' . nl2br(e($q['answer'])) . '</p></details>'; card_close(); }
}
if ($pg === 'locations') {
    $locs = rows('SELECT * FROM interview_locations WHERE candidate_id=? AND archived=0 ORDER BY seq', [$cid]);
    card_open(icon('map-pin', 17) . ' My Interview Locations');
    if (!$locs) echo '<p class="muted">No interview locations yet.</p>';
    foreach ($locs as $l) {
        echo '<div class="card" style="box-shadow:none"><div class="card-h"><h3>' . ordinal($l['seq']) . ' · ' . e($l['company']) . '</h3>' . badge($l['response']) . '</div><p>' . icon('calendar', 14) . ' ' . e(dtfmt($l['interview_at'])) . ' · ' . icon('map-pin', 14) . ' ' . e($l['location']) . ($l['map_link'] ? ' · <a href="' . e($l['map_link']) . '" target="_blank" rel="noopener">Map</a>' : '') . '</p>' . ($l['instructions'] ? '<p class="muted">' . e($l['instructions']) . '</p>' : '');
        if ($l['response'] === 'Pending' && $c['stage'] !== 'Closed') echo '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="loc" value="' . $l['id'] . '"><div><label>Result</label><select name="response" required><option value="">— Choose —</option><option>Selected</option><option>Not Selected</option><option>Issue</option></select></div><div style="flex:2"><label>Note (required for Not Selected / Issue)</label><input name="note"></div><button class="btn">Send</button></form>';
        elseif ($l['response_note']) echo '<p class="small">Your note: ' . e($l['response_note']) . '</p>';
        echo '</div>';
    }
    card_close();
}
if ($pg === 'payments') {
    $plan = $f ? row('SELECT * FROM fee_plans WHERE id=?', [$f['fee_plan_id']]) : null;
    card_open(icon('dollar', 17) . ' Payment summary');
    if (!$f) echo '<p class="muted">No file has been opened yet.</p>';
    else {
        $init = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE entity='inside_files' AND entity_id=? AND pay_type='File Opening Advance'", [$f['id']]);
        echo '<div class="kv"><div><span>Approved total fee</span><b>' . money($m['agreed'], $f['currency']) . '</b></div><div class="good"><span>Initial amount paid</span><b>' . money($init, $f['currency']) . '</b></div><div><span>Amount due after selection</span><b>' . money($m['agreed'] - $f['advance_amount'], $f['currency']) . '</b></div><div><span>Due trigger</span><b>' . e($plan['balance_trigger'] ?? '—') . '</b></div><div><span>Due date</span><b>' . e($m['next'] ? dfmt($m['next']) : '—') . '</b></div><div class="' . ($m['balance'] > 0 ? 'bad' : 'good') . '"><span>Balance</span><b>' . money(max(0, $m['balance']), $f['currency']) . '</b></div></div>';
        echo '<h4 style="margin-top:16px">Receipts</h4>' . simple_table(['Receipt', 'Date', 'Type', 'Amount'], array_map(function ($p) { return ['<b class="ref">' . e($p['ref_no']) . '</b>', e(dfmt($p['pay_date'])), e($p['pay_type']), money($p['amount'])]; }, payments_for('inside_files', $f['id'])), 'No receipts yet.');
        if ($plan && $plan['terms']) echo '<p class="muted small" style="margin-top:12px"><b>Fee terms:</b> ' . e($plan['terms']) . '</p>';
    }
    card_close();
}
if ($pg === 'chat') {
    $parts = chat_participants($cid);
    card_open(icon('chat', 17) . ' Chat with your deployment team', '<span class="muted small">' . icon('lock', 13) . ' Private: only your deployment team can read this</span>');
    if (!$parts) echo '<p class="muted">The chat opens as soon as your file is assigned to the deployment team. You will see a notification here.</p>';
    else {
        echo '<p class="muted small">Your deployment team: <b>' . e(implode(', ', array_map('user_name', $parts))) . '</b></p><div class="msgs" id="msgs" style="max-height:55vh">' . chat_messages_html($cid, 'candidate') . '</div>'
            . '<form method="post" enctype="multipart/form-data" class="chat-form" action="portal.php?p=chat">' . csrf_field() . '<div style="flex:1"><textarea name="body" rows="2" placeholder="Type your message…" maxlength="4000"></textarea><input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="margin-top:6px;font-size:12px"><small class="muted">You can also send a document (PDF, DOC, DOCX, JPG, PNG · max 10 MB).</small></div><button class="btn">' . icon('send', 15) . ' Send</button></form>'
            . '<script nonce="' . csp_nonce() . '">(function(){var m=document.getElementById("msgs"),last=m.innerHTML;m.scrollTop=m.scrollHeight;setInterval(function(){if(document.hidden)return;fetch("portal.php?p=chat&frag=1",{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.ok?r.text():null}).then(function(h){if(h!==null&&h!==last){var atEnd=m.scrollHeight-m.scrollTop-m.clientHeight<80;m.innerHTML=h;last=h;if(atEnd)m.scrollTop=m.scrollHeight}}).catch(function(){})},8000)})();</script>';
    }
    card_close();
}
if ($pg === 'profile') {
    card_open(icon('user', 17) . ' My profile');
    echo '<div class="kv" style="margin:0 0 14px"><div><span>Name</span><b>' . e($c['full_name']) . '</b></div><div><span>Phone</span><b>' . e($c['phone']) . '</b></div><div><span>Email (username)</span><b>' . e($c['email']) . '</b></div><div><span>Nationality</span><b>' . e($c['nationality']) . '</b></div></div>'
        . ($c['cv_file'] ? '<p>' . file_link($c['cv_file'], 'My CV') . '</p>' : '')
        . '<p class="muted small">Official status, selection outcome and fee terms are managed by RH staff. You can update the fields below.</p><form method="post">' . csrf_field() . '<div class="grid-form"><div class="fld"><label>WhatsApp number</label><input name="whatsapp" value="' . e($c['whatsapp']) . '"></div><div class="fld"><label>Qualification</label><input name="qualification" value="' . e($c['qualification']) . '"></div><div class="fld wide"><label>Preferred positions</label><input name="preferred_positions" value="' . e($c['preferred_positions']) . '"></div><div class="fld wide"><label>Skills</label><textarea name="skills" rows="2">' . e($c['skills']) . '</textarea></div><div class="fld wide"><label>Experience</label><textarea name="experience" rows="3">' . e($c['experience']) . '</textarea></div></div><div class="form-actions"><button class="btn">Save profile</button></div></form>';
    card_close();
}
portal_end();
