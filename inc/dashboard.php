<?php
// Super Admin dashboard, one tab per business section, with live counters, income / expense / profit,
// staff performance and attendance. Numbers are always computed from records.

const DASH_SECTIONS = [
    'overview' => ['Overview', 'grid', null, []],
    'inside' => ['Inside Hiring', 'users', 'Inside Hiring', ['Inside Hiring', 'Deployment']],
    'outside' => ['Outside Hiring', 'building', 'Outside Hiring', ['Outside Hiring']],
    'travel' => ['Travel & Tour', 'plane', 'Travel & Tour', ['Travel & Tour']],
    'marketing' => ['Marketing', 'target', 'Marketing', ['Marketing']],
    'marriage' => ['Marriage Bureau', 'heart', 'Marriage Bureau', ['Marriage Bureau']],
    'it' => ['IT & Business Dev', 'code', 'IT & Business Development', ['IT & Business Development']],
    'accounts' => ['Accounts', 'dollar', null, ['Accounts']],
    'staff' => ['Staff & Attendance', 'id', null, []],
];

/** Income and expense per section. Income = money in + other income; expense = money out + expenses. */
function section_pl($from = null, $to = null)
{
    $out = [];
    foreach (SECTIONS as $s) $out[$s] = ['in' => 0.0, 'out' => 0.0];
    $w = ''; $p = [];
    if ($from) { $w .= ' AND pay_date>=?'; $p[] = $from; }
    if ($to) { $w .= ' AND pay_date<=?'; $p[] = $to; }
    foreach (rows("SELECT COALESCE(section,'General') AS s, direction AS d, SUM(amount) AS t FROM payments WHERE 1=1 $w GROUP BY COALESCE(section,'General'), direction", $p) as $r) {
        $s = isset($out[$r['s']]) ? $r['s'] : 'General';
        $out[$s][$r['d'] === 'in' ? 'in' : 'out'] += (float)$r['t'];
    }
    $w = ''; $p = [];
    if ($from) { $w .= ' AND entry_date>=?'; $p[] = $from; }
    if ($to) { $w .= ' AND entry_date<=?'; $p[] = $to; }
    foreach (rows("SELECT COALESCE(business_line,'General') AS s, kind AS k, SUM(amount) AS t FROM finance_entries WHERE archived=0 $w GROUP BY COALESCE(business_line,'General'), kind", $p) as $r) {
        $s = isset($out[$r['s']]) ? $r['s'] : 'General';
        $out[$s][$r['k'] === 'Expense' ? 'out' : 'in'] += (float)$r['t'];
    }
    return $out;
}
/** Last $n months: [labels, income[], expense[]] for one section or all. */
function month_trend($section = null, $n = 6)
{
    $l = []; $in = []; $ex = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $a = date('Y-m-01', strtotime("first day of -$i month"));
        $b = date('Y-m-t', strtotime($a));
        $pl = section_pl($a, $b);
        $l[] = date('M', strtotime($a));
        $in[] = $section ? $pl[$section]['in'] : array_sum(array_column($pl, 'in'));
        $ex[] = $section ? $pl[$section]['out'] : array_sum(array_column($pl, 'out'));
    }
    return [$l, $in, $ex];
}

