<?php
// Module 1 — Inside Hiring: Reception, Candidates, HR, Inside files & fees, Deployment, Recruitment, Marketing.

const CAND_STAGES = ['New', 'HR Assigned', 'Interviewed', 'File Opened', 'In Deployment', 'Selected', 'Placed', 'Closed'];
opt_defaults('close_reasons', ['Placed', 'Withdrawn', 'Inactive', 'Unsuccessful', 'Archived', 'Refund settled']);
opt_defaults('assessment_types', ['Trade Test', 'Driving Test', 'English Test', 'Skills Interview', 'Medical']);

ent('candidates', ['notify_new' => [null], 'label' => 'Candidates', 'prefix' => 'CAN', 'title' => 'full_name', 'icon' => 'user', 'log_view' => true,
    'dupe' => ['phone', 'email', 'passport_no'],
    'fields' => [
        'full_name' => ['Full name', 'text', 'req'],
        'phone' => ['Phone', 'phone', 'req'],
        'email' => ['Email (portal username)', 'email'],
        'whatsapp' => ['WhatsApp number', 'phone'],
        'nationality' => ['Nationality', 'opt:nationalities', 'req'],
        'passport_no' => ['Passport number', 'text'],
        'dob' => ['Date of birth', 'date'],
        'applied_position' => ['Applied position', 'opt:positions', 'req'],
        'expected_salary' => ['Expected salary (AED)', 'money'],
        'visa_type' => ['Visa type', 'enum:Visit|Cancel|Employment|Family|Sponsor|Other'],
        'visa_expiry' => ['Visa expiry', 'date'],
        'through_whom' => ['Through whom (referred / sent by)', 'text'],
        'source' => ['Source', 'opt:sources'],
        'source_post' => ['Source post / campaign', 'ref:campaign_posts', 'norel' => true],
        'qualification' => ['Qualification', 'text'],
        'experience' => ['Experience', 'textarea'],
        'skills' => ['Skills', 'textarea'],
        'preferred_positions' => ['Preferred positions', 'text'],
        'cv_file' => ['CV', 'file', 'req_new', 'dir' => 'cv'],
        'hr_officer' => ['Assign to HR (optional)', 'user', 'role' => 'HR', 'help' => 'Choosing an HR officer moves the candidate to HR Assigned and rings a notification on their screen.'],
        'stage' => ['Stage', 'enum:' . implode('|', CAND_STAGES), 'hide'],
        'interview_status' => ['Interview status', 'enum:Pending|Selected|Rejected|On Hold', 'hide'],
        'interview_notes' => ['Interviewer notes', 'textarea', 'hide'],
        'interviewed_at' => ['Interviewed at', 'datetime', 'hide'],
        'file_decision' => ['File decision', 'enum:Yes|No', 'hide'],
        'decision_reason' => ['Decision reason', 'text', 'hide'],
        'deploy_officer' => ['Deployment officer', 'user', 'hide'],
        'team_id' => ['Deployment team', 'ref:deployment_teams', 'hide'],
        'final_outcome' => ['Final outcome', 'enum:Selected|Rejected|Rescheduled|Withdrawn|Other', 'hide'],
        'final_reason' => ['Final outcome reason', 'text', 'hide'],
        'notes' => ['Internal notes', 'textarea', 'help' => 'Never shown in the candidate portal.'],
    ],
    'list' => ['ref_no', 'full_name', 'phone', 'nationality', 'applied_position', 'stage', 'hr_officer', 'created_at'],
    'filters' => ['stage', 'applied_position', 'nationality', 'hr_officer', 'visa_type'],
    'before_save' => function (&$d, $old) {
        if (!$old) $d['stage'] = !empty($d['hr_officer']) ? 'HR Assigned' : 'New';
        if (!empty($d['email'])) $d['email'] = strtolower(trim($d['email']));
        return null;
    },
    'after_save' => function ($id, $d, $old) {
        if (!$old) {
            ins('stage_history', ['entity' => 'candidates', 'entity_id' => $id, 'from_stage' => null, 'to_stage' => $d['stage'], 'reason' => 'Registered at Reception', 'by_user' => me()['id'], 'by_name' => me()['name'], 'at' => now()]);
            if (!empty($d['hr_officer'])) notify($d['hr_officer'], 'New candidate assigned to you: ' . $d['full_name'], ent_url('candidates', $id), 'assigned');
        } elseif (!empty($d['hr_officer']) && $old['hr_officer'] != $d['hr_officer']) {
            notify($d['hr_officer'], 'Candidate assigned to you: ' . ($d['full_name'] ?? $old['full_name']), ent_url('candidates', $id), 'assigned');
            if ($old['stage'] === 'New') stage_advance('candidates', $id, 'HR Assigned', 'HR officer assigned');
        }
        if (!empty($d['email']) && $old && $d['email'] !== $old['email']) q('UPDATE candidate_logins SET username=? WHERE candidate_id=?', [$d['email'], $id]);
    },
    'can_edit' => function ($r) { return $r['stage'] !== 'Closed'; },
    'cols' => ['File' => function ($r) { $f = candidate_file($r['id']); return $f ? '<span class="ref">' . e($f['ref_no']) . '</span>' : '<span class="muted">—</span>'; }],
    'view' => function ($r) { candidate_overview($r); },
    'view_after' => function ($r) { candidate_panels($r); },
    'actions' => candidate_actions(),
]);

ent('inside_files', ['label' => 'Inside Files', 'one' => 'Inside file', 'prefix' => 'IN-FILE', 'icon' => 'folder', 'financial' => true,
    'fields' => [
        'candidate_id' => ['Candidate', 'ref:candidates', 'req', 'new_only'],
        'fee_plan_id' => ['Fee plan', 'ref:fee_plans', 'req', 'new_only'],
        'agreed_total' => ['Agreed total', 'money', 'req', 'new_only'],
        'currency' => ['Currency', 'enum:AED|USD|PKR|INR', 'default' => 'AED'],
        'advance_amount' => ['Initial amount collected now', 'money', 'new_only', 'help' => 'Recorded as a receipt (RCT) when the file is opened.'],
        'pay_method' => ['Payment method', 'opt:payment_methods', 'new_only'],
        'next_pay_date' => ['Next pay date', 'date'],
        'promise_note' => ['Payment promise ("the candidate says they will pay on…")', 'text'],
        'reminder_owner' => ['Follow-up owner', 'user'],
        'hr_owner' => ['HR owner', 'user'],
        'deploy_owner' => ['Deployment owner', 'user'],
        'skills' => ['Skills / experience summary', 'textarea'],
        'status' => ['File status', 'enum:Open|Closed', 'hide'],
        'outcome' => ['Closure outcome', 'text', 'hide'],
        'close_reason' => ['Close reason', 'text', 'hide'],
        'closed_at' => ['Closed at', 'datetime', 'hide'],
    ],
    'list' => ['ref_no', 'candidate_id', 'agreed_total', 'next_pay_date', 'hr_owner', 'status'],
    'filters' => ['status', 'hr_owner', 'deploy_owner', 'fee_plan_id'],
    'cols' => [
        'Paid' => function ($r) { return money(paid_total('inside_files', $r['id'])); },
        'Remaining' => function ($r) { $b = $r['agreed_total'] - paid_total('inside_files', $r['id']); return $b > 0 ? '<b style="color:var(--bad)">' . money($b) . '</b>' : badge('Paid'); },
    ],
    'create' => function ($d) {
        if (candidate_file($d['candidate_id'])) return 'This candidate already has an open inside file.';
        return create_inside_file($d['candidate_id'], $d['fee_plan_id'], $d['agreed_total'], (float)($d['advance_amount'] ?? 0), $d);
    },
    'can_edit' => function ($r) { return $r['status'] !== 'Closed'; },
    'view' => function ($r) { file_summary($r); },
    'view_after' => function ($r, $mod, $base) { payments_panel('inside_files', $r['id'], $mod, $base); },
    'actions' => [
        'pay' => pay_action('inside_files', 'Receive payment', 'finance', function ($r) { return $r['status'] === 'Open'; }),
        'promise' => ['label' => 'Payment promise', 'icon' => 'calendar', 'perm' => 'edit', 'show' => function ($r) { return $r['status'] === 'Open'; },
            'form' => function ($r) { echo '<div class="grid-form"><div class="fld"><label>Promised pay date *</label><input type="date" name="next_pay_date" required value="' . e($r['next_pay_date']) . '"></div><div class="fld"><label>Follow-up owner</label>' . preg_replace('/^<label.*?<\/label>/', '', field_input('reminder_owner', ['label' => 'Follow-up owner', 'type' => 'user'], $r['reminder_owner'] ?: me()['id'])) . '</div><div class="fld wide"><label>What the candidate said</label><input name="promise_note" value="' . e($r['promise_note']) . '" placeholder="The candidate says they will pay on…"></div></div><div class="form-actions"><button class="btn">Save promise</button></div>'; },
            'do' => function ($r) {
                upd('inside_files', $r['id'], ['next_pay_date' => p('next_pay_date'), 'promise_note' => p('promise_note'), 'reminder_owner' => (int)p('reminder_owner') ?: null, 'updated_at' => now()]);
                audit('promise', 'inside_files', $r['id'], ['next_pay_date' => $r['next_pay_date']], ['next_pay_date' => p('next_pay_date'), 'note' => p('promise_note')]);
                add_task('Payment follow-up: ' . $r['ref_no'], 'Medium', p('next_pay_date'), (int)p('reminder_owner') ?: me()['id'], ent_url('inside_files', $r['id']), 'promise-' . $r['id'] . '-' . p('next_pay_date'));
                return null;
            }],
    ],
]);

ent('fee_plans', ['label' => 'Fee Plans', 'title' => 'name', 'icon' => 'tag',
    'fields' => [
        'name' => ['Plan name', 'text', 'req'],
        'version' => ['Version', 'int', 'default' => 1],
        'currency' => ['Currency', 'enum:AED|USD|PKR|INR', 'default' => 'AED'],
        'total' => ['Agreed total', 'money', 'req'],
        'advance' => ['Initial amount (at file opening)', 'money', 'req'],
        'balance_trigger' => ['Balance due event', 'enum:On selection|On visa issue|On deployment|On joining|Other', 'req', 'default' => 'On selection'],
        'due_days' => ['Balance due within (days)', 'int', 'default' => 7],
        'terms' => ['Acknowledged terms', 'textarea'],
        'active' => ['Active', 'bool', 'default' => 1],
    ],
    'list' => ['name', 'version', 'total', 'advance', 'balance_trigger', 'active'],
    'cols' => ['Balance after event' => function ($r) { return money($r['total'] - $r['advance']); }],
]);

ent('deployment_teams', ['label' => 'Deployment Teams', 'one' => 'Team', 'title' => 'name', 'icon' => 'users',
    'fields' => ['name' => ['Team name', 'text', 'req'], 'leader' => ['Team leader', 'user', 'req'], 'members' => ['Members', 'users'], 'notes' => ['Notes', 'textarea']],
    'list' => ['name', 'leader', 'members'],
    'cols' => ['Candidates' => function ($r) { return (int)val("SELECT COUNT(*) FROM candidates WHERE team_id=? AND stage NOT IN ('Closed') AND archived=0", [$r['id']]); }],
]);

ent('interview_locations', ['label' => 'Interview Locations', 'one' => 'Interview location', 'title' => 'company', 'icon' => 'map-pin',
    'fields' => [
        'candidate_id' => ['Candidate', 'ref:candidates', 'req', 'new_only', 'norel' => true],
        'seq' => ['Sequence', 'int', 'hide'],
        'company' => ['Company', 'text', 'req'],
        'position' => ['Position', 'text'],
        'location' => ['Exact location / address', 'text', 'req'],
        'map_link' => ['Map link', 'url'],
        'interview_at' => ['Interview date & time', 'datetime', 'req'],
        'contact' => ['Contact person & phone', 'text'],
        'instructions' => ['Instructions for the candidate', 'textarea'],
        'response' => ['Candidate response', 'enum:Pending|Selected|Not Selected|Issue', 'default' => 'Pending'],
        'response_note' => ['Response note', 'text'],
        'responded_at' => ['Responded at', 'datetime', 'hide'],
    ],
    'list' => ['candidate_id', 'seq', 'company', 'location', 'interview_at', 'response'], 'filters' => ['response'], 'order' => 'interview_at DESC',
    'cols' => ['#' => function ($r) { return '<b>' . ordinal($r['seq']) . '</b>'; }],
    'before_save' => function (&$d, $old) {
        if (!$old) {
            $c = row('SELECT * FROM candidates WHERE id=?', [$d['candidate_id']]);
            if (!$c) return 'Choose a candidate.';
            if ($c['stage'] === 'Closed') return 'The candidate file is closed.';
            if ($c['final_outcome'] && $c['final_outcome'] !== 'Rescheduled') return 'A final outcome is already recorded for this candidate.';
            $d['seq'] = (int)val('SELECT COALESCE(MAX(seq),0)+1 FROM interview_locations WHERE candidate_id=?', [$d['candidate_id']]);
            $d['response'] = 'Pending';
        }
        if (in_array($d['response'] ?? '', ['Not Selected', 'Issue'], true) && trim((string)($d['response_note'] ?? '')) === '') return 'A note is required when the response is Not Selected or Issue.';
        if ($old && ($d['response'] ?? '') !== $old['response']) $d['responded_at'] = now();
        return null;
    },
    'after_save' => function ($id, $d, $old) { if (!$old) audit('location-sent', 'candidates', $d['candidate_id'], null, ['location' => $d['company'], 'seq' => $d['seq']]); },
]);

ent('candidate_messages', ['label' => 'Candidate Messages', 'one' => 'Message', 'title' => 'kind', 'icon' => 'message',
    'fields' => [
        'candidate_id' => ['Candidate', 'ref:candidates', 'req', 'norel' => true],
        'kind' => ['Type', 'enum:Message|Issue|Question|Missing Document|Schedule Change', 'req', 'default' => 'Message'],
        'direction' => ['Direction', 'enum:From candidate|To candidate', 'req', 'default' => 'To candidate'],
        'body' => ['Message', 'textarea', 'req'],
        'file' => ['Attachment', 'file', 'dir' => 'messages'],
        'assigned_to' => ['Assigned to (deployment team member)', 'user'],
        'status' => ['Status', 'enum:Open|Answered|Closed', 'default' => 'Open'],
    ],
    'list' => ['candidate_id', 'kind', 'direction', 'assigned_to', 'status', 'created_at'], 'filters' => ['kind', 'status', 'assigned_to', 'direction'],
]);

// ---- recruitment
ent('job_offers', ['label' => 'Offers', 'one' => 'Offer', 'prefix' => 'OFR', 'title' => 'position', 'title_ref' => true, 'icon' => 'award',
    'fields' => ['candidate_id' => ['Candidate', 'ref:candidates', 'req'], 'company' => ['Company / employer', 'text', 'req'], 'position' => ['Position', 'opt:positions', 'req'], 'salary' => ['Salary (AED)', 'money'],
        'benefits' => ['Benefits', 'text'], 'status' => ['Status', 'enum:Draft|Sent|Accepted|Declined|Withdrawn', 'default' => 'Draft'], 'sent_on' => ['Sent on', 'date'], 'notes' => ['Notes', 'textarea']],
    'list' => ['ref_no', 'candidate_id', 'company', 'position', 'salary', 'status'], 'filters' => ['status']]);
