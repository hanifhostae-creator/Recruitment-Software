<?php
// Module 2 — Outside Hiring: client companies, demands, agents, batches, workers, outside operations.

const WORKER_STAGES = ['Submitted', 'Interviewed', 'Selected', 'Documentation', 'Visa Processing', 'Ready to Mobilise', 'Joined'];

ent('companies', ['notify_new' => [null], 'label' => 'Client Companies', 'one' => 'Company', 'prefix' => 'CO', 'title' => 'display_name', 'icon' => 'building', 'dupe' => ['legal_name'],
    'fields' => [
        'legal_name' => ['Legal name', 'text', 'req'], 'display_name' => ['Display name', 'text', 'req'], 'industry' => ['Industry', 'opt:industries'],
        'branches' => ['Branches and sites', 'textarea'], 'contact_person' => ['Main contact', 'text'], 'phone' => ['Phone', 'phone'], 'email' => ['Email', 'email'], 'address' => ['Address', 'text'],
        'contract_details' => ['Contract details', 'textarea'], 'account_manager' => ['Account manager', 'user'], 'billing_terms' => ['Billing terms', 'text'],
        'approved_roles' => ['Approved roles', 'text'], 'service_terms' => ['Service terms', 'textarea'], 'status' => ['Status', 'enum:Prospect|Active|On Hold|Inactive', 'req', 'default' => 'Active'],
    ],
    'list' => ['ref_no', 'display_name', 'industry', 'contact_person', 'account_manager', 'status'], 'filters' => ['status', 'industry', 'account_manager'],
    'cols' => ['Open demands' => function ($r) { return (int)val("SELECT COUNT(*) FROM demands WHERE company_id=? AND status NOT IN ('Closed','Cancelled') AND archived=0", [$r['id']]); }],
    'view' => function ($r) { company_dashboard($r); },
]);
ent('client_users', ['label' => 'Client Portal Users', 'one' => 'Client user', 'title' => 'username', 'icon' => 'login', 'related' => false,
    'fields' => ['company_id' => ['Client company', 'ref:companies', 'req'], 'name' => ['Name', 'text', 'req'], 'username' => ['Username', 'text', 'req'], 'password' => ['Password', 'password'], 'email' => ['Email', 'email'], 'active' => ['Active', 'bool', 'default' => 1]],
    'list' => ['username', 'name', 'company_id', 'email', 'active'],
    'actions' => ['resetlink' => ['label' => 'Send password reset link', 'icon' => 'lock', 'perm' => 'edit',
        'form' => function ($r) { echo '<p>An email with a secure link (valid 60 minutes) is sent to <b>' . e($r['email'] ?: 'no email address on file') . '</b>. The link is also shown on screen for you to share.</p><div class="form-actions"><button class="btn">Send reset link</button></div>'; },
        'do' => function ($r) { list($url, $em, $addr) = reset_send('client', $r['id'], me()['id'], 'An administrator has sent you a password reset link for your RH Management account.'); return ['msg' => ($em ? 'Reset link emailed to ' . e($addr) . '. ' : 'No email sent (' . ($addr ? e($GLOBALS['mail_error'] ?? 'email failed') : 'no email address on this account') . '). ') . 'Link (valid 60 minutes): <a href="' . e($url) . '">' . e($url) . '</a>']; }]],
    'before_save' => function (&$d, $old) { $d['username'] = strtolower(trim($d['username'])); if (val('SELECT id FROM client_users WHERE lower(username)=? AND id<>?', [$d['username'], $old['id'] ?? 0])) return 'This username is already used.'; return null; }]);

ent('demands', ['notify_new' => ['Outside Operations', 'approval'], 'label' => 'Demands', 'one' => 'Demand', 'prefix' => 'DEM', 'title' => 'position', 'title_ref' => true, 'icon' => 'clipboard', 'bl' => 'Outside Hiring',
    'fields' => [
        'company_id' => ['Client company', 'ref:companies', 'req'], 'position' => ['Job position', 'opt:positions', 'req'], 'quantity' => ['Quantity requested', 'int', 'req'],
        'job_description' => ['Job description', 'textarea'], 'skills' => ['Skills and experience', 'textarea'], 'salary' => ['Salary (AED)', 'money'], 'benefits' => ['Benefits and terms', 'text'],
        'work_location' => ['Work location', 'text'], 'deadline' => ['Deadline', 'date'], 'interview_req' => ['Interview, test and training requirements', 'textarea'], 'documents_req' => ['Required documents', 'textarea'],
        'contract_po' => ['Contract or PO reference', 'text'], 'priority' => ['Priority', 'enum:Low|Normal|High|Urgent', 'default' => 'Normal'], 'owner' => ['Demand owner', 'user'],
        'status' => ['Status', 'enum:Pending Approval|Approved|In Progress|Closed|Cancelled', 'hide'], 'approved_qty' => ['Approved quantity', 'int', 'hide'], 'replacement_policy' => ['Replacement policy', 'text', 'hide'],
        'rate' => ['Fee / rate per worker', 'money', 'hide'], 'contract_version' => ['Contract version & amendments', 'textarea', 'hide'], 'approved_by' => ['Approved by', 'user', 'hide'], 'approved_at' => ['Approved at', 'datetime', 'hide'],
        'closure_note' => ['Closure categorisation', 'textarea', 'hide'], 'season' => ['Season / batch period', 'text'],
    ],
    'list' => ['ref_no', 'company_id', 'position', 'quantity', 'deadline', 'priority', 'status'], 'filters' => ['status', 'company_id', 'priority', 'owner'],
    'cols' => ['Progress' => function ($r) { $p = demand_progress($r); return progress($p['requested'] ? $p['joined'] / $p['requested'] * 100 : 0, $p['joined'] . ' / ' . $p['requested'] . ' joined'); }],
    'before_save' => function (&$d, $old) { if (!$old) $d['status'] = 'Pending Approval'; if (isset($d['quantity']) && $d['quantity'] < 1) return 'Quantity must be at least 1.'; return null; },
    'after_save' => function ($id, $d, $old) { if (!$old) add_task('Approve new demand: ' . ($d['position'] ?? ''), 'High', date('Y-m-d', strtotime('+1 day')), $d['owner'] ?? null, ent_url('demands', $id), 'dem-approve-' . $id); },
    'can_edit' => function ($r) { return !in_array($r['status'], ['Closed', 'Cancelled'], true); },
    'view' => function ($r) { demand_panel($r); },
    'actions' => [
        'approve' => ['label' => 'Approve', 'icon' => 'check-circle', 'perm' => 'approve', 'show' => function ($r) { return $r['status'] === 'Pending Approval'; },
            'form' => function ($r) { echo '<div class="grid-form"><div class="fld"><label>Approved quantity *</label><input type="number" name="approved_qty" required min="1" value="' . e($r['quantity']) . '"></div><div class="fld"><label>Fee / rate per worker</label><input type="number" step="0.01" name="rate"></div><div class="fld"><label>Replacement policy *</label><input name="replacement_policy" required value="Free replacement within 90 days"></div><div class="fld"><label>Contract version</label><input name="contract_version" value="v1"></div></div><div class="form-actions"><button class="btn ok">Approve demand</button></div>'; },
            'do' => function ($r) {
                if ((int)p('approved_qty') < 1 || !p('replacement_policy')) return 'Approved quantity and replacement policy are required.';
                upd('demands', $r['id'], ['approved_qty' => (int)p('approved_qty'), 'rate' => p('rate') === '' ? null : (float)p('rate'), 'replacement_policy' => p('replacement_policy'), 'contract_version' => p('contract_version'), 'approved_by' => me()['id'], 'approved_at' => now(), 'updated_at' => now()]);
                stage_advance('demands', $r['id'], 'Approved', 'Approved quantity ' . (int)p('approved_qty'), 'status');
                q("UPDATE tasks SET status='Done' WHERE auto_key=?", ['dem-approve-' . $r['id']]);
                return ['msg' => 'Demand approved. Recruitment can start.'];
            }],
        'amend' => ['label' => 'Amendment', 'icon' => 'edit', 'perm' => 'approve', 'show' => function ($r) { return in_array($r['status'], ['Approved', 'In Progress'], true); },
            'form' => function ($r) { echo '<div class="grid-form"><div class="fld"><label>New approved quantity</label><input type="number" name="approved_qty" value="' . e($r['approved_qty']) . '"></div><div class="fld"><label>Contract version *</label><input name="version" required></div><div class="fld wide"><label>Amendment detail *</label><textarea name="detail" required rows="2"></textarea></div></div><div class="form-actions"><button class="btn">Record amendment</button></div>'; },
            'do' => function ($r) { if (!p('version') || !p('detail')) return 'Version and detail are required.'; $hist = trim($r['contract_version'] . "\n" . date('Y-m-d') . ' ' . p('version') . ': ' . p('detail')); upd('demands', $r['id'], ['approved_qty' => (int)p('approved_qty') ?: $r['approved_qty'], 'contract_version' => $hist, 'updated_at' => now()]); audit('amend', 'demands', $r['id'], ['approved_qty' => $r['approved_qty']], ['approved_qty' => (int)p('approved_qty'), 'version' => p('version')], p('detail')); return null; }],
        'close' => ['label' => 'Close demand', 'icon' => 'lock', 'perm' => 'approve', 'show' => function ($r) { return in_array($r['status'], ['Approved', 'In Progress'], true); }, 'confirm' => 'Close this demand? Its full history is kept.',
            'form' => function ($r) {
                $p = demand_progress($r);
                $open = (int)val("SELECT COUNT(*) FROM workers WHERE demand_id=? AND archived=0 AND (slot_outcome IS NULL OR slot_outcome='Open')", [$r['id']]);
                $dep = (int)val("SELECT COUNT(*) FROM workers WHERE demand_id=? AND archived=0 AND slot_outcome='Deployed'", [$r['id']]);
                echo '<p>Every requested slot must be categorised before the demand closes. Requested: <b>' . $p['requested'] . '</b> · deployed: <b>' . $dep . '</b> · remaining slots to categorise: <b>' . max(0, $p['requested'] - $dep) . '</b>.</p>';
                if ($open) echo '<div class="alert bad">' . $open . ' submitted worker(s) still have the slot outcome "Open". Categorise them on the worker record first (deployed, cancelled, unfilled, rejected, withdrawn or resolved).</div>';
                echo '<div class="grid-form g3"><div class="fld"><label>Cancelled slots</label><input type="number" min="0" name="cancelled" value="0"></div><div class="fld"><label>Unfilled slots</label><input type="number" min="0" name="unfilled" value="' . max(0, $p['requested'] - $dep) . '"></div><div class="fld"><label>Withdrawn / otherwise resolved</label><input type="number" min="0" name="resolved" value="0"></div><div class="fld wide"><label>Closure note *</label><input name="note" required></div></div><div class="form-actions"><button class="btn danger">Close demand</button></div>';
            },
            'do' => function ($r) {
                $p = demand_progress($r);
                if ((int)val("SELECT COUNT(*) FROM workers WHERE demand_id=? AND archived=0 AND (slot_outcome IS NULL OR slot_outcome='Open')", [$r['id']])) return 'Some workers are not categorised yet.';
                $dep = (int)val("SELECT COUNT(*) FROM workers WHERE demand_id=? AND archived=0 AND slot_outcome='Deployed'", [$r['id']]);
                $sum = $dep + (int)p('cancelled') + (int)p('unfilled') + (int)p('resolved');
                if ($sum !== (int)$p['requested']) return 'Slots do not add up: deployed ' . $dep . ' + cancelled + unfilled + resolved = ' . $sum . ', but ' . $p['requested'] . ' were requested.';
                if (!p('note')) return 'A closure note is required.';
                $note = "Deployed: $dep, Cancelled: " . (int)p('cancelled') . ', Unfilled: ' . (int)p('unfilled') . ', Resolved/withdrawn: ' . (int)p('resolved') . ' — ' . p('note');
                upd('demands', $r['id'], ['closure_note' => $note, 'updated_at' => now()]);
                stage_advance('demands', $r['id'], 'Closed', $note, 'status');
                return ['msg' => 'Demand closed. Every slot is categorised.'];
            }],
    ],
]);

