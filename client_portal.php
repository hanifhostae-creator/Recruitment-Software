<?php
// Client Portal — a client user sees only their own company's demands.
require __DIR__ . '/inc/boot.php';
if (g('logout')) { portal_logout(); flash('You have logged out.'); redirect('client_portal.php'); }

if (empty($_SESSION['portal']) || $_SESSION['portal']['type'] !== 'client') {
    if (is_post()) {
        $u = row('SELECT * FROM client_users WHERE lower(username)=lower(?) AND active=1 AND archived=0', [p('username')]);
        if ($u && password_verify((string)($_POST['password'] ?? ''), (string)$u['password_hash'])) { portal_login('client', $u['id'], $u['name']); redirect('client_portal.php'); }
        audit('portal-login-failed', 'client_users', null, null, ['username' => p('username')]);
        flash('The username or password is not correct.', 'bad');
        redirect('client_portal.php');
    }
    portal_start('Client login', 'Client Portal');
    echo '<div class="card p-login"><h2>' . icon('building', 22) . ' Client portal</h2><p class="muted">Track your demands, candidate profiles, interviews, visas and deployment.</p><form method="post">' . csrf_field() . '<div class="fld" style="margin-bottom:12px"><label>Username</label><input name="username" required autocomplete="username"></div><div class="fld" style="margin-bottom:12px"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div><button class="btn" style="width:100%">' . icon('login', 16) . ' Sign in</button></form><p class="small" style="margin-top:14px"><a href="forgot.php?t=client">Forgot your password?</a> · <a href="login.php">Staff login</a></p></div>';
    portal_end();
    exit;
}
$uid = require_portal('client', 'client_portal.php');
$cu = row('SELECT * FROM client_users WHERE id=?', [$uid]);
$co = row('SELECT * FROM companies WHERE id=?', [$cu['company_id']]);
$pg = g('p', 'home');
$myDemand = function ($id) use ($co) { return row('SELECT * FROM demands WHERE id=? AND company_id=? AND archived=0', [(int)$id, $co['id']]); };