ent('onboardings', ['label' => 'Onboarding', 'one' => 'Onboarding', 'title' => 'candidate_id', 'icon' => 'check-circle',
    'fields' => ['candidate_id' => ['Candidate', 'ref:candidates', 'req'], 'start_date' => ['Start date', 'date'], 'worksite' => ['Worksite', 'text'], 'supervisor' => ['Supervisor', 'text'],
        'checklist' => ['Checklist progress', 'enum:Not started|In progress|Completed', 'default' => 'Not started'], 'status' => ['Status', 'enum:Pending|Joined|No Show|Replaced', 'default' => 'Pending'], 'notes' => ['Notes', 'textarea']],
    'list' => ['candidate_id', 'start_date', 'worksite', 'checklist', 'status'], 'filters' => ['status']]);
ent('candidate_assessments', ['label' => 'Assessments', 'one' => 'Assessment', 'title' => 'type', 'icon' => 'clipboard',
    'fields' => ['candidate_id' => ['Candidate', 'ref:candidates', 'req'], 'type' => ['Assessment type', 'opt:assessment_types', 'req'], 'assessed_on' => ['Date', 'date', 'default' => date('Y-m-d')],
        'assessor' => ['Assessor', 'user'], 'score' => ['Score', 'number'], 'result' => ['Result', 'enum:Pending|Pass|Fail', 'default' => 'Pending'], 'feedback' => ['Feedback', 'textarea']],
    'list' => ['candidate_id', 'type', 'assessed_on', 'assessor', 'score', 'result'], 'filters' => ['result', 'type']]);
ent('interview_rounds', ['label' => 'Interview Rounds', 'one' => 'Round', 'title' => 'name', 'icon' => 'layers',
    'fields' => ['name' => ['Round name', 'text', 'req'], 'sort' => ['Order', 'int'], 'description' => ['Description', 'textarea']], 'list' => ['sort', 'name'], 'order' => 'sort']);
ent('job_locations', ['label' => 'Job Locations', 'one' => 'Job location', 'title' => 'name', 'icon' => 'map-pin',
    'fields' => ['name' => ['Location name', 'text', 'req'], 'emirate' => ['Emirate', 'opt:emirates'], 'address' => ['Address', 'text'], 'map_link' => ['Map link', 'url'], 'contact' => ['Contact', 'text']],
    'list' => ['name', 'emirate', 'address', 'contact']]);
ent('custom_questions', ['label' => 'Custom Questions', 'one' => 'Question', 'title' => 'question', 'icon' => 'help',
    'fields' => ['question' => ['Question', 'text', 'req'], 'applies_to' => ['Applies to position', 'opt:positions'], 'answer_type' => ['Answer type', 'enum:Text|Yes / No|Number|Choice', 'default' => 'Text'], 'required' => ['Required', 'bool']],
    'list' => ['question', 'applies_to', 'answer_type', 'required']]);
ent('checklist_items', ['label' => 'Checklists', 'one' => 'Checklist item', 'title' => 'name', 'icon' => 'check',
    'fields' => ['name' => ['Item', 'text', 'req'], 'stage' => ['Checklist', 'enum:File opening|Deployment|Onboarding|Outside mobilisation', 'req'], 'required' => ['Required', 'bool', 'default' => 1]],
    'list' => ['name', 'stage', 'required'], 'filters' => ['stage']]);

// ---- marketing
ent('vacancies', ['notify_new' => [null], 'label' => 'Vacancies', 'one' => 'Vacancy', 'prefix' => 'IN-REQ', 'title' => 'position', 'title_ref' => true, 'icon' => 'briefcase', 'bl' => 'Inside Hiring',
    'fields' => [
        'position' => ['Position', 'opt:positions', 'req'], 'quantity' => ['Number required', 'int', 'req'], 'min_qualification' => ['Minimum qualifications', 'text'],
        'salary_terms' => ['Salary and terms (if approved)', 'text'], 'location' => ['Location', 'opt:emirates'], 'urgency' => ['Urgency', 'enum:Normal|Urgent|Very Urgent', 'default' => 'Normal'],
        'open_date' => ['Open date', 'date', 'default' => date('Y-m-d')], 'expiry_date' => ['Expiry date', 'date'], 'status' => ['Status', 'enum:Draft|Open|Paused|Closed', 'req', 'default' => 'Open'],
        'approved' => ['Information approved for advertising', 'bool', 'default' => 1], 'description' => ['Description', 'textarea'],
    ],
    'list' => ['ref_no', 'position', 'quantity', 'location', 'urgency', 'expiry_date', 'status'], 'filters' => ['status', 'urgency', 'position'],
    'cols' => ['Leads' => function ($r) { return (int)val('SELECT COUNT(*) FROM enquiries WHERE vacancy_id=? AND archived=0', [$r['id']]); }],
    'actions' => ['duplicate' => ['label' => 'Duplicate', 'icon' => 'copy', 'perm' => 'add', 'form' => function ($r) { echo '<p>Create a copy of this vacancy as a new Draft?</p><button class="btn">Duplicate</button>'; },
        'do' => function ($r) { $c = $r; unset($c['id']); $c['ref_no'] = next_number('IN-REQ'); $c['status'] = 'Draft'; $c['created_at'] = now(); $c['created_by'] = me()['id']; $c['updated_at'] = null; $id = ins('vacancies', $c); audit('duplicate', 'vacancies', $id, null, ['from' => $r['ref_no']]); return ['msg' => 'Vacancy duplicated.', 'go' => ent_url('vacancies', $id)]; }]],
]);
ent('campaigns', ['label' => 'Campaigns', 'title' => 'name', 'icon' => 'target', 'bl' => 'Marketing',
    'fields' => ['name' => ['Campaign name', 'text', 'req'], 'platform' => ['Main platform', 'opt:platforms'], 'start_date' => ['Start', 'date'], 'end_date' => ['End', 'date'], 'budget' => ['Budget', 'money'], 'owner' => ['Owner', 'user'], 'status' => ['Status', 'enum:Planned|Running|Paused|Completed', 'default' => 'Running'], 'notes' => ['Notes', 'textarea']],
    'list' => ['name', 'platform', 'start_date', 'budget', 'owner', 'status'], 'filters' => ['status', 'platform']]);
ent('campaign_posts', ['label' => 'Campaign Posts', 'one' => 'Post', 'prefix' => 'POST', 'title' => 'platform', 'title_ref' => true, 'icon' => 'send',
    'fields' => ['vacancy_id' => ['Vacancy', 'ref:vacancies'], 'campaign_id' => ['Campaign', 'ref:campaigns'], 'platform' => ['Platform', 'opt:platforms', 'req'], 'account_page' => ['Account / page', 'text'],
        'post_link' => ['Post ID or link', 'text'], 'posted_on' => ['Date posted', 'date', 'default' => date('Y-m-d')], 'creative' => ['Creative / copy version', 'text'], 'marketer' => ['Assigned marketer', 'user'], 'spend' => ['Spend', 'money']],
    'list' => ['ref_no', 'platform', 'vacancy_id', 'campaign_id', 'posted_on', 'marketer', 'spend'], 'filters' => ['platform', 'marketer', 'campaign_id'],
    'cols' => ['Leads' => function ($r) { return (int)val('SELECT COUNT(*) FROM enquiries WHERE post_id=? AND archived=0', [$r['id']]); }]]);
ent('enquiries', ['notify_new' => ['Marketing', 'lead'], 'label' => 'Enquiries', 'one' => 'Enquiry', 'prefix' => 'ENQ', 'title' => 'name', 'icon' => 'phone', 'dupe' => ['phone'],
    'fields' => [
        'name' => ['Name', 'text', 'req'], 'phone' => ['Phone', 'phone', 'req'], 'email' => ['Email', 'email'], 'vacancy_id' => ['Vacancy', 'ref:vacancies'], 'post_id' => ['Source post', 'ref:campaign_posts'],
        'source' => ['Source', 'opt:sources', 'req', 'help' => 'Use "Other / Unknown" and add a note when the source is not known.'], 'category' => ['Category', 'opt:enquiry_categories'],
        'status' => ['Status', 'enum:New|Called|Invited|Visited|Interviewed|File Opened|Not Interested|No Response', 'req', 'default' => 'New'],
        'preferred_date' => ['Preferred interview date', 'date'], 'invited_on' => ['Invited on', 'date'], 'visited_on' => ['Office visit on', 'date'], 'assigned_to' => ['Assigned to', 'user'],
        'candidate_id' => ['Converted candidate', 'ref:candidates', 'hide'], 'notes' => ['Call / message log', 'textarea'],
    ],
    'list' => ['ref_no', 'name', 'phone', 'vacancy_id', 'source', 'status', 'assigned_to'], 'filters' => ['status', 'source', 'vacancy_id', 'assigned_to'],
    'actions' => ['convert' => ['label' => 'Convert to candidate', 'icon' => 'user-plus', 'perm' => 'add', 'show' => function ($r) { return !$r['candidate_id']; },
        'form' => function ($r) { echo '<p>Open the candidate registration form with this enquiry\'s details. The CV upload is still required.</p><button class="btn">Continue</button>'; },
        'do' => function ($r) { return ['msg' => 'Complete the candidate registration (CV required).', 'go' => 'candidates.php?' . http_build_query(['a' => 'new', 'full_name' => $r['name'], 'phone' => $r['phone'], 'email' => $r['email'], 'source' => $r['source'], 'source_post' => $r['post_id'], 'from_enquiry' => $r['id']])]; }]],
]);

// ---------------------------------------------------------------- shared inside-hiring logic

function ordinal($n) { $n = (int)$n; $s = ['th', 'st', 'nd', 'rd']; $v = $n % 100; return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]); }
/** Latest inside file of a candidate. */
function candidate_file($cid) { return row('SELECT * FROM inside_files WHERE candidate_id=? AND archived=0 ORDER BY id DESC LIMIT 1', [$cid]); }
/** Money figures for one inside file — the single source used by file, deployment, reception and finance views. */
function file_money($f)
{
    if (!$f) return ['agreed' => 0, 'paid' => 0, 'balance' => 0, 'next' => null];
    $paid = paid_total('inside_files', $f['id']);
    return ['agreed' => (float)$f['agreed_total'], 'paid' => $paid, 'balance' => round($f['agreed_total'] - $paid, 2), 'next' => $f['next_pay_date']];
}

/** Shared file-creation logic (HR file decision and Inside Hiring "Add New" both use it). */
function create_inside_file($cid, $planId, $total, $advance, array $o = [])
{
    $c = row('SELECT * FROM candidates WHERE id=?', [$cid]);
    $plan = row('SELECT * FROM fee_plans WHERE id=?', [$planId]);
    if (!$c || !$plan) return 'Candidate or fee plan not found.';
    if ($advance > $total) return 'The initial amount cannot be more than the agreed total.';
    $id = ins('inside_files', ['ref_no' => next_number('IN-FILE'), 'candidate_id' => $cid, 'fee_plan_id' => $planId, 'agreed_total' => $total, 'currency' => $o['currency'] ?? $plan['currency'],
        'advance_amount' => $advance, 'pay_method' => $o['pay_method'] ?? 'Cash', 'next_pay_date' => $o['next_pay_date'] ?? null, 'hr_owner' => $o['hr_owner'] ?? ($c['hr_officer'] ?: (me()['id'] ?? null)),
        'deploy_owner' => $o['deploy_owner'] ?? null, 'skills' => $o['skills'] ?? $c['skills'], 'status' => 'Open', 'created_by' => me()['id'] ?? null, 'created_at' => now(), 'archived' => 0]);
    audit('create', 'inside_files', $id, null, ['candidate' => $c['ref_no'], 'total' => $total, 'advance' => $advance, 'plan' => $plan['name'] . ' v' . $plan['version']]);
    if ($advance > 0) record_payment('inside_files', $id, $advance, ['method' => $o['pay_method'] ?? 'Cash', 'pay_type' => 'File Opening Advance', 'party' => $c['full_name'], 'pay_date' => today()]);
    q("UPDATE candidates SET file_decision='Yes', updated_at=? WHERE id=?", [now(), $cid]);
    if (array_search($c['stage'], CAND_STAGES, true) < array_search('File Opened', CAND_STAGES, true)) stage_advance('candidates', $cid, 'File Opened', 'Inside file opened');
    return $id;
}

/** Candidate portal login + 32-character registration token valid for 7 days. Returns the registration URL. */
function candidate_portal_login($cid, $renew = false)
{
    $c = row('SELECT * FROM candidates WHERE id=?', [$cid]);
    $l = row('SELECT * FROM candidate_logins WHERE candidate_id=?', [$cid]);
    $token = bin2hex(random_bytes(16));
    $exp = date('Y-m-d H:i:s', strtotime('+7 days'));
    if (!$l) {
        ins('candidate_logins', ['candidate_id' => $cid, 'username' => $c['email'] ? strtolower(trim($c['email'])) : null, 'legacy_slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '.', $c['full_name'])) . '.' . $cid,
            'token' => $token, 'token_expires' => $exp, 'created_at' => now()]);
        audit('portal-login-created', 'candidates', $cid);
    } elseif ($renew || !$l['token'] || $l['token_expires'] < now()) {
        q('UPDATE candidate_logins SET token=?, token_expires=?, username=COALESCE(username,?) WHERE candidate_id=?', [$token, $exp, $c['email'] ? strtolower($c['email']) : null, $cid]);
        audit('registration-link', 'candidates', $cid);
    }
    $l = row('SELECT * FROM candidate_logins WHERE candidate_id=?', [$cid]);
    return portal_base_url() . 'portal.php?register=' . $l['token'];
}
function portal_base_url() { return app_url(); }

