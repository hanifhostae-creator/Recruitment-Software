<?php
// Daily Reports: Travel, Marketing and Outside Hiring staff report their day; the system adds the numbers it already knows.

const DR_FIELDS = [
    'Travel' => [['tr_contacts', 'Customers contacted (calls / WhatsApp)'], ['tr_followups', 'Follow-ups done'], ['tr_meetings', 'Customers met in the office']],
    'Marketing' => [['mk_posts', 'Posts published'], ['mk_comments', 'Comments replied'], ['mk_messages', 'Messages / DMs replied'], ['mk_leads', 'Leads generated']],
    'Outside' => [['oh_contacted', 'Candidates dealt with'], ['oh_visas', 'Visas received'], ['oh_agents', 'Agents in discussion'], ['oh_docs', 'Documents in process'], ['oh_interviews', 'Interviews arranged']],
];
const DR_TEAM_LABEL = ['Travel' => 'Travel & Tour', 'Marketing' => 'Marketing', 'Outside' => 'Outside Hiring'];

ent('daily_reports', ['label' => 'Daily Reports', 'one' => 'Daily report', 'title' => 'report_date', 'icon' => 'clipboard', 'related' => false,
    'fields' => ['report_date' => ['Date', 'date', 'req'], 'user_id' => ['Staff', 'user', 'req'], 'team' => ['Team', 'enum:Travel|Marketing|Outside|General'],
        'tr_contacts' => ['Customers contacted', 'int'], 'tr_followups' => ['Follow-ups done', 'int'], 'tr_meetings' => ['Customers met', 'int'],
        'mk_posts' => ['Posts published', 'int'], 'mk_comments' => ['Comments replied', 'int'], 'mk_messages' => ['Messages replied', 'int'], 'mk_leads' => ['Leads generated', 'int'], 'mk_platforms' => ['Platforms', 'text'], 'mk_tasks' => ['Tasks done', 'textarea'],
        'oh_contacted' => ['Candidates dealt with', 'int'], 'oh_visas' => ['Visas received', 'int'], 'oh_agents' => ['Agents in discussion', 'int'], 'oh_docs' => ['Documents in process', 'int'], 'oh_interviews' => ['Interviews arranged', 'int'],
        'summary' => ['What I did today', 'textarea'], 'plan' => ['Plan for tomorrow', 'textarea'], 'issues' => ['Problems / help needed', 'text']],
    'list' => ['report_date', 'user_id', 'team'], 'filters' => ['team', 'user_id']]);

