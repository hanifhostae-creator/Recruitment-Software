<?php
// Staff Attendance: monthly overview, one person's day-by-day report, office hours. Uses staff_attendance (check-in / check-out).

const ATT_DAYNAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

function att_month_param() { $m = (string)g('m', date('Y-m')); return preg_match('/^\d{4}-\d{2}$/', $m) ? $m : date('Y-m'); }
/** One day for one person: status, times, hours, late / early / overtime minutes. */
function att_day($rec, $date, $cfg, $joined = null)
{
    $dow = (int)date('N', strtotime($date));
    $hol = null;
    foreach ($cfg['holidays'] as $h) if (($h['date'] ?? '') === $date) $hol = $h['name'] ?: 'Holiday';
    $work = in_array($dow, array_map('intval', $cfg['days']), true) && !$hol;
    $d = ['date' => $date, 'dow' => $dow, 'work' => $work, 'status' => '', 'in' => '', 'out' => '', 'mins' => 0, 'late' => 0, 'early' => 0, 'over' => 0, 'net' => '', 'note' => $hol ?: ''];
    $start = att_minutes($cfg['start']); $end = att_minutes($cfg['end']);
    if ($rec) {
        $in = att_minutes(substr($rec['check_in'], 11, 5));
        $d['in'] = substr($rec['check_in'], 11, 5);
        $d['net'] = $rec['in_ip'] ? (office_network_name($rec['in_ip']) ?: 'Outside office') : '';
        if ($work && $in - $start > $cfg['grace']) $d['late'] = $in - $start;
        if ($rec['check_out']) {
            $out = att_minutes(substr($rec['check_out'], 11, 5));
            $d['out'] = substr($rec['check_out'], 11, 5);
            $d['mins'] = max(0, (int)round((strtotime($rec['check_out']) - strtotime($rec['check_in'])) / 60));
            if ($work && $out < $end) $d['early'] = $end - $out;
            if ($out > $end) $d['over'] = $out - $end;
            if (!$work) $d['over'] = $d['mins'];
        }
        $d['status'] = !$work ? 'Worked on day off' : ($d['late'] ? 'Late' : 'Present');
        if (!$rec['check_out'] && $date < today()) $d['note'] = trim($d['note'] . ' No check-out');
    } elseif ($joined && $date < $joined) $d['status'] = 'Not joined';
    elseif ($hol) $d['status'] = 'Holiday';
    elseif (!$work) $d['status'] = 'Day off';
    elseif ($date > today()) $d['status'] = 'Upcoming';
    elseif ($date === today() && att_minutes(date('H:i')) < $end) $d['status'] = 'Not yet';
    else $d['status'] = 'Absent';
    return $d;
}
/** All days of a month for one person + totals. */
function att_month($uid, $month)
{
    $cfg = att_cfg();
    $first = $month . '-01'; $last = date('Y-m-t', strtotime($first));
    $recs = [];
    foreach (rows('SELECT * FROM staff_attendance WHERE user_id=? AND day BETWEEN ? AND ?', [$uid, $first, $last]) as $r) $recs[$r['day']] = $r;
    $joined = substr((string)val('SELECT created_at FROM users WHERE id=?', [$uid]), 0, 10) ?: null;
    $days = []; $t = ['workdays' => 0, 'present' => 0, 'absent' => 0, 'late_days' => 0, 'late' => 0, 'early_days' => 0, 'early' => 0, 'mins' => 0, 'over' => 0, 'nocheckout' => 0, 'offdays' => 0];
    for ($ts = strtotime($first); $ts <= strtotime($last); $ts += 86400) {
        $date = date('Y-m-d', $ts);
        $d = att_day($recs[$date] ?? null, $date, $cfg, $joined);
        $days[] = $d;
        if ($d['work'] && !in_array($d['status'], ['Upcoming', 'Not joined', 'Not yet'], true)) $t['workdays']++;
        if (in_array($d['status'], ['Present', 'Late'], true)) $t['present']++;
        if ($d['status'] === 'Worked on day off') $t['offdays']++;
        if ($d['status'] === 'Absent') $t['absent']++;
        if ($d['late']) { $t['late_days']++; $t['late'] += $d['late']; }
        if ($d['early']) { $t['early_days']++; $t['early'] += $d['early']; }
        if (strpos($d['note'], 'No check-out') !== false) $t['nocheckout']++;
        $t['mins'] += $d['mins']; $t['over'] += $d['over'];
    }
    $t['rate'] = $t['workdays'] ? round(min($t['present'], $t['workdays']) / $t['workdays'] * 100) : 0;
    return [$days, $t];
}
function att_badge($s)
{
    $map = ['Present' => 'b-ok', 'Late' => 'b-warn', 'Absent' => 'b-bad', 'Worked on day off' => 'b-info', 'Holiday' => 'b-info', 'Day off' => 'b-muted', 'Upcoming' => 'b-muted', 'Not yet' => 'b-muted', 'Not joined' => 'b-muted'];
    return '<span class="badge ' . ($map[$s] ?? 'b-muted') . '">' . e($s) . '</span>';
}
function att_month_form($base, $extra = '')
{
    return '<form class="toolbar filters att-filter" method="get"><input type="hidden" name="t" value="' . e(g('t', 'month')) . '">' . $extra . '<label class="small">Month <input type="month" name="m" value="' . e(att_month_param()) . '" max="' . date('Y-m') . '" data-autosubmit></label></form>';
}