/** Candidate journey box (detail page and Reports). */
function candidate_journey($c)
{
    $hist = [];
    foreach (rows('SELECT * FROM stage_history WHERE entity=? AND entity_id=? ORDER BY id', ['candidates', $c['id']]) as $h) $hist[$h['to_stage']] = $h;
    $ci = array_search($c['stage'], CAND_STAGES, true);
    $f = candidate_file($c['id']);
    $m = file_money($f);
    echo '<div class="journey">';
    foreach (CAND_STAGES as $i => $s) {
        $cls = $i <= $ci ? 'done' : '';
        if ($i === $ci) $cls .= ' cur';
        echo '<div class="jstep ' . $cls . '"><i>' . ($i <= $ci ? '✓' : $i + 1) . '</i>' . e($s) . (isset($hist[$s]) ? '<br><small class="muted">' . e(date('d M', strtotime($hist[$s]['at']))) . '</small>' : '') . '</div>';
    }
    echo '</div><div class="kv">'
        . '<div><span>Interview</span><b>' . e($c['interview_status'] ?: 'Not interviewed') . '</b></div>'
        . '<div><span>File</span><b>' . e($f ? $f['ref_no'] . ' · ' . $f['status'] : ($c['file_decision'] === 'No' ? 'Not opened (' . $c['decision_reason'] . ')' : 'No file')) . '</b></div>'
        . '<div class="' . ($m['balance'] > 0 ? 'warn' : ($f ? 'good' : '')) . '"><span>Payment status</span><b>' . ($f ? ($m['balance'] <= 0 ? 'Fully paid' : 'Balance due') : '—') . '</b></div>'
        . '<div><span>Agreed total</span><b>' . money($m['agreed']) . '</b></div><div class="good"><span>Paid</span><b>' . money($m['paid']) . '</b></div>'
        . '<div class="' . ($m['balance'] > 0 ? 'bad' : '') . '"><span>Remaining</span><b>' . money(max(0, $m['balance'])) . '</b></div>'
        . '<div><span>Next pay date</span><b>' . e($m['next'] ? dfmt($m['next']) : '—') . '</b></div>'
        . '<div class="' . ($c['stage'] === 'Closed' ? 'bad' : '') . '"><span>File closed</span><b>' . ($f && $f['status'] === 'Closed' ? 'Yes · ' . e($f['close_reason']) : 'No') . '</b></div>'
        . '<div class="' . ($c['final_outcome'] === 'Selected' ? 'good' : ($c['final_outcome'] ? 'warn' : '')) . '"><span>Final outcome</span><b>' . e($c['final_outcome'] ? $c['final_outcome'] . ($c['final_reason'] ? ' · ' . $c['final_reason'] : '') : 'Pending') . '</b></div>'
        . '</div>';
}
function candidate_overview($c)
{
    $f = candidate_file($c['id']);
    if ($c['stage'] === 'Closed' && $f) echo '<div class="banner">' . icon('lock', 20) . ' This file is closed (' . e($f['close_reason']) . ' · ' . e(dtfmt($f['closed_at'])) . '). It cannot advance further.</div>';
    card_open(icon('activity', 17) . ' Candidate journey', ($c['cv_file'] ? file_link($c['cv_file'], 'Download CV') : '<span class="badge b-bad">No CV</span>') . (function_exists('chat_participants') && chat_participants($c['id']) && chat_can($c['id']) ? ' <a class="btn ghost sm" href="chat.php?c=' . (int)$c['id'] . '">' . icon('chat', 14) . ' Private chat</a>' : ''));
    candidate_journey($c);
    card_close();
}
function candidate_panels($c)
{
    $locs = rows('SELECT * FROM interview_locations WHERE candidate_id=? AND archived=0 ORDER BY seq', [$c['id']]);
    card_open(icon('map-pin', 17) . ' Interview locations <span class="count">' . count($locs) . '</span>');
    echo simple_table(['#', 'Company', 'Location', 'Date & time', 'Candidate response', 'Note'], array_map(function ($l) {
        return ['<b>' . ordinal($l['seq']) . '</b>', e($l['company']) . ($l['position'] ? '<br><small class="muted">' . e($l['position']) . '</small>' : ''), e($l['location']) . ($l['map_link'] ? ' · <a href="' . e($l['map_link']) . '" target="_blank" rel="noopener">Map</a>' : ''), e(dtfmt($l['interview_at'])), badge($l['response']), e($l['response_note'])];
    }, $locs), 'No interview locations sent yet.', array_map(function ($l) { return ent_url('interview_locations', $l['id']); }, $locs));
    card_close();
    $f = candidate_file($c['id']);
    if ($f) {
        card_open(icon('dollar', 17) . ' Payments on ' . e($f['ref_no']), '<a class="btn ghost sm" href="' . e(ent_url('inside_files', $f['id'])) . '">Open file</a>');
        payments_table(payments_for('inside_files', $f['id']), false);
        card_close();
    }
}

/** Inside file summary card. */
function file_summary($f)
{
    $m = file_money($f);
    $c = row('SELECT * FROM candidates WHERE id=?', [$f['candidate_id']]);
    $plan = row('SELECT * FROM fee_plans WHERE id=?', [$f['fee_plan_id']]);
    if ($f['status'] === 'Closed') echo '<div class="banner">' . icon('lock', 20) . ' File closed · ' . e($f['close_reason']) . ' · ' . e(dtfmt($f['closed_at'])) . '</div>';
    card_open(icon('folder', 17) . ' File balance', $c ? '<a class="btn ghost sm" href="' . e(ent_url('candidates', $c['id'])) . '">' . icon('user', 14) . ' ' . e($c['full_name']) . '</a>' : '');
    echo '<div class="kv"><div><span>Fee plan</span><b>' . e($plan ? $plan['name'] . ' v' . $plan['version'] : '—') . '</b></div><div><span>Agreed total</span><b>' . money($m['agreed'], $f['currency']) . '</b></div>'
        . '<div class="good"><span>Paid (net of reversals)</span><b>' . money($m['paid'], $f['currency']) . '</b></div><div class="' . ($m['balance'] > 0 ? 'bad' : 'good') . '"><span>Remaining balance</span><b>' . money($m['balance'], $f['currency']) . '</b></div>'
        . '<div><span>Balance due event</span><b>' . e($plan['balance_trigger'] ?? '—') . '</b></div><div class="' . ($m['next'] && $m['next'] < today() && $m['balance'] > 0 ? 'bad' : '') . '"><span>Next pay date</span><b>' . e($m['next'] ? dfmt($m['next']) : '—') . '</b></div>'
        . '<div><span>Promise</span><b>' . e($f['promise_note'] ?: '—') . '</b></div><div><span>Candidate stage</span><b>' . e($c['stage'] ?? '') . '</b></div></div>';
    if ($m['agreed'] > 0) echo '<div style="margin-top:12px">' . progress($m['paid'] / $m['agreed'] * 100, round($m['paid'] / $m['agreed'] * 100) . '% collected') . '</div>';
    card_close();
}

/** Generic "receive / make payment" action used by every module that takes money. */
function pay_action($entity, $label, $perm = 'finance', $show = null, $direction = 'in', $types = null)
{
    return ['label' => $label, 'icon' => 'dollar', 'perm' => $perm, 'show' => $show, 'done' => 'Payment recorded.',
        'form' => function ($r) use ($direction) {
            $o = $GLOBALS['_form_row'];
            echo '<div class="grid-form"><div class="fld"><label>Amount *</label><input type="number" step="0.01" min="0.01" name="amount" required value="' . e($o['amount'] ?? '') . '"></div>'
                . '<div class="fld"><label>Date *</label><input type="date" name="pay_date" required value="' . e($o['pay_date'] ?? today()) . '"></div>'
                . '<div class="fld"><label>Method *</label><select name="method" required>';
            foreach (options('payment_methods') as $m) echo '<option>' . e($m) . '</option>';
            echo '</select></div><div class="fld"><label>Payment type *</label><select name="pay_type" required>';
            foreach (options('pay_types') as $m) echo '<option>' . e($m) . '</option>';
            echo '</select></div><div class="fld wide"><label>Reference / note</label><input name="note" value="' . e($o['note'] ?? '') . '" placeholder="Bank reference, cheque number…"></div></div>'
                . '<p class="muted small">A ' . ($direction === 'in' ? 'receipt (RCT-)' : 'payment voucher (PAY-)') . ' number is issued and a balanced journal entry is posted. Entries cannot be edited later — corrections are made with a reversal.</p><div class="form-actions"><button class="btn">' . icon('check', 15) . ' Record payment</button></div>';
        },
        'do' => function ($r) use ($entity, $direction) {
            $amt = (float)p('amount');
            if ($amt <= 0) return 'Enter an amount greater than zero.';
            if ($entity === 'inside_files' && $direction === 'in') {
                $bal = file_money($r)['balance'];
                if ($amt > $bal + 0.001) return 'The amount is more than the remaining balance (' . money($bal) . ').';
            }
            record_payment($entity, $r['id'], $amt, ['direction' => $direction, 'method' => p('method'), 'pay_type' => p('pay_type'), 'pay_date' => p('pay_date'), 'note' => p('note'), 'party' => $r['ref_no'] ?? null]);
            return null;
        }];
}

/** Payments list with reversal (Finance only). */
function payments_table(array $list, $withReverse = true, $mod = null, $base = null)
{
    $out = [];
    $tot = 0;
    foreach ($list as $pm) {
        $tot += $pm['amount'];
        $act = '';
        if ($withReverse && !$pm['reversed'] && !$pm['reversal_of'] && $mod && can($mod, 'finance'))
            $act = '<form method="post" action="' . e(u($base, ['a' => 'act', 'id' => $pm['entity_id'], 'act' => '_reverse'])) . '" class="inline-form" data-confirm="Reverse ' . e($pm['ref_no']) . '? A reversal entry will be posted; the original stays in the history.">' . csrf_field() . '<input type="hidden" name="pid" value="' . $pm['id'] . '"><input name="reason" required placeholder="Reason" style="width:130px;padding:5px 8px"><button class="btn ghost sm danger">Reverse</button></form>';
        $out[] = ['<b class="ref">' . e($pm['ref_no']) . '</b>', e(dfmt($pm['pay_date'])), e($pm['pay_type']), e($pm['method']), '<span class="num"' . ($pm['amount'] < 0 ? ' style="color:var(--bad)"' : '') . '>' . money($pm['amount']) . '</span>',
            ($pm['reversed'] ? badge('Reversed') : ($pm['reversal_of'] ? badge('Reversal') : badge('Valid'))) . ($pm['reason'] ? '<br><small class="muted">' . e($pm['reason']) . '</small>' : ''), e(user_name($pm['created_by'])), $act];
    }
    echo simple_table(['Receipt', 'Date', 'Type', 'Method', 'Amount', 'Status', 'By', ''], $out, 'No payments recorded yet.');
    if ($list) echo '<p class="right small" style="margin:8px 4px 0"><b>Net total: ' . money($tot) . '</b></p>';
}
function payments_panel($entity, $id, $mod, $base)
{
    card_open(icon('dollar', 17) . ' Receipts &amp; payments');
    payments_table(payments_for($entity, $id), true, $mod, $base);
    card_close();
}
/** Reversal handler shared by all entities that show payments_panel (posted as act=_reverse). */
function reversal_action()
{
    return ['label' => 'Reverse', 'perm' => 'finance', 'hidden' => true, 'form' => function () {},'do' => function ($r) { return reverse_payment((int)p('pid'), p('reason')) ?: ['msg' => 'Payment reversed. The original entry is kept in the history.']; }];
}
$GLOBALS['ENT']['inside_files']['actions']['_reverse'] = reversal_action();