/** Which report form a person fills: from their staff team, otherwise from the modules they can open. */
function dr_team($uid = null)
{
    $u = $uid ? row('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.id=?', [$uid]) : me();
    if (!$u) return 'General';
    $map = ['Travel & Tour' => 'Travel', 'Marketing' => 'Marketing', 'Outside Hiring' => 'Outside'];
    if (isset($map[$u['team'] ?? ''])) return $map[$u['team']];
    $role = (string)($u['role_name'] ?? '');
    foreach (['Travel' => 'Travel', 'Outside' => 'Outside', 'Marketing' => 'Marketing'] as $pre => $t) if (stripos($role, $pre) === 0) return $t;
    return 'General';
}
/** Numbers the system already knows for a person and period (no typing needed). [key => [label, value, kind]] */
function dr_auto($uid, $from, $to, $team)
{
    $r = [];
    if ($team === 'Travel') {
        $leads = (int)val('SELECT COUNT(*) FROM travel_enquiries WHERE archived=0 AND date(created_at) BETWEEN ? AND ? AND (created_by=? OR assigned_to=?)', [$from, $to, $uid, $uid]);
        $sale = 0; $cost = 0; $payable = 0; $deals = 0;
        foreach (rows("SELECT * FROM travel_bookings WHERE archived=0 AND status<>'Cancelled' AND date(created_at) BETWEEN ? AND ? AND (handled_by=? OR created_by=?)", [$from, $to, $uid, $uid]) as $b) { $m = travel_money($b); $deals++; $sale += $m['sale']; $cost += $m['cost']; $payable += max(0, $m['payable']); }
        $open = (int)val("SELECT COUNT(*) FROM travel_enquiries WHERE archived=0 AND assigned_to=? AND status IN ('New','Contacted','Under Discussion','Quoted')", [$uid]);
        $r = ['leads' => ['New leads', $leads, 'num'], 'deals' => ['Deals done', $deals, 'num'], 'conv' => ['Leads → deals', $leads ? round($deals / $leads * 100) . '%' : '—', 'text'],
            'sale' => ['Sales', $sale, 'money'], 'payable' => ['To pay vendors', $payable, 'money'], 'profit' => ['Profit', $sale - $cost, 'money'], 'open' => ['Leads under discussion now', $open, 'num']];
    } elseif ($team === 'Marketing') {
        $r = ['posts' => ['Posts recorded', (int)val('SELECT COUNT(*) FROM campaign_posts WHERE archived=0 AND date(created_at) BETWEEN ? AND ? AND (created_by=? OR marketer=?)', [$from, $to, $uid, $uid]), 'num'],
            'enq' => ['Enquiries entered', (int)val('SELECT COUNT(*) FROM enquiries WHERE archived=0 AND date(created_at) BETWEEN ? AND ? AND created_by=?', [$from, $to, $uid]), 'num'],
            'leads' => ['Leads entered', (int)val('SELECT COUNT(*) FROM leads WHERE archived=0 AND date(created_at) BETWEEN ? AND ? AND created_by=?', [$from, $to, $uid]), 'num'],
            'cands' => ['Candidates from your posts', (int)val('SELECT COUNT(*) FROM candidates c JOIN campaign_posts p ON p.id=c.source_post WHERE c.archived=0 AND date(c.created_at) BETWEEN ? AND ? AND (p.created_by=? OR p.marketer=?)', [$from, $to, $uid, $uid]), 'num']];
    } elseif ($team === 'Outside') {
        $r = ['sub' => ['Candidates submitted', (int)val('SELECT COUNT(*) FROM workers WHERE archived=0 AND date(created_at) BETWEEN ? AND ? AND created_by=?', [$from, $to, $uid]), 'num'],
            'visa' => ['Moved to visa / ready', (int)val("SELECT COUNT(*) FROM stage_history WHERE entity='workers' AND to_stage IN ('Visa Processing','Ready to Mobilise') AND date(at) BETWEEN ? AND ? AND by_user=?", [$from, $to, $uid]), 'num'],
            'joined' => ['Joined', (int)val("SELECT COUNT(*) FROM stage_history WHERE entity='workers' AND to_stage='Joined' AND date(at) BETWEEN ? AND ? AND by_user=?", [$from, $to, $uid]), 'num'],
            'comms' => ['Agent conversations logged', (int)val('SELECT COUNT(*) FROM agent_comms WHERE archived=0 AND date(created_at) BETWEEN ? AND ? AND created_by=?', [$from, $to, $uid]), 'num'],
            'docs' => ['Documents added', (int)val('SELECT COUNT(*) FROM documents WHERE archived=0 AND worker_id IS NOT NULL AND date(created_at) BETWEEN ? AND ? AND created_by=?', [$from, $to, $uid]), 'num'],
            'inproc' => ['Workers in documentation / visa now', (int)val("SELECT COUNT(*) FROM workers WHERE archived=0 AND stage IN ('Documentation','Visa Processing')"), 'num']];
    }
    return $r;
}
function dr_fmt($x) { return $x[2] === 'money' ? money($x[1]) : (is_int($x[1]) ? number_format($x[1]) : e((string)$x[1])); }
function dr_auto_tiles(array $auto)
{
    $tone = ['leads' => 'brand', 'deals' => 'ok', 'sale' => 'brand', 'payable' => 'warn', 'profit' => 'ok', 'open' => 'info', 'conv' => 'gold', 'posts' => 'brand', 'enq' => 'info', 'cands' => 'ok', 'sub' => 'brand', 'visa' => 'ok', 'joined' => 'gold', 'comms' => 'info', 'docs' => 'warn', 'inproc' => 'info'];
    $ico = ['leads' => 'phone', 'deals' => 'check-circle', 'sale' => 'tag', 'payable' => 'card', 'profit' => 'award', 'open' => 'chat', 'conv' => 'target', 'posts' => 'send', 'enq' => 'phone', 'cands' => 'users', 'sub' => 'user-plus', 'visa' => 'id', 'joined' => 'award', 'comms' => 'message', 'docs' => 'file-text', 'inproc' => 'clock'];
    $h = '<div class="stats">';
    foreach ($auto as $k => $x) $h .= stat_tile($x[0], $x[2] === 'money' ? money($x[1]) : (string)$x[1], $ico[$k] ?? 'activity', $tone[$k] ?? 'brand');
    return $h . '</div>';
}
function dr_can_see_all() { return is_super() || can('dailyreport', 'approve'); }