ent('agents', ['label' => 'Agents & Agencies', 'one' => 'Agent', 'prefix' => 'AG', 'title' => 'agency_name', 'icon' => 'globe', 'financial' => true,
    'fields' => [
        'agency_name' => ['Agency name', 'text', 'req'], 'agent_name' => ['Individual agent name', 'text'], 'phone' => ['Phone', 'phone'], 'email' => ['Email', 'email'],
        'location' => ['City / location', 'text'], 'country' => ['Country', 'opt:countries', 'req'], 'status' => ['Approval status', 'enum:Pending|Approved|Suspended', 'req', 'default' => 'Pending'],
        'agreement' => ['Agreement / contract', 'text'], 'agreement_file' => ['Agreement file', 'file', 'dir' => 'agents'], 'permitted_countries' => ['Permitted source countries', 'text'],
        'payment_terms' => ['Payment terms', 'text'], 'rh_owner' => ['Responsible RH owner', 'user'], 'compliance_notes' => ['Compliance and document notes', 'textarea'],
    ],
    'list' => ['ref_no', 'agency_name', 'agent_name', 'country', 'rh_owner', 'status'], 'filters' => ['status', 'country', 'rh_owner'],
    'cols' => ['Balance due' => function ($r) { $b = agent_balance($r['id']); return money($b['outstanding']); }],
    'view' => function ($r) { agent_panel($r); },
    'view_after' => function ($r, $mod, $base) { payments_panel('agents', $r['id'], $mod, $base); },
    'actions' => ['pay' => pay_action('agents', 'Pay agent', 'finance', null, 'out')],
]);
ent('agent_users', ['label' => 'Agent Portal Users', 'one' => 'Agent user', 'title' => 'username', 'icon' => 'login', 'related' => false,
    'fields' => ['agent_id' => ['Agent / agency', 'ref:agents', 'req'], 'name' => ['Name', 'text', 'req'], 'username' => ['Username', 'text', 'req'], 'password' => ['Password', 'password'], 'email' => ['Email', 'email'], 'active' => ['Active', 'bool', 'default' => 1]],
    'list' => ['username', 'name', 'agent_id', 'email', 'active'],
    'actions' => ['resetlink' => ['label' => 'Send password reset link', 'icon' => 'lock', 'perm' => 'edit',
        'form' => function ($r) { echo '<p>An email with a secure link (valid 60 minutes) is sent to <b>' . e($r['email'] ?: 'no email address on file') . '</b>. The link is also shown on screen for you to share.</p><div class="form-actions"><button class="btn">Send reset link</button></div>'; },
        'do' => function ($r) { list($url, $em, $addr) = reset_send('agent', $r['id'], me()['id'], 'An administrator has sent you a password reset link for your RH Management account.'); return ['msg' => ($em ? 'Reset link emailed to ' . e($addr) . '. ' : 'No email sent (' . ($addr ? e($GLOBALS['mail_error'] ?? 'email failed') : 'no email address on this account') . '). ') . 'Link (valid 60 minutes): <a href="' . e($url) . '">' . e($url) . '</a>']; }]],
    'before_save' => function (&$d, $old) { $d['username'] = strtolower(trim($d['username'])); if (val('SELECT id FROM agent_users WHERE lower(username)=? AND id<>?', [$d['username'], $old['id'] ?? 0])) return 'This username is already used.'; return null; }]);

ent('allocations', ['label' => 'Demand Allocations', 'one' => 'Allocation', 'prefix' => 'ALC', 'title' => 'demand_id', 'title_ref' => true, 'icon' => 'link',
    'fields' => ['demand_id' => ['Client demand', 'ref:demands', 'req'], 'agent_id' => ['Agent / agency', 'ref:agents', 'req'], 'target_qty' => ['Agreed target quantity', 'int', 'req'],
        'deadline' => ['Submission deadline', 'date'], 'fee_per_worker' => ['Agent fee per joined worker', 'money'], 'checklist' => ['Document checklist', 'textarea'], 'terms' => ['Terms', 'textarea'],
        'coordinator' => ['RH coordinator', 'user'], 'status' => ['Status', 'enum:Active|Completed|Cancelled', 'default' => 'Active']],
    'list' => ['ref_no', 'demand_id', 'agent_id', 'target_qty', 'deadline', 'coordinator', 'status'], 'filters' => ['status', 'agent_id', 'demand_id'],
    'cols' => ['Submitted' => function ($r) { return (int)val('SELECT COUNT(*) FROM workers WHERE demand_id=? AND agent_id=? AND archived=0', [$r['demand_id'], $r['agent_id']]) . ' / ' . (int)$r['target_qty']; }],
    'before_save' => function (&$d, $old) { $a = row('SELECT status FROM agents WHERE id=?', [$d['agent_id'] ?? 0]); if ($a && $a['status'] !== 'Approved') return 'Only approved agents can be allocated to a demand.'; $dm = row('SELECT status FROM demands WHERE id=?', [$d['demand_id'] ?? 0]); if ($dm && $dm['status'] === 'Pending Approval') return 'The demand must be approved before allocating agents.'; return null; },
    'after_save' => function ($id, $d, $old) { if (!$old) { $dm = row('SELECT * FROM demands WHERE id=?', [$d['demand_id']]); if ($dm && $dm['status'] === 'Approved') stage_advance('demands', $dm['id'], 'In Progress', 'Agent allocated', 'status'); } }]);