/** Animated area chart with two series (income / expense). */
function chart_trend(array $labels, array $a, array $b, $la = 'Income', $lb = 'Expense')
{
    $max = max(1, max(array_merge($a, $b)));
    $n = count($labels);
    $W = 600; $H = 190; $pad = 10;
    $pt = function ($i, $v) use ($n, $W, $H, $pad, $max) { return round($pad + $i * (($W - 2 * $pad) / max(1, $n - 1)), 1) . ',' . round($H - $pad - ($v / $max) * ($H - 2 * $pad), 1); };
    $line = function ($vals) use ($pt) { $o = []; foreach ($vals as $i => $v) $o[] = $pt($i, $v); return implode(' ', $o); };
    $area = function ($vals) use ($line, $W, $H, $pad, $n) { return $pad . ',' . ($H - $pad) . ' ' . $line($vals) . ' ' . round($pad + ($n - 1) * (($W - 2 * $pad) / max(1, $n - 1)), 1) . ',' . ($H - $pad); };
    $grid = '';
    for ($g = 1; $g <= 3; $g++) { $y = round($pad + ($H - 2 * $pad) * $g / 4, 1); $grid .= '<line x1="0" x2="' . $W . '" y1="' . $y . '" y2="' . $y . '"/>'; }
    $dots = '';
    foreach ($a as $i => $v) { list($x, $y) = explode(',', $pt($i, $v)); $dots .= '<circle class="d-a" cx="' . $x . '" cy="' . $y . '" r="3.5"><title>' . e($labels[$i] . ' · ' . $la . ': ' . money($v)) . '</title></circle>'; }
    foreach ($b as $i => $v) { list($x, $y) = explode(',', $pt($i, $v)); $dots .= '<circle class="d-b" cx="' . $x . '" cy="' . $y . '" r="3.5"><title>' . e($labels[$i] . ' · ' . $lb . ': ' . money($v)) . '</title></circle>'; }
    return '<div class="trend"><div class="trend-leg"><span class="la">' . e($la) . '</span><span class="lb">' . e($lb) . '</span></div><svg viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" role="img" aria-label="' . e($la . ' and ' . $lb . ' trend') . '"><g class="grid">' . $grid . '</g>'
        . '<polygon class="ar-a" points="' . $area($a) . '"/><polygon class="ar-b" points="' . $area($b) . '"/><polyline class="ln-a" points="' . $line($a) . '"/><polyline class="ln-b" points="' . $line($b) . '"/>' . $dots . '</svg>'
        . '<div class="col-labels" style="grid-template-columns:repeat(' . $n . ',1fr)">' . implode('', array_map(function ($x) { return '<span>' . e($x) . '</span>'; }, $labels)) . '</div></div>';
}
/** Income vs expense bars per label, with profit or loss. */
function chart_pl(array $pl)
{
    $max = 1;
    foreach ($pl as $v) $max = max($max, $v['in'], $v['out']);
    $h = '<div class="plbars">';
    $i = 0;
    foreach ($pl as $k => $v) {
        $net = $v['in'] - $v['out'];
        $h .= '<div class="plrow" style="--d:' . ($i++ * 70) . 'ms"><span class="pl-l">' . e($k) . '</span><span class="pl-b"><i class="in" style="--w:' . round($v['in'] / $max * 100, 1) . '%" title="Income ' . e(money($v['in'])) . '"></i><i class="out" style="--w:' . round($v['out'] / $max * 100, 1) . '%" title="Expense ' . e(money($v['out'])) . '"></i></span><b class="' . ($net < 0 ? 'neg' : 'pos') . '">' . ($net < 0 ? '−' : '+') . e(money(abs($net))) . '</b></div>';
    }
    return $h . '<div class="pl-leg"><span class="in">Income</span><span class="out">Expense</span><span>Profit / loss</span></div></div>';
}