if (is_post()) {
    if ($pg === 'issues') {
        $d = $myDemand(p('demand_id'));
        if (!$d || !p('subject') || !p('details')) { flash('Choose the demand and describe the issue.', 'bad'); redirect('client_portal.php?p=issues'); }
        $id = ins('issues', ['ref_no' => next_number('ISS'), 'subject' => p('subject'), 'party' => 'Client', 'demand_id' => $d['id'], 'category' => 'Client Complaint', 'severity' => 'Medium', 'owner' => $d['owner'], 'due_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'Open', 'details' => p('details'), 'created_at' => now(), 'archived' => 0]);
        audit('portal-issue', 'issues', $id);
        if ($d['owner']) notify($d['owner'], 'Client issue raised: ' . p('subject'), ent_url('issues', $id));
        flash('Issue raised. Our team will update you here.');
        redirect('client_portal.php?p=issues');
    }
    if ($pg === 'feedback') {
        $d = p('demand_id') ? $myDemand(p('demand_id')) : null;
        ins('client_feedback', ['company_id' => $co['id'], 'demand_id' => $d['id'] ?? null, 'rating' => max(1, min(5, (int)p('rating'))), 'feedback' => p('feedback'), 'source' => 'Portal', 'created_at' => now(), 'archived' => 0]);
        flash('Thank you for your feedback.');
        redirect('client_portal.php?p=feedback');
    }
}

portal_start('Client portal', 'Client Portal · ' . $co['display_name'], ['home' => 'Overview', 'demands' => 'Demands', 'issues' => 'Issues', 'feedback' => 'Feedback'], $pg === 'demand' ? 'demands' : $pg, 'client_portal.php?logout=1');
echo '<div class="p-hero"><h1>' . e($co['display_name']) . '</h1><p>Welcome, ' . e($cu['name']) . ' · Account manager: ' . e(user_name($co['account_manager']) ?: 'RH Management') . '</p></div>';
$ds = rows("SELECT * FROM demands WHERE company_id=? AND archived=0 AND status<>'Cancelled' ORDER BY id DESC", [$co['id']]);

if ($pg === 'home' || $pg === 'demands') {
    $tot = array_fill_keys(['requested', 'submitted', 'interviewed', 'selected', 'rejected', 'documentation', 'visa', 'ready', 'joined', 'outstanding'], 0);
    $data = []; $links = [];
    foreach ($ds as $d) { $p = demand_progress($d); foreach ($tot as $k => $v) $tot[$k] += $p[$k]; $data[] = array_merge(['<b class="ref">' . e($d['ref_no']) . '</b><br>' . e($d['position']), badge($d['status'])], progress_row($p)); $links[] = 'client_portal.php?p=demand&id=' . $d['id']; }
    if ($pg === 'home') echo '<div class="kv" style="margin:0 0 16px"><div class="hl"><span>Requested</span><b>' . $tot['requested'] . '</b></div><div><span>Submitted</span><b>' . $tot['submitted'] . '</b></div><div><span>Interviewed</span><b>' . $tot['interviewed'] . '</b></div><div><span>Selected</span><b>' . $tot['selected'] . '</b></div><div><span>In documentation</span><b>' . $tot['documentation'] . '</b></div><div><span>Mobilised</span><b>' . $tot['ready'] . '</b></div><div class="good"><span>Joined</span><b>' . $tot['joined'] . '</b></div><div class="bad"><span>Outstanding</span><b>' . $tot['outstanding'] . '</b></div></div>';
    card_open(icon('clipboard', 17) . ' Headcount progress by demand');
    echo simple_table(array_merge(['Demand', 'Status'], PROGRESS_HEAD), $data, 'No demands yet. Contact your account manager to place a demand.', $links);
    card_close();
}
if ($pg === 'demand') {
    $d = $myDemand(g('id'));
    if (!$d) { echo '<div class="card empty">Demand not found.</div>'; portal_end(); exit; }
    card_open(icon('clipboard', 17) . ' ' . e($d['ref_no'] . ' · ' . $d['position']), badge($d['status']));
    echo '<div class="kv"><div><span>Quantity</span><b>' . (int)($d['approved_qty'] ?: $d['quantity']) . '</b></div><div><span>Salary</span><b>' . money($d['salary']) . '</b></div><div><span>Location</span><b>' . e($d['work_location']) . '</b></div><div><span>Deadline</span><b>' . e(dfmt($d['deadline'])) . '</b></div></div>';
    echo '<div style="margin-top:12px">' . simple_table(PROGRESS_HEAD, [progress_row(demand_progress($d))]) . '</div>';
    card_close();
    $ws = rows('SELECT * FROM workers WHERE demand_id=? AND archived=0 ORDER BY id', [$d['id']]);
    card_open(icon('users', 17) . ' Submitted candidates <span class="count">' . count($ws) . '</span>');
    echo simple_table(['Candidate', 'Nationality', 'Submitted', 'CV', 'Interview / test', 'Selection', 'Documents', 'Visa', 'Deployment'], array_map(function ($w) {
        $t = row('SELECT * FROM worker_tests WHERE worker_id=? AND archived=0 ORDER BY id DESC LIMIT 1', [$w['id']]);
        $docs = (int)val("SELECT COUNT(*) FROM documents WHERE worker_id=? AND status IN ('Received','Verified')", [$w['id']]) . ' OK / ' . (int)val("SELECT COUNT(*) FROM documents WHERE worker_id=? AND status='Missing'", [$w['id']]) . ' missing';
        return ['<b>' . e($w['full_name']) . '</b><br><small class="ref">' . e($w['ref_no']) . '</small>', e($w['nationality']), e(dfmt($w['submitted_on'])), $w['cv_file'] ? file_link($w['cv_file'], 'CV') : '', $t ? e($t['kind']) . ' ' . badge($t['result']) : '<span class="muted">—</span>', badge($w['result']), e($docs), badge($w['visa_status']), badge($w['stage']) . ($w['joining_date'] ? '<br><small>Joined ' . e(dfmt($w['joining_date'])) . '</small>' : '')];
    }, $ws), 'No candidates submitted yet.');
    card_close();
}
if ($pg === 'issues') {
    echo '<div class="grid g2">';
    card_open(icon('alert', 17) . ' Raise an issue');
    echo '<form method="post">' . csrf_field() . '<div class="fld" style="margin-bottom:12px"><label>Demand *</label><select name="demand_id" required><option value="">— Select —</option>';
    foreach ($ds as $d) echo '<option value="' . $d['id'] . '">' . e($d['ref_no'] . ' · ' . $d['position']) . '</option>';
    echo '</select></div><div class="fld" style="margin-bottom:12px"><label>Subject *</label><input name="subject" required></div><div class="fld" style="margin-bottom:12px"><label>Details *</label><textarea name="details" required rows="4"></textarea></div><button class="btn">Raise issue</button></form>';
    card_close();
    $iss = rows('SELECT * FROM issues WHERE demand_id IN (SELECT id FROM demands WHERE company_id=?) AND party=? AND archived=0 ORDER BY id DESC', [$co['id'], 'Client']);
    card_open(icon('inbox', 17) . ' My issues');
    echo simple_table(['Ref', 'Subject', 'Demand', 'Status', 'Update'], array_map(function ($i) { return ['<b class="ref">' . e($i['ref_no']) . '</b>', e($i['subject']), e(ent_title('demands', $i['demand_id'])), badge($i['status']), e($i['resolution'])]; }, $iss), 'No issues raised.');
    card_close();
    echo '</div>';
}
if ($pg === 'feedback') {
    card_open(icon('star', 17) . ' Share feedback');
    echo '<form method="post" style="max-width:560px">' . csrf_field() . '<div class="fld" style="margin-bottom:12px"><label>Demand (optional)</label><select name="demand_id"><option value="">General</option>';
    foreach ($ds as $d) echo '<option value="' . $d['id'] . '">' . e($d['ref_no'] . ' · ' . $d['position']) . '</option>';
    echo '</select></div><div class="fld" style="margin-bottom:12px"><label>Rating</label><select name="rating"><option value="5">5 — Excellent</option><option value="4">4 — Good</option><option value="3">3 — Average</option><option value="2">2 — Poor</option><option value="1">1 — Very poor</option></select></div><div class="fld" style="margin-bottom:12px"><label>Feedback *</label><textarea name="feedback" required rows="4"></textarea></div><button class="btn">Send feedback</button></form>';
    card_close();
}
portal_end();
