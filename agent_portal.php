<?php
// Agent Portal — an agent sees only their own submissions, documents, issues and payments.
require __DIR__ . '/inc/boot.php';
if (g('logout')) { portal_logout(); flash('You have logged out.'); redirect('agent_portal.php'); }

if (empty($_SESSION['portal']) || $_SESSION['portal']['type'] !== 'agent') {
    if (is_post()) {
        if (too_many_attempts()) { flash('Too many failed attempts. Please wait 15 minutes.', 'bad'); redirect('agent_portal.php'); }
        $u = row('SELECT * FROM agent_users WHERE lower(username)=lower(?) AND active=1 AND archived=0', [p('username')]);
        if ($u && password_verify((string)($_POST['password'] ?? ''), (string)$u['password_hash'])) { portal_login('agent', $u['id'], $u['name']); redirect('agent_portal.php'); }
        audit('portal-login-failed', 'agent_users', null, null, ['username' => p('username')]);
        flash('The username or password is not correct.', 'bad');
        redirect('agent_portal.php');
    }
    portal_start('Agent login', 'Agent Portal');
    echo '<div class="card p-login"><h2>' . icon('globe', 22) . ' Agent portal</h2><p class="muted">Submit workers, upload documents and follow processing and payments.</p><form method="post">' . csrf_field() . '<div class="fld" style="margin-bottom:12px"><label>Username</label><input name="username" required autocomplete="username"></div><div class="fld" style="margin-bottom:12px"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div><button class="btn" style="width:100%">' . icon('login', 16) . ' Sign in</button></form><p class="small" style="margin-top:14px"><a href="forgot.php?t=agent">Forgot your password?</a> · <a href="login.php">Staff login</a></p></div>';
    portal_end();
    exit;
}
$uid = require_portal('agent', 'agent_portal.php');
$au = row('SELECT * FROM agent_users WHERE id=?', [$uid]);
$ag = row('SELECT * FROM agents WHERE id=?', [$au['agent_id']]);
$aid = (int)$ag['id'];
$pg = g('p', 'home');
$allocs = rows("SELECT a.*, d.ref_no dref, d.position, d.status dstatus FROM allocations a JOIN demands d ON d.id=a.demand_id WHERE a.agent_id=? AND a.archived=0 ORDER BY a.id DESC", [$aid]);
$myWorker = function ($id) use ($aid) { return row('SELECT * FROM workers WHERE id=? AND agent_id=? AND archived=0', [(int)$id, $aid]); };