/** Key numbers for one person, chosen by their team. */
function staff_perf($u, $from)
{
    $id = (int)$u['id'];
    $c = function ($sql, $p) { return (int)val($sql, $p); };
    switch ($u['team']) {
        case 'Inside Hiring':
            return ['Entries' => $c('SELECT COUNT(*) FROM candidates WHERE created_by=? AND created_at>=?', [$id, $from]), 'Interviews' => $c('SELECT COUNT(*) FROM candidates WHERE hr_officer=? AND interviewed_at>=?', [$id, $from]),
                'Files' => $c('SELECT COUNT(*) FROM inside_files WHERE hr_owner=? AND created_at>=?', [$id, $from]), 'To deployment' => $c("SELECT COUNT(*) FROM stage_history WHERE entity='candidates' AND to_stage='In Deployment' AND by_user=? AND at>=?", [$id, $from])];
        case 'Deployment':
            return ['Candidates' => $c("SELECT COUNT(*) FROM candidates WHERE deploy_officer=? AND stage IN ('In Deployment','Selected','Placed')", [$id]), 'Locations sent' => $c('SELECT COUNT(*) FROM interview_locations WHERE created_by=? AND created_at>=?', [$id, $from]),
                'Selected' => $c("SELECT COUNT(*) FROM stage_history h JOIN candidates c ON c.id=h.entity_id WHERE h.entity='candidates' AND h.to_stage='Selected' AND c.deploy_officer=? AND h.at>=?", [$id, $from]), 'Chats' => $c("SELECT COUNT(*) FROM chat_messages WHERE sender_type='Staff' AND sender_id=? AND created_at>=?", [$id, $from])];
        case 'Outside Hiring':
            return ['Open demands' => $c("SELECT COUNT(*) FROM demands WHERE owner=? AND status IN ('Approved','In Progress') AND archived=0", [$id]), 'Workers added' => $c('SELECT COUNT(*) FROM workers WHERE created_by=? AND created_at>=?', [$id, $from]),
                'Joined' => $c("SELECT COUNT(*) FROM stage_history WHERE entity='workers' AND to_stage='Joined' AND by_user=? AND at>=?", [$id, $from])];
        case 'Travel & Tour':
            return ['Enquiries' => $c('SELECT COUNT(*) FROM travel_enquiries WHERE assigned_to=? AND created_at>=?', [$id, $from]), 'Bookings' => $c('SELECT COUNT(*) FROM travel_bookings WHERE created_by=? AND created_at>=?', [$id, $from])];
        case 'Marketing':
            return ['Enquiries' => $c('SELECT COUNT(*) FROM enquiries WHERE (assigned_to=? OR created_by=?) AND created_at>=?', [$id, $id, $from]), 'Leads' => $c('SELECT COUNT(*) FROM leads WHERE owner=? AND created_at>=?', [$id, $from]), 'Posts' => $c('SELECT COUNT(*) FROM campaign_posts WHERE marketer=? AND created_at>=?', [$id, $from])];
        case 'Marriage Bureau':
            return ['Files' => $c('SELECT COUNT(*) FROM paybook_files WHERE owner=? AND created_at>=?', [$id, $from]), 'Tasks done' => $c("SELECT COUNT(*) FROM paybook_tasks WHERE assignee=? AND status='Done'", [$id])];
        case 'IT & Business Development':
            return ['Tasks done' => $c("SELECT COUNT(*) FROM it_tasks WHERE assignee=? AND status='Done'", [$id]), 'Open tickets' => $c("SELECT COUNT(*) FROM support_tickets WHERE assigned_to=? AND status NOT IN ('Resolved','Closed') AND archived=0", [$id])];
        case 'Accounts':
            return ['Receipts posted' => $c('SELECT COUNT(*) FROM payments WHERE created_by=? AND created_at>=?', [$id, $from]), 'Expenses' => $c('SELECT COUNT(*) FROM finance_entries WHERE created_by=? AND created_at>=?', [$id, $from])];
    }
    return ['Records created' => $c("SELECT COUNT(*) FROM audit WHERE actor_id=? AND actor_type='staff' AND action='create' AND at>=?", [$id, $from])];
}
/** Staff table with today's attendance, this month's presence and key numbers. */
function staff_table(array $teams)
{
    if (!$teams) return '';
    $from = date('Y-m-01');
    $us = rows('SELECT u.*, r.name AS role FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.active=1 AND u.team IN (' . implode(',', array_fill(0, count($teams), '?')) . ') ORDER BY u.team, u.name', $teams);
    $data = [];
    foreach ($us as $u) {
        $a = attendance_today($u['id']);
        $days = rows('SELECT check_in, check_out FROM staff_attendance WHERE user_id=? AND day>=?', [$u['id'], $from]);
        $hrs = 0; $n = 0;
        foreach ($days as $d) if ($d['check_out']) { $hrs += (strtotime($d['check_out']) - strtotime($d['check_in'])) / 3600; $n++; }
        $perf = staff_perf($u, $from);
        $pf = '';
        foreach ($perf as $k => $v) $pf .= '<span class="perf"><b>' . (int)$v . '</b> ' . e($k) . '</span>';
        $data[] = ['<span class="avatar sm">' . e(strtoupper(substr($u['name'], 0, 1))) . '</span> <b>' . e($u['name']) . '</b><br><small class="muted">' . e($u['role']) . ' · ' . e($u['team']) . '</small>',
            $a ? ($a['check_out'] ? badge('Left ' . substr($a['check_out'], 11, 5)) : '<span class="att-on"><i></i>In ' . e(substr($a['check_in'], 11, 5)) . '</span>') : badge('Absent'),
            count($days) . ' d' . ($n ? '<br><small class="muted">' . round($hrs / $n, 1) . ' h avg</small>' : ''), $pf];
    }
    return simple_table(['Staff', 'Today', 'This month', 'Performance this month'], $data, 'No staff in this team yet. Set each person\'s team in Staff & Roles.');
}
/** Latest records added in the given tables. */
function activity_feed(array $tables, $limit = 8)
{
    $where = $tables ? ' AND tbl IN (' . implode(',', array_fill(0, count($tables), '?')) . ')' : '';
    $list = rows("SELECT * FROM audit WHERE action IN ('create','payment','stage','file-close','final-outcome','self-register') $where ORDER BY id DESC LIMIT " . (int)$limit, $tables);
    $h = '<ul class="feed">';
    foreach ($list as $a) {
        $what = ['create' => 'added', 'payment' => 'recorded a payment on', 'stage' => 'moved', 'file-close' => 'closed', 'final-outcome' => 'recorded an outcome for', 'self-register' => 'registered online'][$a['action']] ?? $a['action'];
        $url = $a['tbl'] && $a['rec_id'] && entdef($a['tbl']) ? ent_url($a['tbl'], $a['rec_id']) : null;
        $rec = $a['tbl'] && entdef($a['tbl']) ? ent_title($a['tbl'], $a['rec_id']) : $a['tbl'];
        $h .= '<li><i></i><div><b>' . e(preg_replace('/ \(.*\)$/', '', $a['actor'])) . '</b> ' . e($what) . ' ' . ($url ? '<a href="' . e($url) . '">' . e($rec) . '</a>' : e($rec)) . '<small>' . e(ago($a['at'])) . '</small></div></li>';
    }
    return $h . ($list ? '' : '<li class="muted">No activity yet.</li>') . '</ul>';
}