ent('batches', ['label' => 'Batches', 'one' => 'Batch', 'prefix' => 'BAT', 'icon' => 'layers',
    'fields' => ['agent_id' => ['Agent / agency', 'ref:agents', 'req'], 'demand_id' => ['Demand', 'ref:demands', 'req'], 'name' => ['Batch name', 'text'], 'submitted_on' => ['Submitted on', 'date', 'default' => date('Y-m-d')], 'notes' => ['Notes', 'textarea']],
    'list' => ['ref_no', 'name', 'agent_id', 'demand_id', 'submitted_on'], 'filters' => ['agent_id', 'demand_id'],
    'cols' => ['Workers' => function ($r) { return (int)val('SELECT COUNT(*) FROM workers WHERE batch_id=? AND archived=0', [$r['id']]); }]]);

ent('workers', ['label' => 'Workers', 'one' => 'Worker', 'prefix' => 'WRK', 'title' => 'full_name', 'icon' => 'users', 'dupe' => ['passport_no', 'phone'], 'log_view' => true,
    'fields' => [
        'full_name' => ['Full name', 'text', 'req'], 'passport_no' => ['Passport number', 'text', 'req'], 'nationality' => ['Nationality', 'opt:nationalities', 'req'], 'phone' => ['Phone', 'phone'],
        'demand_id' => ['Demand', 'ref:demands', 'req'], 'agent_id' => ['Agent / agency (blank = direct)', 'ref:agents'], 'batch_id' => ['Batch', 'ref:batches'], 'position' => ['Position', 'opt:positions'],
        'submitted_on' => ['Submission date', 'date', 'default' => date('Y-m-d')], 'submitted_by' => ['Submitted by', 'text'], 'cv_file' => ['CV / profile', 'file', 'dir' => 'cv'], 'profile_version' => ['Profile version', 'text', 'default' => 'v1'],
        'client_response' => ['Client response', 'enum:Pending|Shortlisted|Interview Requested|Rejected', 'default' => 'Pending'],
        'stage' => ['Stage', 'enum:' . implode('|', WORKER_STAGES), 'hide'],
        'result' => ['Selection result', 'enum:Pending|Selected|Rejected|On Hold|Alternate Position', 'hide'], 'result_reason' => ['Decision reason / client feedback', 'text', 'hide'], 'decision_by' => ['Decision maker', 'text', 'hide'], 'decision_on' => ['Decision date', 'date', 'hide'], 'next_action' => ['Next action', 'text', 'hide'],
        'visa_status' => ['Visa / permit status', 'enum:Not Started|Applied|Approved|Issued|Rejected', 'default' => 'Not Started'], 'permit_no' => ['Permit / visa number', 'text'], 'visa_expiry' => ['Visa expiry', 'date'],
        'ticket' => ['Ticket', 'text'], 'planned_departure' => ['Planned departure', 'date'], 'actual_departure' => ['Actual departure', 'date'], 'arrival' => ['Arrival', 'date'],
        'travel_status' => ['Travel status (worker side)', 'enum:Pending|Travelled|Did Not Travel', 'default' => 'Pending'], 'no_travel_reason' => ['Reason worker did not travel', 'text'],
        'company_travel_status' => ['Travel status (company side)', 'enum:Pending|Arranged|Travelled|Did Not Travel', 'default' => 'Pending'], 'company_no_travel_reason' => ['Reason (company side)', 'text'],
        'replacement_worker_id' => ['Replacement worker', 'ref:workers', 'norel' => true],
        'joining_date' => ['Joining date', 'date'], 'worksite' => ['Worksite', 'text'], 'supervisor' => ['Supervisor', 'text'], 'wage' => ['Monthly wage', 'money'],
        'slot_outcome' => ['Slot outcome', 'enum:Open|Deployed|Cancelled|Unfilled|Rejected|Withdrawn|Resolved', 'default' => 'Open'],
    ],
    'list' => ['ref_no', 'full_name', 'nationality', 'demand_id', 'agent_id', 'stage', 'result', 'visa_status'], 'filters' => ['stage', 'result', 'demand_id', 'agent_id', 'batch_id', 'visa_status', 'travel_status', 'slot_outcome'],
    'before_save' => function (&$d, $old) {
        if (!$old) { $d['stage'] = 'Submitted'; $d['result'] = 'Pending'; if (empty($d['submitted_by'])) $d['submitted_by'] = me()['name']; }
        if (($d['travel_status'] ?? '') === 'Did Not Travel' && trim((string)($d['no_travel_reason'] ?? '')) === '') return 'Give the reason the worker did not travel.';
        if (($d['company_travel_status'] ?? '') === 'Did Not Travel' && trim((string)($d['company_no_travel_reason'] ?? '')) === '') return 'Give the company-side reason for not travelling.';
        if (!empty($d['batch_id']) && !empty($d['agent_id'])) { $b = row('SELECT agent_id, demand_id FROM batches WHERE id=?', [$d['batch_id']]); if ($b && ((int)$b['agent_id'] !== (int)$d['agent_id'] || (int)$b['demand_id'] !== (int)$d['demand_id'])) return 'The batch belongs to a different agent or demand.'; }
        $dm = row('SELECT status FROM demands WHERE id=?', [$d['demand_id'] ?? ($old['demand_id'] ?? 0)]);
        if (!$old && $dm && in_array($dm['status'], ['Pending Approval', 'Closed', 'Cancelled'], true)) return 'Workers can only be submitted to an approved, open demand.';
        return null;
    },
    'view' => function ($r) { worker_panel($r); },
    'actions' => [
        'advance' => ['label' => 'Advance stage', 'icon' => 'arrow-right', 'perm' => 'edit', 'show' => function ($r) { return $r['stage'] !== 'Joined' && $r['result'] !== 'Rejected'; },
            'form' => function ($r) { $i = array_search($r['stage'], WORKER_STAGES, true); echo '<div class="grid-form"><div class="fld"><label>Move to stage *</label><select name="stage" required>'; foreach (WORKER_STAGES as $k => $s) if ($k > $i) echo '<option>' . $s . '</option>'; echo '</select><small class="muted">Stages only move forward.</small></div><div class="fld"><label>Note</label><input name="note"></div></div><div class="form-actions"><button class="btn">Advance</button></div>'; },
            'do' => function ($r) {
                $to = p('stage');
                if (in_array($to, ['Selected', 'Documentation', 'Visa Processing', 'Ready to Mobilise', 'Joined'], true) && $r['result'] !== 'Selected') return 'Record the selection decision first (Selection button).';
                if ($to === 'Joined' && !$r['joining_date']) q('UPDATE workers SET joining_date=? WHERE id=?', [today(), $r['id']]);
                $err = stage_advance('workers', $r['id'], $to, p('note'));
                if ($err) return $err;
                if ($to === 'Joined') q("UPDATE workers SET slot_outcome='Deployed' WHERE id=?", [$r['id']]);
                return ['msg' => 'Worker moved to ' . $to . '.'];
            }],
        'select' => ['label' => 'Selection', 'icon' => 'target', 'perm' => 'approve', 'show' => function ($r) { return in_array($r['result'], ['Pending', 'On Hold', null], true); },
            'form' => function ($r) { echo '<div class="grid-form"><div class="fld"><label>Decision *</label><select name="result" required><option>Selected</option><option>Rejected</option><option>On Hold</option><option>Alternate Position</option></select></div><div class="fld"><label>Decision maker *</label><input name="decision_by" required placeholder="Client name / RH manager"></div><div class="fld wide"><label>Reason / client feedback (required for rejection)</label><input name="reason"></div><div class="fld wide"><label>Next action</label><input name="next_action"></div></div><div class="form-actions"><button class="btn">Save decision</button></div>'; },
            'do' => function ($r) {
                $res = p('result');
                if ($res === 'Rejected' && !p('reason')) return 'A rejection requires a reason.';
                if (!p('decision_by')) return 'Enter the decision maker.';
                upd('workers', $r['id'], ['result' => $res, 'result_reason' => p('reason'), 'decision_by' => p('decision_by'), 'decision_on' => today(), 'next_action' => p('next_action'), 'updated_at' => now(), 'slot_outcome' => $res === 'Rejected' ? 'Rejected' : $r['slot_outcome']]);
                audit('selection', 'workers', $r['id'], ['result' => $r['result']], ['result' => $res], p('reason'));
                if ($res === 'Selected' && array_search($r['stage'], WORKER_STAGES, true) < 2) { if ($r['stage'] === 'Submitted') stage_advance('workers', $r['id'], 'Interviewed', 'Assessed'); stage_advance('workers', $r['id'], 'Selected', 'Selected by ' . p('decision_by')); }
                return ['msg' => 'Decision recorded: ' . $res . '.'];
            }],
        'travel' => ['label' => 'Travel (both sides)', 'icon' => 'plane', 'perm' => 'edit', 'show' => function ($r) { return $r['result'] === 'Selected'; },
            'form' => function ($r) {
                $sel = function ($n, $opts, $v) { $h = '<select name="' . $n . '">'; foreach ($opts as $o) $h .= '<option' . ($o === $v ? ' selected' : '') . '>' . $o . '</option>'; return $h . '</select>'; };
                echo '<div class="grid-form"><div class="fld"><label>Worker side</label>' . $sel('travel_status', ['Pending', 'Travelled', 'Did Not Travel'], $r['travel_status']) . '</div><div class="fld"><label>Worker-side reason (if did not travel)</label><input name="no_travel_reason" value="' . e($r['no_travel_reason']) . '"></div>'
                    . '<div class="fld"><label>Company side</label>' . $sel('company_travel_status', ['Pending', 'Arranged', 'Travelled', 'Did Not Travel'], $r['company_travel_status']) . '</div><div class="fld"><label>Company-side reason</label><input name="company_no_travel_reason" value="' . e($r['company_no_travel_reason']) . '"></div>'
                    . '<div class="fld"><label>Ticket</label><input name="ticket" value="' . e($r['ticket']) . '"></div><div class="fld"><label>Actual departure</label><input type="date" name="actual_departure" value="' . e($r['actual_departure']) . '"></div>'
                    . '<div class="fld"><label>Replacement worker (if arranged)</label><select name="replacement_worker_id"><option value="">— None —</option>';
                foreach (rows('SELECT id, ref_no, full_name FROM workers WHERE demand_id=? AND id<>? AND archived=0', [$r['demand_id'], $r['id']]) as $w) echo '<option value="' . $w['id'] . '"' . ($r['replacement_worker_id'] == $w['id'] ? ' selected' : '') . '>' . e($w['ref_no'] . ' · ' . $w['full_name']) . '</option>';
                echo '</select></div></div><div class="form-actions"><button class="btn">Save travel</button></div>';
            },
            'do' => function ($r) {
                $d = ['travel_status' => p('travel_status'), 'no_travel_reason' => p('no_travel_reason'), 'company_travel_status' => p('company_travel_status'), 'company_no_travel_reason' => p('company_no_travel_reason'), 'ticket' => p('ticket'), 'actual_departure' => p('actual_departure') ?: null, 'replacement_worker_id' => (int)p('replacement_worker_id') ?: null];
                if ($d['travel_status'] === 'Did Not Travel' && !$d['no_travel_reason']) return 'Give the worker-side reason.';
                if ($d['company_travel_status'] === 'Did Not Travel' && !$d['company_no_travel_reason']) return 'Give the company-side reason.';
                $d['updated_at'] = now();
                upd('workers', $r['id'], $d);
                audit('travel', 'workers', $r['id'], ['travel_status' => $r['travel_status'], 'company_travel_status' => $r['company_travel_status']], ['travel_status' => $d['travel_status'], 'company_travel_status' => $d['company_travel_status']]);
                return ['msg' => 'Travel record updated on both sides.'];
            }],
    ],
]);