mod('dailyreport', ['label' => 'Daily Reports', 'icon' => 'clipboard', 'area' => 'Home', 'order' => 4,
    'desc' => 'Each team member reports the day: what they did, the work numbers and the plan for tomorrow. The system adds the numbers it already knows.',
    'tabs' => [
        'mine' => ['label' => 'My daily report', 'icon' => 'edit', 'post' => function ($base, $mod) {
            if (!is_post()) return;
            $team = dr_team(); $date = (string)p('report_date');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today() || $date < date('Y-m-d', strtotime('-7 days'))) { flash('You can report today or up to 7 days back.', 'bad'); redirect($base); }
            $d = ['report_date' => $date, 'user_id' => me()['id'], 'team' => $team, 'summary' => mb_substr(trim(p('summary')), 0, 3000), 'plan' => mb_substr(trim(p('plan')), 0, 2000), 'issues' => mb_substr(trim(p('issues')), 0, 300)];
            foreach (DR_FIELDS[$team] ?? [] as $f) $d[$f[0]] = max(0, min(100000, (int)p($f[0])));
            if ($team === 'Marketing') { $d['mk_platforms'] = mb_substr(trim(p('mk_platforms')), 0, 150); $d['mk_tasks'] = mb_substr(trim(p('mk_tasks')), 0, 3000); }
            if ($d['summary'] === '' && ($team !== 'Marketing' || $d['mk_tasks'] === '')) { flash('Write what you did today.', 'bad'); $_SESSION['dr_old'] = $_POST; redirect($base . '&d=' . $date); }
            $old = row('SELECT * FROM daily_reports WHERE user_id=? AND report_date=? AND archived=0', [me()['id'], $date]);
            if ($old) { upd('daily_reports', $old['id'], $d + ['updated_at' => now(), 'updated_by' => me()['id']]); audit('update', 'daily_reports', $old['id'], array_intersect_key($old, $d), $d); }
            else { $id = ins('daily_reports', $d + ['created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]); audit('create', 'daily_reports', $id, null, $d); }
            say_now([vclip('report_saved')], 'Your daily report has been saved. Thank you.');
            flash('Daily report saved for ' . e(dfmt($date)) . '.'); redirect($base . '&d=' . $date);
        }, 'fn' => function ($base, $mod) {
            $team = dr_team(); $date = (string)g('d', today());
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today()) $date = today();
            $rec = row('SELECT * FROM daily_reports WHERE user_id=? AND report_date=? AND archived=0', [me()['id'], $date]) ?: [];
            $o = $_SESSION['dr_old'] ?? []; unset($_SESSION['dr_old']); $v = function ($k) use ($o, $rec) { return $o[$k] ?? ($rec[$k] ?? ''); };
            if (is_super()) { echo '<div class="alert info">' . icon('help', 15) . ' The Super Admin does not file daily reports. Open <a href="' . e('dailyreport.php?t=team') . '">Team reports</a> or <a href="dailyreport.php?t=progress">Progress</a>.</div>'; return; }
            echo '<div class="dr-head"><div><h3>' . icon('calendar', 17) . ' ' . e(date('l, d F Y', strtotime($date))) . '</h3><span class="muted small">' . e(DR_TEAM_LABEL[$team] ?? 'General') . ' report · ' . ($rec ? badge('Submitted') . ' <small class="muted">last saved ' . e(dtfmt($rec['updated_at'] ?: $rec['created_at'])) . '</small>' : badge('Not submitted yet')) . '</span></div>'
                . '<form method="get" class="att-filter"><input type="hidden" name="t" value="mine"><label class="small">Day <input type="date" name="d" value="' . e($date) . '" min="' . date('Y-m-d', strtotime('-7 days')) . '" max="' . today() . '" data-autosubmit></label></form></div>';
            $auto = dr_auto(me()['id'], $date, $date, $team);
            if ($auto) echo '<h4 class="dr-sub">' . icon('zap', 15) . ' Counted automatically from your records</h4>' . dr_auto_tiles($auto);
            echo '<form method="post" class="card" action="' . e($base) . '">' . csrf_field() . '<input type="hidden" name="report_date" value="' . e($date) . '"><div class="card-h"><h3>' . icon('edit', 17) . ' Your report</h3></div>';
            if (!empty(DR_FIELDS[$team])) { echo '<div class="grid-form dr-nums">'; foreach (DR_FIELDS[$team] as $f) echo '<div class="fld"><label>' . e($f[1]) . '</label><input type="number" min="0" name="' . $f[0] . '" value="' . e($v($f[0])) . '" placeholder="0"></div>'; echo '</div>'; }
            echo '<div class="grid-form">';
            if ($team === 'Marketing') echo '<div class="fld"><label>Platforms</label><input name="mk_platforms" value="' . e($v('mk_platforms')) . '" placeholder="Facebook, Instagram, TikTok…"></div><div class="fld wide"><label>Tasks done today *</label><textarea name="mk_tasks" rows="3" placeholder="For example: 3 job posts for drivers, replied to comments, designed the Umrah flyer">' . e($v('mk_tasks')) . '</textarea></div>';
            echo '<div class="fld wide"><label>What I did today' . ($team === 'Marketing' ? '' : ' *') . '</label><textarea name="summary" rows="4" placeholder="Short notes about your day">' . e($v('summary')) . '</textarea></div>'
                . '<div class="fld wide"><label>Plan for tomorrow</label><textarea name="plan" rows="2">' . e($v('plan')) . '</textarea></div><div class="fld wide"><label>Problems / help needed</label><input name="issues" value="' . e($v('issues')) . '"></div></div>'
                . '<div class="form-actions"><button class="btn big">' . icon('check', 16) . ' ' . ($rec ? 'Update report' : 'Submit report') . '</button></div></form>';
            $m = date('Y-m', strtotime($date)); $from = $m . '-01'; $to = min(today(), date('Y-m-t', strtotime($from)));
            $mine = rows('SELECT * FROM daily_reports WHERE user_id=? AND archived=0 AND report_date BETWEEN ? AND ? ORDER BY report_date DESC', [me()['id'], $from, $to]);
            echo '<h4 class="dr-sub">' . icon('bar-chart', 15) . ' My progress · ' . e(date('F Y', strtotime($from))) . ' <small class="muted">(' . count($mine) . ' reports)</small></h4>' . dr_auto_tiles(dr_auto(me()['id'], $from, $to, $team));
            echo dr_report_list($mine, false);
        }],
        'team' => ['label' => 'Team reports', 'icon' => 'users', 'perm' => 'approve', 'post' => function ($base, $mod) {
            if (g('a') !== 'export') return;
            list($from, $to, $team, $uid) = dr_filters(); $data = [];
            foreach (dr_rows($from, $to, $team, $uid) as $r) { $row = [$r['report_date'], user_name($r['user_id']), DR_TEAM_LABEL[$r['team']] ?? $r['team']]; foreach (DR_FIELDS as $fs) foreach ($fs as $f) $row[] = $r[$f[0]]; $row[] = $r['mk_tasks']; $row[] = $r['summary']; $row[] = $r['plan']; $row[] = $r['issues']; $data[] = $row; }
            $head = ['Date', 'Staff', 'Team']; foreach (DR_FIELDS as $fs) foreach ($fs as $f) $head[] = $f[1]; array_push($head, 'Marketing tasks', 'What I did', 'Plan for tomorrow', 'Problems');
            csv_out('daily-reports-' . $from . '-to-' . $to . '.csv', $head, $data, 'daily_reports');
        }, 'fn' => function ($base, $mod) {
            list($from, $to, $team, $uid) = dr_filters();
            echo dr_filter_form('team', $from, $to, $team, $uid, $base . '&a=export&from=' . $from . '&to=' . $to . '&team=' . $team . '&u=' . $uid);
            $missing = [];
            if ($from <= today() && $to >= today()) foreach (dr_staff() as $s) if (!val('SELECT id FROM daily_reports WHERE user_id=? AND report_date=? AND archived=0', [$s['id'], today()])) $missing[] = $s['name'];
            if ($missing) echo '<div class="alert warn">' . icon('clock', 15) . ' Not submitted today: <b>' . e(implode(', ', $missing)) . '</b></div>';
            echo dr_report_list(dr_rows($from, $to, $team, $uid), true);
        }],
        'progress' => ['label' => 'Progress', 'icon' => 'bar-chart', 'perm' => 'approve', 'fn' => function ($base, $mod) {
            $m = (string)g('m', date('Y-m')); if (!preg_match('/^\d{4}-\d{2}$/', $m)) $m = date('Y-m');
            $from = $m . '-01'; $to = min(today(), date('Y-m-t', strtotime($from)));
            echo '<form class="toolbar filters att-filter" method="get"><input type="hidden" name="t" value="progress"><label class="small">Month <input type="month" name="m" value="' . e($m) . '" max="' . date('Y-m') . '" data-autosubmit></label></form>';
            $byTeam = []; foreach (dr_staff() as $s) $byTeam[dr_team($s['id'])][] = $s;
            foreach (['Travel', 'Marketing', 'Outside'] as $team) {
                if (empty($byTeam[$team])) continue;
                card_open(icon(['Travel' => 'plane', 'Marketing' => 'target', 'Outside' => 'building'][$team], 17) . ' ' . e(DR_TEAM_LABEL[$team]) . ' · ' . e(date('F Y', strtotime($from))));
                $rows = []; $head = ['Staff', 'Reports'];
                foreach (DR_FIELDS[$team] as $f) $head[] = $f[1];
                $autoHead = []; 
                foreach ($byTeam[$team] as $s) {
                    $sum = row('SELECT COUNT(*) n' . implode('', array_map(function ($f) { return ', COALESCE(SUM(' . $f[0] . '),0) ' . $f[0]; }, DR_FIELDS[$team])) . ' FROM daily_reports WHERE user_id=? AND archived=0 AND report_date BETWEEN ? AND ?', [$s['id'], $from, $to]);
                    $auto = dr_auto($s['id'], $from, $to, $team); if (!$autoHead) $autoHead = array_map(function ($x) { return $x[0]; }, $auto);
                    $row = ['<b>' . e($s['name']) . '</b>', (int)$sum['n']]; foreach (DR_FIELDS[$team] as $f) $row[] = number_format((int)$sum[$f[0]]); foreach ($auto as $x) $row[] = '<b>' . dr_fmt($x) . '</b>';
                    $rows[] = $row;
                }
                echo simple_table(array_merge($head, $autoHead), $rows);
                echo '<p class="muted small">Typed numbers come from the daily reports; bold numbers are counted from the records.</p>';
                card_close();
            }
            if (!array_intersect_key($byTeam, ['Travel' => 1, 'Marketing' => 1, 'Outside' => 1])) echo '<div class="card"><div class="empty">' . icon('users', 30) . '<p>No Travel, Marketing or Outside Hiring staff yet. Set the staff member\'s team in Staff &amp; Roles.</p></div></div>';
        }],
    ]]);

function dr_staff() { return array_values(array_filter(attendance_users(), function ($s) { return in_array(dr_team($s['id']), ['Travel', 'Marketing', 'Outside'], true); })); }
function dr_filters()
{
    $from = (string)g('from', date('Y-m-d', strtotime('-6 days'))); $to = (string)g('to', today());
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-6 days')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = today();
    $team = in_array(g('team'), ['Travel', 'Marketing', 'Outside'], true) ? g('team') : ''; $uid = (int)g('u');
    return [$from, $to, $team, $uid];
}
function dr_rows($from, $to, $team, $uid)
{
    $w = 'archived=0 AND report_date BETWEEN ? AND ?'; $p = [$from, $to];
    if ($team) { $w .= ' AND team=?'; $p[] = $team; } if ($uid) { $w .= ' AND user_id=?'; $p[] = $uid; }
    return rows("SELECT * FROM daily_reports WHERE $w ORDER BY report_date DESC, id DESC LIMIT 300", $p);
}
function dr_filter_form($tab, $from, $to, $team, $uid, $export)
{
    $h = '<form class="toolbar filters att-filter" method="get"><input type="hidden" name="t" value="' . e($tab) . '"><label class="small">From <input type="date" name="from" value="' . e($from) . '"></label><label class="small">To <input type="date" name="to" value="' . e($to) . '"></label><select name="team"><option value="">All teams</option>';
    foreach (DR_TEAM_LABEL as $k => $l) $h .= '<option value="' . $k . '"' . ($team === $k ? ' selected' : '') . '>' . e($l) . '</option>';
    $h .= '</select><select name="u"><option value="">All staff</option>';
    foreach (dr_staff() as $s) $h .= '<option value="' . $s['id'] . '"' . ($uid === (int)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
    return $h . '</select><button class="btn ghost sm">Show</button><a class="btn ghost sm" href="' . e($export) . '">' . icon('download', 14) . ' Download Excel</a></form>';
}
function dr_report_list(array $list, $withName)
{
    if (!$list) return '<div class="card"><div class="empty">' . icon('clipboard', 30) . '<p>No reports in this period.</p></div></div>';
    $h = '<div class="dr-list">';
    foreach ($list as $r) {
        $chips = '';
        foreach (DR_FIELDS[$r['team']] ?? [] as $f) if ((int)$r[$f[0]]) $chips .= '<span class="chip"><b>' . (int)$r[$f[0]] . '</b> ' . e($f[1]) . '</span>';
        $auto = dr_auto($r['user_id'], $r['report_date'], $r['report_date'], $r['team']);
        foreach ($auto as $k => $x) if ($k !== 'open' && $k !== 'inproc' && $x[1] && $x[1] !== '—') $chips .= '<span class="chip auto" title="Counted from the records">' . icon('zap', 11) . ' <b>' . dr_fmt($x) . '</b> ' . e($x[0]) . '</span>';
        $h .= '<div class="card dr-item"><div class="dr-top"><div>' . ($withName ? '<span class="avatar sm">' . e(strtoupper(substr(user_name($r['user_id']), 0, 1))) . '</span> <b>' . e(user_name($r['user_id'])) . '</b> · ' : '') . '<b>' . e(date('D d M', strtotime($r['report_date']))) . '</b></div><span class="badge b-info">' . e(DR_TEAM_LABEL[$r['team']] ?? $r['team']) . '</span></div>'
            . ($chips ? '<div class="chips dr-chips">' . $chips . '</div>' : '')
            . ($r['mk_tasks'] ? '<p><b>Tasks:</b> ' . nl2br(e($r['mk_tasks'])) . ($r['mk_platforms'] ? ' <span class="muted">(' . e($r['mk_platforms']) . ')</span>' : '') . '</p>' : '')
            . ($r['summary'] ? '<p>' . nl2br(e($r['summary'])) . '</p>' : '') . ($r['plan'] ? '<p class="small"><b>Tomorrow:</b> ' . nl2br(e($r['plan'])) . '</p>' : '') . ($r['issues'] ? '<p class="small" style="color:var(--warn)"><b>Help needed:</b> ' . e($r['issues']) . '</p>' : '') . '</div>';
    }
    return $h . '</div>';
}
