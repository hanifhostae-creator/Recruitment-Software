<?php
// Core: home screens, tasks, documents, issues, control centre, demo roles and users.

opt_defaults('business_lines', ['Inside Hiring', 'Outside Hiring', 'Travel & Tour', 'Marriage Bureau', 'Marketing', 'IT & Business Development', 'Chairman Services', 'General']);
opt_defaults('staff_teams', ['Management', 'Inside Hiring', 'Deployment', 'Outside Hiring', 'Travel & Tour', 'Marketing', 'Marriage Bureau', 'IT & Business Development', 'Accounts']);
opt_defaults('positions', ['Driver', 'Heavy Driver', 'Cleaner', 'Security Guard', 'Electrician', 'Plumber', 'Mason', 'Carpenter', 'Steel Fixer', 'Welder', 'Painter', 'General Helper', 'Cook', 'Waiter', 'Housemaid', 'Nanny', 'Sales Executive', 'Accountant', 'Receptionist', 'Nurse', 'Mechanic', 'Office Boy', 'Storekeeper', 'Barista']);
opt_defaults('nationalities', ['Pakistan', 'India', 'Bangladesh', 'Nepal', 'Sri Lanka', 'Philippines', 'Indonesia', 'Kenya', 'Uganda', 'Ghana', 'Nigeria', 'Ethiopia', 'Egypt', 'Cameroon', 'Sierra Leone', 'United Arab Emirates']);
opt_defaults('countries', ['United Arab Emirates', 'Pakistan', 'India', 'Bangladesh', 'Nepal', 'Sri Lanka', 'Philippines', 'Kenya', 'Uganda', 'Ghana', 'Nigeria', 'Ethiopia', 'Egypt', 'Saudi Arabia', 'Qatar', 'Oman']);
opt_defaults('sources', ['Walk-in', 'Facebook', 'Instagram', 'TikTok', 'WhatsApp', 'Website', 'Referral', 'Agent', 'Other / Unknown']);
opt_defaults('doc_types', ['Passport', 'Visa', 'Emirates ID', 'Photo', 'Educational Certificate', 'Experience Letter', 'Medical Report', 'Police Clearance', 'Driving Licence', 'Labour Card', 'Contract']);
opt_defaults('payment_methods', ['Cash', 'Bank Transfer', 'Card', 'Cheque']);
opt_defaults('pay_types', ['File Opening Advance', 'Balance After Selection', 'Deployment Payment', 'Instalment', 'Agent Payment', 'Client Payment', 'Supplier Payment', 'Wage', 'Expense']);
opt_defaults('issue_reasons', ['Document Missing', 'Payment Dispute', 'Interview Reschedule', 'Visa Delay', 'Medical', 'Travel Problem', 'Client Complaint', 'Worker Complaint', 'Absconding', 'Replacement Needed']);
opt_defaults('departments', ['Management', 'Reception', 'HR', 'Deployment', 'Finance', 'Marketing', 'Operations', 'Travel', 'IT & Digital', 'Administration']);
opt_defaults('designations', ['Managing Director', 'General Manager', 'HR Officer', 'Deployment Officer', 'Receptionist', 'Accountant', 'Marketing Executive', 'Operations Coordinator', 'Travel Consultant', 'Web Developer', 'Office Assistant']);
opt_defaults('leave_types', ['Annual Leave', 'Sick Leave', 'Emergency Leave', 'Unpaid Leave', 'Maternity Leave']);
opt_defaults('industries', ['Construction', 'Facilities Management', 'Hospitality', 'Retail', 'Logistics', 'Healthcare', 'Manufacturing', 'Security Services', 'Cleaning Services', 'Oil & Gas']);
opt_defaults('platforms', ['Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'WhatsApp', 'Website', 'Google Ads', 'YouTube']);
opt_defaults('enquiry_categories', ['Job Enquiry', 'Hire Staff', 'Visa / Business Setup', 'Travel & Tour', 'Web Development', 'Marriage Paybook', 'Complaint', 'Payment Question']);
opt_defaults('expense_categories', ['Rent', 'Salaries', 'Utilities', 'Marketing', 'Visa Fees', 'Medical Fees', 'Transport', 'Office Supplies', 'Government Fees', 'Bank Charges']);
opt_defaults('emirates', ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah', 'Umm Al Quwain']);

ent('tasks', ['label' => 'Tasks & Alerts', 'one' => 'Task', 'prefix' => 'TSK', 'title' => 'title', 'icon' => 'check-circle',
    'fields' => [
        'title' => ['Task', 'text', 'req'],
        'severity' => ['Severity', 'enum:Low|Medium|High|Critical', 'req', 'default' => 'Medium'],
        'due_date' => ['Due date', 'date'],
        'owner' => ['Owner', 'user'],
        'status' => ['Status', 'enum:Open|In Progress|Done', 'req', 'default' => 'Open'],
        'source' => ['Source', 'enum:Manual|System', 'hide'],
        'link' => ['Related link', 'text', 'hide'],
        'auto_key' => ['Auto key', 'text', 'hide'],
        'notes' => ['Notes', 'textarea'],
    ],
    'list' => ['ref_no', 'title', 'severity', 'due_date', 'owner', 'status'], 'filters' => ['status', 'severity', 'owner'],
    'order' => "CASE status WHEN 'Done' THEN 1 ELSE 0 END, due_date IS NULL, due_date",
    'cols' => ['Flag' => function ($r) { return $r['status'] !== 'Done' && $r['due_date'] && $r['due_date'] < today() ? badge('Overdue · SLA breach') : ($r['status'] !== 'Done' && $r['severity'] === 'Critical' ? badge('Escalated') : ''); },
        'Open' => function ($r) { return $r['link'] ? '<a href="' . e($r['link']) . '">Open record</a>' : ''; }],
    'before_save' => function (&$d, $old) { if (!$old) $d['source'] = 'Manual'; return null; },
    'actions' => ['done' => ['label' => 'Mark done', 'icon' => 'check', 'perm' => 'edit', 'show' => function ($r) { return $r['status'] !== 'Done'; },
        'form' => function ($r) { echo '<p>Mark this task as done?</p><button class="btn ok">' . icon('check', 15) . ' Mark done</button>'; },
        'do' => function ($r) { q("UPDATE tasks SET status='Done', updated_at=? WHERE id=?", [now(), $r['id']]); audit('update', 'tasks', $r['id'], ['status' => $r['status']], ['status' => 'Done']); return null; }]],
]);

ent('documents', ['label' => 'Documents', 'prefix' => 'DOC', 'title' => 'doc_type', 'icon' => 'file-text',
    'fields' => [
        'doc_type' => ['Document type', 'opt:doc_types', 'req'],
        'candidate_id' => ['Candidate', 'ref:candidates'],
        'worker_id' => ['Worker (outside)', 'ref:workers'],
        'employee_id' => ['Employee', 'ref:employees'],
        'doc_no' => ['Document number', 'text'],
        'status' => ['Status', 'enum:Missing|Received|Verified|Corrected|Expired', 'req', 'default' => 'Received'],
        'expiry' => ['Expiry date', 'date'],
        'file' => ['File', 'file', 'dir' => 'docs'],
        'verified_by' => ['Verified by', 'user'],
        'notes' => ['Notes', 'textarea'],
    ],
    'list' => ['ref_no', 'doc_type', 'candidate_id', 'worker_id', 'employee_id', 'status', 'expiry'], 'filters' => ['doc_type', 'status'],
    'cols' => ['Expiry alert' => function ($r) { if (!$r['expiry']) return ''; $d = (strtotime($r['expiry']) - time()) / 86400; return $d < 0 ? badge('Expired') : ($d < 30 ? badge('Expires in ' . ceil($d) . ' d') : ''); }],
    'before_save' => function (&$d, $old) { if (empty($d['candidate_id']) && empty($d['worker_id']) && empty($d['employee_id']) && empty($old['candidate_id']) && empty($old['worker_id']) && empty($old['employee_id'])) return 'Link the document to a candidate, worker or employee.'; return null; },
    'after_save' => function ($id, $d, $old) { if (($d['status'] ?? '') === 'Missing') add_task('Collect missing document: ' . ($d['doc_type'] ?? ''), 'Medium', date('Y-m-d', strtotime('+3 days')), me()['id'], ent_url('documents', $id), 'doc-missing-' . $id); },
]);

ent('issues', ['label' => 'Issues', 'prefix' => 'ISS', 'title' => 'subject', 'title_ref' => true, 'icon' => 'alert',
    'fields' => [
        'subject' => ['Subject', 'text', 'req'],
        'party' => ['Raised by / about', 'enum:Candidate|Client|Agent|Worker|Internal', 'req', 'default' => 'Internal'],
        'candidate_id' => ['Candidate', 'ref:candidates'],
        'demand_id' => ['Demand', 'ref:demands'],
        'worker_id' => ['Worker', 'ref:workers'],
        'agent_id' => ['Agent', 'ref:agents'],
        'category' => ['Category', 'opt:issue_reasons', 'req'],
        'severity' => ['Severity', 'enum:Low|Medium|High|Critical', 'req', 'default' => 'Medium'],
        'owner' => ['Owner', 'user'],
        'due_date' => ['Due date', 'date'],
        'status' => ['Status', 'enum:Open|In Progress|Resolved|Closed', 'req', 'default' => 'Open'],
        'details' => ['Details', 'textarea'],
        'resolution' => ['Resolution / updates', 'textarea'],
        'replacement_worker_id' => ['Replacement worker', 'ref:workers', 'norel' => true],
    ],
    'list' => ['ref_no', 'subject', 'party', 'category', 'severity', 'owner', 'due_date', 'status'], 'filters' => ['status', 'severity', 'party', 'owner'],
    'cols' => ['Flag' => function ($r) { return !in_array($r['status'], ['Resolved', 'Closed'], true) && $r['due_date'] && $r['due_date'] < today() ? badge('Overdue') : ''; }],
    'before_save' => function (&$d, $old) { if (in_array($d['status'] ?? '', ['Resolved', 'Closed'], true) && trim((string)($d['resolution'] ?? '')) === '') return 'Add the resolution before resolving or closing an issue.'; return null; },
    'after_save' => function ($id, $d, $old) {
        if (!empty($d['owner']) && (!$old || $old['owner'] != $d['owner'])) notify($d['owner'], 'Issue assigned to you: ' . $d['subject'], ent_url('issues', $id));
        if (in_array($d['severity'] ?? '', ['High', 'Critical'], true) && !$old) add_task('Resolve issue: ' . $d['subject'], $d['severity'], $d['due_date'] ?: date('Y-m-d', strtotime('+2 days')), $d['owner'] ?? null, ent_url('issues', $id), 'issue-' . $id);
    },
]);

mod('dashboard', ['label' => 'Dashboard', 'icon' => 'grid', 'area' => 'Home', 'page' => 'index.php', 'order' => 1]);
mod('mydash', ['label' => 'My Dashboard', 'icon' => 'user', 'area' => 'Home', 'page' => 'mydash.php', 'order' => 2]);
mod('search', ['label' => 'Search', 'icon' => 'search', 'area' => 'Home', 'page' => 'search.php', 'order' => 3, 'menu' => false]);
mod('tasks', ['label' => 'Tasks & Alerts', 'icon' => 'check-circle', 'area' => 'Home', 'order' => 4, 'desc' => 'System-generated alerts (balances due, documents, overdue issues, visa delays) and manual tasks, with severity and SLA flags.',
    'tabs' => ['mine' => ['label' => 'My open tasks', 'ent' => 'tasks', 'scope' => function () { return ["owner=? AND status<>'Done'", [me()['id']]]; }, 'nohome' => true], 'all' => 'tasks', 'issues' => ['label' => 'Issues', 'ent' => 'issues', 'nohome' => true]]]);
mod('notifications', ['label' => 'Notifications', 'icon' => 'bell', 'area' => 'Home', 'order' => 9, 'menu' => false]);
mod('reports', ['label' => 'Reports', 'icon' => 'bar-chart', 'area' => 'Control', 'order' => 1]);
mod('staff', ['label' => 'Staff & Roles', 'icon' => 'shield', 'area' => 'Control', 'order' => 2]);
mod('admin', ['label' => 'Admin Control Centre', 'icon' => 'settings', 'area' => 'Control', 'order' => 3]);
mod('audit', ['label' => 'Audit Report', 'icon' => 'eye', 'area' => 'Control', 'order' => 4]);
mod('settings', ['label' => 'Settings', 'icon' => 'settings', 'area' => 'Control', 'order' => 5, 'menu' => false]);

widget('tasks', 'My open tasks', function () { return (int)val("SELECT COUNT(*) FROM tasks WHERE owner=? AND status<>'Done' AND archived=0", [me()['id']]); }, 'tasks.php', 'check-circle', 'violet');
widget('tasks', 'Overdue tasks (all)', function () { return (int)val("SELECT COUNT(*) FROM tasks WHERE status<>'Done' AND due_date<? AND archived=0", [today()]); }, 'tasks.php?t=all', 'alert', 'red');

search_provider('tasks', function ($s) {
    $o = [];
    foreach (rows("SELECT * FROM issues WHERE archived=0 AND (ref_no LIKE ? OR subject LIKE ?) LIMIT 8", ["%$s%", "%$s%"]) as $r) $o[] = ['Issue', $r['ref_no'] . ' · ' . $r['subject'], $r['status'], ent_url('issues', $r['id'])];
    return $o;
});

report_section('kpi', 'Executive KPIs', 'reports', function () { reports_kpi(); });
report_section('saved', 'Saved views', 'reports', function () {
    $v = rows('SELECT * FROM saved_views WHERE user_id=? ORDER BY id DESC', [me()['id']]);
    card_open(icon('star', 17) . ' My saved views');
    echo simple_table(['Name', 'Saved', ''], array_map(function ($x) { return ['<a href="' . e($x['url']) . '"><b>' . e($x['name']) . '</b></a>', e(dtfmt($x['at'])), '<a href="' . e($x['url']) . '">Open</a>']; }, $v), 'No saved views yet. Filter any list and click "Save view".');
    card_close();
});

/** Roles from the specification (Section 5). */
function default_roles()
{
    $std = ['add', 'edit', 'export'];
    return [
        ['Super Admin', 'All modules, Admin Control Centre and the full audit trail.', [], [], 1],
        ['Reception', 'Registers walk-in candidates, assigns HR, runs queues and manual attendance.', ['reception', 'candidates', 'whatsapp'], ['reception' => ['add', 'edit'], 'candidates' => ['add', 'edit', 'assign'], 'whatsapp' => ['add', 'edit']], 0],
        ['HR', 'Candidate entry, interviews, file decisions and own progress. No staff attendance.', ['candidates', 'inside', 'fees', 'recruitment'], ['candidates' => ['add', 'edit', 'approve', 'assign', 'export'], 'inside' => ['add', 'edit', 'approve'], 'fees' => [], 'recruitment' => ['add', 'edit', 'approve']], 0],
        ['Deployment', 'Teams, interview locations, deployment payments, final outcomes and file closure.', ['deployment', 'candidates', 'inside'], ['deployment' => ['add', 'edit', 'assign', 'export'], 'candidates' => ['edit', 'assign'], 'inside' => []], 0],
        ['Finance', 'Ledgers, receipts, reconciliation, accounting and invoices.', ['finance', 'accounting', 'invoices', 'fees', 'inside', 'reports', 'paybook', 'travel'], ['finance' => ['add', 'edit', 'finance', 'approve', 'export'], 'accounting' => ['add', 'edit', 'finance', 'export'], 'invoices' => ['add', 'edit', 'finance', 'export'], 'fees' => ['add', 'edit', 'finance', 'export'], 'inside' => ['finance', 'export'], 'paybook' => ['finance'], 'travel' => ['finance']], 0],
        ['Marketing', 'Vacancy board, campaigns, posts, enquiries and CRM leads.', ['marketing', 'crm', 'whatsapp', 'cms', 'media'], ['marketing' => ['add', 'edit', 'export'], 'crm' => $std, 'whatsapp' => ['add', 'edit'], 'cms' => ['add', 'edit'], 'media' => ['add', 'edit']], 0],
        ['Outside Operations', 'Client companies, demands, agents, batches, workers and outside operations.', ['outside', 'outside_ops', 'reports'], ['outside' => ['add', 'edit', 'approve', 'assign', 'export'], 'outside_ops' => ['add', 'edit', 'approve', 'assign', 'export']], 0],
        ['Travel Team', 'Packages, enquiries, bookings and supplier payments.', ['travel'], ['travel' => ['add', 'edit', 'finance', 'export']], 0],
        ['IT / Digital', 'Service catalogue, projects, milestones, tasks, change requests and tickets.', ['it', 'projects', 'support', 'kb'], ['it' => ['add', 'edit', 'assign', 'export'], 'projects' => ['add', 'edit', 'assign'], 'support' => ['add', 'edit'], 'kb' => ['add', 'edit']], 0],
    ];
}

seeder(function () {
    foreach ([['company_name', 'RH Business Solutions & Marketing Management'], ['company_short', 'RH Management'], ['currency', 'AED'], ['company_phone', '+971 555843001'],
        ['company_email', 'info@rhmanagements.com'], ['company_address', 'Dubai, United Arab Emirates'], ['leave_entitlement', '30'], ['smtp_sec', 'ssl'], ['smtp_port', '465']] as $s) set_setting($s[0], $s[1]);
    foreach (default_roles() as $r) ins('roles', ['name' => $r[0], 'description' => $r[1], 'modules' => json_encode($r[2]), 'actions' => json_encode((object)$r[3]), 'is_super' => $r[4], 'created_at' => now()]);
    set_setting('upgrade_v11', now());
    set_setting('upgrade_v12', now());
    if (!DEMO_DATA) return; // live install: the first visit shows the "Create the Super Admin" screen (setup.php)
    $role = function ($n) { return (int)val('SELECT id FROM roles WHERE name=?', [$n]); };
    $pw = password_hash('rh@123', PASSWORD_DEFAULT);
    foreach ([['admin', 'Ateeq Rehman', 'Super Admin', 'admin@rhmanagements.com', 'Management'], ['hr', 'Sana Malik', 'HR', 'hr@rhmanagements.com', 'Inside Hiring'], ['deploy', 'Imran Qureshi', 'Deployment', 'deploy@rhmanagements.com', 'Deployment'],
        ['finance', 'Farah Ahmed', 'Finance', 'finance@rhmanagements.com', 'Accounts'], ['reception', 'Ayesha Noor', 'Reception', 'reception@rhmanagements.com', 'Inside Hiring'], ['marketing', 'Bilal Hassan', 'Marketing', 'marketing@rhmanagements.com', 'Marketing'],
        ['ops', 'Omar Siddiqui', 'Outside Operations', 'ops@rhmanagements.com', 'Outside Hiring'], ['travel', 'Mariam Yusuf', 'Travel Team', 'travel@rhmanagements.com', 'Travel & Tour'], ['it', 'Zain Ali', 'IT / Digital', 'it@rhmanagements.com', 'IT & Business Development']] as $u)
        ins('users', ['username' => $u[0], 'name' => $u[1], 'email' => $u[3], 'role_id' => $role($u[2]), 'team' => $u[4], 'password_hash' => $pw, 'must_change' => 1, 'active' => 1, 'created_at' => now()]);
}, 1);

/** Notify every active user whose role name starts with $rolePrefix (for example Reception), except the person acting. */
function notify_role($rolePrefix, $text, $link = '', $kind = 'system')
{
    foreach (rows('SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.active=1 AND r.name LIKE ?', [$rolePrefix . '%']) as $x)
        if ((int)$x['id'] !== (int)(me()['id'] ?? 0)) notify($x['id'], $text, $link, $kind);
}
/** Notify every Super Admin (used for "new entry" and "payment received" alerts on the admin dashboard). */
function notify_super($text, $link = '', $kind = 'entry')
{
    foreach (rows('SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.active=1 AND r.is_super=1') as $x)
        if ((int)$x['id'] !== (int)(me()['id'] ?? 0)) notify($x['id'], $text, $link, $kind);
}
/** One-time data upgrades for installs created by an earlier version (never removes anything). */
function apply_upgrades()
{
    apply_upgrades_v12();
    if (setting('upgrade_v11')) return;
    $r = row("SELECT * FROM roles WHERE name='Reception'");
    if ($r) {
        $a = json_decode($r['actions'], true) ?: [];
        $a['candidates'] = array_values(array_unique(array_merge($a['candidates'] ?? [], ['assign'])));
        upd('roles', $r['id'], ['actions' => json_encode((object)$a)]);
    }
    set_setting('upgrade_v11', now());
}
/** v1.2: HR no longer sees staff attendance; new business lines; payments get their section; staff get a team. */
function apply_upgrades_v12()
{
    if (setting('upgrade_v12')) return;
    $r = row("SELECT * FROM roles WHERE name='HR'");
    if ($r) {
        $m = array_values(array_diff(json_decode($r['modules'], true) ?: [], ['hrm', 'reception', 'marketing']));
        $a = json_decode($r['actions'], true) ?: [];
        unset($a['hrm'], $a['reception'], $a['marketing']);
        upd('roles', $r['id'], ['modules' => json_encode($m), 'actions' => json_encode((object)$a), 'description' => 'Candidate entry, interviews, file decisions and own progress. No staff attendance.']);
    }
    $have = array_column(rows("SELECT value FROM options WHERE list='business_lines'"), 'value');
    if ($have) foreach (['Travel & Tour', 'Marriage Bureau', 'IT & Business Development'] as $i => $bl) if (!in_array($bl, $have, true)) ins('options', ['list' => 'business_lines', 'value' => $bl, 'sort' => 2 + $i, 'active' => 1]);
    foreach (rows("SELECT id, entity, entity_id FROM payments WHERE section IS NULL OR section=''") as $pm) q('UPDATE payments SET section=? WHERE id=?', [section_of_entity($pm['entity'], $pm['entity_id']), $pm['id']]);
    $teamOf = ['Super Admin' => 'Management', 'HR' => 'Inside Hiring', 'Reception' => 'Inside Hiring', 'Deployment' => 'Deployment', 'Finance' => 'Accounts', 'Marketing' => 'Marketing', 'Outside Operations' => 'Outside Hiring', 'Travel Team' => 'Travel & Tour', 'IT / Digital' => 'IT & Business Development'];
    foreach (rows("SELECT u.id, r.name FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.team IS NULL OR u.team=''") as $u) if (isset($teamOf[$u['name']])) q('UPDATE users SET team=? WHERE id=?', [$teamOf[$u['name']], $u['id']]);
    set_setting('upgrade_v12', now());
}

/** Executive KPI report section (also used on the management dashboard). */
function reports_kpi()
{
    $k = [
        'Candidates registered' => (int)val('SELECT COUNT(*) FROM candidates WHERE archived=0'),
        'Inside files open' => (int)val("SELECT COUNT(*) FROM inside_files WHERE status='Open' AND archived=0"),
        'Value of files created' => (float)val('SELECT COALESCE(SUM(agreed_total),0) FROM inside_files WHERE archived=0'),
        'Cash collected (all)' => (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction='in'"),
        'Open demands' => (int)val("SELECT COUNT(*) FROM demands WHERE status IN ('Approved','In Progress') AND archived=0"),
        'Workers joined' => (int)val("SELECT COUNT(*) FROM workers WHERE stage='Joined' AND archived=0"),
        'Open issues' => (int)val("SELECT COUNT(*) FROM issues WHERE status IN ('Open','In Progress') AND archived=0"),
        'Employees' => (int)val("SELECT COUNT(*) FROM employees WHERE status='Active' AND archived=0"),
    ];
    echo '<div class="kv">';
    foreach ($k as $l => $v) echo '<div class="' . (strpos($l, 'Value') !== false || strpos($l, 'Cash') !== false ? 'hl' : '') . '"><span>' . e($l) . '</span><b>' . e(is_float($v) ? money($v) : num($v)) . '</b></div>';
    echo '</div>';
}
