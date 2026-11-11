<?php
require __DIR__ . '/inc/boot.php';
require_login();
$u = me();
page_start('My Dashboard', 'mydash');
page_head('My Dashboard', 'user', 'Your personal workload, overdue items and upcoming interviews.');
echo '<div class="grid g3"><div>';
attendance_box();
echo '</div><div class="span2">';
$tasks = rows("SELECT * FROM tasks WHERE owner=? AND status<>'Done' AND archived=0 ORDER BY due_date IS NULL, due_date LIMIT 12", [$u['id']]);
card_open(icon('check-circle', 17) . ' My open tasks <span class="count">' . count($tasks) . '</span>', '<a class="small" href="tasks.php?t=all&a=new">Add task</a>');
echo simple_table(['Task', 'Severity', 'Due', ''], array_map(function ($t) { return ['<b>' . e($t['title']) . '</b>', badge($t['severity']), e(dfmt($t['due_date'])), $t['due_date'] && $t['due_date'] < today() ? badge('Overdue') : '']; }, $tasks), 'No open tasks. Well done!', array_map(function ($t) { return $t['link'] ?: ent_url('tasks', $t['id']); }, $tasks));
card_close();
echo '</div></div>';

echo '<div class="grid g2">';
$mine = rows("SELECT * FROM candidates WHERE archived=0 AND stage NOT IN ('Closed','Placed') AND (hr_officer=? OR deploy_officer=?) ORDER BY id DESC LIMIT 12", [$u['id'], $u['id']]);
card_open(icon('users', 17) . ' My candidates <span class="count">' . count($mine) . '</span>');
echo candidate_queue_table($mine, 'No candidates assigned to you.');
card_close();
$upc = rows("SELECT l.*, c.full_name FROM interview_locations l JOIN candidates c ON c.id=l.candidate_id WHERE l.interview_at>=? AND l.archived=0 AND (c.deploy_officer=? OR c.hr_officer=? OR ?=1) ORDER BY l.interview_at LIMIT 10", [date('Y-m-d'), $u['id'], $u['id'], is_super() ? 1 : 0]);
$mt = rows("SELECT * FROM meetings WHERE meeting_at>=? AND archived=0 AND (','||attendees||',' LIKE ? OR created_by=?) ORDER BY meeting_at LIMIT 6", [date('Y-m-d'), '%,' . $u['id'] . ',%', $u['id']]);
card_open(icon('calendar', 17) . ' Upcoming interviews &amp; meetings');
$items = [];
$links = [];
foreach ($upc as $l) { $items[] = [e(dtfmt($l['interview_at'])), 'Interview · <b>' . e($l['full_name']) . '</b> at ' . e($l['company']), badge($l['response'])]; $links[] = ent_url('candidates', $l['candidate_id']); }
foreach ($mt as $m) { $items[] = [e(dtfmt($m['meeting_at'])), 'Meeting · <b>' . e($m['title']) . '</b>', $m['zoom_link'] ? '<a href="' . e($m['zoom_link']) . '" target="_blank" rel="noopener">Join</a>' : '']; $links[] = ent_url('meetings', $m['id']); }
echo simple_table(['When', 'What', ''], $items, 'Nothing scheduled.', $links);
card_close();
echo '</div>';

$od = [];
foreach (rows("SELECT * FROM inside_files WHERE status='Open' AND archived=0 AND (hr_owner=? OR deploy_owner=? OR reminder_owner=?) AND next_pay_date<?", [$u['id'], $u['id'], $u['id'], today()]) as $f) { $b = file_money($f)['balance']; if ($b > 0) $od[] = ['File balance overdue', e($f['ref_no'] . ' · ' . ent_title('candidates', $f['candidate_id'])), money($b), ent_url('inside_files', $f['id'])]; }
foreach (rows("SELECT * FROM issues WHERE owner=? AND status IN ('Open','In Progress') AND due_date<? AND archived=0", [$u['id'], today()]) as $i) $od[] = ['Issue overdue', e($i['ref_no'] . ' ' . $i['subject']), e(dfmt($i['due_date'])), ent_url('issues', $i['id'])];
foreach (rows("SELECT * FROM followups WHERE owner=? AND status='Open' AND due_date<? AND archived=0", [$u['id'], today()]) as $f) $od[] = ['Follow-up overdue', e($f['note']), e(dfmt($f['due_date'])), ent_url('followups', $f['id'])];
list($cw, $cp) = chat_scope(true);
foreach (rows("SELECT c.id, c.full_name, COUNT(m.id) n FROM chat_messages m JOIN candidates c ON c.id=m.candidate_id WHERE m.sender_type='Candidate' AND m.read_at IS NULL AND m.archived=0 AND $cw GROUP BY c.id", $cp) as $m) $od[] = ['Chat waiting for your reply', e($m['full_name']) . ': ' . (int)$m['n'] . ' unread message(s)', '', 'chat.php?c=' . $m['id']];
card_open(icon('alert', 17) . ' My overdue items <span class="count">' . count($od) . '</span>');
echo simple_table(['Type', 'Item', ''], array_map(function ($x) { return ['<b>' . e($x[0]) . '</b>', $x[1], $x[2]]; }, $od), 'Nothing overdue.', array_column($od, 3));
card_close();
page_end();