ent('worker_tests', ['label' => 'Interviews, Tests & Training', 'one' => 'Interview / test', 'title' => 'kind', 'icon' => 'clipboard',
    'fields' => ['worker_id' => ['Worker', 'ref:workers', 'req'], 'demand_id' => ['Demand', 'ref:demands'], 'kind' => ['Type', 'enum:Interview|Trade Test|Training|Medical|Other', 'req', 'default' => 'Interview'],
        'location' => ['Location', 'text'], 'scheduled_at' => ['Date & time', 'datetime', 'req'], 'assessor' => ['Assessor', 'text'], 'attendance' => ['Attendance', 'enum:Scheduled|Attended|No Show|Rescheduled', 'default' => 'Scheduled'],
        'result' => ['Result', 'enum:Pending|Pass|Fail', 'default' => 'Pending'], 'score' => ['Score / feedback', 'text'], 'feedback' => ['Notes', 'textarea'], 'reschedule_reason' => ['Reschedule / issue reason', 'text']],
    'list' => ['worker_id', 'kind', 'scheduled_at', 'location', 'attendance', 'result'], 'filters' => ['kind', 'attendance', 'result'], 'order' => 'scheduled_at DESC',
    'before_save' => function (&$d, $old) { if (($d['attendance'] ?? '') === 'Rescheduled' && !$d['reschedule_reason']) return 'Give the reschedule reason.'; if (empty($d['demand_id']) && !empty($d['worker_id'])) $d['demand_id'] = val('SELECT demand_id FROM workers WHERE id=?', [$d['worker_id']]); return null; },
    'after_save' => function ($id, $d, $old) { if (($d['attendance'] ?? '') === 'Attended') { $w = row('SELECT * FROM workers WHERE id=?', [$d['worker_id']]); if ($w && $w['stage'] === 'Submitted') stage_advance('workers', $w['id'], 'Interviewed', ($d['kind'] ?? 'Interview') . ' attended'); } }]);

ent('agent_comms', ['label' => 'Agent Communications', 'one' => 'Communication', 'title' => 'subject', 'icon' => 'message',
    'fields' => ['agent_id' => ['Agent', 'ref:agents', 'req'], 'allocation_id' => ['Allocation', 'ref:allocations'], 'batch_id' => ['Batch', 'ref:batches'], 'subject' => ['Subject', 'text', 'req'], 'message' => ['Message / notes', 'textarea', 'req'],
        'channel' => ['Channel', 'enum:Phone|WhatsApp|Email|Meeting|Portal', 'default' => 'WhatsApp'], 'follow_up' => ['Follow-up date', 'date'], 'assigned_to' => ['Assigned to', 'user'], 'status' => ['Status', 'enum:Open|Done', 'default' => 'Open']],
    'list' => ['agent_id', 'subject', 'channel', 'follow_up', 'assigned_to', 'status', 'created_at'], 'filters' => ['status', 'agent_id', 'assigned_to'],
    'cols' => ['Flag' => function ($r) { return $r['status'] === 'Open' && $r['follow_up'] && $r['follow_up'] < today() ? badge('Overdue') : ''; }]]);
ent('client_feedback', ['label' => 'Client Feedback', 'one' => 'Feedback', 'title' => 'company_id', 'icon' => 'star',
    'fields' => ['company_id' => ['Company', 'ref:companies', 'req'], 'demand_id' => ['Demand', 'ref:demands'], 'worker_id' => ['Worker', 'ref:workers'], 'rating' => ['Rating (1–5)', 'int'], 'feedback' => ['Feedback', 'textarea', 'req'], 'source' => ['Source', 'enum:Portal|Phone|Email|Meeting', 'default' => 'Phone']],
    'list' => ['company_id', 'demand_id', 'worker_id', 'rating', 'source', 'created_at']]);

// ---------------------------------------------------------------- calculations

/** Demand progress counters (client side + agent side use the same numbers). */
function demand_progress($d, $agentId = null)
{
    $ws = rows('SELECT * FROM workers WHERE demand_id=? AND archived=0' . ($agentId ? ' AND agent_id=' . (int)$agentId : ''), [$d['id']]);
    $idx = function ($w) { return array_search($w['stage'], WORKER_STAGES, true); };
    $c = ['requested' => (int)($agentId ? val('SELECT COALESCE(SUM(target_qty),0) FROM allocations WHERE demand_id=? AND agent_id=?', [$d['id'], $agentId]) : ($d['approved_qty'] ?: $d['quantity'])), 'submitted' => count($ws), 'interviewed' => 0, 'selected' => 0, 'rejected' => 0, 'documentation' => 0, 'visa' => 0, 'ready' => 0, 'joined' => 0];
    foreach ($ws as $w) {
        $i = $idx($w);
        if ($i >= 1) $c['interviewed']++;
        if ($w['result'] === 'Selected') $c['selected']++;
        if ($w['result'] === 'Rejected') $c['rejected']++;
        if ($w['stage'] === 'Documentation') $c['documentation']++;
        if ($w['stage'] === 'Visa Processing' || $w['visa_status'] === 'Applied') $c['visa']++;
        if ($w['stage'] === 'Ready to Mobilise') $c['ready']++;
        if ($w['stage'] === 'Joined') $c['joined']++;
    }
    $c['outstanding'] = max(0, $c['requested'] - $c['joined']);
    return $c;
}
function progress_row($p) { return [$p['requested'], $p['submitted'], $p['interviewed'], $p['selected'], $p['rejected'], $p['documentation'], $p['visa'], $p['ready'], '<b>' . $p['joined'] . '</b>', $p['outstanding'] ? '<b style="color:var(--bad)">' . $p['outstanding'] . '</b>' : badge('Complete')]; }
const PROGRESS_HEAD = ['Requested', 'Submitted', 'Interviewed', 'Selected', 'Rejected', 'In docs', 'Visa pending', 'Ready', 'Joined', 'Outstanding'];

