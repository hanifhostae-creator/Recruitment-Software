<?php
require __DIR__ . '/inc/boot.php';
require_login();
$u = me();
page_start('Dashboard', 'dashboard');
$h = (int)date('G');
page_head(($h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening')) . ', ' . explode(' ', $u['name'])[0], 'grid', e(date('l, d F Y')) . ' · ' . e($u['role_name']) . ' dashboard — live counters computed from records.',
    (can('candidates', 'add') ? '<a class="btn" href="candidates.php?a=new">' . icon('user-plus', 16) . ' New candidate</a>' : '') . (can('outside', 'add') ? '<a class="btn ghost" href="outside.php?t=demands&a=new">' . icon('plus', 16) . ' New demand</a>' : ''));

if (is_super()) {
    if (trim((string)setting('site_url', '')) === '') echo '<div class="alert warn">' . icon('alert', 15) . ' <b>Set the System URL</b> (Settings → Company), for example <code>https://crm.yourdomain.com</code>. Password reset and portal emails use it, so every link is safe and correct. <a href="settings.php">Open Settings</a></div>';
    if (trim((string)setting('smtp_host', '')) === '') echo '<div class="alert warn">' . icon('mail', 15) . ' <b>Email is not set up.</b> Password reset emails need an SMTP mailbox. Settings → Email, then press "Send a test email". <a href="settings.php">Open Settings</a></div>';
}
render_widgets();

echo '<div class="grid g3">';
if (can('candidates')) {
    $by = [];
    foreach (CAND_STAGES as $s) $by[$s] = (int)val('SELECT COUNT(*) FROM candidates WHERE stage=? AND archived=0', [$s]);
    echo '<div class="card"><div class="card-h"><h3>' . icon('users', 17) . ' Inside hiring pipeline</h3><a class="small" href="recruitment.php">Open</a></div>' . chart_bars($by) . '</div>';
}
if (can('finance') || can('fees') || is_super()) {
    $days = [];
    for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $days[date('d M', strtotime($d))] = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction='in' AND pay_date=?", [$d]); }
    echo '<div class="card"><div class="card-h"><h3>' . icon('dollar', 17) . ' Collections · last 14 days</h3><a class="small" href="finance.php?t=daily">Open</a></div>' . chart_cols($days) . '</div>';
}
if (can('outside')) {
    $tot = ['Requested' => 0, 'Submitted' => 0, 'Selected' => 0, 'Joined' => 0];
    foreach (rows("SELECT * FROM demands WHERE archived=0 AND status IN ('Approved','In Progress')") as $d) { $p = demand_progress($d); $tot['Requested'] += $p['requested']; $tot['Submitted'] += $p['submitted']; $tot['Selected'] += $p['selected']; $tot['Joined'] += $p['joined']; }
    echo '<div class="card"><div class="card-h"><h3>' . icon('building', 17) . ' Outside demand progress</h3><a class="small" href="outside.php">Open</a></div>' . chart_bars($tot) . '</div>';
}
echo '</div>';

// exceptions & approvals
$ex = [];
if (can('inside')) foreach (rows("SELECT * FROM inside_files WHERE status='Open' AND archived=0 AND next_pay_date<? ORDER BY next_pay_date LIMIT 6", [today()]) as $f) { $b = file_money($f)['balance']; if ($b > 0) $ex[] = [badge('Overdue'), 'Balance overdue · ' . e($f['ref_no']) . ' · ' . e(ent_title('candidates', $f['candidate_id'])), money($b), ent_url('inside_files', $f['id'])]; }
if (can('outside_ops')) foreach (rows("SELECT * FROM demands WHERE status='Pending Approval' AND archived=0 LIMIT 5") as $d) $ex[] = [badge('Approval'), 'Demand waiting for approval · ' . e($d['ref_no'] . ' ' . $d['position']), e(ent_title('companies', $d['company_id'])), ent_url('demands', $d['id']) . '&act=approve'];
if (can('hrm', 'approve')) foreach (rows("SELECT * FROM leave_requests WHERE status='Pending' AND archived=0 LIMIT 5") as $l) $ex[] = [badge('Approval'), 'Leave request · ' . e(ent_title('employees', $l['employee_id'])), num($l['days']) . ' days', ent_url('leave_requests', $l['id']) . '&act=decide'];
if (can('erp', 'approve')) foreach (rows("SELECT * FROM purchase_requests WHERE status='Pending' AND archived=0 LIMIT 5") as $l) $ex[] = [badge('Approval'), 'Purchase request · ' . e($l['item']), money($l['est_cost']), ent_url('purchase_requests', $l['id'])];
foreach (rows("SELECT * FROM issues WHERE status IN ('Open','In Progress') AND severity IN ('High','Critical') AND archived=0 LIMIT 5") as $i) $ex[] = [badge($i['severity']), 'Issue · ' . e($i['ref_no'] . ' ' . $i['subject']), e(user_name($i['owner'])), ent_url('issues', $i['id'])];
foreach (rows("SELECT * FROM tasks WHERE status<>'Done' AND due_date<? AND archived=0 AND (owner=? OR ?=1) LIMIT 5", [today(), $u['id'], is_super() ? 1 : 0]) as $t) $ex[] = [badge('Overdue'), 'Task · ' . e($t['title']), e(user_name($t['owner'])), $t['link'] ?: ent_url('tasks', $t['id'])];

echo '<div class="grid g2" style="margin-top:16px"><div class="card"><div class="card-h"><h3>' . icon('alert', 17) . ' Exceptions &amp; approvals <span class="count">' . count($ex) . '</span></h3></div>'
    . simple_table(['', 'Item', ''], array_map(function ($x) { return [$x[0], $x[1], '<span class="muted">' . $x[2] . '</span>']; }, $ex), 'Nothing needs your attention right now.', array_column($ex, 3)) . '</div>';

$goals = rows('SELECT * FROM company_goals WHERE archived=0 ORDER BY target_date');
echo '<div class="card"><div class="card-h"><h3>' . icon('target', 17) . ' Company goals</h3><a class="small" href="goals.php">Open</a></div><ul class="list-plain">';
foreach ($goals as $g) echo '<li><span><b>' . e($g['title']) . '</b><br><small class="muted">' . e($g['business_line']) . ' · ' . e(dfmt($g['target_date'])) . '</small></span><span style="min-width:140px">' . progress(goal_progress($g['id'])) . badge($g['status']) . '</span></li>';
echo $goals ? '' : '<li class="muted">No goals yet.</li>';
echo '</ul></div></div>';

if (can('reports')) { card_open(icon('bar-chart', 17) . ' Executive KPIs', '<a class="small" href="reports.php">All reports</a>'); reports_kpi(); card_close(); }
if (is_super()) {
    $a = rows('SELECT * FROM audit ORDER BY id DESC LIMIT 8');
    card_open(icon('eye', 17) . ' Recent activity (audit)', '<a class="small" href="audit.php">Full audit report</a>');
    echo simple_table(['When', 'Who', 'Action', 'Record'], array_map(function ($x) { return [e(ago($x['at'])), e($x['actor']), e(ucfirst($x['action'])), e(($x['tbl'] ? $x['tbl'] . ' #' . $x['rec_id'] : ''))]; }, $a));
    card_close();
}
page_end();
