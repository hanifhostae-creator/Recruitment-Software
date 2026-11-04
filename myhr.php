<?php
require __DIR__ . '/inc/boot.php';
require_login();
$u = me();
$emp = row('SELECT * FROM employees WHERE user_id=? AND archived=0', [$u['id']]);
if (is_post() && $emp && p('do') === 'leave') {
    if (!p('date_from') || !p('date_to') || p('date_to') < p('date_from')) { flash('Choose valid leave dates.', 'bad'); redirect('myhr.php'); }
    $days = (strtotime(p('date_to')) - strtotime(p('date_from'))) / 86400 + 1;
    $id = ins('leave_requests', ['ref_no' => next_number('LV'), 'employee_id' => $emp['id'], 'leave_type' => p('leave_type'), 'date_from' => p('date_from'), 'date_to' => p('date_to'), 'days' => $days, 'reason' => p('reason'), 'status' => 'Pending', 'created_by' => $u['id'], 'created_at' => now(), 'archived' => 0]);
    audit('create', 'leave_requests', $id);
    flash('Leave request submitted.');
    redirect('myhr.php');
}
if (is_post() && $emp && p('do') === 'request') {
    $id = ins('hr_requests', ['employee_id' => $emp['id'], 'kind' => p('kind'), 'details' => p('details'), 'status' => 'Open', 'created_by' => $u['id'], 'created_at' => now(), 'archived' => 0]);
    audit('create', 'hr_requests', $id);
    flash('Request submitted to HR.');
    redirect('myhr.php');
}
page_start('My HR', 'selfservice');
page_head('My HR', 'user', 'Employee self-service — you only see your own record.');
if (!$emp) { echo '<div class="card empty">' . icon('id', 30) . '<p>Your login is not linked to an employee record yet. Ask HR to link it (HRM → Employees → "Linked staff login").</p></div>'; page_end(); exit; }
echo '<div class="kv" style="margin:0 0 16px"><div class="hl"><span>Employee</span><b>' . e($emp['ref_no'] . ' · ' . $emp['name']) . '</b></div><div><span>Designation</span><b>' . e($emp['designation']) . '</b></div><div><span>Department</span><b>' . e($emp['department']) . '</b></div><div><span>Joined</span><b>' . e(dfmt($emp['joining_date'])) . '</b></div><div class="good"><span>Annual leave left</span><b>' . leave_balance($emp['id']) . ' days</b></div><div><span>Visa expiry</span><b>' . e(dfmt($emp['visa_expiry'])) . '</b></div></div>';
echo '<div class="grid g2"><div>';
attendance_box();
card_open(icon('calendar', 17) . ' Request leave');
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="leave"><div class="grid-form"><div class="fld"><label>Leave type</label><select name="leave_type">' . implode('', array_map(function ($x) { return '<option>' . e($x) . '</option>'; }, options('leave_types'))) . '</select></div><div class="fld"><label>Reason</label><input name="reason"></div><div class="fld"><label>From</label><input type="date" name="date_from" required></div><div class="fld"><label>To</label><input type="date" name="date_to" required></div></div><div class="form-actions"><button class="btn">Submit request</button></div></form>';
card_close();
card_open(icon('inbox', 17) . ' Request a document or service');
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="request"><div class="grid-form"><div class="fld"><label>Request</label><select name="kind"><option>Salary certificate</option><option>Experience letter</option><option>NOC</option><option>Advance salary</option><option>Equipment</option><option>Other</option></select></div><div class="fld"><label>Details</label><input name="details"></div></div><div class="form-actions"><button class="btn">Send to HR</button></div></form>';
card_close();
echo '</div><div>';
card_open(icon('calendar', 17) . ' My leave');
echo simple_table(['Ref', 'Type', 'Dates', 'Days', 'Status'], array_map(function ($l) { return ['<b class="ref">' . e($l['ref_no']) . '</b>', e($l['leave_type']), e(dfmt($l['date_from']) . ' – ' . dfmt($l['date_to'])), num($l['days']), badge($l['status'])]; }, rows('SELECT * FROM leave_requests WHERE employee_id=? AND archived=0 ORDER BY id DESC', [$emp['id']])), 'No leave requests.');
card_close();
card_open(icon('dollar', 17) . ' My payslips');
echo simple_table(['Period', 'Net pay', 'Status'], array_map(function ($p) { return [e($p['period']), money($p['net']), badge($p['status'])]; }, rows("SELECT * FROM payroll WHERE employee_id=? AND status IN ('Approved','Paid') AND archived=0 ORDER BY period DESC", [$emp['id']])), 'No payslips yet.');
card_close();
card_open(icon('clock', 17) . ' My attendance (last 14 days)');
echo simple_table(['Day', 'In', 'Out'], array_map(function ($a) { return [e(dfmt($a['day'])), e(substr($a['check_in'], 11, 5)), e($a['check_out'] ? substr($a['check_out'], 11, 5) : '—')]; }, rows('SELECT * FROM staff_attendance WHERE user_id=? AND day>=? ORDER BY day DESC', [$u['id'], date('Y-m-d', strtotime('-14 days'))])), 'No attendance yet.');
card_close();
echo '</div></div>';
page_end();