function demand_panel($d)
{
    if ($d['status'] === 'Closed') echo '<div class="banner">' . icon('lock', 20) . ' Demand closed · ' . e($d['closure_note']) . '</div>';
    $p = demand_progress($d);
    card_open(icon('activity', 17) . ' Demand progress', $d['status'] === 'Pending Approval' ? badge('Waiting for approval') : '');
    echo simple_table(PROGRESS_HEAD, [progress_row($p)]);
    echo '<div style="margin-top:12px">' . progress($p['requested'] ? $p['joined'] / $p['requested'] * 100 : 0, $p['joined'] . ' of ' . $p['requested'] . ' joined') . '</div>';
    card_close();
    $agents = rows('SELECT a.*, g.agency_name FROM allocations a JOIN agents g ON g.id=a.agent_id WHERE a.demand_id=? AND a.archived=0', [$d['id']]);
    if ($agents) {
        card_open(icon('globe', 17) . ' Agent side');
        echo simple_table(array_merge(['Agent', 'Deadline'], PROGRESS_HEAD), array_map(function ($a) use ($d) { return array_merge(['<b>' . e($a['agency_name']) . '</b>', e(dfmt($a['deadline']))], progress_row(demand_progress($d, $a['agent_id']))); }, $agents));
        card_close();
    }
}
function company_dashboard($co)
{
    $ds = rows('SELECT * FROM demands WHERE company_id=? AND archived=0 ORDER BY id DESC', [$co['id']]);
    $tot = ['requested' => 0, 'submitted' => 0, 'interviewed' => 0, 'selected' => 0, 'rejected' => 0, 'documentation' => 0, 'visa' => 0, 'ready' => 0, 'joined' => 0, 'outstanding' => 0];
    $rowsOut = []; $links = [];
    foreach ($ds as $d) { $p = demand_progress($d); foreach ($tot as $k => $v) $tot[$k] += $p[$k]; $rowsOut[] = array_merge(['<b class="ref">' . e($d['ref_no']) . '</b> ' . e($d['position']), badge($d['status'])], progress_row($p)); $links[] = ent_url('demands', $d['id']); }
    $inv = (float)val("SELECT COALESCE(SUM(total),0) FROM invoices WHERE company_id=? AND archived=0 AND status<>'Cancelled'", [$co['id']]);
    $paid = (float)val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.entity_id WHERE p.entity='invoices' AND i.company_id=?", [$co['id']]);
    echo '<div class="kv" style="margin:0 0 16px"><div class="hl"><span>Requested headcount</span><b>' . $tot['requested'] . '</b></div><div><span>Submitted</span><b>' . $tot['submitted'] . '</b></div><div><span>Selected</span><b>' . $tot['selected'] . '</b></div><div class="good"><span>Joined</span><b>' . $tot['joined'] . '</b></div><div class="bad"><span>Outstanding</span><b>' . $tot['outstanding'] . '</b></div><div><span>Invoiced</span><b>' . money($inv) . '</b></div><div><span>Paid</span><b>' . money($paid) . '</b></div></div>';
    card_open(icon('clipboard', 17) . ' Demand progress for this client');
    echo simple_table(array_merge(['Demand', 'Status'], PROGRESS_HEAD), $rowsOut, 'No demands yet.', $links);
    card_close();
}
/** Agent balance: fee per joined worker (from allocations) minus payments made. */
function agent_balance($aid)
{
    $due = 0;
    foreach (rows('SELECT * FROM allocations WHERE agent_id=? AND archived=0', [$aid]) as $al) $due += (float)$al['fee_per_worker'] * (int)val("SELECT COUNT(*) FROM workers WHERE agent_id=? AND demand_id=? AND stage='Joined' AND archived=0", [$aid, $al['demand_id']]);
    $paid = paid_total('agents', $aid, 'out');
    $disputed = (int)val("SELECT COUNT(*) FROM issues WHERE party='Agent' AND category='Payment Dispute' AND status IN ('Open','In Progress') AND archived=0 AND (worker_id IN (SELECT id FROM workers WHERE agent_id=?))", [$aid]);
    return ['due' => $due, 'paid' => $paid, 'outstanding' => $due - $paid, 'disputed' => $disputed];
}
function agent_performance_row($a)
{
    $ws = rows('SELECT * FROM workers WHERE agent_id=? AND archived=0', [$a['id']]);
    $n = count($ws);
    $sel = count(array_filter($ws, function ($w) { return $w['result'] === 'Selected'; }));
    $rej = count(array_filter($ws, function ($w) { return $w['result'] === 'Rejected'; }));
    $mob = count(array_filter($ws, function ($w) { return in_array($w['stage'], ['Ready to Mobilise', 'Joined'], true); }));
    $iss = (int)val("SELECT COUNT(*) FROM issues WHERE worker_id IN (SELECT id FROM workers WHERE agent_id=?) AND archived=0", [$a['id']]);
    $pct = function ($x) use ($n) { return $n ? round($x / $n * 100) . '%' : '—'; };
    return ['<b>' . e($a['agency_name']) . '</b><br><small class="muted">' . e($a['country']) . '</small>', $n, $sel . ' (' . $pct($sel) . ')', $rej . ' (' . $pct($rej) . ')', $mob . ' (' . $pct($mob) . ')', $iss . ' (' . $pct($iss) . ')'];
}
function agent_panel($a)
{
    $b = agent_balance($a['id']);
    echo '<div class="kv" style="margin:0 0 16px"><div><span>Amount due (joined × fee)</span><b>' . money($b['due']) . '</b></div><div class="good"><span>Paid</span><b>' . money($b['paid']) . '</b></div><div class="' . ($b['outstanding'] > 0 ? 'bad' : '') . '"><span>Outstanding</span><b>' . money($b['outstanding']) . '</b></div><div class="warn"><span>Disputes open</span><b>' . $b['disputed'] . '</b></div></div>';
    card_open(icon('bar-chart', 17) . ' Agent performance');
    echo simple_table(['Agent', 'Submissions', 'Selected', 'Rejected', 'Mobilised', 'Issues'], [agent_performance_row($a)]);
    card_close();
}
function worker_panel($w)
{
    $i = array_search($w['stage'], WORKER_STAGES, true);
    card_open(icon('activity', 17) . ' Worker progress', $w['cv_file'] ? file_link($w['cv_file'], 'CV') : '');
    echo '<div class="journey">';
    foreach (WORKER_STAGES as $k => $s) echo '<div class="jstep' . ($k <= $i ? ' done' : '') . ($k === $i ? ' cur' : '') . '"><i>' . ($k <= $i ? '✓' : $k + 1) . '</i>' . e($s) . '</div>';
    echo '</div><div class="kv"><div><span>Selection</span><b>' . e($w['result'] ?: 'Pending') . '</b></div><div><span>Visa</span><b>' . e($w['visa_status']) . '</b></div><div class="' . ($w['travel_status'] === 'Did Not Travel' ? 'bad' : '') . '"><span>Travel · worker</span><b>' . e($w['travel_status']) . '</b></div><div class="' . ($w['company_travel_status'] === 'Did Not Travel' ? 'bad' : '') . '"><span>Travel · company</span><b>' . e($w['company_travel_status']) . '</b></div><div><span>Slot outcome</span><b>' . e($w['slot_outcome']) . '</b></div>' . ($w['replacement_worker_id'] ? '<div class="warn"><span>Replacement</span><b>' . ref_link('workers', $w['replacement_worker_id']) . '</b></div>' : '') . '</div>';
    card_close();
}

// ---------------------------------------------------------------- screens

mod('outside', ['label' => 'Outside Hiring', 'icon' => 'building', 'area' => 'Outside Hiring', 'order' => 1, 'desc' => 'Client companies, demands, agents and agencies, allocations, batches and workers.',
    'tabs' => [
        'dash' => ['label' => 'Client dashboard', 'fn' => function ($base, $mod) { outside_dashboard(); }],
        'companies' => 'companies', 'demands' => 'demands', 'agents' => 'agents', 'allocations' => 'allocations', 'batches' => 'batches', 'workers' => 'workers',
        'clientusers' => ['label' => 'Client logins', 'ent' => 'client_users', 'perm' => 'edit'], 'agentusers' => ['label' => 'Agent logins', 'ent' => 'agent_users', 'perm' => 'edit'],
    ]]);