if (is_post()) {
    if ($pg === 'submit') {
        $al = row("SELECT a.* FROM allocations a JOIN demands d ON d.id=a.demand_id WHERE a.id=? AND a.agent_id=? AND a.status='Active' AND d.status IN ('Approved','In Progress')", [(int)p('allocation_id'), $aid]);
        if (!$al || !p('full_name') || !p('passport_no') || !p('nationality')) { flash('Choose the demand and fill name, passport and nationality.', 'bad'); redirect('agent_portal.php?p=submit'); }
        if (val('SELECT id FROM workers WHERE passport_no=? AND archived=0', [p('passport_no')])) { flash('A worker with this passport number already exists.', 'bad'); redirect('agent_portal.php?p=submit'); }
        list($cv, $e) = upload_file('cv', 'cv', true);
        if ($e) { flash('CV: ' . $e, 'bad'); redirect('agent_portal.php?p=submit'); }
        $batch = (int)p('batch_id') ? (int)val('SELECT id FROM batches WHERE id=? AND agent_id=? AND demand_id=?', [(int)p('batch_id'), $aid, $al['demand_id']]) : 0;
        if (!$batch) $batch = ins('batches', ['ref_no' => next_number('BAT'), 'agent_id' => $aid, 'demand_id' => $al['demand_id'], 'name' => 'Portal batch ' . date('d M Y'), 'submitted_on' => today(), 'created_at' => now(), 'archived' => 0]);
        $id = ins('workers', ['ref_no' => next_number('WRK'), 'full_name' => p('full_name'), 'passport_no' => p('passport_no'), 'nationality' => p('nationality'), 'phone' => p('phone'), 'demand_id' => $al['demand_id'], 'agent_id' => $aid, 'batch_id' => $batch, 'position' => val('SELECT position FROM demands WHERE id=?', [$al['demand_id']]), 'submitted_on' => today(), 'submitted_by' => $au['name'] . ' (agent portal)', 'cv_file' => $cv, 'profile_version' => 'v1', 'client_response' => 'Pending', 'stage' => 'Submitted', 'result' => 'Pending', 'visa_status' => 'Not Started', 'travel_status' => 'Pending', 'company_travel_status' => 'Pending', 'slot_outcome' => 'Open', 'created_at' => now(), 'archived' => 0]);
        audit('portal-submit', 'workers', $id);
        if ($al['coordinator']) notify($al['coordinator'], $ag['agency_name'] . ' submitted ' . p('full_name'), ent_url('workers', $id));
        flash('Worker submitted.');
        redirect('agent_portal.php?p=submissions');
    }
    if ($pg === 'documents') {
        $w = $myWorker(p('worker_id'));
        list($path, $e) = upload_file('file', 'docs', true);
        if (!$w || $e) { flash($e ?: 'Choose the worker.', 'bad'); redirect('agent_portal.php?p=documents'); }
        $dt = in_array(p('doc_type'), options('doc_types'), true) ? p('doc_type') : 'Passport';
        $id = ins('documents', ['ref_no' => next_number('DOC'), 'doc_type' => $dt, 'worker_id' => $w['id'], 'doc_no' => p('doc_no'), 'status' => 'Received', 'expiry' => p('expiry') ?: null, 'file' => $path, 'created_at' => now(), 'archived' => 0]);
        audit('portal-document', 'documents', $id);
        flash('Document uploaded. RH will verify it.');
        redirect('agent_portal.php?p=documents');
    }
    if ($pg === 'issues') {
        $w = p('worker_id') ? $myWorker(p('worker_id')) : null;
        if (!p('subject') || !p('details')) { flash('Describe the issue.', 'bad'); redirect('agent_portal.php?p=issues'); }
        $id = ins('issues', ['ref_no' => next_number('ISS'), 'subject' => p('subject'), 'party' => 'Agent', 'agent_id' => $aid, 'worker_id' => $w['id'] ?? null, 'demand_id' => $w['demand_id'] ?? null, 'category' => p('category') === 'Payment Dispute' ? 'Payment Dispute' : 'Worker Complaint', 'severity' => 'Medium', 'owner' => $ag['rh_owner'], 'due_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'Open', 'details' => p('details'), 'created_at' => now(), 'archived' => 0]);
        audit('portal-issue', 'issues', $id);
        if ($ag['rh_owner']) notify($ag['rh_owner'], 'Agent issue: ' . p('subject'), ent_url('issues', $id));
        flash('Issue raised.');
        redirect('agent_portal.php?p=issues');
    }
}

portal_start('Agent portal', 'Agent Portal · ' . $ag['agency_name'], ['home' => 'Overview', 'submissions' => 'Submissions', 'submit' => 'Submit worker', 'documents' => 'Documents', 'issues' => 'Issues', 'payments' => 'Payments'], $pg, 'agent_portal.php?logout=1');
echo '<div class="p-hero"><h1>' . e($ag['agency_name']) . '</h1><p>' . e($ag['country']) . ' · ' . badge($ag['status']) . ' · RH coordinator: ' . e(user_name($ag['rh_owner'])) . '</p></div>';

if ($pg === 'home') {
    card_open(icon('clipboard', 17) . ' My demand allocations — processing progress');
    echo simple_table(array_merge(['Demand', 'Target', 'Deadline'], PROGRESS_HEAD), array_map(function ($a) use ($aid) { $d = row('SELECT * FROM demands WHERE id=?', [$a['demand_id']]); return array_merge(['<b class="ref">' . e($a['dref']) . '</b> ' . e($a['position']), (int)$a['target_qty'], e(dfmt($a['deadline']))], progress_row(demand_progress($d, $aid))); }, $allocs), 'No allocations yet.');
    card_close();
    $b = agent_balance($aid);
    echo '<div class="kv"><div><span>Amount due</span><b>' . money($b['due']) . '</b></div><div class="good"><span>Paid to you</span><b>' . money($b['paid']) . '</b></div><div class="hl"><span>Outstanding</span><b>' . money($b['outstanding']) . '</b></div></div>';
}
if ($pg === 'submissions') {
    $ws = rows('SELECT * FROM workers WHERE agent_id=? AND archived=0 ORDER BY position, id DESC', [$aid]);
    card_open(icon('users', 17) . ' My submissions by position <span class="count">' . count($ws) . '</span>', '<a class="btn sm" href="?p=submit">' . icon('plus', 14) . ' Submit worker</a>');
    echo simple_table(['Position', 'Worker', 'Demand', 'Batch', 'Submitted', 'Selection', 'Stage', 'Visa', 'Travel'], array_map(function ($w) { return [e($w['position']), '<b>' . e($w['full_name']) . '</b><br><small class="ref">' . e($w['ref_no']) . ' · ' . e($w['passport_no']) . '</small>', e(ent_title('demands', $w['demand_id'])), e(ent_title('batches', $w['batch_id'])), e(dfmt($w['submitted_on'])), badge($w['result']) . ($w['result'] === 'Rejected' && $w['result_reason'] ? '<br><small>' . e($w['result_reason']) . '</small>' : ''), badge($w['stage']), badge($w['visa_status']), badge($w['travel_status'])]; }, $ws), 'No submissions yet.');
    card_close();
}
if ($pg === 'submit') {
    card_open(icon('user-plus', 17) . ' Submit a worker');
    $open = array_filter($allocs, function ($a) { return $a['status'] === 'Active' && in_array($a['dstatus'], ['Approved', 'In Progress'], true); });
    if (!$open) echo '<p class="muted">You have no active allocations. RH will allocate demands to you.</p>';
    else {
        echo '<form method="post" enctype="multipart/form-data">' . csrf_field() . '<div class="grid-form"><div class="fld"><label>Demand *</label><select name="allocation_id" required>';
        foreach ($open as $a) echo '<option value="' . $a['id'] . '">' . e($a['dref'] . ' · ' . $a['position'] . ' (target ' . $a['target_qty'] . ')') . '</option>';
        echo '</select></div><div class="fld"><label>Batch</label><select name="batch_id"><option value="">New batch</option>';
        foreach (rows('SELECT * FROM batches WHERE agent_id=? AND archived=0 ORDER BY id DESC', [$aid]) as $bt) echo '<option value="' . $bt['id'] . '">' . e($bt['ref_no'] . ' · ' . $bt['name']) . '</option>';
        echo '</select></div><div class="fld"><label>Full name *</label><input name="full_name" required></div><div class="fld"><label>Passport number *</label><input name="passport_no" required></div><div class="fld"><label>Nationality *</label><select name="nationality" required>';
        foreach (options('nationalities') as $n) echo '<option>' . e($n) . '</option>';
        echo '</select></div><div class="fld"><label>Phone</label><input name="phone"></div><div class="fld wide"><label>CV / profile *</label><input type="file" name="cv" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div></div><div class="form-actions"><button class="btn">Submit worker</button></div></form>';
    }
    card_close();
}
if ($pg === 'documents') {
    $ws = rows('SELECT * FROM workers WHERE agent_id=? AND archived=0 ORDER BY full_name', [$aid]);
    echo '<div class="grid g2">';
    card_open(icon('upload', 17) . ' Upload a document');
    echo '<form method="post" enctype="multipart/form-data">' . csrf_field() . '<div class="fld" style="margin-bottom:12px"><label>Worker *</label><select name="worker_id" required>';
    foreach ($ws as $w) echo '<option value="' . $w['id'] . '">' . e($w['full_name'] . ' · ' . $w['passport_no']) . '</option>';
    echo '</select></div><div class="fld" style="margin-bottom:12px"><label>Document type</label><select name="doc_type">';
    foreach (options('doc_types') as $d) echo '<option>' . e($d) . '</option>';
    echo '</select></div><div class="grid-form"><div class="fld"><label>Document number</label><input name="doc_no"></div><div class="fld"><label>Expiry</label><input type="date" name="expiry"></div></div><div class="fld" style="margin:12px 0"><label>File *</label><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div><button class="btn">Upload</button></form>';
    card_close();
    $docs = rows('SELECT d.*, w.full_name FROM documents d JOIN workers w ON w.id=d.worker_id WHERE w.agent_id=? AND d.archived=0 ORDER BY d.id DESC', [$aid]);
    card_open(icon('file-text', 17) . ' Documents');
    echo simple_table(['Worker', 'Document', 'Status', 'Expiry', 'File'], array_map(function ($d) { return [e($d['full_name']), e($d['doc_type']), badge($d['status']), e(dfmt($d['expiry'])), $d['file'] ? file_link($d['file'], 'View') : '']; }, $docs), 'No documents yet.');
    card_close();
    echo '</div>';
}
if ($pg === 'issues') {
    echo '<div class="grid g2">';
    card_open(icon('alert', 17) . ' Raise an issue');
    echo '<form method="post">' . csrf_field() . '<div class="fld" style="margin-bottom:12px"><label>Worker (optional)</label><select name="worker_id"><option value="">General</option>';
    foreach (rows('SELECT id, full_name FROM workers WHERE agent_id=? AND archived=0', [$aid]) as $w) echo '<option value="' . $w['id'] . '">' . e($w['full_name']) . '</option>';
    echo '</select></div><div class="fld" style="margin-bottom:12px"><label>Type</label><select name="category"><option>Processing question</option><option>Payment Dispute</option></select></div><div class="fld" style="margin-bottom:12px"><label>Subject *</label><input name="subject" required></div><div class="fld" style="margin-bottom:12px"><label>Details *</label><textarea name="details" required rows="4"></textarea></div><button class="btn">Raise issue</button></form>';
    card_close();
    $iss = rows("SELECT * FROM issues WHERE archived=0 AND (agent_id=? OR worker_id IN (SELECT id FROM workers WHERE agent_id=?)) ORDER BY id DESC", [$aid, $aid]);
    card_open(icon('inbox', 17) . ' My issues');
    echo simple_table(['Ref', 'Subject', 'Status', 'Update'], array_map(function ($i) { return ['<b class="ref">' . e($i['ref_no']) . '</b>', e($i['subject']), badge($i['status']), e($i['resolution'])]; }, $iss), 'No issues raised.');
    card_close();
    echo '</div>';
}
if ($pg === 'payments') {
    $b = agent_balance($aid);
    echo '<div class="kv" style="margin:0 0 16px"><div><span>Amount due (joined workers × fee)</span><b>' . money($b['due']) . '</b></div><div class="good"><span>Paid</span><b>' . money($b['paid']) . '</b></div><div class="hl"><span>Outstanding</span><b>' . money($b['outstanding']) . '</b></div></div>';
    card_open(icon('file-text', 17) . ' Payment terms');
    echo '<p>' . e($ag['payment_terms'] ?: 'As agreed in your agency agreement.') . '</p>' . simple_table(['Demand', 'Fee per joined worker', 'Terms'], array_map(function ($a) { return [e($a['dref'] . ' · ' . $a['position']), money($a['fee_per_worker']), e($a['terms'])]; }, $allocs), 'No allocations.');
    card_close();
    card_open(icon('dollar', 17) . ' Receipts (payments made to you)');
    echo simple_table(['Ref', 'Date', 'Method', 'Amount', 'Note'], array_map(function ($p) { return ['<b class="ref">' . e($p['ref_no']) . '</b>', e(dfmt($p['pay_date'])), e($p['method']), money($p['amount']), e($p['reversal_of'] ? 'Reversal' : $p['note'])]; }, rows("SELECT * FROM payments WHERE entity='agents' AND entity_id=? ORDER BY id DESC", [$aid])), 'No payments yet.');
    card_close();
}
portal_end();