/** Tiles: key => [label, value, format, icon, tone, link]. */
function dash_tiles($sec)
{
    $m = date('Y-m-01');
    $t = today();
    $c = function ($sql, $p = []) { return (float)val($sql, $p); };
    $pl = function ($s) use ($m) { $x = section_pl($m)[$s]; return $x; };
    switch ($sec) {
        case 'overview':
            $all = section_pl($m);
            $in = array_sum(array_column($all, 'in')); $out = array_sum(array_column($all, 'out'));
            return [
                'cand_today' => ['New candidates today', $c('SELECT COUNT(*) FROM candidates WHERE created_at>=? AND archived=0', [$t]), 'num', 'user-plus', 'blue', 'reception.php'],
                'in_deploy' => ['In deployment', $c("SELECT COUNT(*) FROM candidates WHERE stage='In Deployment' AND archived=0"), 'num', 'send', 'violet', 'deployment.php?t=pipeline'],
                'demands' => ['Open outside demands', $c("SELECT COUNT(*) FROM demands WHERE status IN ('Approved','In Progress') AND archived=0"), 'num', 'building', 'amber', 'outside.php?t=demands'],
                'trips' => ['Upcoming trips', $c("SELECT COUNT(*) FROM travel_bookings WHERE travel_date>=? AND status='Confirmed' AND archived=0", [$t]), 'num', 'plane', 'blue', 'travel.php?t=bookings'],
                'marriage' => ['Open marriage files', $c("SELECT COUNT(*) FROM paybook_files WHERE status IN ('Open','In Progress') AND archived=0"), 'num', 'heart', 'red', 'paybook.php'],
                'income' => ['Income this month', $in, 'money', 'dollar', 'green', 'finance.php?t=sections'],
                'expense' => ['Expenses this month', $out, 'money', 'card', 'red', 'finance.php?t=sections'],
                'profit' => ['Profit this month', $in - $out, 'money', 'bar-chart', $in - $out < 0 ? 'red' : 'gold', 'finance.php?t=sections'],
                'present' => ['Staff present today', (int)$c('SELECT COUNT(*) FROM staff_attendance WHERE day=?', [$t]) . ' / ' . (int)$c('SELECT COUNT(*) FROM users WHERE active=1'), 'text', 'clock', 'green', 'index.php?s=staff'],
                'issues' => ['Open issues', $c("SELECT COUNT(*) FROM issues WHERE status IN ('Open','In Progress') AND archived=0"), 'num', 'alert', 'amber', 'tasks.php?t=issues'],
            ];
        case 'inside':
            $x = $pl('Inside Hiring');
            $bal = 0; foreach (rows("SELECT * FROM inside_files WHERE status='Open' AND archived=0") as $f) $bal += max(0, file_money($f)['balance']);
            return [
                'today' => ['Entries today', $c('SELECT COUNT(*) FROM candidates WHERE created_at>=? AND archived=0', [$t]), 'num', 'user-plus', 'blue', 'reception.php'],
                'hrq' => ['Waiting for HR', $c("SELECT COUNT(*) FROM candidates WHERE stage IN ('New','HR Assigned') AND archived=0"), 'num', 'clock', 'amber', 'reception.php'],
                'ready' => ['Ready for deployment', $c("SELECT COUNT(*) FROM candidates WHERE stage='File Opened' AND archived=0"), 'num', 'folder', 'violet', 'reception.php?t=ready'],
                'deploy' => ['In deployment', $c("SELECT COUNT(*) FROM candidates WHERE stage='In Deployment' AND archived=0"), 'num', 'send', 'blue', 'deployment.php?t=pipeline'],
                'files' => ['Files opened this month', $c('SELECT COUNT(*) FROM inside_files WHERE created_at>=? AND archived=0', [$m]), 'num', 'file-text', 'gold', 'inside.php'],
                'sel' => ['Selected this month', $c("SELECT COUNT(*) FROM stage_history WHERE entity='candidates' AND to_stage='Selected' AND at>=?", [$m]), 'num', 'award', 'green', 'deployment.php?t=pipeline'],
                'in' => ['Income this month', $x['in'], 'money', 'dollar', 'green', 'finance.php?t=sections&sec=Inside+Hiring'],
                'out' => ['Expenses this month', $x['out'], 'money', 'card', 'red', 'finance.php?t=sections&sec=Inside+Hiring'],
                'bal' => ['Outstanding balances', $bal, 'money', 'alert', 'amber', 'inside.php?t=dues'],
            ];
        case 'outside':
            $x = $pl('Outside Hiring');
            $req = 0; $sub = 0; $joined = 0;
            foreach (rows("SELECT * FROM demands WHERE archived=0 AND status IN ('Approved','In Progress')") as $d) { $p = demand_progress($d); $req += $p['requested']; $sub += $p['submitted']; $joined += $p['joined']; }
            return [
                'dem' => ['Open demands', $c("SELECT COUNT(*) FROM demands WHERE status IN ('Approved','In Progress') AND archived=0"), 'num', 'clipboard', 'blue', 'outside.php?t=demands'],
                'appr' => ['Waiting for approval', $c("SELECT COUNT(*) FROM demands WHERE status='Pending Approval' AND archived=0"), 'num', 'clock', 'amber', 'outside_ops.php'],
                'req' => ['Requested headcount', $req, 'num', 'users', 'violet', 'outside.php'],
                'sub' => ['Submitted', $sub, 'num', 'upload', 'blue', 'outside.php?t=workers'],
                'joined' => ['Joined', $joined, 'num', 'check-circle', 'green', 'outside.php?t=workers'],
                'visa' => ['Visa pending', $c("SELECT COUNT(*) FROM workers WHERE (stage='Visa Processing' OR visa_status='Applied') AND archived=0"), 'num', 'id', 'gold', 'outside_ops.php?t=travel'],
                'in' => ['Income this month', $x['in'], 'money', 'dollar', 'green', 'finance.php?t=sections&sec=Outside+Hiring'],
                'out' => ['Expenses this month', $x['out'], 'money', 'card', 'red', 'finance.php?t=sections&sec=Outside+Hiring'],
            ];
        case 'travel':
            $x = $pl('Travel & Tour');
            $b = rows("SELECT * FROM travel_bookings WHERE archived=0 AND status<>'Cancelled'");
            $cp = 0; $sp = 0; foreach ($b as $y) { $cp += paid_total('travel_bookings', $y['id']); $sp += paid_total('travel_bookings', $y['id'], 'out'); }
            return [
                'enq' => ['New enquiries', $c("SELECT COUNT(*) FROM travel_enquiries WHERE status='New' AND archived=0"), 'num', 'phone', 'gold', 'travel.php?t=enquiries'],
                'bk' => ['Bookings this month', $c('SELECT COUNT(*) FROM travel_bookings WHERE created_at>=? AND archived=0', [$m]), 'num', 'plane', 'blue', 'travel.php?t=bookings'],
                'trips' => ['Upcoming trips', $c("SELECT COUNT(*) FROM travel_bookings WHERE travel_date>=? AND status='Confirmed' AND archived=0", [$t]), 'num', 'calendar', 'violet', 'travel.php?t=bookings'],
                'cust' => ['Customer outstanding', array_sum(array_column($b, 'sale_price')) - $cp, 'money', 'alert', 'amber', 'travel.php'],
                'supp' => ['Supplier payable', array_sum(array_column($b, 'supplier_cost')) - $sp, 'money', 'truck', 'red', 'travel.php'],
                'in' => ['Income this month', $x['in'], 'money', 'dollar', 'green', 'finance.php?t=sections&sec=Travel+%26+Tour'],
                'out' => ['Expenses this month', $x['out'], 'money', 'card', 'red', 'finance.php?t=sections&sec=Travel+%26+Tour'],
            ];
        case 'marketing':
            $x = $pl('Marketing');
            $leads = $c('SELECT COUNT(*) FROM enquiries WHERE created_at>=? AND archived=0', [$m]);
            $spend = $c('SELECT COALESCE(SUM(spend),0) FROM campaign_posts WHERE posted_on>=? AND archived=0', [$m]);
            return [
                'vac' => ['Open vacancies', $c("SELECT COUNT(*) FROM vacancies WHERE status='Open' AND archived=0"), 'num', 'briefcase', 'blue', 'marketing.php?t=vacancies'],
                'enq_t' => ['Enquiries today', $c('SELECT COUNT(*) FROM enquiries WHERE created_at>=? AND archived=0', [$t]), 'num', 'phone', 'gold', 'marketing.php?t=enquiries'],
                'enq_m' => ['Enquiries this month', $leads, 'num', 'activity', 'violet', 'marketing.php?t=enquiries'],
                'cand_m' => ['Candidates this month', $c('SELECT COUNT(*) FROM candidates WHERE created_at>=? AND archived=0', [$m]), 'num', 'users', 'green', 'marketing.php?t=sources'],
                'leads' => ['Open CRM leads', $c("SELECT COUNT(*) FROM leads WHERE status NOT IN ('Won','Lost') AND archived=0"), 'num', 'target', 'blue', 'crm.php?t=leads'],
                'spend' => ['Ad spend this month', $spend, 'money', 'card', 'red', 'marketing.php?t=performance'],
                'cpl' => ['Cost per enquiry', $leads ? $spend / $leads : 0, 'money', 'percent', 'amber', 'marketing.php?t=performance'],
            ];
        case 'marriage':
            $x = $pl('Marriage Bureau');
            $files = rows("SELECT * FROM paybook_files WHERE archived=0 AND status<>'Cancelled'");
            $paid = 0; foreach ($files as $f) $paid += paid_total('paybook_files', $f['id']) - paid_total('paybook_files', $f['id'], 'out');
            return [
                'open' => ['Open files', $c("SELECT COUNT(*) FROM paybook_files WHERE status IN ('Open','In Progress') AND archived=0"), 'num', 'heart', 'red', 'paybook.php'],
                'new' => ['New this month', $c('SELECT COUNT(*) FROM paybook_files WHERE created_at>=? AND archived=0', [$m]), 'num', 'user-plus', 'blue', 'paybook.php'],
                'value' => ['Agreed value', array_sum(array_column($files, 'agreed_value')), 'money', 'tag', 'violet', 'paybook.php'],
                'bal' => ['Balance to collect', array_sum(array_column($files, 'agreed_value')) - $paid, 'money', 'alert', 'amber', 'paybook.php'],
                'in' => ['Income this month', $x['in'], 'money', 'dollar', 'green', 'finance.php?t=sections&sec=Marriage+Bureau'],
                'out' => ['Expenses this month', $x['out'], 'money', 'card', 'red', 'finance.php?t=sections&sec=Marriage+Bureau'],
            ];
        case 'it':
            $x = $pl('IT & Business Development');
            return [
                'prj' => ['IT projects running', $c("SELECT COUNT(*) FROM it_projects WHERE stage<>'Support & Renewal' AND archived=0"), 'num', 'code', 'violet', 'it.php?t=projects'],
                'tkt' => ['Open tickets', $c("SELECT COUNT(*) FROM support_tickets WHERE status NOT IN ('Resolved','Closed') AND archived=0"), 'num', 'headphones', 'amber', 'it.php?t=tickets'],
                'deals' => ['Open deals value', $c("SELECT COALESCE(SUM(value),0) FROM crm_deals WHERE stage NOT IN ('Won','Lost') AND archived=0"), 'money', 'briefcase', 'gold', 'crm.php'],
                'won' => ['Deals won this month', $c("SELECT COUNT(*) FROM crm_deals WHERE stage='Won' AND updated_at>=? AND archived=0", [$m]), 'num', 'award', 'green', 'crm.php?t=deals'],
                'in' => ['Income this month', $x['in'], 'money', 'dollar', 'green', 'finance.php?t=sections&sec=IT+%26+Business+Development'],
                'out' => ['Expenses this month', $x['out'], 'money', 'card', 'red', 'finance.php?t=sections&sec=IT+%26+Business+Development'],
            ];
        case 'accounts':
            $all = section_pl($m); $life = section_pl();
            $in = array_sum(array_column($all, 'in')); $out = array_sum(array_column($all, 'out'));
            $bank = 0; foreach (rows('SELECT * FROM bank_accounts WHERE archived=0') as $b) $bank += bank_balance($b);
            return [
                'in' => ['Income this month', $in, 'money', 'dollar', 'green', 'finance.php?t=sections'],
                'out' => ['Expenses this month', $out, 'money', 'card', 'red', 'finance.php?t=sections'],
                'net' => ['Profit this month', $in - $out, 'money', 'bar-chart', $in - $out < 0 ? 'red' : 'gold', 'finance.php?t=sections'],
                'life' => ['Profit (all time)', array_sum(array_column($life, 'in')) - array_sum(array_column($life, 'out')), 'money', 'pie', 'violet', 'accounting.php?t=balance'],
                'today' => ['Collected today', $c("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction='in' AND pay_date=?", [$t]), 'money', 'zap', 'blue', 'finance.php?t=daily'],
                'bank' => ['Bank balance', $bank, 'money', 'card', 'blue', 'accounting.php?t=banks'],
            ];
        case 'staff':
            $act = (int)$c('SELECT COUNT(*) FROM users WHERE active=1');
            $pres = (int)$c('SELECT COUNT(*) FROM staff_attendance WHERE day=?', [$t]);
            return [
                'staff' => ['Active staff', $act, 'num', 'users', 'blue', 'staff.php'],
                'present' => ['Present today', $pres, 'num', 'check-circle', 'green', 'hrm.php?t=register'],
                'absent' => ['Not checked in', max(0, $act - $pres), 'num', 'alert', 'red', 'reception.php?t=attendance'],
                'out' => ['Checked out', $c('SELECT COUNT(*) FROM staff_attendance WHERE day=? AND check_out IS NOT NULL', [$t]), 'num', 'logout', 'amber', 'hrm.php?t=register'],
                'leave' => ['Leave requests pending', $c("SELECT COUNT(*) FROM leave_requests WHERE status='Pending' AND archived=0"), 'num', 'calendar', 'violet', 'hrm.php?t=leave'],
            ];
    }
    return [];
}
function dash_fmt($v, $f) { return $f === 'money' ? money($v) : ($f === 'num' ? num($v) : (string)$v); }