mod('outside_ops', ['label' => 'Outside Operations', 'icon' => 'truck', 'area' => 'Outside Hiring', 'order' => 2, 'desc' => 'Approvals, interviews and tests, documents, visa, travel on both sides, issues, closure, agent communications and performance.',
    'tabs' => [
        'approvals' => ['label' => 'Approvals', 'ent' => 'demands', 'nohome' => true, 'noadd' => true, 'scope' => function () { return ["status='Pending Approval'", []]; }],
        'tests' => 'worker_tests',
        'documents' => ['label' => 'Worker documents', 'ent' => 'documents', 'nohome' => true, 'scope' => function () { return ['worker_id IS NOT NULL', []]; }, 'list' => ['ref_no', 'doc_type', 'worker_id', 'status', 'expiry']],
        'travel' => ['label' => 'Travel & mobilisation', 'fn' => function ($base, $mod) {
            $ws = rows("SELECT * FROM workers WHERE archived=0 AND result='Selected' ORDER BY planned_departure IS NULL, planned_departure");
            card_open(icon('plane', 17) . ' Travel record — worker side and company side');
            echo simple_table(['Worker', 'Demand', 'Stage', 'Visa', 'Planned', 'Worker side', 'Company side', 'Replacement'], array_map(function ($w) {
                return ['<b>' . e($w['full_name']) . '</b><br><small class="ref">' . e($w['ref_no']) . '</small>', ref_link('demands', $w['demand_id']), badge($w['stage']), badge($w['visa_status']), e(dfmt($w['planned_departure'])), badge($w['travel_status']) . ($w['no_travel_reason'] ? '<br><small>' . e($w['no_travel_reason']) . '</small>' : ''), badge($w['company_travel_status']) . ($w['company_no_travel_reason'] ? '<br><small>' . e($w['company_no_travel_reason']) . '</small>' : ''), ref_link('workers', $w['replacement_worker_id'])];
            }, $ws), 'No selected workers yet.', array_map(function ($w) { return ent_url('workers', $w['id']); }, $ws));
            card_close();
        }],
        'issues' => ['label' => 'Issues & replacements', 'ent' => 'issues', 'nohome' => true, 'scope' => function () { return ["party IN ('Client','Agent','Worker')", []]; }],
        'closure' => ['label' => 'Demand closure', 'fn' => function ($base, $mod) {
            $ds = rows("SELECT * FROM demands WHERE archived=0 AND status IN ('Approved','In Progress') ORDER BY deadline");
            card_open(icon('lock', 17) . ' Open demands — closure readiness');
            echo simple_table(['Demand', 'Company', 'Requested', 'Deployed', 'Uncategorised workers', 'Deadline', 'Ready to close?'], array_map(function ($d) {
                $p = demand_progress($d); $open = (int)val("SELECT COUNT(*) FROM workers WHERE demand_id=? AND archived=0 AND (slot_outcome IS NULL OR slot_outcome='Open')", [$d['id']]);
                $dep = (int)val("SELECT COUNT(*) FROM workers WHERE demand_id=? AND slot_outcome='Deployed' AND archived=0", [$d['id']]);
                return ['<b class="ref">' . e($d['ref_no']) . '</b> ' . e($d['position']), ref_link('companies', $d['company_id']), $p['requested'], $dep, $open, e(dfmt($d['deadline'])) . ($d['deadline'] && $d['deadline'] < today() ? ' ' . badge('Overdue') : ''), $open ? badge('Categorise workers first') : badge('Ready')];
            }, $ds), 'No open demands.', array_map(function ($d) { return ent_url('demands', $d['id']) . '&act=close'; }, $ds));
            card_close();
        }],
        'comms' => 'agent_comms',
        'performance' => ['label' => 'Agent performance', 'fn' => function ($base, $mod) {
            $ag = rows('SELECT * FROM agents WHERE archived=0 ORDER BY agency_name');
            card_open(icon('bar-chart', 17) . ' Submissions, selections, rejections, mobilisation and issue rates per agent');
            echo simple_table(['Agent', 'Submissions', 'Selected', 'Rejected', 'Mobilised', 'Issues'], array_map('agent_performance_row', $ag), 'No agents yet.', array_map(function ($a) { return ent_url('agents', $a['id']); }, $ag));
            card_close();
        }],
        'feedback' => 'client_feedback',
        'alerts' => ['label' => 'Alerts', 'fn' => function ($base, $mod) { outside_alerts(); }],
    ]]);

function outside_dashboard()
{
    render_widgets(['outside', 'outside_ops']);
    $all = rows("SELECT * FROM demands WHERE archived=0 AND status NOT IN ('Cancelled') ORDER BY id DESC");
    $tot = array_fill_keys(['requested', 'submitted', 'interviewed', 'selected', 'rejected', 'documentation', 'visa', 'ready', 'joined', 'outstanding'], 0);
    foreach ($all as $d) { $p = demand_progress($d); foreach ($tot as $k => $v) $tot[$k] += $p[$k]; }
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('filter', 17) . ' Headcount: requested versus progress</h3></div>' . chart_bars(['Requested' => $tot['requested'], 'Submitted' => $tot['submitted'], 'Interviewed' => $tot['interviewed'], 'Selected' => $tot['selected'], 'In documentation' => $tot['documentation'], 'Visa pending' => $tot['visa'], 'Ready to mobilise' => $tot['ready'], 'Joined' => $tot['joined'], 'Outstanding' => $tot['outstanding']]) . '</div>';
    $byCo = [];
    foreach (rows('SELECT c.display_name n, SUM(COALESCE(d.approved_qty,d.quantity)) q FROM demands d JOIN companies c ON c.id=d.company_id WHERE d.archived=0 GROUP BY c.id ORDER BY q DESC LIMIT 8') as $r) $byCo[$r['n']] = (int)$r['q'];
    echo '<div class="card"><div class="card-h"><h3>' . icon('building', 17) . ' Requested headcount by company</h3></div>' . chart_bars($byCo) . '</div></div>';
    card_open(icon('clipboard', 17) . ' Company demand progress table', '<span class="muted small">Workers arranged, documents, season, payments and progress per demand</span>');
    $data = []; $links = [];
    foreach ($all as $d) {
        $p = demand_progress($d);
        $docs = (int)val("SELECT COUNT(*) FROM documents WHERE worker_id IN (SELECT id FROM workers WHERE demand_id=?) AND status IN ('Received','Verified')", [$d['id']]);
        $paid = (float)val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.entity_id WHERE p.entity='invoices' AND i.demand_id=?", [$d['id']]);
        $data[] = array_merge(['<b class="ref">' . e($d['ref_no']) . '</b><br>' . e($d['position']), ref_link('companies', $d['company_id']), badge($d['status']), e($d['season']), $docs, money($paid)], progress_row($p));
        $links[] = ent_url('demands', $d['id']);
    }
    echo simple_table(array_merge(['Demand', 'Company', 'Status', 'Season', 'Docs OK', 'Paid'], PROGRESS_HEAD), $data, 'No demands yet.', $links);
    card_close();
    outside_alerts();
}
function outside_alerts()
{
    $al = [];
    foreach (rows("SELECT * FROM demands WHERE archived=0 AND status IN ('Approved','In Progress') AND deadline IS NOT NULL AND deadline<=?", [date('Y-m-d', strtotime('+7 days'))]) as $d) $al[] = [badge($d['deadline'] < today() ? 'Overdue' : 'Due soon'), 'Demand deadline', e($d['ref_no'] . ' · ' . $d['position']) . ' — ' . e(dfmt($d['deadline'])), ent_url('demands', $d['id'])];
    foreach (rows("SELECT * FROM workers WHERE archived=0 AND client_response='Pending' AND submitted_on<=?", [date('Y-m-d', strtotime('-5 days'))]) as $w) $al[] = [badge('Pending'), 'Client feedback pending', e($w['ref_no'] . ' · ' . $w['full_name']) . ' submitted ' . e(dfmt($w['submitted_on'])), ent_url('workers', $w['id'])];
    foreach (rows("SELECT * FROM documents WHERE archived=0 AND worker_id IS NOT NULL AND (status='Missing' OR (expiry IS NOT NULL AND expiry<?))", [today()]) as $d) $al[] = [badge('Missing'), 'Overdue document', e($d['doc_type']) . ' · ' . e(ent_title('workers', $d['worker_id'])), ent_url('documents', $d['id'])];
    foreach (rows("SELECT * FROM workers WHERE archived=0 AND visa_status='Applied' AND updated_at<=?", [date('Y-m-d', strtotime('-14 days'))]) as $w) $al[] = [badge('Visa delay'), 'Visa delay (14+ days)', e($w['ref_no'] . ' · ' . $w['full_name']), ent_url('workers', $w['id'])];
    foreach (rows("SELECT * FROM worker_tests WHERE archived=0 AND result='Fail' AND scheduled_at>=?", [date('Y-m-d', strtotime('-30 days'))]) as $t) $al[] = [badge('Fail'), 'Failed test', e($t['kind']) . ' · ' . e(ent_title('workers', $t['worker_id'])), ent_url('worker_tests', $t['id'])];
    foreach (rows("SELECT * FROM workers WHERE archived=0 AND stage='Ready to Mobilise' AND planned_departure IS NOT NULL AND planned_departure<?", [today()]) as $w) $al[] = [badge('Late'), 'Joining delay', e($w['ref_no'] . ' · ' . $w['full_name']), ent_url('workers', $w['id'])];
    foreach (rows("SELECT * FROM demands WHERE archived=0 AND status IN ('Approved','In Progress')") as $d) { $p = demand_progress($d); if ($p['outstanding'] && $d['deadline'] && $d['deadline'] < today()) $al[] = [badge('Unfilled'), 'Unfilled headcount', e($d['ref_no']) . ' — ' . $p['outstanding'] . ' outstanding', ent_url('demands', $d['id'])]; }
    card_open(icon('alert', 17) . ' Alerts <span class="count">' . count($al) . '</span>');
    echo simple_table(['', 'Alert', 'Detail'], array_map(function ($a) { return [$a[0], '<b>' . e($a[1]) . '</b>', $a[2]]; }, $al), 'No alerts. Everything is on track.', array_column($al, 3));
    card_close();
}