/** Candidate detail page action buttons (spec 4 & 7). */
function candidate_actions()
{
    $open = function ($r) { return $r['stage'] !== 'Closed'; };
    $userSel = function ($name, $v = null, $req = true) { $h = '<select name="' . $name . '"' . ($req ? ' required' : '') . '><option value="">— Select —</option>'; foreach (staff_list() as $s) $h .= '<option value="' . $s['id'] . '"' . ((string)$v === (string)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>'; return $h . '</select>'; };
    return [
        'hr' => ['label' => 'HR', 'icon' => 'user', 'perm' => 'edit', 'show' => function ($r) { return in_array($r['stage'], ['New', 'HR Assigned'], true); },
            'form' => function ($r) use ($userSel) { echo '<div class="grid-form"><div class="fld"><label>HR officer *</label>' . $userSel('hr_officer', $r['hr_officer']) . '</div></div><div class="form-actions"><button class="btn">Assign to HR</button></div>'; },
            'do' => function ($r) {
                $uid = (int)p('hr_officer');
                if (!$uid) return 'Choose an HR officer.';
                upd('candidates', $r['id'], ['hr_officer' => $uid, 'updated_at' => now(), 'updated_by' => me()['id']]);
                audit('assign-hr', 'candidates', $r['id'], ['hr_officer' => $r['hr_officer']], ['hr_officer' => $uid]);
                if ($r['stage'] === 'New') stage_advance('candidates', $r['id'], 'HR Assigned', 'Assigned to ' . user_name($uid));
                notify($uid, 'Candidate assigned to you: ' . $r['full_name'], ent_url('candidates', $r['id']), 'assigned');
                return ['msg' => 'Assigned to ' . user_name($uid) . '.'];
            }],
        'interview' => ['label' => 'Interview', 'icon' => 'clipboard', 'perm' => 'approve', 'show' => function ($r) { return in_array($r['stage'], ['New', 'HR Assigned', 'Interviewed'], true); },
            'form' => function ($r) {
                echo '<div class="grid-form"><div class="fld"><label>Interview outcome *</label><select name="interview_status" required>';
                foreach (['Pending', 'Selected', 'Rejected', 'On Hold'] as $s) echo '<option' . ($r['interview_status'] === $s ? ' selected' : '') . '>' . $s . '</option>';
                echo '</select></div><div class="fld"><label>Attendance</label><select name="attendance"><option>Attended</option><option>No show</option><option>Rescheduled</option></select></div><div class="fld wide"><label>Interviewer notes *</label><textarea name="interview_notes" required rows="3">' . e($r['interview_notes']) . '</textarea></div></div><div class="form-actions"><button class="btn">Save interview</button></div>';
            },
            'do' => function ($r) {
                $st = p('interview_status');
                if (!in_array($st, ['Pending', 'Selected', 'Rejected', 'On Hold'], true)) return 'Choose an outcome.';
                $notes = p('interview_notes') . (p('attendance') !== 'Attended' ? "\n[" . p('attendance') . ']' : '');
                upd('candidates', $r['id'], ['interview_status' => $st, 'interview_notes' => $notes, 'interviewed_at' => now(), 'hr_officer' => $r['hr_officer'] ?: me()['id'], 'updated_at' => now()]);
                audit('interview', 'candidates', $r['id'], ['interview_status' => $r['interview_status']], ['interview_status' => $st]);
                if (p('attendance') === 'Attended') stage_advance('candidates', $r['id'], 'Interviewed', 'Interview: ' . $st);
                if ($st === 'Selected' && p('attendance') === 'Attended') return ['msg' => 'Interview recorded: Selected. Now confirm the file: choose the fee plan and enter the advance paid today.', 'go' => ent_url('candidates', $r['id']) . '&act=file'];
                return ['msg' => 'Interview recorded: ' . $st . '.'];
            }],
        'file' => ['label' => 'File decision', 'icon' => 'folder', 'perm' => 'approve', 'show' => function ($r) { return $r['stage'] === 'Interviewed' && !candidate_file($r['id']); },
            'form' => function ($r) use ($userSel) {
                $plans = rows('SELECT * FROM fee_plans WHERE active=1 AND archived=0 ORDER BY name');
                echo '<div class="grid-form"><div class="fld wide"><label>Open a file for this candidate? *</label><label class="check"><input type="radio" name="decision" value="Yes" checked> Yes — open the inside file</label> &nbsp; <label class="check"><input type="radio" name="decision" value="No"> No</label></div>'
                    . '<div class="fld"><label>Fee plan (for Yes)</label><select name="fee_plan_id"><option value="">— Select —</option>';
                foreach ($plans as $pl) echo '<option value="' . $pl['id'] . '" data-total="' . e($pl['total']) . '" data-adv="' . e($pl['advance']) . '">' . e($pl['name'] . ' v' . $pl['version'] . ' · ' . money($pl['total'], $pl['currency'])) . '</option>';
                echo '</select><small class="muted">Agreed total defaults to the plan total.</small></div><div class="fld"><label>Agreed total (leave blank for plan total)</label><input type="number" step="0.01" name="agreed_total"></div>'
                    . '<div class="fld"><label>Initial amount collected now</label><input type="number" step="0.01" name="advance" placeholder="Plan advance if blank"></div><div class="fld"><label>Payment method</label><select name="pay_method">';
                foreach (options('payment_methods') as $m) echo '<option>' . e($m) . '</option>';
                echo '</select></div><div class="fld"><label>Next pay date</label><input type="date" name="next_pay_date"></div>'
                    . '<div class="fld"><label>Reason (for No) </label><select name="no_reason" data-other="no_other"><option value="">— Select —</option><option>Will come later</option><option>Not interested in fee terms</option><option>Not suitable</option><option>Other</option></select><input class="other-detail" id="no_other" name="no_reason_other" placeholder="Please specify"></div></div>'
                    . '<div class="form-actions"><button class="btn">Save decision</button></div>';
            },
            'do' => function ($r) {
                if (p('decision') === 'No') {
                    $why = p('no_reason') === 'Other' ? p('no_reason_other') : p('no_reason');
                    if (!$why) return 'Choose the reason for No.';
                    upd('candidates', $r['id'], ['file_decision' => 'No', 'decision_reason' => $why, 'updated_at' => now()]);
                    audit('file-decision', 'candidates', $r['id'], null, ['decision' => 'No', 'reason' => $why]);
                    return ['msg' => 'Decision recorded: no file (' . $why . '). The candidate stays out of Deployment.'];
                }
                $plan = row('SELECT * FROM fee_plans WHERE id=?', [(int)p('fee_plan_id')]);
                if (!$plan) return 'Choose a fee plan.';
                $total = p('agreed_total') !== '' ? (float)p('agreed_total') : (float)$plan['total'];
                $adv = p('advance') !== '' ? (float)p('advance') : (float)$plan['advance'];
                $res = create_inside_file($r['id'], $plan['id'], $total, $adv, ['pay_method' => p('pay_method'), 'next_pay_date' => p('next_pay_date') ?: null]);
                if (is_string($res)) return $res;
                $ref = val('SELECT ref_no FROM inside_files WHERE id=?', [$res]);
                notify_role('Reception', 'File confirmed by HR for ' . $r['full_name'] . ' (' . $ref . ') - ready to assign to Deployment', 'reception.php?t=ready', 'file');
                return ['msg' => 'File ' . $ref . ' opened and Reception has been notified. You can assign the Deployment team now below, or leave it to Reception.', 'go' => ent_url('candidates', $r['id']) . '&act=deploy'];
            }],
        'deploy' => ['label' => 'Deploy', 'icon' => 'send', 'perm' => 'assign', 'show' => function ($r) { return in_array($r['stage'], ['File Opened', 'In Deployment'], true); },
            'form' => function ($r) use ($userSel) {
                echo '<div class="grid-form"><div class="fld"><label>Deployment team</label><select name="team_id"><option value="">— None —</option>';
                foreach (rows('SELECT * FROM deployment_teams WHERE archived=0 ORDER BY name') as $t) echo '<option value="' . $t['id'] . '"' . ($r['team_id'] == $t['id'] ? ' selected' : '') . '>' . e($t['name']) . '</option>';
                echo '</select></div><div class="fld"><label>Deployment officer</label>' . deploy_select('deploy_officer', $r['deploy_officer']) . '</div></div><p class="muted small">Assigning opens the private chat, creates the candidate\'s portal login and emails the registration link to the candidate.</p><div class="form-actions"><button class="btn">Assign to Deployment</button></div>';
            },
            'do' => function ($r) {
                $team = (int)p('team_id') ?: null;
                $off = (int)p('deploy_officer') ?: null;
                if (!$team && !$off) return 'Choose a team or a deployment officer.';
                if (!$off && $team) $off = (int)val('SELECT leader FROM deployment_teams WHERE id=?', [$team]);
                upd('candidates', $r['id'], ['team_id' => $team, 'deploy_officer' => $off, 'updated_at' => now()]);
                $f = candidate_file($r['id']);
                if ($f) q('UPDATE inside_files SET deploy_owner=? WHERE id=?', [$off, $f['id']]);
                audit('deploy', 'candidates', $r['id'], ['team_id' => $r['team_id'], 'deploy_officer' => $r['deploy_officer']], ['team_id' => $team, 'deploy_officer' => $off]);
                stage_advance('candidates', $r['id'], 'In Deployment', 'Assigned to deployment');
                $link = candidate_portal_login($r['id']);
                foreach (chat_participants($r['id']) as $pid) if ((int)$pid !== (int)me()['id']) notify($pid, 'Candidate assigned to your team: ' . $r['full_name'], ent_url('candidates', $r['id']), 'deploy');
                $mail = candidate_welcome_email($r['id'], $link);
                return ['msg' => 'Assigned to Deployment. ' . $mail . ' Registration link (valid 7 days): <a href="' . e($link) . '">' . e($link) . '</a>'];
            }],
        'location' => ['label' => 'Location', 'icon' => 'map-pin', 'perm' => 'assign', 'show' => function ($r) { return $r['stage'] === 'In Deployment' && (!$r['final_outcome'] || $r['final_outcome'] === 'Rescheduled'); },
            'form' => function ($r) {
                $n = (int)val('SELECT COALESCE(MAX(seq),0)+1 FROM interview_locations WHERE candidate_id=?', [$r['id']]);
                $pend = val("SELECT COUNT(*) FROM interview_locations WHERE candidate_id=? AND response='Pending'", [$r['id']]);
                if ($pend) echo '<div class="alert warn">The previous location is still waiting for the candidate\'s response.</div>';
                echo '<p>Send the <b>' . ordinal($n) . '</b> interview location.</p><div class="grid-form"><div class="fld"><label>Company *</label><input name="company" required></div><div class="fld"><label>Position</label><input name="position" value="' . e($r['applied_position']) . '"></div>'
                    . '<div class="fld"><label>Exact location *</label><input name="location" required></div><div class="fld"><label>Map link</label><input type="url" name="map_link" placeholder="https://maps…"></div><div class="fld"><label>Date &amp; time *</label><input type="datetime-local" name="interview_at" required></div>'
                    . '<div class="fld"><label>Contact person &amp; phone</label><input name="contact"></div><div class="fld wide"><label>Instructions</label><textarea name="instructions" rows="2" placeholder="Bring passport copy, CV, photos…"></textarea></div></div><div class="form-actions"><button class="btn">Send location</button></div>';
            },
            'do' => function ($r) {
                if (!p('company') || !p('location') || !p('interview_at')) return 'Company, location and date are required.';
                $seq = (int)val('SELECT COALESCE(MAX(seq),0)+1 FROM interview_locations WHERE candidate_id=?', [$r['id']]);
                $id = ins('interview_locations', ['candidate_id' => $r['id'], 'seq' => $seq, 'company' => p('company'), 'position' => p('position'), 'location' => p('location'), 'map_link' => p('map_link'),
                    'interview_at' => str_replace('T', ' ', p('interview_at')) . ':00', 'contact' => p('contact'), 'instructions' => p('instructions'), 'response' => 'Pending', 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
                audit('location-sent', 'candidates', $r['id'], null, ['seq' => $seq, 'company' => p('company')]);
                return ['msg' => ordinal($seq) . ' interview location sent. The candidate sees it under "My Interview Locations".'];
            }],
        'deppay' => array_merge(pay_action('inside_files', 'Deployment payment', 'assign'), ['show' => function ($r) { $f = candidate_file($r['id']); return $f && $f['status'] === 'Open' && in_array($r['stage'], ['In Deployment', 'Selected', 'Placed'], true); },
            'do' => function ($r) {
                $f = candidate_file($r['id']);
                $amt = (float)p('amount');
                if ($amt <= 0) return 'Enter an amount.';
                if ($amt > file_money($f)['balance'] + 0.001) return 'The amount is more than the remaining balance (' . money(file_money($f)['balance']) . ').';
                record_payment('inside_files', $f['id'], $amt, ['method' => p('method'), 'pay_type' => p('pay_type') ?: 'Deployment Payment', 'pay_date' => p('pay_date'), 'note' => p('note'), 'party' => $r['full_name']]);
                return ['msg' => 'Payment recorded on ' . $f['ref_no'] . '. File and deployment balances are updated together.'];
            }]),
        'outcome' => ['label' => 'Final outcome', 'icon' => 'target', 'perm' => 'assign', 'show' => function ($r) { return $r['stage'] === 'In Deployment'; },
            'form' => function ($r) {
                echo '<div class="grid-form"><div class="fld"><label>Final outcome *</label><select name="final_outcome" required data-other="fo_other"><option value="">— Select —</option>';
                foreach (['Selected', 'Rejected', 'Rescheduled', 'Withdrawn', 'Other'] as $o) echo '<option>' . $o . '</option>';
                echo '</select><input class="other-detail" id="fo_other" name="final_outcome_other" placeholder="Please specify"></div><div class="fld"><label>Selected at company (if selected)</label><input name="company"></div><div class="fld wide"><label>Reason *</label><input name="final_reason" required placeholder="Required for every outcome"></div></div><div class="form-actions"><button class="btn">Record final outcome</button></div>';
            },
            'do' => function ($r) {
                $o = p('final_outcome');
                if (!$o || !p('final_reason')) return 'Outcome and reason are required.';
                $reason = p('final_reason') . ($o === 'Other' ? ' (' . p('final_outcome_other') . ')' : '') . (p('company') ? ' · ' . p('company') : '');
                upd('candidates', $r['id'], ['final_outcome' => $o, 'final_reason' => $reason, 'updated_at' => now()]);
                audit('final-outcome', 'candidates', $r['id'], ['final_outcome' => $r['final_outcome']], ['final_outcome' => $o], $reason);
                if ($o === 'Selected') {
                    stage_advance('candidates', $r['id'], 'Selected', $reason);
                    $f = candidate_file($r['id']);
                    if ($f) {
                        $plan = row('SELECT * FROM fee_plans WHERE id=?', [$f['fee_plan_id']]);
                        $due = date('Y-m-d', strtotime('+' . (int)($plan['due_days'] ?? 7) . ' days'));
                        $bal = file_money($f)['balance'];
                        if ($bal > 0) {
                            q('UPDATE inside_files SET next_pay_date=? WHERE id=?', [$due, $f['id']]);
                            add_task('Selection balance due: ' . $r['full_name'] . ' (' . money($bal) . ')', 'High', $due, $f['hr_owner'] ?: me()['id'], ent_url('inside_files', $f['id']), 'balance-' . $f['id']);
                        }
                    }
                }
                return ['msg' => 'Final outcome recorded: ' . $o . '.'];
            }],
        'placed' => ['label' => 'Mark placed', 'icon' => 'check-circle', 'perm' => 'assign', 'show' => function ($r) { return $r['stage'] === 'Selected'; },
            'form' => function ($r) { echo '<p>Confirm the candidate has joined the employer.</p><div class="fld"><label>Note</label><input name="note"></div><div class="form-actions"><button class="btn ok">Mark placed</button></div>'; },
            'do' => function ($r) { return stage_advance('candidates', $r['id'], 'Placed', p('note') ?: 'Joined employer') ?: ['msg' => 'Candidate marked as placed.']; }],
        'close' => ['label' => 'File close', 'icon' => 'lock', 'perm' => 'assign', 'show' => function ($r) { return $r['stage'] !== 'Closed' && candidate_file($r['id']); }, 'confirm' => 'Closing a file is final. The candidate cannot advance afterwards. Continue?',
            'form' => function ($r) {
                $f = candidate_file($r['id']);
                $bal = file_money($f)['balance'];
                if ($bal > 0) echo '<div class="alert warn">Remaining balance on the file: <b>' . money($bal) . '</b>.</div>';
                echo '<div class="grid-form"><div class="fld"><label>Closure outcome *</label><select name="reason" required data-other="cl_other"><option value="">— Select —</option>';
                foreach (options('close_reasons') as $o) echo '<option>' . e($o) . '</option>';
                echo '<option>Other</option></select><input class="other-detail" id="cl_other" name="reason_other" placeholder="Please specify"></div><div class="fld"><label>Details *</label><input name="details" required></div></div><div class="form-actions"><button class="btn danger">' . icon('lock', 15) . ' Close file</button></div>';
            },
            'do' => function ($r) {
                $reason = (p('reason') === 'Other' ? p('reason_other') : p('reason'));
                if (!$reason || !p('details')) return 'A closure outcome and details are required.';
                $full = $reason . ' — ' . p('details');
                $ts = now();
                $f = candidate_file($r['id']);
                q("UPDATE inside_files SET status='Closed', outcome=?, close_reason=?, closed_at=?, updated_at=? WHERE id=?", [$reason, $full, $ts, $ts, $f['id']]);
                audit('file-close', 'inside_files', $f['id'], ['status' => 'Open'], ['status' => 'Closed'], $full);
                stage_advance('candidates', $r['id'], 'Closed', $full);
                return ['msg' => 'File closed. This is terminal.'];
            }],
        'reglink' => ['label' => 'Registration link', 'icon' => 'link', 'perm' => 'assign', 'show' => function ($r) { return in_array($r['stage'], ['File Opened', 'In Deployment', 'Selected', 'Placed'], true); },
            'form' => function ($r) {
                $l = row('SELECT * FROM candidate_logins WHERE candidate_id=?', [$r['id']]);
                if ($l && $l['token'] && $l['token_expires'] > now()) {
                    $url = portal_base_url() . 'portal.php?register=' . $l['token'];
                    echo '<p>Portal username: <b>' . e($l['username'] ?: '(add the candidate\'s email)') . '</b> · ' . ($l['password_hash'] ? badge('Password set') : badge('Waiting for registration')) . '</p><div class="copybox"><input id="reglink" readonly value="' . e($url) . '"><button type="button" class="btn ghost" data-copy="reglink">Copy</button></div><p class="muted small">Valid until ' . e(dtfmt($l['token_expires'])) . '. Share it with the candidate (WhatsApp / SMS). Email delivery is not relied on.</p>';
                } else echo '<p>' . ($l ? 'The registration link has expired.' : 'No portal login yet.') . '</p>';
                echo '<div class="form-actions"><button class="btn">' . icon('refresh', 15) . ' ' . ($l ? 'Issue a new link (7 days)' : 'Create portal login') . '</button></div>';
            },
            'do' => function ($r) { $url = candidate_portal_login($r['id'], true); $mail = candidate_welcome_email($r['id'], $url); return ['msg' => $mail . ' New registration link (valid 7 days): <a href="' . e($url) . '">' . e($url) . '</a>', 'go' => ent_url('candidates', $r['id']) . '&act=reglink']; }],
    ];
}

/** Reception queue rows with interview, file and money columns. */
function candidate_queue_table(array $list, $empty = 'Nobody in this queue.')
{
    $data = []; $links = [];
    foreach ($list as $c) {
        $f = candidate_file($c['id']);
        $m = file_money($f);
        $data[] = ['<b class="ref">' . e($c['ref_no']) . '</b>', '<b>' . e($c['full_name']) . '</b><br><small class="muted">' . e($c['phone']) . ' · ' . e($c['applied_position']) . '</small>', badge($c['stage']),
            badge($c['interview_status'] ?: 'Not interviewed'), $f ? badge($f['status'] === 'Open' ? 'File open' : 'Closed') . '<br><small class="ref">' . e($f['ref_no']) . '</small>' : '<span class="muted">No file</span>',
            '<span class="num">' . money($m['paid']) . '</span>', '<span class="num"' . ($m['balance'] > 0 ? ' style="color:var(--bad);font-weight:700"' : '') . '>' . money(max(0, $m['balance'])) . '</span>',
            $m['next'] ? '<span' . ($m['next'] < today() && $m['balance'] > 0 ? ' style="color:var(--bad);font-weight:700"' : '') . '>' . e(dfmt($m['next'])) . '</span>' : '<span class="muted">—</span>', e(user_name($c['hr_officer']))];
        $links[] = ent_url('candidates', $c['id']);
    }
    return simple_table(['No.', 'Candidate', 'Stage', 'Interview', 'File', 'Paid', 'Remaining', 'Next pay date', 'HR'], $data, $empty, $links);
}

/** HR officers (role name starting with HR) and Deployment staff (roles that have the Deployment module). */
function hr_staff() { return staff_list('HR'); }
function deploy_staff()
{
    $l = rows("SELECT u.id, u.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.active=1 AND (r.modules LIKE '%\"deployment\"%') ORDER BY u.name");
    return $l ?: staff_list();
}
function deploy_select($name, $v = null)
{
    $h = '<select name="' . e($name) . '"><option value="">— Select —</option>';
    foreach (deploy_staff() as $s) $h .= '<option value="' . $s['id'] . '"' . ((string)$v === (string)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
    return $h . '</select>';
}
/** Email the candidate the portal registration link. Returns a sentence for the staff screen. */
function candidate_welcome_email($cid, $link)
{
    $c = row('SELECT * FROM candidates WHERE id=?', [$cid]);
    $f = candidate_file($cid);
    if (!$c || !$c['email']) return 'No email was sent because the candidate has no email address on file (add it, then use "Registration link").';
    $body = 'Dear ' . $c['full_name'] . ",\n\nWelcome to RH Business Solutions & Marketing Management. " . ($f ? 'Your file ' . $f['ref_no'] . ' has been passed to our deployment team' : 'Your registration is complete') . ", and your personal candidate portal is ready.\n\n"
        . "In your portal you can follow your progress, see your interview locations and confirm the result of each interview, check your payments, and chat privately with your deployment team.\n\n"
        . 'To activate it, press the button below and choose your password. Your username will be your email address (' . $c['email'] . '). The link is valid for 7 days.';
    $ok = send_email($c['email'], 'Your RH Management candidate portal is ready', $body, 'Candidate registration link', ['button' => ['Activate my portal', $link], 'sensitive' => true]);
    return $ok ? 'The registration email was sent to ' . e($c['email']) . '.' : 'The email to ' . e($c['email']) . ' could not be sent (' . e($GLOBALS['mail_error'] ?? 'unknown error') . '); share the link below with the candidate.';
}
/** Reception queue with an inline "assign" form on each row (HR officer, or Deployment team / officer). */
function reception_assign_table(array $list, $kind, $empty)
{
    $can = $kind === 'hr' ? can('candidates', 'edit') : can('candidates', 'assign');
    $hr = hr_staff();
    $teams = rows('SELECT id, name FROM deployment_teams WHERE archived=0 ORDER BY name');
    $dp = deploy_staff();
    $data = [];
    $links = [];
    foreach ($list as $c) {
        $f = candidate_file($c['id']);
        $m = file_money($f);
        $form = '<span class="muted small">Your role cannot assign</span>';
        if ($can) {
            $form = '<form method="post" class="inline-form" action="' . e('candidates.php?t=all&a=act&id=' . $c['id'] . '&act=' . $kind) . '">' . csrf_field() . '<input type="hidden" name="_back" value="' . e('reception.php' . ($kind === 'deploy' ? '?t=ready' : '')) . '">';
            if ($kind === 'hr') {
                $form .= '<select name="hr_officer" required aria-label="HR officer"><option value="">Choose HR officer…</option>';
                foreach ($hr as $s) $form .= '<option value="' . $s['id'] . '">' . e($s['name']) . '</option>';
                $form .= '</select>';
            } else {
                $form .= '<select name="team_id" aria-label="Deployment team"><option value="">Team (optional)</option>';
                foreach ($teams as $t) $form .= '<option value="' . $t['id'] . '">' . e($t['name']) . '</option>';
                $form .= '</select><select name="deploy_officer" aria-label="Deployment officer"><option value="">Officer…</option>';
                foreach ($dp as $s) $form .= '<option value="' . $s['id'] . '">' . e($s['name']) . '</option>';
                $form .= '</select>';
            }
            $form .= '<button class="btn sm">' . icon('send', 13) . ' Assign</button></form>';
        }
        $data[] = ['<b class="ref">' . e($c['ref_no']) . '</b>', '<b>' . e($c['full_name']) . '</b><br><small class="muted">' . e($c['phone']) . ' · ' . e($c['applied_position']) . '</small>', badge($c['interview_status'] ?: 'Not interviewed'),
            $f ? '<small class="ref">' . e($f['ref_no']) . '</small>' : '<span class="muted">No file</span>', '<span class="num">' . money($m['paid']) . '</span>', '<span class="num"' . ($m['balance'] > 0 ? ' style="color:var(--bad);font-weight:700"' : '') . '>' . money(max(0, $m['balance'])) . '</span>', $m['next'] ? e(dfmt($m['next'])) : '<span class="muted">—</span>', $form];
        $links[] = ent_url('candidates', $c['id']);
    }
    return simple_table(['No.', 'Candidate', 'Interview', 'File', 'Paid', 'Remaining', 'Next pay date', $kind === 'hr' ? 'Assign to HR' : 'Assign to Deployment'], $data, $empty, $links);
}

// ---------------------------------------------------------------- module screens

mod('reception', ['label' => 'Reception', 'icon' => 'home', 'area' => 'Inside Hiring', 'order' => 1, 'desc' => 'Today\'s entries, HR-assign and ready-for-deployment queues, and staff attendance.',
    'tabs' => ['today' => ['label' => 'Queues', 'fn' => function ($base, $mod) {
        echo '<div class="ph-r" style="margin-bottom:14px">' . (can('candidates', 'add') ? '<a class="btn" href="candidates.php?a=new">' . icon('user-plus', 16) . ' Add New candidate</a>' : '') . ' <a class="btn ghost" href="candidates.php">All candidates</a></div>';
        render_widgets(['reception']);
        echo '<div class="grid g2"><div class="span2">';
        card_open(icon('calendar', 17) . ' Today\'s entries');
        echo candidate_queue_table(rows("SELECT * FROM candidates WHERE archived=0 AND date(created_at)=? ORDER BY id DESC", [today()]), 'No candidates registered today.');
        card_close();
        card_open(icon('user', 17) . ' HR-assign queue (waiting for an HR officer)');
        echo reception_assign_table(rows("SELECT * FROM candidates WHERE archived=0 AND stage='New' ORDER BY id"), 'hr', 'Everyone has an HR officer.');
        card_close();
        card_open(icon('clipboard', 17) . ' With HR (assigned / interviewed)');
        echo candidate_queue_table(rows("SELECT * FROM candidates WHERE archived=0 AND stage IN ('HR Assigned','Interviewed') ORDER BY id"), 'Nobody waiting with HR.');
        card_close();
        card_open(icon('send', 17) . ' Ready for deployment (file opened)');
        echo reception_assign_table(rows("SELECT * FROM candidates WHERE archived=0 AND stage='File Opened' ORDER BY id"), 'deploy', 'No files waiting for deployment.');
        card_close();
        echo '</div></div>';
    }], 'ready' => ['label' => 'Ready for deployment', 'fn' => function ($base, $mod) {
        $list = rows("SELECT * FROM candidates WHERE archived=0 AND stage='File Opened' ORDER BY id");
        card_open(icon('send', 17) . ' Ready for deployment <span class="count">' . count($list) . '</span>', '<span class="muted small">HR has confirmed these files. Pick the deployment team or officer and press Assign.</span>');
        echo reception_assign_table($list, 'deploy', 'Nothing waiting. New confirmed files appear here with a notification.');
        card_close();
    }], 'attendance' => ['label' => 'Attendance', 'fn' => function ($base, $mod) {
        attendance_box();
        card_open(icon('users', 17) . ' Manual attendance box · ' . date('l d M Y'), '<span class="muted small">Mark staff who did not check in themselves</span>');
        $data = [];
        foreach (staff_list() as $s) {
            $a = attendance_today($s['id']);
            $btn = '';
            if (can('reception', 'edit')) {
                $btn = '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="att_user" value="' . $s['id'] . '">';
                if (!$a) $btn .= '<button class="btn sm" name="_att" value="in">Check in</button>';
                elseif (!$a['check_out']) $btn .= '<button class="btn ghost sm" name="_att" value="out">Check out</button>';
                $btn .= '</form>';
            }
            $data[] = ['<b>' . e($s['name']) . '</b> <span class="muted small">@' . e($s['username']) . '</span>', $a ? badge('Present') : badge('Not checked in'), e($a ? substr($a['check_in'], 11, 5) : '—'), e($a && $a['check_out'] ? substr($a['check_out'], 11, 5) : '—'), $a && $a['marked_by'] != $s['id'] ? '<small class="muted">by ' . e(user_name($a['marked_by'])) . '</small>' : '', $btn];
        }
        echo simple_table(['Staff', 'Today', 'Check-in', 'Check-out', 'Marked', ''], $data);
        card_close();
    }]]]);

mod('candidates', ['label' => 'Candidates', 'icon' => 'users', 'area' => 'Inside Hiring', 'order' => 2, 'desc' => 'Candidate master — one record per person (CAN- number), full journey, documents and messages.',
    'tabs' => ['all' => ['ent' => 'candidates', 'label' => 'Candidates', 'noimport' => false], 'documents' => 'documents', 'issues' => ['label' => 'Issues', 'ent' => 'issues', 'nohome' => true, 'scope' => function () { return ["party IN ('Candidate','Internal')", []]; }]]]);

mod('deployment', ['label' => 'Deployment', 'icon' => 'send', 'area' => 'Inside Hiring', 'order' => 3, 'desc' => 'My Candidates, teams, interview locations, deployment payments, final outcomes and file closure.',
    'tabs' => [
        'mine' => ['label' => 'My Candidates', 'fn' => function ($base, $mod) {
            $uid = me()['id'];
            $teams = array_column(rows("SELECT id FROM deployment_teams WHERE archived=0 AND (leader=? OR ','||members||',' LIKE ?)", [$uid, '%,' . $uid . ',%']), 'id');
            $w = 'deploy_officer=?';
            $p = [$uid];
            if ($teams) { $w .= ' OR team_id IN (' . implode(',', array_map('intval', $teams)) . ')'; }
            $list = rows("SELECT * FROM candidates WHERE archived=0 AND ($w) AND stage IN ('File Opened','In Deployment','Selected','Placed') ORDER BY id DESC", $p);
            render_widgets(['deployment']);
            card_open(icon('user', 17) . ' My Candidates <span class="count">' . count($list) . '</span>', '<span class="muted small">Assigned to you directly or through your team</span>');
            $data = []; $links = [];
            foreach ($list as $c) {
                $f = candidate_file($c['id']); $m = file_money($f);
                $last = row('SELECT * FROM interview_locations WHERE candidate_id=? ORDER BY seq DESC LIMIT 1', [$c['id']]);
                $data[] = ['<b>' . e($c['full_name']) . '</b><br><small class="muted">' . e($c['ref_no']) . ' · ' . e($c['phone']) . '</small>', e($c['applied_position']), badge($c['stage']), $last ? ordinal($last['seq']) . ' · ' . e($last['company']) . ' ' . badge($last['response']) : '<span class="muted">None sent</span>', money(max(0, $m['balance'])), $c['team_id'] ? e(ent_title('deployment_teams', $c['team_id'])) : '<span class="muted">Direct</span>', $c['cv_file'] ? file_link($c['cv_file'], 'CV') : ''];
                $links[] = ent_url('candidates', $c['id']);
            }
            echo simple_table(['Candidate', 'Position', 'Stage', 'Last location', 'Remaining', 'Team', 'CV'], $data, 'No candidates assigned to you yet.', $links);
            card_close();
        }],
        'pipeline' => ['label' => 'All in deployment', 'ent' => 'candidates', 'nohome' => true, 'noadd' => true, 'scope' => function () { return ["stage IN ('File Opened','In Deployment','Selected','Placed')", []]; }, 'list' => ['ref_no', 'full_name', 'applied_position', 'stage', 'deploy_officer', 'team_id', 'final_outcome']],
        'teams' => 'deployment_teams',
        'locations' => 'interview_locations',
        'closed' => ['label' => 'Closed files', 'ent' => 'inside_files', 'nohome' => true, 'noadd' => true, 'scope' => function () { return ["status='Closed'", []]; }, 'list' => ['ref_no', 'candidate_id', 'agreed_total', 'deploy_owner', 'status']],
    ]]);

mod('inside', ['label' => 'Inside Hiring', 'icon' => 'folder', 'area' => 'Inside Hiring', 'order' => 4, 'desc' => 'Inside files, agreed totals, balances, due dates and payment promises.',
    'tabs' => [
        'files' => 'inside_files',
        'dues' => ['label' => 'Dues & promises', 'fn' => function ($base, $mod) {
            $list = rows("SELECT * FROM inside_files WHERE status='Open' AND archived=0 ORDER BY next_pay_date IS NULL, next_pay_date");
            $data = []; $links = []; $tot = 0; $over = 0;
            foreach ($list as $f) {
                $m = file_money($f);
                if ($m['balance'] <= 0) continue;
                $tot += $m['balance'];
                $late = $m['next'] && $m['next'] < today();
                if ($late) $over += $m['balance'];
                $data[] = ['<b class="ref">' . e($f['ref_no']) . '</b>', ref_link('candidates', $f['candidate_id']), money($m['agreed']), money($m['paid']), '<b>' . money($m['balance']) . '</b>', $m['next'] ? e(dfmt($m['next'])) . ($late ? ' ' . badge('Overdue') : '') : '<span class="muted">No date</span>', e($f['promise_note']), e(user_name($f['reminder_owner'] ?: $f['hr_owner']))];
                $links[] = ent_url('inside_files', $f['id']);
            }
            echo '<div class="kv" style="margin:0 0 16px"><div class="hl"><span>Outstanding balance</span><b>' . money($tot) . '</b></div><div class="bad"><span>Overdue</span><b>' . money($over) . '</b></div><div><span>Files with balance</span><b>' . count($data) . '</b></div></div>';
            card_open(icon('clock', 17) . ' Balances due');
            echo simple_table(['File', 'Candidate', 'Agreed', 'Paid', 'Remaining', 'Due / promise date', 'Promise', 'Follow-up owner'], $data, 'No outstanding balances.', $links);
            card_close();
        }],
    ]]);

mod('fees', ['label' => 'Fees & Receipts', 'icon' => 'tag', 'area' => 'Inside Hiring', 'order' => 5, 'desc' => 'Named, versioned fee plans and receipts (RCT-). Refunds and waivers only by authorised reversal.',
    'tabs' => [
        'plans' => 'fee_plans',
        'receipts' => ['label' => 'Receipts', 'post' => function ($base, $mod) {
            if (g('a') === 'export') { require_action($mod, 'export'); $d = rows("SELECT p.*, f.ref_no AS file FROM payments p LEFT JOIN inside_files f ON f.id=p.entity_id WHERE p.entity='inside_files' ORDER BY p.id"); csv_out('receipts-' . date('Ymd') . '.csv', ['Receipt', 'File', 'Date', 'Type', 'Method', 'Amount', 'Reversed', 'Reason'], array_map(function ($r) { return [$r['ref_no'], $r['file'], $r['pay_date'], $r['pay_type'], $r['method'], $r['amount'], $r['reversed'] ? 'Yes' : '', $r['reason']]; }, $d), 'payments'); }
            if (is_post() && g('a') === 'reverse') { require_action($mod, 'finance'); $err = reverse_payment((int)p('pid'), p('reason')); flash($err ?: 'Payment reversed.', $err ? 'bad' : 'ok'); redirect($base); }
        }, 'fn' => function ($base, $mod) {
            $from = g('from', date('Y-m-01')); $to = g('to', today());
            $list = rows("SELECT p.*, f.ref_no AS file_ref, f.candidate_id FROM payments p LEFT JOIN inside_files f ON f.id=p.entity_id WHERE p.entity='inside_files' AND p.pay_date BETWEEN ? AND ? ORDER BY p.id DESC", [$from, $to]);
            echo '<form class="toolbar filters" method="get"><input type="hidden" name="t" value="receipts"><label class="small">From <input type="date" name="from" value="' . e($from) . '"></label><label class="small">To <input type="date" name="to" value="' . e($to) . '"></label><button class="btn ghost sm">Apply</button>' . (can($mod, 'export') ? '<a class="btn ghost sm" href="' . e(u($base, ['a' => 'export'])) . '">' . icon('download', 14) . ' Export</a>' : '') . '</form>';
            $data = []; $tot = 0;
            foreach ($list as $pm) {
                $tot += $pm['amount'];
                $rev = !$pm['reversed'] && !$pm['reversal_of'] && can($mod, 'finance') ? '<form method="post" action="' . e(u($base, ['a' => 'reverse'])) . '" class="inline-form" data-confirm="Reverse this receipt?">' . csrf_field() . '<input type="hidden" name="pid" value="' . $pm['id'] . '"><input name="reason" required placeholder="Reason" style="width:120px;padding:5px 8px"><button class="btn ghost sm danger">Reverse</button></form>' : '';
                $data[] = ['<b class="ref">' . e($pm['ref_no']) . '</b>', '<a href="' . e(ent_url('inside_files', $pm['entity_id'])) . '">' . e($pm['file_ref']) . '</a>', ref_link('candidates', $pm['candidate_id']), e(dfmt($pm['pay_date'])), e($pm['pay_type']), e($pm['method']), '<span class="num">' . money($pm['amount']) . '</span>', $pm['reversed'] ? badge('Reversed') : ($pm['reversal_of'] ? badge('Reversal') . '<br><small>' . e($pm['reason']) . '</small>' : badge('Valid')), e(user_name($pm['created_by'])), $rev];
            }
            card_open(icon('dollar', 17) . ' Receipts ' . e(dfmt($from)) . ' – ' . e(dfmt($to)), '<b>Net: ' . money($tot) . '</b>');
            echo simple_table(['Receipt', 'File', 'Candidate', 'Date', 'Type', 'Method', 'Amount', 'Status', 'By', ''], $data, 'No receipts in this period.');
            card_close();
        }],
        'recon' => ['label' => 'Reconciliation', 'fn' => function ($base, $mod) { inside_reconciliation(); }],
    ]]);

function inside_reconciliation()
{
    $from = g('from', date('Y-m-d', strtotime('-13 days')));
    $byDay = [];
    for ($d = strtotime($from); $d <= time(); $d += 86400) $byDay[date('d M', $d)] = 0;
    foreach (rows("SELECT pay_date, SUM(amount) s FROM payments WHERE entity='inside_files' AND direction='in' AND pay_date>=? GROUP BY pay_date", [$from]) as $r) $byDay[date('d M', strtotime($r['pay_date']))] = (float)$r['s'];
    $byOfficer = [];
    foreach (rows("SELECT f.hr_owner, SUM(p.amount) s FROM payments p JOIN inside_files f ON f.id=p.entity_id WHERE p.entity='inside_files' AND p.direction='in' GROUP BY f.hr_owner") as $r) $byOfficer[user_name($r['hr_owner']) ?: 'Unassigned'] = (float)$r['s'];
    $byType = [];
    foreach (rows("SELECT COALESCE(pay_type,'Other') t, SUM(amount) s FROM payments WHERE entity='inside_files' GROUP BY t") as $r) $byType[$r['t']] = (float)$r['s'];
    $collected = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE entity='inside_files' AND amount>0");
    $refunded = -(float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE entity='inside_files' AND amount<0");
    $due = 0; $over = 0;
    foreach (rows("SELECT * FROM inside_files WHERE status='Open' AND archived=0") as $f) { $b = file_money($f)['balance']; if ($b > 0) { $due += $b; if ($f['next_pay_date'] && $f['next_pay_date'] < today()) $over += $b; } }
    $disputed = (int)val("SELECT COUNT(*) FROM issues WHERE category='Payment Dispute' AND status IN ('Open','In Progress')");
    echo '<div class="kv" style="margin:0 0 16px"><div class="good"><span>Collected</span><b>' . money($collected) . '</b></div><div class="hl"><span>Due (open balances)</span><b>' . money($due) . '</b></div><div class="bad"><span>Overdue</span><b>' . money($over) . '</b></div><div class="warn"><span>Disputed (open issues)</span><b>' . $disputed . '</b></div><div><span>Refunded / reversed</span><b>' . money($refunded) . '</b></div></div>';
    echo '<div class="grid g2"><div class="card span2"><div class="card-h"><h3>' . icon('bar-chart', 17) . ' Collected per day (last 14 days)</h3></div>' . chart_cols($byDay) . '</div>';
    echo '<div class="card"><div class="card-h"><h3>By HR officer</h3></div>' . chart_bars($byOfficer, 'money') . '</div><div class="card"><div class="card-h"><h3>By payment type</h3></div>' . chart_donut($byType) . '</div></div>';
}

mod('recruitment', ['label' => 'Recruitment', 'icon' => 'briefcase', 'area' => 'Inside Hiring', 'order' => 6, 'desc' => 'Pipeline dashboard, offers, onboarding, assessments, interview rounds, job locations, custom questions and checklists.',
    'tabs' => ['pipeline' => ['label' => 'Pipeline', 'fn' => function ($base, $mod) {
        $by = [];
        foreach (CAND_STAGES as $s) $by[$s] = (int)val('SELECT COUNT(*) FROM candidates WHERE stage=? AND archived=0', [$s]);
        echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('layers', 17) . ' Candidates by stage</h3></div>' . chart_bars($by) . '</div>';
        $pos = [];
        foreach (rows('SELECT applied_position p, COUNT(*) n FROM candidates WHERE archived=0 GROUP BY p ORDER BY n DESC LIMIT 8') as $r) $pos[$r['p'] ?: '—'] = (int)$r['n'];
        echo '<div class="card"><div class="card-h"><h3>' . icon('briefcase', 17) . ' Top applied positions</h3></div>' . chart_bars($pos) . '</div></div>';
        echo '<h3 style="margin:18px 0 10px">HR vacancy board</h3>';
        vacancy_board();
    }], 'offers' => 'job_offers', 'onboarding' => 'onboardings', 'assessments' => 'candidate_assessments', 'rounds' => 'interview_rounds', 'joblocations' => 'job_locations', 'questions' => 'custom_questions', 'checklists' => 'checklist_items']]);

mod('marketing', ['label' => 'Marketing & Vacancies', 'icon' => 'target', 'area' => 'Sales & Marketing', 'order' => 1, 'desc' => 'Vacancy board, campaigns, posts, enquiries and lead-source performance.',
    'tabs' => ['board' => ['label' => 'Vacancy board', 'fn' => function ($base, $mod) { render_widgets(['marketing']); vacancy_board(); }], 'vacancies' => 'vacancies', 'campaigns' => 'campaigns', 'posts' => 'campaign_posts', 'enquiries' => 'enquiries',
        'performance' => ['label' => 'Performance', 'fn' => function ($base, $mod) { marketing_performance(); }],
        'sources' => ['label' => 'Candidate sources', 'fn' => function ($base, $mod) { candidate_sources_report(); }]]]);

/** Who sent the candidates (through whom) and from which source — per day / period. */
function candidate_sources_report()
{
    $from = g('from', date('Y-m-01'));
    $to = g('to', today());
    echo '<form class="toolbar filters" method="get">' . (g('t') ? '<input type="hidden" name="t" value="' . e(g('t')) . '">' : '') . (g('r') ? '<input type="hidden" name="r" value="' . e(g('r')) . '">' : '')
        . '<label class="small">From <input type="date" name="from" value="' . e($from) . '"></label><label class="small">To <input type="date" name="to" value="' . e($to) . '"></label><button class="btn ghost sm">Apply</button>'
        . '<a class="btn ghost sm" href="?' . e(http_build_query(array_filter(['t' => g('t'), 'r' => g('r'), 'from' => today(), 'to' => today()]))) . '">Today</a></form>';
    $p = [$from, $to . ' 23:59:59'];
    $files = "SUM(CASE WHEN EXISTS (SELECT 1 FROM inside_files f WHERE f.candidate_id=c.id) THEN 1 ELSE 0 END)";
    $placed = "SUM(CASE WHEN c.stage IN ('Selected','Placed') THEN 1 ELSE 0 END)";
    $bySrc = rows("SELECT COALESCE(NULLIF(c.source,''),'Unknown') AS k, COUNT(*) AS n, $files AS f, $placed AS pl FROM candidates c WHERE c.archived=0 AND c.created_at BETWEEN ? AND ? GROUP BY COALESCE(NULLIF(c.source,''),'Unknown') ORDER BY n DESC", $p);
    $byRef = rows("SELECT MIN(COALESCE(NULLIF(TRIM(c.through_whom),''),'Not given')) AS k, COUNT(*) AS n, $files AS f, $placed AS pl FROM candidates c WHERE c.archived=0 AND c.created_at BETWEEN ? AND ? GROUP BY LOWER(TRIM(COALESCE(c.through_whom,''))) ORDER BY n DESC", $p);
    $total = array_sum(array_column($bySrc, 'n'));
    echo '<div class="kv" style="margin:0 0 16px"><div class="hl"><span>Candidates in period</span><b>' . $total . '</b></div><div><span>Top source</span><b>' . e($bySrc[0]['k'] ?? '—') . '</b></div><div><span>Top referrer</span><b>' . e($byRef[0]['k'] ?? '—') . '</b></div><div class="good"><span>Files opened</span><b>' . array_sum(array_column($bySrc, 'f')) . '</b></div><div><span>Selected / placed</span><b>' . array_sum(array_column($bySrc, 'pl')) . '</b></div></div>';
    $days = [];
    for ($d = max(strtotime($from), strtotime($to . ' -13 days')); $d <= strtotime($to); $d += 86400) $days[date('d M', $d)] = (int)val('SELECT COUNT(*) FROM candidates WHERE archived=0 AND created_at BETWEEN ? AND ?', [date('Y-m-d', $d), date('Y-m-d', $d) . ' 23:59:59']);
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('bar-chart', 17) . ' Candidates per day</h3></div>' . chart_cols($days) . '</div><div class="card"><div class="card-h"><h3>' . icon('pie', 17) . ' By source</h3></div>' . chart_donut(array_column($bySrc, 'n', 'k')) . '</div></div>';
    $fmt = function ($r) use ($total) { return ['<b>' . e($r['k']) . '</b>', (int)$r['n'], $total ? round($r['n'] / $total * 100) . '%' : '—', (int)$r['f'], (int)$r['pl']]; };
    echo '<div class="grid g2" style="margin-top:16px"><div class="card flush"><div class="card-h"><h3>' . icon('user', 17) . ' Through whom (who sent the candidates)</h3></div>' . simple_table(['Referred / sent by', 'Candidates', 'Share', 'Files opened', 'Selected'], array_map($fmt, $byRef), 'No candidates in this period.') . '</div>'
        . '<div class="card flush"><div class="card-h"><h3>' . icon('target', 17) . ' By source</h3></div>' . simple_table(['Source', 'Candidates', 'Share', 'Files opened', 'Selected'], array_map($fmt, $bySrc), 'No candidates in this period.') . '</div></div>';
}

function vacancy_board()
{
    $v = rows("SELECT * FROM vacancies WHERE status='Open' AND approved=1 AND archived=0 AND (expiry_date IS NULL OR expiry_date>=?) ORDER BY CASE urgency WHEN 'Very Urgent' THEN 0 WHEN 'Urgent' THEN 1 ELSE 2 END, id DESC", [today()]);
    if (!$v) { echo '<div class="card empty">No open approved vacancies.</div>'; return; }
    echo '<div class="vac-grid">';
    foreach ($v as $x) {
        $leads = (int)val('SELECT COUNT(*) FROM enquiries WHERE vacancy_id=? AND archived=0', [$x['id']]);
        echo '<a class="vac" href="' . e(ent_url('vacancies', $x['id'])) . '" style="text-decoration:none"><div class="chips"><span class="chip ref">' . e($x['ref_no']) . '</span>' . badge($x['urgency']) . '</div><h4>' . e($x['position']) . '</h4><p><b>' . (int)$x['quantity'] . '</b> required · ' . e($x['location']) . '</p><p class="muted">' . e($x['salary_terms']) . '</p><p class="muted small">' . e($x['min_qualification']) . '</p><p class="small">' . $leads . ' leads · expires ' . e(dfmt($x['expiry_date']) ?: '—') . '</p></a>';
    }
    echo '</div>';
}
function marketing_performance()
{
    $posts = rows('SELECT * FROM campaign_posts WHERE archived=0 ORDER BY id DESC');
    $data = [];
    foreach ($posts as $p) {
        $e = rows('SELECT * FROM enquiries WHERE post_id=? AND archived=0', [$p['id']]);
        $cnt = function ($st) use ($e) { return count(array_filter($e, function ($x) use ($st) { return in_array($x['status'], $st, true); })); };
        $cands = array_filter(array_column($e, 'candidate_id'));
        $direct = array_column(rows('SELECT id FROM candidates WHERE source_post=? AND archived=0', [$p['id']]), 'id');
        $cands = array_unique(array_merge($cands, $direct));
        $files = $cands ? (int)val('SELECT COUNT(*) FROM inside_files WHERE candidate_id IN (' . implode(',', array_map('intval', $cands)) . ')') : 0;
        $placed = $cands ? (int)val("SELECT COUNT(*) FROM candidates WHERE id IN (" . implode(',', array_map('intval', $cands)) . ") AND stage IN ('Selected','Placed')") : 0;
        $data[] = ['<b class="ref">' . e($p['ref_no']) . '</b>', e($p['platform']), ref_link('campaigns', $p['campaign_id']), ref_link('vacancies', $p['vacancy_id']), e(user_name($p['marketer'])), count($e), $cnt(['Called', 'Invited', 'Visited', 'Interviewed', 'File Opened']), $cnt(['Invited', 'Visited', 'Interviewed', 'File Opened']), $cnt(['Visited', 'Interviewed', 'File Opened']), $files, $placed, money($p['spend']), count($e) ? money($p['spend'] / count($e)) : '—'];
    }
    card_open(icon('target', 17) . ' Leads, calls, invitations, visits, files and placements per post');
    echo simple_table(['Post', 'Platform', 'Campaign', 'Vacancy', 'Marketer', 'Leads', 'Calls', 'Invites', 'Visits', 'Files', 'Placed', 'Spend', 'Cost / lead'], $data, 'No posts recorded yet.');
    card_close();
    $bySrc = [];
    foreach (rows('SELECT source s, COUNT(*) n FROM enquiries WHERE archived=0 GROUP BY s') as $r) $bySrc[$r['s'] ?: 'Unknown'] = (int)$r['n'];
    $byPlat = [];
    foreach (rows('SELECT p.platform s, COUNT(e.id) n FROM campaign_posts p LEFT JOIN enquiries e ON e.post_id=p.id GROUP BY p.platform') as $r) $byPlat[$r['s']] = (int)$r['n'];
    $byMk = [];
    foreach (rows('SELECT p.marketer m, COUNT(e.id) n FROM campaign_posts p LEFT JOIN enquiries e ON e.post_id=p.id GROUP BY p.marketer') as $r) $byMk[user_name($r['m']) ?: 'Unassigned'] = (int)$r['n'];
    echo '<div class="grid g3"><div class="card"><div class="card-h"><h3>Leads by source</h3></div>' . chart_donut($bySrc) . '</div><div class="card"><div class="card-h"><h3>Leads by platform</h3></div>' . chart_bars($byPlat) . '</div><div class="card"><div class="card-h"><h3>Leads by marketer</h3></div>' . chart_bars($byMk) . '</div></div>';
}

// ---------------------------------------------------------------- widgets, search, reports

widget('reception', 'Today\'s entries', function () { return (int)val('SELECT COUNT(*) FROM candidates WHERE date(created_at)=? AND archived=0', [today()]); }, 'reception.php', 'user-plus', 'blue');
widget('reception', 'HR-assign queue', function () { return (int)val("SELECT COUNT(*) FROM candidates WHERE stage='New' AND archived=0"); }, 'reception.php', 'clock', 'amber');
widget('reception', 'Ready for deployment', function () { return (int)val("SELECT COUNT(*) FROM candidates WHERE stage='File Opened' AND archived=0"); }, 'reception.php', 'send', 'green');
widget('candidates', 'Active candidates', function () { return (int)val("SELECT COUNT(*) FROM candidates WHERE stage<>'Closed' AND archived=0"); }, 'candidates.php', 'users', 'blue');
widget('inside', 'Open inside files', function () { return (int)val("SELECT COUNT(*) FROM inside_files WHERE status='Open' AND archived=0"); }, 'inside.php', 'folder', 'violet');
widget('inside', 'Outstanding balances', function () { $t = 0; foreach (rows("SELECT * FROM inside_files WHERE status='Open' AND archived=0") as $f) $t += max(0, file_money($f)['balance']); return money($t); }, 'inside.php?t=dues', 'dollar', 'gold');
widget('deployment', 'In deployment', function () { return (int)val("SELECT COUNT(*) FROM candidates WHERE stage='In Deployment' AND archived=0"); }, 'deployment.php?t=pipeline', 'send', 'blue');
widget('deployment', 'Locations awaiting response', function () { return (int)val("SELECT COUNT(*) FROM interview_locations WHERE response='Pending' AND archived=0"); }, 'deployment.php?t=locations', 'map-pin', 'amber');
widget('deployment', 'Selected this month', function () { return (int)val("SELECT COUNT(*) FROM stage_history WHERE entity='candidates' AND to_stage='Selected' AND at>=?", [date('Y-m-01')]); }, 'deployment.php?t=pipeline', 'award', 'green');
widget('marketing', 'Open vacancies', function () { return (int)val("SELECT COUNT(*) FROM vacancies WHERE status='Open' AND archived=0"); }, 'marketing.php?t=vacancies', 'briefcase', 'blue');
widget('marketing', 'New enquiries', function () { return (int)val("SELECT COUNT(*) FROM enquiries WHERE status='New' AND archived=0"); }, 'marketing.php?t=enquiries', 'phone', 'gold');

search_provider('candidates', function ($s) {
    $o = [];
    foreach (rows('SELECT * FROM candidates WHERE archived=0 AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR ref_no LIKE ? OR passport_no LIKE ?) LIMIT 10', array_fill(0, 5, "%$s%")) as $r) $o[] = ['Candidate', $r['ref_no'] . ' · ' . $r['full_name'], $r['phone'] . ' · ' . $r['stage'], ent_url('candidates', $r['id'])];
    return $o;
});
search_provider('inside', function ($s) {
    $o = [];
    foreach (rows('SELECT f.*, c.full_name FROM inside_files f LEFT JOIN candidates c ON c.id=f.candidate_id WHERE f.archived=0 AND (f.ref_no LIKE ? OR c.full_name LIKE ?) LIMIT 8', ["%$s%", "%$s%"]) as $r) $o[] = ['Inside file', $r['ref_no'] . ' · ' . $r['full_name'], $r['status'], ent_url('inside_files', $r['id'])];
    foreach (rows("SELECT * FROM payments WHERE ref_no LIKE ? LIMIT 8", ["%$s%"]) as $r) { $u = ent_url($r['entity'], $r['entity_id']); $o[] = ['Receipt', $r['ref_no'] . ' · ' . money($r['amount']), $r['entity'] . ' · ' . $r['pay_date'], $u]; }
    return $o;
});

report_section('funnel', 'Inside hiring funnel', 'reports', function () {
    $st = [
        'Enquiries' => (int)val('SELECT COUNT(*) FROM enquiries WHERE archived=0'),
        'Invitations' => (int)val("SELECT COUNT(*) FROM enquiries WHERE status IN ('Invited','Visited','Interviewed','File Opened') AND archived=0"),
        'Office visits / registrations' => (int)val('SELECT COUNT(*) FROM candidates WHERE archived=0'),
        'HR interviews' => (int)val('SELECT COUNT(*) FROM candidates WHERE interview_status IS NOT NULL AND archived=0'),
        'Files opened' => (int)val('SELECT COUNT(*) FROM inside_files WHERE archived=0'),
        'Deployment interviews' => (int)val('SELECT COUNT(DISTINCT candidate_id) FROM interview_locations WHERE archived=0'),
        'Selections' => (int)val("SELECT COUNT(*) FROM candidates WHERE final_outcome='Selected' AND archived=0"),
        'Placements' => (int)val("SELECT COUNT(*) FROM candidates WHERE stage='Placed' AND archived=0"),
    ];
    $max = max(1, max($st));
    $prev = null;
    card_open(icon('filter', 17) . ' Conversion funnel');
    echo '<div class="funnel">';
    foreach ($st as $k => $v) { echo '<div><span>' . e($k) . '</span><span class="bar" style="--w:' . max(1, round($v / $max * 100)) . '%"></span><b>' . $v . '</b><small>' . ($prev ? round($v / max(1, $prev) * 100) . '%' : '') . '</small></div>'; $prev = $v; }
    echo '</div>';
    card_close();
    $avg = function ($a, $b) { return val("SELECT AVG(" . sql_days('a.at', 'b.at') . ") FROM stage_history a JOIN stage_history b ON a.entity=b.entity AND a.entity_id=b.entity_id WHERE a.entity='candidates' AND a.to_stage=? AND b.to_stage=?", [$a, $b]); };
    $fmt = function ($v) { return $v === null ? '—' : round($v, 1) . ' days'; };
    echo '<div class="kv"><div><span>Registration → HR interview</span><b>' . $fmt($avg('New', 'Interviewed') ?? $avg('HR Assigned', 'Interviewed')) . '</b></div><div><span>File opened → deployment</span><b>' . $fmt($avg('File Opened', 'In Deployment')) . '</b></div><div><span>File → selection (file-to-opportunity)</span><b>' . $fmt($avg('File Opened', 'Selected')) . '</b></div><div><span>Selection → closure</span><b>' . $fmt($avg('Selected', 'Closed')) . '</b></div></div>';
});
report_section('hr', 'HR productivity', 'reports', function () {
    $from = g('from', date('Y-m-01'));
    echo '<form class="filters" method="get" style="margin-bottom:12px"><input type="hidden" name="r" value="hr"><label class="small">From <input type="date" name="from" value="' . e($from) . '" data-autosubmit></label></form>';
    $data = [];
    foreach (staff_list() as $s) {
        $files = rows('SELECT * FROM inside_files WHERE hr_owner=? AND created_at>=? AND archived=0', [$s['id'], $from]);
        $intv = (int)val('SELECT COUNT(*) FROM candidates WHERE hr_officer=? AND interviewed_at>=?', [$s['id'], $from]);
        $assigned = (int)val('SELECT COUNT(*) FROM candidates WHERE hr_officer=? AND created_at>=?', [$s['id'], $from]);
        if (!$files && !$intv && !$assigned) continue;
        $value = array_sum(array_column($files, 'agreed_total'));
        $init = 0;
        foreach ($files as $f) $init += (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE entity='inside_files' AND entity_id=? AND pay_type='File Opening Advance'", [$f['id']]);
        $cash = 0;
        foreach ($files as $f) $cash += paid_total('inside_files', $f['id']);
        $rej = (int)val("SELECT COUNT(*) FROM candidates WHERE hr_officer=? AND interview_status='Rejected' AND interviewed_at>=?", [$s['id'], $from]);
        $absent = (int)val("SELECT COUNT(*) FROM candidates WHERE hr_officer=? AND interview_notes LIKE '%No show%' AND interviewed_at>=?", [$s['id'], $from]);
        $toDep = (int)val("SELECT COUNT(*) FROM stage_history h JOIN candidates c ON c.id=h.entity_id WHERE h.entity='candidates' AND h.to_stage='In Deployment' AND c.hr_officer=? AND h.at>=?", [$s['id'], $from]);
        $data[] = ['<b>' . e($s['name']) . '</b>', $assigned, $intv, $absent, count($files), $rej, $toDep, money($init), '<b>' . money($value) . '</b>', money($cash)];
    }
    card_open(icon('users', 17) . ' Per HR officer since ' . e(dfmt($from)), '<span class="muted small">Total value of files created is shown separately from actual cash collected</span>');
    echo simple_table(['HR officer', 'Assigned', 'Interviewed', 'Absent', 'Files created', 'Rejected', 'Referred to deployment', 'Initial payments', 'Value of files created', 'Cash collected'], $data, 'No HR activity in this period.');
    card_close();
    $by = function ($col) { $o = []; foreach (rows("SELECT $col k, COUNT(*) n FROM candidates WHERE archived=0 GROUP BY k ORDER BY n DESC LIMIT 8") as $r) $o[$r['k'] ?: '—'] = (int)$r['n']; return $o; };
    echo '<div class="grid g3"><div class="card"><div class="card-h"><h3>By position</h3></div>' . chart_bars($by('applied_position')) . '</div><div class="card"><div class="card-h"><h3>By nationality</h3></div>' . chart_bars($by('nationality')) . '</div><div class="card"><div class="card-h"><h3>By status</h3></div>' . chart_donut($by('stage')) . '</div></div>';
    $noNext = (int)val("SELECT COUNT(*) FROM inside_files f WHERE status='Open' AND archived=0 AND (next_pay_date IS NULL) AND agreed_total > (SELECT COALESCE(SUM(amount),0) FROM payments WHERE entity='inside_files' AND entity_id=f.id)");
    $missing = (int)val("SELECT COUNT(*) FROM documents WHERE status='Missing' AND candidate_id IS NOT NULL AND archived=0");
    $issues = (int)val("SELECT COUNT(*) FROM issues WHERE candidate_id IS NOT NULL AND status IN ('Open','In Progress') AND archived=0");
    echo '<div class="kv"><div class="warn"><span>Files with balance and no next action date</span><b>' . $noNext . '</b></div><div class="warn"><span>Missing candidate documents</span><b>' . $missing . '</b></div><div class="bad"><span>Open candidate issues</span><b>' . $issues . '</b></div><div><span>Pending file tasks</span><b>' . (int)val("SELECT COUNT(*) FROM tasks WHERE status<>'Done' AND link LIKE '%inside%'") . '</b></div></div>';
});
report_section('deployment', 'Deployment performance', 'reports', function () {
    $data = [];
    foreach (staff_list() as $s) {
        $n = (int)val('SELECT COUNT(*) FROM candidates WHERE deploy_officer=? AND archived=0', [$s['id']]);
        if (!$n) continue;
        $locs = (int)val('SELECT COUNT(*) FROM interview_locations l JOIN candidates c ON c.id=l.candidate_id WHERE c.deploy_officer=?', [$s['id']]);
        $sel = (int)val("SELECT COUNT(*) FROM candidates WHERE deploy_officer=? AND final_outcome='Selected'", [$s['id']]);
        $closed = (int)val("SELECT COUNT(*) FROM candidates WHERE deploy_officer=? AND stage='Closed'", [$s['id']]);
        $coll = (float)val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN inside_files f ON f.id=p.entity_id WHERE p.entity='inside_files' AND f.deploy_owner=? AND p.pay_type IN ('Deployment Payment','Balance After Selection')", [$s['id']]);
        $data[] = ['<b>' . e($s['name']) . '</b>', $n, $locs, $sel, $n ? round($sel / $n * 100) . '%' : '—', $closed, money($coll)];
    }
    card_open(icon('send', 17) . ' Per deployment officer');
    echo simple_table(['Officer', 'Candidates', 'Locations sent', 'Selected', 'Selection rate', 'Closed', 'Deployment collections'], $data, 'No deployment activity yet.');
    card_close();
    $resp = [];
    foreach (rows('SELECT response r, COUNT(*) n FROM interview_locations WHERE archived=0 GROUP BY r') as $r) $resp[$r['r']] = (int)$r['n'];
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>Interview location responses</h3></div>' . chart_donut($resp) . '</div>';
    $out = [];
    foreach (rows('SELECT final_outcome r, COUNT(*) n FROM candidates WHERE final_outcome IS NOT NULL GROUP BY r') as $r) $out[$r['r']] = (int)$r['n'];
    echo '<div class="card"><div class="card-h"><h3>Final outcomes</h3></div>' . chart_donut($out) . '</div></div>';
});
report_section('marketing', 'Marketing performance', 'reports', function () { marketing_performance(); });
report_section('sources', 'Candidate sources', 'reports', function () { candidate_sources_report(); });
report_section('journey', 'Candidate journey', 'reports', function () {
    $cid = (int)g('cid');
    echo '<form class="filters" method="get" style="margin-bottom:14px"><input type="hidden" name="r" value="journey"><select name="cid" data-autosubmit style="min-width:280px"><option value="">— Choose a candidate —</option>';
    foreach (rows('SELECT id, ref_no, full_name FROM candidates WHERE archived=0 ORDER BY id DESC LIMIT 500') as $c) echo '<option value="' . $c['id'] . '"' . ($cid === (int)$c['id'] ? ' selected' : '') . '>' . e($c['ref_no'] . ' · ' . $c['full_name']) . '</option>';
    echo '</select></form>';
    $c = $cid ? row('SELECT * FROM candidates WHERE id=?', [$cid]) : null;
    if (!$c) return;
    $f = candidate_file($cid);
    if ($c['stage'] === 'Closed' && $f) echo '<div class="banner">' . icon('lock', 20) . ' File closed · ' . e($f['close_reason']) . '</div>';
    card_open(icon('activity', 17) . ' ' . e($c['full_name']) . ' · ' . e($c['ref_no']), '<a class="btn ghost sm" href="' . e(ent_url('candidates', $cid)) . '">Open candidate</a>');
    candidate_journey($c);
    card_close();
    card_open(icon('clock', 17) . ' Stage history');
    echo simple_table(['When', 'From', 'To', 'Reason', 'By'], array_map(function ($h) { return [e(dtfmt($h['at'])), e($h['from_stage'] ?: '—'), badge($h['to_stage']), e($h['reason']), e($h['by_name'])]; }, rows("SELECT * FROM stage_history WHERE entity='candidates' AND entity_id=? ORDER BY id", [$cid])));
    card_close();
});
report_section('docs', 'Document & visa tracking', 'reports', function () {
    $soon = date('Y-m-d', strtotime('+60 days'));
    $docs = rows("SELECT * FROM documents WHERE archived=0 AND (status='Missing' OR (expiry IS NOT NULL AND expiry<=?)) ORDER BY expiry", [$soon]);
    card_open(icon('file-text', 17) . ' Missing or expiring documents (next 60 days)');
    echo simple_table(['Document', 'Candidate', 'Worker', 'Employee', 'Status', 'Expiry'], array_map(function ($d) { return [e($d['doc_type']), ref_link('candidates', $d['candidate_id']), ref_link('workers', $d['worker_id']), ref_link('employees', $d['employee_id']), badge($d['status']), e(dfmt($d['expiry']))]; }, $docs), 'Nothing missing or expiring.', array_map(function ($d) { return ent_url('documents', $d['id']); }, $docs));
    card_close();
    $visa = rows("SELECT * FROM candidates WHERE archived=0 AND stage<>'Closed' AND visa_expiry IS NOT NULL AND visa_expiry<=? ORDER BY visa_expiry", [$soon]);
    card_open(icon('id', 17) . ' Candidate visas expiring');
    echo simple_table(['Candidate', 'Visa type', 'Expiry', 'Stage'], array_map(function ($c) { return [e($c['ref_no'] . ' · ' . $c['full_name']), e($c['visa_type']), e(dfmt($c['visa_expiry'])) . ($c['visa_expiry'] < today() ? ' ' . badge('Expired') : ''), badge($c['stage'])]; }, $visa), 'No candidate visas expiring.', array_map(function ($c) { return ent_url('candidates', $c['id']); }, $visa));
    card_close();
    $wv = rows("SELECT * FROM workers WHERE archived=0 AND (visa_status IN ('Applied','Not Started') OR (visa_expiry IS NOT NULL AND visa_expiry<=?)) ORDER BY visa_expiry", [$soon]);
    card_open(icon('globe', 17) . ' Outside workers — visa pending or expiring');
    echo simple_table(['Worker', 'Demand', 'Visa status', 'Visa expiry'], array_map(function ($w) { return [e($w['ref_no'] . ' · ' . $w['full_name']), ref_link('demands', $w['demand_id']), badge($w['visa_status']), e(dfmt($w['visa_expiry']))]; }, $wv), 'No worker visas pending.', array_map(function ($w) { return ent_url('workers', $w['id']); }, $wv));
    card_close();
});

// ---------------------------------------------------------------- demo data

seeder(function () {
    $u = function ($n) { return (int)val('SELECT id FROM users WHERE username=?', [$n]); };
    $now = now();
    $base = ['created_at' => $now, 'archived' => 0, 'created_by' => $u('reception')];
    $plan1 = ins('fee_plans', $base + ['name' => 'Standard Inside File', 'version' => 1, 'currency' => 'AED', 'total' => 3500, 'advance' => 1000, 'balance_trigger' => 'On selection', 'due_days' => 7, 'terms' => 'AED 1,000 is paid when HR opens the file. The remaining AED 2,500 is payable within 7 days after selection.', 'active' => 1]);
    ins('fee_plans', $base + ['name' => 'Premium Placement', 'version' => 2, 'currency' => 'AED', 'total' => 5000, 'advance' => 1500, 'balance_trigger' => 'On selection', 'due_days' => 10, 'terms' => 'Premium placement with priority interviews.', 'active' => 1]);
    ins('fee_plans', $base + ['name' => 'Driver Package', 'version' => 1, 'currency' => 'AED', 'total' => 2500, 'advance' => 800, 'balance_trigger' => 'On selection', 'due_days' => 7, 'terms' => 'For light and heavy drivers.', 'active' => 1]);
    $t1 = ins('deployment_teams', $base + ['name' => 'Team Alpha', 'leader' => $u('deploy'), 'members' => (string)$u('deploy'), 'notes' => 'Construction and facilities placements']);
    ins('deployment_teams', $base + ['name' => 'Team Hospitality', 'leader' => $u('deploy'), 'members' => (string)$u('deploy'), 'notes' => 'Hotels and restaurants']);
    foreach (['HR screening', 'Trade test', 'Client interview'] as $i => $n) ins('interview_rounds', $base + ['name' => $n, 'sort' => $i + 1]);
    foreach ([['Dubai Investment Park', 'Dubai'], ['Al Quoz Industrial 3', 'Dubai'], ['Musaffah', 'Abu Dhabi']] as $j) ins('job_locations', $base + ['name' => $j[0], 'emirate' => $j[1]]);
    foreach ([['Passport copy received', 'File opening'], ['Two passport photos', 'File opening'], ['Medical fitness', 'Deployment'], ['Signed offer letter', 'Onboarding']] as $c) ins('checklist_items', $base + ['name' => $c[0], 'stage' => $c[1], 'required' => 1]);
    ins('custom_questions', $base + ['question' => 'Do you hold a valid UAE driving licence?', 'applies_to' => 'Driver', 'answer_type' => 'Yes / No', 'required' => 1]);

    $camp = ins('campaigns', $base + ['name' => 'November Hiring Drive', 'platform' => 'Facebook', 'start_date' => date('Y-m-01'), 'budget' => 2500, 'owner' => $u('marketing'), 'status' => 'Running', 'business_line' => 'Marketing']);
    $vac = [];
    foreach ([['Heavy Driver', 12, 'Dubai', 'Urgent', 'AED 3,000 + accommodation'], ['Security Guard', 20, 'Abu Dhabi', 'Normal', 'AED 2,200 + transport'], ['Cook', 4, 'Dubai', 'Very Urgent', 'AED 2,800 + food'], ['Electrician', 6, 'Sharjah', 'Normal', 'AED 2,600']] as $v)
        $vac[] = ins('vacancies', $base + ['ref_no' => next_number('IN-REQ'), 'position' => $v[0], 'quantity' => $v[1], 'location' => $v[2], 'urgency' => $v[3], 'salary_terms' => $v[4], 'min_qualification' => 'Relevant experience, valid passport', 'open_date' => date('Y-m-d', strtotime('-10 days')), 'expiry_date' => date('Y-m-d', strtotime('+25 days')), 'status' => 'Open', 'approved' => 1, 'business_line' => 'Inside Hiring']);
    $posts = [];
    foreach ([['Facebook', 0, 650], ['Instagram', 1, 400], ['TikTok', 2, 300]] as $p)
        $posts[] = ins('campaign_posts', $base + ['ref_no' => next_number('POST'), 'vacancy_id' => $vac[$p[1]], 'campaign_id' => $camp, 'platform' => $p[0], 'account_page' => 'RH Management Jobs', 'post_link' => 'https://example.com/post/' . rand(1000, 9999), 'posted_on' => date('Y-m-d', strtotime('-8 days')), 'creative' => 'v1', 'marketer' => $u('marketing'), 'spend' => $p[2]]);
    foreach ([['Hamza Tariq', '0501234501', 0, 'Invited'], ['Ravi Kumar', '0501234502', 0, 'Called'], ['Joseph Mensah', '0501234503', 1, 'New'], ['Sajid Mehmood', '0501234504', 2, 'Visited'], ['Peter Otieno', '0501234505', 1, 'No Response']] as $e)
        ins('enquiries', $base + ['ref_no' => next_number('ENQ'), 'name' => $e[0], 'phone' => $e[1], 'vacancy_id' => $vac[$e[2]], 'post_id' => $posts[$e[2]], 'source' => ['Facebook', 'Instagram', 'TikTok'][$e[2]], 'category' => 'Job Enquiry', 'status' => $e[3], 'assigned_to' => $u('marketing')]);

    $mk = function ($name, $phone, $email, $nat, $pos, $hr, $stage, $extra = []) use ($u, $base, $posts) {
        $id = ins('candidates', $base + ['ref_no' => next_number('CAN'), 'full_name' => $name, 'phone' => $phone, 'email' => $email, 'nationality' => $nat, 'applied_position' => $pos, 'expected_salary' => 2500, 'visa_type' => 'Visit',
            'visa_expiry' => date('Y-m-d', strtotime('+40 days')), 'through_whom' => 'Walk-in', 'source' => 'Facebook', 'source_post' => $posts[0], 'qualification' => 'Secondary school', 'experience' => '3 years in the UAE', 'skills' => 'Team work, punctual',
            'cv_file' => 'cv/sample-cv.pdf', 'hr_officer' => $hr ? $u($hr) : null, 'stage' => 'New'] + $extra);
        ins('stage_history', ['entity' => 'candidates', 'entity_id' => $id, 'to_stage' => 'New', 'reason' => 'Registered at Reception', 'by_name' => 'Ayesha Noor', 'at' => date('Y-m-d H:i:s', strtotime('-9 days'))]);
        foreach (CAND_STAGES as $s) { if ($s === 'New') continue; if (array_search($s, CAND_STAGES) > array_search($stage, CAND_STAGES)) break; stage_advance('candidates', $id, $s, 'Demo data'); }
        return $id;
    };
    // demo candidate from Appendix C
    $ali = $mk('Ali Khan', '0521112233', 'ali.khan@example.com', 'Pakistan', 'Heavy Driver', 'hr', 'Interviewed', ['interview_status' => 'Selected', 'interview_notes' => 'Strong driving record, GCC licence.', 'interviewed_at' => $now, 'passport_no' => 'AB1234567']);
    $fid = create_inside_file($ali, $plan1, 3500, 1000, ['pay_method' => 'Cash', 'next_pay_date' => date('Y-m-d', strtotime('+12 days'))]);
    q('UPDATE inside_files SET hr_owner=? WHERE id=?', [$u('hr'), $fid]);
    q('UPDATE candidates SET team_id=?, deploy_officer=? WHERE id=?', [$t1, $u('deploy'), $ali]);
    stage_advance('candidates', $ali, 'In Deployment', 'Assigned to Team Alpha');
    candidate_portal_login($ali);
    q('UPDATE candidate_logins SET password_hash=? WHERE candidate_id=?', [password_hash('Candidate@123', PASSWORD_DEFAULT), $ali]);
    ins('interview_locations', $base + ['candidate_id' => $ali, 'seq' => 1, 'company' => 'Emirates Transport Co.', 'position' => 'Heavy Driver', 'location' => 'Al Qusais Depot, Dubai', 'map_link' => 'https://maps.google.com/?q=Al+Qusais', 'interview_at' => date('Y-m-d 10:00:00', strtotime('-2 days')), 'contact' => 'Mr. Rashid · 04 000 0000', 'instructions' => 'Bring original licence and passport copy.', 'response' => 'Not Selected', 'response_note' => 'They needed 5 years UAE experience.', 'responded_at' => $now]);
    ins('interview_locations', $base + ['candidate_id' => $ali, 'seq' => 2, 'company' => 'Gulf Logistics LLC', 'position' => 'Heavy Driver', 'location' => 'Jebel Ali Free Zone, Gate 5', 'interview_at' => date('Y-m-d 09:30:00', strtotime('+1 day')), 'contact' => 'Ms. Leena · 04 111 1111', 'instructions' => 'Report at 9:00.', 'response' => 'Pending']);
    ins('chat_messages', ['candidate_id' => $ali, 'sender_type' => 'Staff', 'sender_id' => $u('deploy'), 'body' => 'Welcome Ali! I am your deployment officer. Please keep your passport and licence ready for the interview.', 'read_at' => $now, 'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')), 'archived' => 0]);
    ins('chat_messages', ['candidate_id' => $ali, 'sender_type' => 'Candidate', 'sender_id' => $ali, 'body' => 'Thank you. Can I pay the balance in two parts?', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'archived' => 0]);
    ins('documents', $base + ['ref_no' => next_number('DOC'), 'doc_type' => 'Passport', 'candidate_id' => $ali, 'doc_no' => 'AB1234567', 'status' => 'Verified', 'expiry' => date('Y-m-d', strtotime('+3 years'))]);
    ins('documents', $base + ['ref_no' => next_number('DOC'), 'doc_type' => 'Driving Licence', 'candidate_id' => $ali, 'status' => 'Missing']);

    $c2 = $mk('Bilal Ahmed', '0551230001', 'bilal.ahmed@example.com', 'Pakistan', 'Electrician', 'hr', 'HR Assigned');
    $c3 = $mk('Suresh Nair', '0551230002', 'suresh.n@example.com', 'India', 'Cook', null, 'New');
    $c4 = $mk('Kwame Asante', '0551230003', null, 'Ghana', 'Security Guard', 'hr', 'Interviewed', ['interview_status' => 'On Hold', 'interview_notes' => 'Needs height verification.', 'interviewed_at' => $now]);
    $c5 = $mk('Maria Santos', '0551230004', 'maria.santos@example.com', 'Philippines', 'Housemaid', 'hr', 'Interviewed', ['interview_status' => 'Selected', 'interviewed_at' => $now]);
    $f5 = create_inside_file($c5, $plan1, 3500, 1000, ['pay_method' => 'Bank Transfer', 'next_pay_date' => date('Y-m-d', strtotime('-2 days'))]);
    record_payment('inside_files', $f5, 500, ['method' => 'Cash', 'pay_type' => 'Instalment', 'pay_date' => date('Y-m-d', strtotime('-1 day'))]);
    $c6 = $mk('Dawit Bekele', '0551230005', 'dawit.b@example.com', 'Ethiopia', 'General Helper', 'hr', 'Interviewed', ['interview_status' => 'Selected', 'interviewed_at' => $now]);
    $f6 = create_inside_file($c6, $plan1, 3500, 1000, ['pay_method' => 'Cash']);
    q('UPDATE candidates SET team_id=?, deploy_officer=?, final_outcome=?, final_reason=? WHERE id=?', [$t1, $u('deploy'), 'Selected', 'Selected by Al Futtaim Facilities', $c6]);
    stage_advance('candidates', $c6, 'In Deployment', 'Demo');
    stage_advance('candidates', $c6, 'Selected', 'Selected by Al Futtaim Facilities');
    record_payment('inside_files', $f6, 2500, ['method' => 'Cash', 'pay_type' => 'Balance After Selection']);
    $c7 = $mk('Rahim Uddin', '0551230006', null, 'Bangladesh', 'Mason', 'hr', 'Interviewed', ['interview_status' => 'Rejected', 'interviewed_at' => $now, 'file_decision' => 'No', 'decision_reason' => 'Will come later']);
    $c8 = $mk('Grace Achieng', '0551230007', 'grace.a@example.com', 'Kenya', 'Waiter', 'hr', 'Interviewed', ['interview_status' => 'Selected', 'interviewed_at' => $now]);
    $f8 = create_inside_file($c8, $plan1, 3500, 1000, ['pay_method' => 'Cash']);
    q("UPDATE inside_files SET status='Closed', outcome='Withdrawn', close_reason='Withdrawn — candidate travelled home', closed_at=? WHERE id=?", [$now, $f8]);
    stage_advance('candidates', $c8, 'Closed', 'Withdrawn — candidate travelled home');
    ins('issues', $base + ['ref_no' => next_number('ISS'), 'subject' => 'Candidate asks to split balance', 'party' => 'Candidate', 'candidate_id' => $ali, 'category' => 'Payment Dispute', 'severity' => 'Medium', 'owner' => $u('finance'), 'due_date' => date('Y-m-d', strtotime('+2 days')), 'status' => 'Open', 'details' => 'Ali Khan wants to pay AED 2,500 in two instalments.']);
    add_task('Collect driving licence copy from Ali Khan', 'Medium', date('Y-m-d', strtotime('+1 day')), $u('deploy'), 'candidates.php?a=view&id=' . $ali, 'demo-1');
    add_task('Follow up Maria Santos overdue balance', 'High', date('Y-m-d', strtotime('-1 day')), $u('hr'), 'inside.php?a=view&id=' . $f5, 'demo-2');
    ins('candidate_assessments', $base + ['candidate_id' => $ali, 'type' => 'Driving Test', 'assessed_on' => today(), 'assessor' => $u('hr'), 'score' => 88, 'result' => 'Pass', 'feedback' => 'Good control of heavy vehicle.']);
    ins('job_offers', $base + ['ref_no' => next_number('OFR'), 'candidate_id' => $c6, 'company' => 'Al Futtaim Facilities', 'position' => 'General Helper', 'salary' => 1800, 'status' => 'Accepted', 'sent_on' => today()]);
}, 20, true);
