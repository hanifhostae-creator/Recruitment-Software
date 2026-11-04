<?php
require __DIR__ . '/inc/boot.php';
require_login();
$m = preg_match('/^\d{4}-\d{2}$/', g('m')) ? g('m') : date('Y-m');
$start = $m . '-01';
$end = date('Y-m-t', strtotime($start));
$ev = [];
$add = function ($d, $text, $link, $cls = '') use (&$ev) { $ev[substr($d, 0, 10)][] = [$text, $link, $cls]; };
if (can('candidates') || can('deployment')) foreach (rows('SELECT l.*, c.full_name FROM interview_locations l JOIN candidates c ON c.id=l.candidate_id WHERE l.interview_at BETWEEN ? AND ? AND l.archived=0', [$start, $end . ' 23:59']) as $x) $add($x['interview_at'], substr($x['interview_at'], 11, 5) . ' ' . $x['full_name'] . ' @ ' . $x['company'], ent_url('candidates', $x['candidate_id']));
if (can('outside_ops')) foreach (rows('SELECT * FROM worker_tests WHERE scheduled_at BETWEEN ? AND ? AND archived=0', [$start, $end . ' 23:59']) as $x) $add($x['scheduled_at'], $x['kind'] . ': ' . ent_title('workers', $x['worker_id']), ent_url('worker_tests', $x['id']));
foreach (rows('SELECT * FROM meetings WHERE meeting_at BETWEEN ? AND ? AND archived=0', [$start, $end . ' 23:59']) as $x) $add($x['meeting_at'], substr($x['meeting_at'], 11, 5) . ' ' . $x['title'], ent_url('meetings', $x['id']), 'k-meet');
if (can('outside')) foreach (rows("SELECT * FROM demands WHERE deadline BETWEEN ? AND ? AND archived=0 AND status NOT IN ('Closed','Cancelled')", [$start, $end]) as $x) $add($x['deadline'], 'Deadline ' . $x['ref_no'], ent_url('demands', $x['id']), 'k-dead');
foreach (rows("SELECT * FROM tasks WHERE due_date BETWEEN ? AND ? AND status<>'Done' AND archived=0 AND (owner=? OR ?=1)", [$start, $end, me()['id'], is_super() ? 1 : 0]) as $x) $add($x['due_date'], $x['title'], $x['link'] ?: ent_url('tasks', $x['id']), 'k-task');
if (can('hrm')) foreach (rows("SELECT * FROM leave_requests WHERE status='Approved' AND date_from BETWEEN ? AND ? AND archived=0", [$start, $end]) as $x) $add($x['date_from'], 'Leave: ' . ent_title('employees', $x['employee_id']), ent_url('leave_requests', $x['id']), 'k-meet');
foreach (rows('SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? AND archived=0', [$start, $end]) as $x) $add($x['holiday_date'], '🎉 ' . $x['name'], ent_url('holidays', $x['id']), 'k-dead');
if (can('travel')) foreach (rows("SELECT * FROM travel_bookings WHERE travel_date BETWEEN ? AND ? AND archived=0", [$start, $end]) as $x) $add($x['travel_date'], '✈ ' . $x['customer'], ent_url('travel_bookings', $x['id']));

page_start('Calendar', 'calendar');
$prev = date('Y-m', strtotime($start . ' -1 month'));
$next = date('Y-m', strtotime($start . ' +1 month'));
page_head('Calendar', 'calendar', 'Interviews, tests, meetings, deadlines, tasks, leave and trips.', '<a class="btn ghost sm" href="?m=' . $prev . '">' . icon('arrow-left', 14) . '</a><span class="btn ghost sm" style="pointer-events:none">' . e(date('F Y', strtotime($start))) . '</span><a class="btn ghost sm" href="?m=' . $next . '">' . icon('arrow-right', 14) . '</a><a class="btn sm" href="meetings.php?t=all&a=new">' . icon('plus', 14) . ' Meeting</a>');
echo '<div class="table-wrap"><div class="cal" style="min-width:720px">';
foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d) echo '<div class="dh">' . $d . '</div>';
$first = (int)date('N', strtotime($start));
$day = strtotime($start . ' -' . ($first - 1) . ' days');
for ($i = 0; $i < 42; $i++) {
    $ds = date('Y-m-d', $day);
    $out = substr($ds, 0, 7) !== $m;
    if ($i >= 35 && $out) break;
    echo '<div class="' . ($out ? 'out' : '') . ($ds === today() ? ' today' : '') . '"><span class="d">' . date('j', $day) . '</span>';
    foreach (array_slice($ev[$ds] ?? [], 0, 4) as $e) echo '<a class="' . $e[2] . '" href="' . e($e[1]) . '" title="' . e($e[0]) . '">' . e($e[0]) . '</a>';
    if (count($ev[$ds] ?? []) > 4) echo '<small class="muted">+' . (count($ev[$ds]) - 4) . ' more</small>';
    echo '</div>';
    $day = strtotime('+1 day', $day);
}
echo '</div></div>';
page_end();