widget('outside', 'Open demands', function () { return (int)val("SELECT COUNT(*) FROM demands WHERE status IN ('Approved','In Progress') AND archived=0"); }, 'outside.php?t=demands', 'clipboard', 'blue');
widget('outside', 'Requested headcount (open)', function () { return (int)val("SELECT COALESCE(SUM(COALESCE(approved_qty,quantity)),0) FROM demands WHERE status IN ('Approved','In Progress') AND archived=0"); }, 'outside.php', 'users', 'violet');
widget('outside', 'Workers joined', function () { return (int)val("SELECT COUNT(*) FROM workers WHERE stage='Joined' AND archived=0"); }, 'outside.php?t=workers', 'check-circle', 'green');
widget('outside_ops', 'Demands awaiting approval', function () { return (int)val("SELECT COUNT(*) FROM demands WHERE status='Pending Approval' AND archived=0"); }, 'outside_ops.php', 'clock', 'amber');
widget('outside_ops', 'Visa pending', function () { return (int)val("SELECT COUNT(*) FROM workers WHERE (stage='Visa Processing' OR visa_status='Applied') AND archived=0"); }, 'outside_ops.php?t=travel', 'id', 'gold');

search_provider('outside', function ($s) {
    $o = [];
    foreach (rows('SELECT * FROM companies WHERE archived=0 AND (display_name LIKE ? OR legal_name LIKE ? OR ref_no LIKE ?) LIMIT 6', ["%$s%", "%$s%", "%$s%"]) as $r) $o[] = ['Client', $r['ref_no'] . ' · ' . $r['display_name'], $r['status'], ent_url('companies', $r['id'])];
    foreach (rows('SELECT * FROM demands WHERE archived=0 AND (ref_no LIKE ? OR position LIKE ?) LIMIT 6', ["%$s%", "%$s%"]) as $r) $o[] = ['Demand', $r['ref_no'] . ' · ' . $r['position'], $r['status'], ent_url('demands', $r['id'])];
    foreach (rows('SELECT * FROM agents WHERE archived=0 AND (agency_name LIKE ? OR agent_name LIKE ? OR ref_no LIKE ?) LIMIT 6', ["%$s%", "%$s%", "%$s%"]) as $r) $o[] = ['Agent', $r['ref_no'] . ' · ' . $r['agency_name'], $r['country'], ent_url('agents', $r['id'])];
    foreach (rows('SELECT * FROM batches WHERE archived=0 AND (ref_no LIKE ? OR name LIKE ?) LIMIT 6', ["%$s%", "%$s%"]) as $r) $o[] = ['Batch', $r['ref_no'] . ' · ' . $r['name'], '', ent_url('batches', $r['id'])];
    foreach (rows('SELECT * FROM workers WHERE archived=0 AND (full_name LIKE ? OR passport_no LIKE ? OR ref_no LIKE ?) LIMIT 6', ["%$s%", "%$s%", "%$s%"]) as $r) $o[] = ['Worker', $r['ref_no'] . ' · ' . $r['full_name'], $r['stage'], ent_url('workers', $r['id'])];
    return $o;
});

report_section('demand', 'Demand progress', 'reports', function () {
    $ds = rows("SELECT * FROM demands WHERE archived=0 ORDER BY id DESC");
    card_open(icon('clipboard', 17) . ' Client side');
    echo simple_table(array_merge(['Demand', 'Company', 'Status'], PROGRESS_HEAD), array_map(function ($d) { return array_merge(['<b class="ref">' . e($d['ref_no']) . '</b> ' . e($d['position']), e(ent_title('companies', $d['company_id'])), badge($d['status'])], progress_row(demand_progress($d))); }, $ds), 'No demands.');
    card_close();
    $al = rows('SELECT a.*, g.agency_name, d.ref_no dref, d.position FROM allocations a JOIN agents g ON g.id=a.agent_id JOIN demands d ON d.id=a.demand_id WHERE a.archived=0');
    card_open(icon('globe', 17) . ' Agent side');
    echo simple_table(array_merge(['Agent', 'Demand'], PROGRESS_HEAD), array_map(function ($a) { $d = row('SELECT * FROM demands WHERE id=?', [$a['demand_id']]); return array_merge(['<b>' . e($a['agency_name']) . '</b>', e($a['dref'] . ' · ' . $a['position'])], progress_row(demand_progress($d, $a['agent_id']))); }, $al), 'No allocations.');
    card_close();
    card_open(icon('bar-chart', 17) . ' Agent performance');
    echo simple_table(['Agent', 'Submissions', 'Selected', 'Rejected', 'Mobilised', 'Issues'], array_map('agent_performance_row', rows('SELECT * FROM agents WHERE archived=0')), 'No agents.');
    card_close();
});