mod('attendance', ['label' => 'Staff Attendance', 'icon' => 'clock', 'area' => 'People', 'order' => 2,
    'desc' => 'Check-in and check-out records of every staff member: days present and absent, late arrivals, hours worked and Excel export.',
    'tabs' => [
        'month' => ['label' => 'Monthly overview', 'icon' => 'bar-chart', 'post' => function ($base, $mod) {
            if (g('a') !== 'export') return;
            $m = att_month_param(); $data = [];
            foreach (attendance_users() as $s) { list(, $t) = att_month($s['id'], $m); $data[] = [$s['name'], $t['workdays'], $t['present'], $t['absent'], $t['late_days'], att_dur($t['late']), $t['early_days'], att_dur($t['early']), round($t['mins'] / 60, 2), round($t['over'] / 60, 2), $t['nocheckout'], $t['rate'] . '%']; }
            csv_out('attendance-' . $m . '.csv', ['Staff', 'Working days', 'Present', 'Absent', 'Late days', 'Late total', 'Left early (days)', 'Left early total', 'Hours worked', 'Overtime hours', 'No check-out', 'Attendance %'], $data, 'staff_attendance');
        }, 'fn' => function ($base, $mod) {
            $m = att_month_param(); $cfg = att_cfg();
            $staff = attendance_users(); $rows = []; $links = []; $sum = ['present' => 0, 'absent' => 0, 'late' => 0, 'mins' => 0];
            foreach ($staff as $s) {
                list(, $t) = att_month($s['id'], $m);
                $sum['present'] += $t['present']; $sum['absent'] += $t['absent']; $sum['late'] += $t['late_days']; $sum['mins'] += $t['mins'];
                $rows[] = ['<span class="avatar sm">' . e(strtoupper(substr($s['name'], 0, 1))) . '</span> <b>' . e($s['name']) . '</b>', '<b class="num">' . $t['present'] . '</b> / ' . $t['workdays'] . ($t['offdays'] ? ' <small class="muted">+' . $t['offdays'] . ' day off</small>' : ''), $t['absent'] ? '<b style="color:var(--bad)">' . $t['absent'] . '</b>' : '0',
                    $t['late_days'] ? '<span style="color:var(--warn);font-weight:700">' . $t['late_days'] . '</span> <small class="muted">' . att_dur($t['late']) . '</small>' : '0', $t['early_days'] ? $t['early_days'] . ' <small class="muted">' . att_dur($t['early']) . '</small>' : '0',
                    '<b>' . att_dur($t['mins']) . '</b>', $t['over'] ? att_dur($t['over']) : '—', progress($t['rate'], $t['rate'] . '%')];
                $links[] = 'attendance.php?t=staff&u=' . $s['id'] . '&m=' . $m;
            }
            echo '<div class="att-head">' . att_month_form($base) . '<a class="btn ghost sm" href="' . e($base . '&a=export&m=' . $m) . '">' . icon('download', 14) . ' Download Excel</a></div>';
            echo '<div class="stats">' . stat_tile('Present (days)', $sum['present'], 'check-circle', 'ok') . stat_tile('Absent (days)', $sum['absent'], 'x', 'bad') . stat_tile('Late arrivals', $sum['late'], 'clock', 'warn') . stat_tile('Hours worked', att_dur($sum['mins']), 'activity', 'brand') . '</div>';
            card_open(icon('users', 17) . ' ' . e(date('F Y', strtotime($m . '-01'))) . ' · ' . count($staff) . ' staff', '<span class="muted small">Office hours ' . e($cfg['start']) . '–' . e($cfg['end']) . ($cfg['grace'] ? ' · ' . (int)$cfg['grace'] . ' min grace' : '') . ' · click a name for the day-by-day report</span>');
            echo simple_table(['Staff', 'Present', 'Absent', 'Late', 'Left early', 'Hours worked', 'Overtime', 'Attendance'], $rows, 'No staff yet.', $links);
            card_close();
        }],
        'staff' => ['label' => 'Staff report', 'icon' => 'user', 'post' => function ($base, $mod) {
            if (g('a') !== 'export') return;
            $uid = (int)g('u'); $m = att_month_param();
            if (!$uid || !val('SELECT id FROM users WHERE id=?', [$uid])) redirect($base);
            list($days, $t) = att_month($uid, $m);
            $data = array_map(function ($d) { return [$d['date'], ATT_DAYNAMES[$d['dow']], $d['status'], $d['in'], $d['out'], $d['mins'] ? round($d['mins'] / 60, 2) : '', $d['late'] ? $d['late'] : '', $d['early'] ? $d['early'] : '', $d['over'] ? $d['over'] : '', $d['net'], $d['note']]; }, $days);
            $data[] = []; $data[] = ['TOTAL', '', 'Present ' . $t['present'] . ' / ' . $t['workdays'] . ' · Absent ' . $t['absent'], '', '', round($t['mins'] / 60, 2), $t['late'], $t['early'], $t['over'], '', 'Late days ' . $t['late_days'] . ' · Attendance ' . $t['rate'] . '%'];
            csv_out('attendance-' . preg_replace('/[^a-z0-9]+/i', '-', user_name($uid)) . '-' . $m . '.csv', ['Date', 'Day', 'Status', 'Check-in', 'Check-out', 'Hours', 'Late (min)', 'Left early (min)', 'Overtime (min)', 'Network', 'Note'], $data, 'staff_attendance');
        }, 'fn' => function ($base, $mod) {
            $m = att_month_param(); $uid = (int)g('u'); $staff = attendance_users();
            $sel = '<label class="small">Staff <select name="u" data-autosubmit><option value="">— Choose a name —</option>';
            foreach ($staff as $s) $sel .= '<option value="' . $s['id'] . '"' . ($uid === (int)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
            $sel .= '</select></label>';
            echo '<div class="att-head">' . att_month_form($base, $sel) . ($uid ? '<a class="btn ghost sm" href="' . e($base . '&a=export&u=' . $uid . '&m=' . $m) . '">' . icon('download', 14) . ' Download Excel</a>' : '') . '</div>';
            if (!$uid) { echo '<div class="card"><div class="empty">' . icon('user', 32) . '<p>Choose a staff member to see the full month: every day, check-in and check-out times, late arrivals, hours and absences.</p></div></div>'; return; }
            list($days, $t) = att_month($uid, $m); $cfg = att_cfg();
            echo '<div class="stats">' . stat_tile('Days present', $t['present'] . ' / ' . $t['workdays'], 'check-circle', 'ok') . stat_tile('Days absent', $t['absent'], 'x', 'bad') . stat_tile('Late arrivals', $t['late_days'] . ' · ' . att_dur($t['late']), 'clock', 'warn')
                . stat_tile('Hours worked', att_dur($t['mins']), 'activity', 'brand') . stat_tile('Overtime', att_dur($t['over']), 'zap', 'info') . stat_tile('Attendance', $t['rate'] . '%', 'target', 'gold') . '</div>';
            $rows = [];
            foreach ($days as $d) $rows[] = ['<b>' . e(date('d M', strtotime($d['date']))) . '</b>', '<span class="' . ($d['work'] ? '' : 'muted') . '">' . e(substr(ATT_DAYNAMES[$d['dow']], 0, 3)) . '</span>', att_badge($d['status']),
                e($d['in'] ?: '—'), e($d['out'] ?: '—'), $d['mins'] ? '<b>' . att_dur($d['mins']) . '</b>' : '—', $d['late'] ? '<span style="color:var(--warn);font-weight:700">' . att_dur($d['late']) . '</span>' : '—',
                $d['early'] ? '<span style="color:var(--bad)">' . att_dur($d['early']) . '</span>' : '—', $d['over'] ? att_dur($d['over']) : '—', '<small>' . e($d['net']) . '</small>', '<small class="muted">' . e($d['note']) . '</small>'];
            card_open(icon('calendar', 17) . ' ' . e(user_name($uid)) . ' · ' . e(date('F Y', strtotime($m . '-01'))), '<span class="muted small">Office hours ' . e($cfg['start']) . '–' . e($cfg['end']) . '</span>');
            echo simple_table(['Date', 'Day', 'Status', 'Check-in', 'Check-out', 'Hours', 'Late by', 'Left early', 'Overtime', 'Network', 'Note'], $rows);
            card_close();
        }],
        'testing' => ['label' => 'Testing', 'icon' => 'refresh', 'perm' => 'edit', 'post' => function ($base, $mod) {
            if (!is_post()) return;
            require_action($mod, 'edit');
            if (p('do') === 'mode') { $on = p('on') === '1'; set_setting('att_test', $on ? '1' : '0'); audit('attendance-testing', 'settings', null, null, ['on' => $on]); flash($on ? 'Testing mode is on: staff can check in again after checking out.' : 'Testing mode is off. Normal rule: one check-in and one check-out per day.'); }
            if (p('do') === 'reset') { $uid = (int)p('uid'); flash(att_reset_today($uid, 'Reset by ' . me()['name'] . ' for testing') ? e(user_name($uid)) . ' can check in again today.' : 'There was nothing to reset.', 'ok'); }
            if (p('do') === 'reset_all') { $n = 0; foreach (rows('SELECT user_id FROM staff_attendance WHERE day=?', [today()]) as $r) $n += att_reset_today((int)$r['user_id'], 'Reset all by ' . me()['name'] . ' for testing') ? 1 : 0; flash($n . ' check-in' . ($n === 1 ? '' : 's') . ' reset. Everyone can check in again today.'); }
            redirect($base);
        }, 'fn' => function ($base, $mod) {
            $on = att_testing();
            echo '<div class="office-hero card ' . ($on ? 'on' : 'off') . '"><div class="oh-ico">' . icon('refresh', 30) . '</div><div class="oh-t"><small>Check-in testing</small><h2>' . ($on ? 'Testing mode is ON' : 'Testing mode is off') . '</h2><p class="muted">'
                . ($on ? 'After checking out, staff see "Check in again" and can test the check-in and check-out voice as many times as they like. Each new check-in replaces today\'s times. Switch it off when testing is finished.' : 'Normal rule: one check-in and one check-out per day. Switch this on to let staff check in and out again while you test the voice and the screens.') . '</p></div>'
                . '<form method="post" action="' . e($base) . '" data-confirm="' . ($on ? 'Switch testing mode off?' : 'Switch testing mode on?') . '">' . csrf_field() . '<input type="hidden" name="do" value="mode"><input type="hidden" name="on" value="' . ($on ? '0' : '1') . '"><button class="btn big ' . ($on ? 'ghost' : '') . '">' . icon($on ? 'x' : 'refresh', 16) . ' ' . ($on ? 'Switch off' : 'Switch on') . '</button></form></div>';
            $today = rows('SELECT a.*, u.name FROM staff_attendance a JOIN users u ON u.id=a.user_id WHERE a.day=? ORDER BY u.name', [today()]);
            $rows = array_map(function ($a) use ($base) { return ['<b>' . e($a['name']) . '</b>', e(substr($a['check_in'], 11, 5)), e($a['check_out'] ? substr($a['check_out'], 11, 5) : '—'),
                '<form method="post" action="' . e($base) . '" data-confirm="Reset today\'s check-in of ' . e($a['name']) . '? They can check in again. The old times stay in the audit log.">' . csrf_field() . '<input type="hidden" name="do" value="reset"><input type="hidden" name="uid" value="' . (int)$a['user_id'] . '"><button class="btn ghost sm">' . icon('refresh', 13) . ' Reset today</button></form>']; }, $today);
            card_open(icon('clock', 17) . ' Today\'s check-ins · ' . e(dfmt(today())), $today ? '<form method="post" action="' . e($base) . '" data-confirm="Reset ALL of today\'s check-ins? Everyone can check in again.">' . csrf_field() . '<input type="hidden" name="do" value="reset_all"><button class="btn ghost sm danger">' . icon('refresh', 13) . ' Reset everyone today</button></form>' : '');
            echo '<p class="muted small">Reset lets one person check in and out again today, even when testing mode is off. Every reset is written to the audit log with the old times.</p>' . simple_table(['Staff', 'Check-in', 'Check-out', ''], $rows, 'Nobody has checked in today yet.');
            card_close();
            $log = rows("SELECT * FROM audit WHERE action='attendance-reset' ORDER BY id DESC LIMIT 15");
            card_open(icon('shield', 17) . ' Reset history');
            echo simple_table(['When', 'By / reason', 'Old times'], array_map(function ($l) { $o = json_decode((string)$l['old_v'], true) ?: []; return [e(dtfmt($l['at'])), e($l['reason'] ?: $l['actor']), e(substr($o['check_in'] ?? '', 11, 5) . ' – ' . (($o['check_out'] ?? '') ? substr($o['check_out'], 11, 5) : '—')) . ' <small class="muted">' . e(user_name($l['rec_id'])) . '</small>']; }, $log), 'No resets yet.');
            card_close();
        }],
        'settings' => ['label' => 'Office hours', 'icon' => 'settings', 'perm' => 'edit', 'post' => function ($base, $mod) {
            if (!is_post()) return;
            require_action($mod, 'edit');
            $ok = function ($t) { return preg_match('/^\d{2}:\d{2}$/', $t) ? $t : null; };
            $start = $ok(p('start')); $end = $ok(p('end'));
            if (!$start || !$end || att_minutes($end) <= att_minutes($start)) { flash('Enter a start and an end time (the end must be after the start).', 'bad'); redirect($base); }
            $days = array_values(array_intersect(array_map('intval', (array)($_POST['days'] ?? [])), [1, 2, 3, 4, 5, 6, 7]));
            $hol = [];
            foreach (preg_split('/\r?\n/', (string)p('holidays')) as $line) if (preg_match('/^\s*(\d{4}-\d{2}-\d{2})\s*(.*)$/', $line, $mm)) $hol[] = ['date' => $mm[1], 'name' => trim($mm[2], " -\t")];
            $old = att_cfg(); $new = ['start' => $start, 'end' => $end, 'grace' => max(0, min(120, (int)p('grace'))), 'days' => $days, 'holidays' => $hol];
            set_setting('att_cfg', json_encode($new)); audit('attendance-settings', 'settings', null, $old, $new);
            flash('Office hours saved.'); redirect($base);
        }, 'fn' => function ($base, $mod) {
            $c = att_cfg();
            echo '<form method="post" class="card" action="' . e($base) . '">' . csrf_field() . '<div class="card-h"><h3>' . icon('clock', 17) . ' Office hours</h3></div><div class="grid-form">'
                . '<div class="fld"><label>Start time *</label><input type="time" name="start" value="' . e($c['start']) . '" required></div><div class="fld"><label>End time *</label><input type="time" name="end" value="' . e($c['end']) . '" required></div>'
                . '<div class="fld"><label>Grace minutes</label><input type="number" name="grace" min="0" max="120" value="' . (int)$c['grace'] . '"><small class="muted">Arriving within these minutes after the start is not counted as late.</small></div>'
                . '<div class="fld wide"><label>Working days</label><div class="check-grid">';
            foreach (ATT_DAYNAMES as $n => $l) echo '<label class="check"><input type="checkbox" name="days[]" value="' . $n . '"' . (in_array($n, array_map('intval', $c['days']), true) ? ' checked' : '') . '> ' . $l . '</label>';
            echo '</div></div><div class="fld wide"><label>Public holidays (one per line: date and name)</label><textarea name="holidays" rows="5" placeholder="2026-12-02 National Day">' . e(implode("\n", array_map(function ($h) { return $h['date'] . ' ' . $h['name']; }, $c['holidays']))) . '</textarea><small class="muted">Holidays and days off are never counted as absent.</small></div></div>'
                . '<div class="form-actions"><button class="btn">' . icon('check', 15) . ' Save office hours</button></div></form>';
        }],
    ]]);