/** JSON for the live refresh. */
function dash_live_json($sec)
{
    $o = [];
    foreach (dash_tiles($sec) as $k => $t) $o[$k] = ['v' => dash_fmt($t[1], $t[2]), 'n' => is_numeric($t[1]) ? (float)$t[1] : null];
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['tiles' => $o, 'at' => date('H:i:s')]);
    exit;
}

function dash_render($sec)
{
    $S = DASH_SECTIONS[$sec];
    echo '<nav class="sec-tabs" aria-label="Business sections">';
    foreach (DASH_SECTIONS as $k => $d) echo '<a class="' . ($k === $sec ? 'on' : '') . '" href="index.php?s=' . $k . '">' . icon($d[1], 16) . '<span>' . e($d[0]) . '</span></a>';
    echo '</nav>';
    echo '<div class="live-head"><h2>' . icon($S[1], 22) . ' ' . e($S[0]) . '</h2><span class="live"><i></i>Live · updated <b data-live-at>' . date('H:i:s') . '</b></span></div>';
    echo '<div class="stats" data-live-src="index.php?s=' . e($sec) . '&live=1">';
    $i = 0;
    foreach (dash_tiles($sec) as $k => $t) {
        echo '<a class="stat t-' . e($t[4]) . '" data-k="' . e($k) . '" style="--d:' . ($i++ * 45) . 'ms" href="' . e($t[5]) . '"><span class="stat-i">' . icon($t[3], 20) . '</span><span class="stat-v" data-count="' . e(is_numeric($t[1]) && $t[2] === 'num' ? $t[1] : '') . '">' . e(dash_fmt($t[1], $t[2])) . '</span><span class="stat-l">' . e($t[0]) . '</span></a>';
    }
    echo '</div>';
    $m = date('Y-m-01');

    if ($sec === 'overview') {
        list($l, $a, $b) = month_trend(null);
        $pl = section_pl($m);
        echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('activity', 17) . ' Income &amp; expenses · last 6 months</h3></div>' . chart_trend($l, $a, $b) . '</div>'
            . '<div class="card"><div class="card-h"><h3>' . icon('bar-chart', 17) . ' Sections this month</h3><a class="small" href="index.php?s=accounts">Accounts</a></div>' . chart_pl(array_filter($pl, function ($v) { return $v['in'] || $v['out']; }) ?: $pl) . '</div></div>';
        $by = [];
        foreach (CAND_STAGES as $s) $by[$s] = (int)val('SELECT COUNT(*) FROM candidates WHERE stage=? AND archived=0', [$s]);
        echo '<div class="grid g3" style="margin-top:16px"><div class="card"><div class="card-h"><h3>' . icon('users', 17) . ' Inside hiring pipeline</h3></div>' . chart_bars($by) . '</div>';
        $team = [];
        foreach (rows("SELECT COALESCE(u.team,'Unassigned') AS t, COUNT(u.id) AS n, SUM(CASE WHEN a.id IS NULL THEN 0 ELSE 1 END) AS p FROM users u LEFT JOIN staff_attendance a ON a.user_id=u.id AND a.day=? WHERE u.active=1 GROUP BY COALESCE(u.team,'Unassigned')", [today()]) as $r) $team[$r['t'] . ' (' . (int)$r['p'] . '/' . (int)$r['n'] . ')'] = (int)$r['p'];
        echo '<div class="card"><div class="card-h"><h3>' . icon('clock', 17) . ' Present today by team</h3><a class="small" href="index.php?s=staff">Staff</a></div>' . chart_bars($team) . '</div>';
        echo '<div class="card"><div class="card-h"><h3>' . icon('zap', 17) . ' Live activity</h3></div>' . activity_feed([]) . '</div></div>';
        return;
    }
    if ($sec === 'accounts') {
        $month = section_pl($m); $life = section_pl();
        list($l, $a, $b) = month_trend(null);
        echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('bar-chart', 17) . ' Profit / loss by section · this month</h3></div>' . chart_pl($month) . '</div><div class="card"><div class="card-h"><h3>' . icon('activity', 17) . ' Company trend</h3></div>' . chart_trend($l, $a, $b) . '</div></div>';
        $rowsOut = [];
        foreach (SECTIONS as $s) $rowsOut[] = ['<b>' . e($s) . '</b>', money($month[$s]['in']), money($month[$s]['out']), '<b class="' . ($month[$s]['in'] - $month[$s]['out'] < 0 ? 'neg' : 'pos') . '">' . money($month[$s]['in'] - $month[$s]['out']) . '</b>', money($life[$s]['in']), money($life[$s]['out']), '<b>' . money($life[$s]['in'] - $life[$s]['out']) . '</b>', '<a href="finance.php?t=sections&sec=' . urlencode($s) . '">Open</a>'];
        card_open(icon('book', 17) . ' Section accounts');
        echo simple_table(['Section', 'Income (month)', 'Expense (month)', 'Profit (month)', 'Income (all)', 'Expense (all)', 'Profit (all)', ''], $rowsOut);
        card_close();
        card_open(icon('users', 17) . ' Accounts team');
        echo staff_table(['Accounts']);
        card_close();
        return;
    }
    if ($sec === 'staff') {
        foreach (options('staff_teams') as $team) {
            $n = (int)val('SELECT COUNT(*) FROM users WHERE active=1 AND team=?', [$team]);
            if (!$n) continue;
            card_open(icon('users', 17) . ' ' . e($team) . ' <span class="count">' . $n . '</span>');
            echo staff_table([$team]);
            card_close();
        }
        $none = (int)val("SELECT COUNT(*) FROM users WHERE active=1 AND (team IS NULL OR team='')");
        if ($none) echo '<div class="alert warn">' . $none . ' staff member(s) have no team. Set it in <a href="staff.php">Staff &amp; Roles</a>.</div>';
        return;
    }

    // a business section
    list($l, $a, $b) = month_trend($S[2]);
    $x = section_pl($m)[$S[2]];
    $net = $x['in'] - $x['out'];
    echo '<div class="grid g3"><div class="card span2"><div class="card-h"><h3>' . icon('activity', 17) . ' Income &amp; expenses · last 6 months</h3><span class="chip ' . ($net < 0 ? 'neg' : 'pos') . '">This month: ' . ($net < 0 ? 'loss ' : 'profit ') . e(money(abs($net))) . '</span></div>' . chart_trend($l, $a, $b) . '</div>';
    echo '<div class="card"><div class="card-h"><h3>' . icon('pie', 17) . ' Breakdown</h3></div>';
    $d = [];
    switch ($sec) {
        case 'inside': foreach (rows("SELECT COALESCE(source,'Unknown') AS k, COUNT(*) AS n FROM candidates WHERE created_at>=? AND archived=0 GROUP BY COALESCE(source,'Unknown')", [$m]) as $r) $d[$r['k']] = (int)$r['n']; echo '<p class="muted small">Candidates by source this month</p>'; break;
        case 'outside': foreach (rows("SELECT c.display_name AS k, SUM(COALESCE(d.approved_qty,d.quantity)) AS n FROM demands d JOIN companies c ON c.id=d.company_id WHERE d.archived=0 AND d.status IN ('Approved','In Progress') GROUP BY c.display_name") as $r) $d[$r['k']] = (int)$r['n']; echo '<p class="muted small">Requested headcount by client</p>'; break;
        case 'travel': foreach (rows("SELECT status AS k, COUNT(*) AS n FROM travel_enquiries WHERE archived=0 GROUP BY status") as $r) $d[$r['k']] = (int)$r['n']; echo '<p class="muted small">Enquiries by status</p>'; break;
        case 'marketing': foreach (rows("SELECT COALESCE(source,'Unknown') AS k, COUNT(*) AS n FROM enquiries WHERE created_at>=? AND archived=0 GROUP BY COALESCE(source,'Unknown')", [$m]) as $r) $d[$r['k']] = (int)$r['n']; echo '<p class="muted small">Enquiries by source this month</p>'; break;
        case 'marriage': foreach (rows("SELECT COALESCE(source,'Unknown') AS k, COUNT(*) AS n FROM paybook_files WHERE archived=0 GROUP BY COALESCE(source,'Unknown')") as $r) $d[$r['k']] = (int)$r['n']; echo '<p class="muted small">Files by marketing source</p>'; break;
        case 'it': foreach (rows("SELECT stage AS k, COUNT(*) AS n FROM it_projects WHERE archived=0 GROUP BY stage") as $r) $d[$r['k']] = (int)$r['n']; echo '<p class="muted small">IT projects by stage</p>'; break;
    }
    echo chart_donut($d) . '</div></div>';
    $tables = ['inside' => ['candidates', 'inside_files', 'interview_locations'], 'outside' => ['demands', 'workers', 'companies', 'agents'], 'travel' => ['travel_bookings', 'travel_enquiries'],
        'marketing' => ['enquiries', 'campaign_posts', 'leads', 'vacancies'], 'marriage' => ['paybook_files'], 'it' => ['it_projects', 'support_tickets', 'crm_deals']][$sec];
    echo '<div class="grid g3" style="margin-top:16px"><div class="card span2"><div class="card-h"><h3>' . icon('users', 17) . ' Team performance &amp; attendance</h3><span class="muted small">This month</span></div>' . staff_table($S[3]) . '</div>'
        . '<div class="card"><div class="card-h"><h3>' . icon('zap', 17) . ' Latest activity</h3></div>' . activity_feed($tables) . '</div></div>';
}