seeder(function () {
    $u = function ($n) { return (int)val('SELECT id FROM users WHERE username=?', [$n]); };
    $b = ['created_at' => now(), 'archived' => 0, 'created_by' => $u('ops')];
    $gulf = ins('companies', $b + ['ref_no' => next_number('CO'), 'legal_name' => 'Gulf Construction LLC', 'display_name' => 'Gulf Construction LLC', 'industry' => 'Construction', 'branches' => "Head office: Business Bay, Dubai\nSite: Dubai South Phase 2", 'contact_person' => 'Mr. Khalid Al Mansoori', 'phone' => '04 222 3344', 'email' => 'hr@gulfconstruction.example', 'account_manager' => $u('ops'), 'billing_terms' => '30 days from invoice', 'approved_roles' => 'Mason, Steel Fixer, Helper, Heavy Driver', 'status' => 'Active']);
    $emi = ins('companies', $b + ['ref_no' => next_number('CO'), 'legal_name' => 'Emirates Facilities Management LLC', 'display_name' => 'Emirates FM', 'industry' => 'Facilities Management', 'contact_person' => 'Ms. Huda Saeed', 'phone' => '04 555 6677', 'email' => 'jobs@emiratesfm.example', 'account_manager' => $u('ops'), 'billing_terms' => '15 days', 'status' => 'Active']);
    ins('client_users', $b + ['company_id' => $gulf, 'name' => 'Khalid Al Mansoori', 'username' => 'gulf', 'password_hash' => password_hash('client123', PASSWORD_DEFAULT), 'email' => 'hr@gulfconstruction.example', 'active' => 1]);
    $ktm = ins('agents', $b + ['ref_no' => next_number('AG'), 'agency_name' => 'Kathmandu Manpower Agency', 'agent_name' => 'Ram Bahadur', 'phone' => '+977 1 4400000', 'email' => 'info@ktmmanpower.example', 'location' => 'Kathmandu', 'country' => 'Nepal', 'status' => 'Approved', 'agreement' => 'Agency agreement 2026', 'permitted_countries' => 'Nepal', 'payment_terms' => 'AED 400 per joined worker, paid monthly', 'rh_owner' => $u('ops')]);
    $nbo = ins('agents', $b + ['ref_no' => next_number('AG'), 'agency_name' => 'Nairobi Talent Bridge', 'agent_name' => 'Grace Wanjiru', 'country' => 'Kenya', 'status' => 'Approved', 'permitted_countries' => 'Kenya, Uganda', 'payment_terms' => 'AED 350 per joined worker', 'rh_owner' => $u('ops')]);
    ins('agent_users', $b + ['agent_id' => $ktm, 'name' => 'Ram Bahadur', 'username' => 'kathmandu', 'email' => 'info@ktmmanpower.example', 'password_hash' => password_hash('agent123', PASSWORD_DEFAULT), 'active' => 1]);
    $d1 = ins('demands', $b + ['ref_no' => next_number('DEM'), 'company_id' => $gulf, 'position' => 'Mason', 'quantity' => 10, 'approved_qty' => 10, 'job_description' => 'Block work and plastering on residential towers.', 'salary' => 1600, 'benefits' => 'Accommodation, transport, 2-year visa', 'work_location' => 'Dubai South', 'deadline' => date('Y-m-d', strtotime('+20 days')), 'priority' => 'High', 'owner' => $u('ops'), 'status' => 'In Progress', 'replacement_policy' => 'Free replacement within 90 days', 'rate' => 1200, 'contract_version' => 'v1', 'approved_by' => $u('admin'), 'approved_at' => now(), 'season' => 'Winter 2026', 'business_line' => 'Outside Hiring']);
    $d2 = ins('demands', $b + ['ref_no' => next_number('DEM'), 'company_id' => $gulf, 'position' => 'Steel Fixer', 'quantity' => 6, 'work_location' => 'Business Bay', 'deadline' => date('Y-m-d', strtotime('+30 days')), 'priority' => 'Normal', 'owner' => $u('ops'), 'status' => 'Pending Approval', 'business_line' => 'Outside Hiring']);
    $d3 = ins('demands', $b + ['ref_no' => next_number('DEM'), 'company_id' => $emi, 'position' => 'Cleaner', 'quantity' => 8, 'approved_qty' => 8, 'salary' => 1300, 'work_location' => 'Downtown Dubai', 'deadline' => date('Y-m-d', strtotime('-3 days')), 'priority' => 'Urgent', 'owner' => $u('ops'), 'status' => 'In Progress', 'replacement_policy' => '60 days', 'rate' => 900, 'season' => 'Winter 2026', 'business_line' => 'Outside Hiring']);
    ins('allocations', $b + ['ref_no' => next_number('ALC'), 'demand_id' => $d1, 'agent_id' => $ktm, 'target_qty' => 10, 'deadline' => date('Y-m-d', strtotime('+10 days')), 'fee_per_worker' => 400, 'checklist' => 'Passport, photo, medical, police clearance', 'coordinator' => $u('ops'), 'status' => 'Active']);
    ins('allocations', $b + ['ref_no' => next_number('ALC'), 'demand_id' => $d3, 'agent_id' => $nbo, 'target_qty' => 8, 'deadline' => date('Y-m-d', strtotime('-5 days')), 'fee_per_worker' => 350, 'coordinator' => $u('ops'), 'status' => 'Active']);
    $bt1 = ins('batches', $b + ['ref_no' => next_number('BAT'), 'agent_id' => $ktm, 'demand_id' => $d1, 'name' => 'KTM Masons Batch 1', 'submitted_on' => date('Y-m-d', strtotime('-12 days'))]);
    $bt2 = ins('batches', $b + ['ref_no' => next_number('BAT'), 'agent_id' => $nbo, 'demand_id' => $d3, 'name' => 'Nairobi Cleaners A', 'submitted_on' => date('Y-m-d', strtotime('-9 days'))]);
    $names = [['Sanjay Tamang', 'Joined', 'Selected'], ['Bishnu Gurung', 'Ready to Mobilise', 'Selected'], ['Hari Thapa', 'Visa Processing', 'Selected'], ['Prakash Rai', 'Documentation', 'Selected'], ['Dipesh Magar', 'Interviewed', 'Rejected'], ['Kiran Shrestha', 'Submitted', 'Pending']];
    foreach ($names as $i => $n) {
        $id = ins('workers', $b + ['ref_no' => next_number('WRK'), 'full_name' => $n[0], 'passport_no' => 'NP' . (700100 + $i), 'nationality' => 'Nepal', 'phone' => '+97798000000' . $i, 'demand_id' => $d1, 'agent_id' => $ktm, 'batch_id' => $bt1, 'position' => 'Mason', 'submitted_on' => date('Y-m-d', strtotime('-12 days')), 'submitted_by' => 'Ram Bahadur', 'cv_file' => 'cv/sample-cv.pdf', 'profile_version' => 'v1',
            'client_response' => $n[2] === 'Pending' ? 'Pending' : 'Shortlisted', 'stage' => $n[1], 'result' => $n[2], 'result_reason' => $n[2] === 'Rejected' ? 'Failed block-work trade test' : null, 'decision_by' => 'Khalid Al Mansoori', 'visa_status' => in_array($n[1], ['Joined', 'Ready to Mobilise'], true) ? 'Issued' : ($n[1] === 'Visa Processing' ? 'Applied' : 'Not Started'),
            'travel_status' => $n[1] === 'Joined' ? 'Travelled' : 'Pending', 'company_travel_status' => $n[1] === 'Joined' ? 'Travelled' : ($n[1] === 'Ready to Mobilise' ? 'Arranged' : 'Pending'), 'joining_date' => $n[1] === 'Joined' ? date('Y-m-d', strtotime('-2 days')) : null, 'worksite' => 'Dubai South', 'wage' => 1600, 'slot_outcome' => $n[1] === 'Joined' ? 'Deployed' : ($n[2] === 'Rejected' ? 'Rejected' : 'Open'), 'planned_departure' => $n[1] === 'Ready to Mobilise' ? date('Y-m-d', strtotime('+4 days')) : null]);
        ins('worker_tests', $b + ['worker_id' => $id, 'demand_id' => $d1, 'kind' => 'Trade Test', 'location' => 'Kathmandu test centre', 'scheduled_at' => date('Y-m-d 10:00:00', strtotime('-10 days')), 'assessor' => 'Gulf Construction QA', 'attendance' => $n[1] === 'Submitted' ? 'Scheduled' : 'Attended', 'result' => $n[2] === 'Rejected' ? 'Fail' : ($n[2] === 'Selected' ? 'Pass' : 'Pending'), 'score' => $n[2] === 'Rejected' ? '48/100' : '80/100']);
        ins('documents', $b + ['ref_no' => next_number('DOC'), 'doc_type' => 'Passport', 'worker_id' => $id, 'doc_no' => 'NP' . (700100 + $i), 'status' => 'Verified', 'expiry' => date('Y-m-d', strtotime('+4 years'))]);
    }
    foreach ([['Mary Akinyi', 'Selected', 'Did Not Travel', 'Family emergency'], ['John Ochieng', 'Selected', 'Pending', null], ['Esther Nakato', 'Pending', 'Pending', null]] as $i => $n) {
        $id = ins('workers', $b + ['ref_no' => next_number('WRK'), 'full_name' => $n[0], 'passport_no' => 'KE' . (55100 + $i), 'nationality' => $i === 2 ? 'Uganda' : 'Kenya', 'demand_id' => $d3, 'agent_id' => $nbo, 'batch_id' => $bt2, 'position' => 'Cleaner', 'submitted_on' => date('Y-m-d', strtotime('-9 days')), 'submitted_by' => 'Grace Wanjiru',
            'client_response' => 'Pending', 'stage' => $n[1] === 'Selected' ? 'Documentation' : 'Submitted', 'result' => $n[1], 'visa_status' => 'Not Started', 'travel_status' => $n[2], 'no_travel_reason' => $n[3], 'company_travel_status' => $n[2] === 'Did Not Travel' ? 'Did Not Travel' : 'Pending', 'company_no_travel_reason' => $n[3] ? 'Worker withdrew before flight' : null, 'slot_outcome' => $n[2] === 'Did Not Travel' ? 'Withdrawn' : 'Open']);
        if ($i === 0) $mary = $id;
        if ($i === 1) q('UPDATE workers SET replacement_worker_id=? WHERE id=?', [$id, $mary]);
    }
    ins('documents', $b + ['ref_no' => next_number('DOC'), 'doc_type' => 'Medical Report', 'worker_id' => $mary, 'status' => 'Missing']);
    ins('issues', $b + ['ref_no' => next_number('ISS'), 'subject' => 'Worker did not travel — replacement needed', 'party' => 'Worker', 'demand_id' => $d3, 'worker_id' => $mary, 'category' => 'Replacement Needed', 'severity' => 'High', 'owner' => $u('ops'), 'due_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'In Progress', 'details' => 'Mary Akinyi withdrew because of a family emergency.', 'resolution' => 'Replacement John Ochieng arranged.']);
    ins('agent_comms', $b + ['agent_id' => $ktm, 'subject' => 'Batch 2 timeline', 'message' => 'Agent confirms 4 more masons by next week.', 'channel' => 'WhatsApp', 'follow_up' => date('Y-m-d', strtotime('+3 days')), 'assigned_to' => $u('ops'), 'status' => 'Open']);
    ins('client_feedback', $b + ['company_id' => $gulf, 'demand_id' => $d1, 'rating' => 4, 'feedback' => 'Good quality masons; please speed up visas.', 'source' => 'Phone']);
}, 30, true);
