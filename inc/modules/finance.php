<?php
// Module 2 — Finance, Accounting (double entry) and Invoices.

ent('accounts', ['label' => 'Chart of Accounts', 'one' => 'Account', 'title' => 'name', 'icon' => 'book', 'financial' => true, 'order' => 'code',
    'fields' => ['code' => ['Account code', 'text', 'req'], 'name' => ['Account name', 'text', 'req'], 'type' => ['Type', 'enum:Asset|Liability|Equity|Income|Expense', 'req'], 'description' => ['Description', 'text'], 'active' => ['Active', 'bool', 'default' => 1]],
    'list' => ['code', 'name', 'type', 'active'], 'filters' => ['type'], 'related' => false,
    'cols' => ['Balance' => function ($r) { return '<span class="num">' . money(account_balance($r)) . '</span>'; }],
    'before_save' => function (&$d, $old) { if (val('SELECT id FROM accounts WHERE code=? AND id<>?', [$d['code'], $old['id'] ?? 0])) return 'This account code already exists.'; return null; }]);

ent('bank_accounts', ['label' => 'Bank Accounts', 'one' => 'Bank account', 'title' => 'name', 'icon' => 'card', 'financial' => true,
    'fields' => ['name' => ['Account name', 'text', 'req'], 'bank' => ['Bank', 'text', 'req'], 'account_no' => ['Account number', 'text'], 'iban' => ['IBAN', 'text'], 'account_id' => ['Ledger account', 'ref:accounts', 'req', 'norel' => true], 'opening_balance' => ['Opening balance', 'money', 'default' => 0], 'currency' => ['Currency', 'enum:AED|USD', 'default' => 'AED']],
    'list' => ['name', 'bank', 'account_no', 'currency'],
    'cols' => ['Current balance' => function ($r) { return '<b class="num">' . money(bank_balance($r)) . '</b>'; }]]);

ent('bank_transactions', ['label' => 'Bank Transactions', 'one' => 'Bank transaction', 'prefix' => 'BTX', 'icon' => 'refresh', 'immutable' => true, 'financial' => true, 'order' => 'tx_date DESC, id DESC',
    'fields' => ['bank_account_id' => ['Bank account', 'ref:bank_accounts', 'req'], 'tx_date' => ['Date', 'date', 'req', 'default' => date('Y-m-d')], 'kind' => ['Type', 'enum:Deposit|Withdrawal', 'req'],
        'amount' => ['Amount', 'money', 'req'], 'counter_account_id' => ['Counter account (income / expense / other)', 'ref:accounts', 'req', 'norel' => true], 'reference' => ['Reference', 'text'], 'memo' => ['Memo', 'text'],
        'reversal_of' => ['Reversal of', 'ref:bank_transactions', 'hide', 'norel' => true], 'journal_id' => ['Journal', 'int', 'hide']],
    'list' => ['ref_no', 'tx_date', 'bank_account_id', 'kind', 'amount', 'counter_account_id', 'reference'], 'filters' => ['bank_account_id', 'kind'],
    'before_save' => function (&$d, $old) { if (($d['amount'] ?? 0) <= 0) return 'Amount must be greater than zero.'; return null; },
    'after_save' => function ($id, $d, $old) {
        if ($old) return;
        $ba = row('SELECT * FROM bank_accounts WHERE id=?', [$d['bank_account_id']]);
        $lines = $d['kind'] === 'Deposit' ? [[(int)$ba['account_id'], $d['amount'], 0], [(int)$d['counter_account_id'], 0, $d['amount']]] : [[(int)$d['counter_account_id'], $d['amount'], 0], [(int)$ba['account_id'], 0, $d['amount']]];
        $j = post_journal($d['tx_date'], 'Bank ' . strtolower($d['kind']) . ': ' . ($d['memo'] ?: $d['reference']), $lines, 'bank', $id);
        q('UPDATE bank_transactions SET journal_id=? WHERE id=?', [$j, $id]);
    },
    'actions' => ['reverse' => ['label' => 'Reverse', 'icon' => 'refresh', 'perm' => 'finance', 'show' => function ($r) { return !$r['reversal_of'] && !val('SELECT id FROM bank_transactions WHERE reversal_of=?', [$r['id']]); }, 'confirm' => 'Post a reversing transaction?',
        'form' => function ($r) { echo '<div class="fld"><label>Reason *</label><input name="reason" required></div><div class="form-actions"><button class="btn danger">Post reversal</button></div>'; },
        'do' => function ($r) {
            if (!p('reason')) return 'A reason is required.';
            $id = ins('bank_transactions', ['ref_no' => next_number('BTX'), 'bank_account_id' => $r['bank_account_id'], 'tx_date' => today(), 'kind' => $r['kind'] === 'Deposit' ? 'Withdrawal' : 'Deposit', 'amount' => $r['amount'], 'counter_account_id' => $r['counter_account_id'], 'reference' => 'Reversal of ' . $r['ref_no'], 'memo' => p('reason'), 'reversal_of' => $r['id'], 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
            if ($r['journal_id']) { $j = reverse_journal($r['journal_id'], p('reason')); q('UPDATE bank_transactions SET journal_id=? WHERE id=?', [$j, $id]); }
            audit('reverse', 'bank_transactions', $r['id'], null, ['reversal' => $id], p('reason'));
            return ['msg' => 'Reversal posted.', 'go' => ent_url('bank_transactions', $id)];
        }]]]);

ent('budgets', ['label' => 'Budgets', 'title' => 'name', 'icon' => 'pie',
    'fields' => ['name' => ['Budget name', 'text', 'req'], 'period_start' => ['Period start', 'date', 'req'], 'period_end' => ['Period end', 'date', 'req'], 'notes' => ['Notes', 'textarea']],
    'list' => ['name', 'period_start', 'period_end'], 'view' => function ($r) { budget_vs_actual($r); }]);
ent('budget_allocations', ['label' => 'Budget Allocations', 'one' => 'Allocation', 'title' => 'account_id', 'icon' => 'pie',
    'fields' => ['budget_id' => ['Budget', 'ref:budgets', 'req'], 'account_id' => ['Account / line', 'ref:accounts', 'req', 'norel' => true], 'amount' => ['Budgeted amount', 'money', 'req'], 'notes' => ['Notes', 'text']],
    'list' => ['budget_id', 'account_id', 'amount']]);

ent('finance_entries', ['label' => 'Expenses & Other Income', 'one' => 'Finance entry', 'prefix' => 'FIN', 'icon' => 'dollar', 'immutable' => true, 'financial' => true, 'bl' => 'General',
    'fields' => ['entry_date' => ['Date', 'date', 'req', 'default' => date('Y-m-d')], 'kind' => ['Type', 'enum:Expense|Other Income', 'req', 'default' => 'Expense'], 'category' => ['Category', 'opt:expense_categories', 'req'],
        'amount' => ['Amount', 'money', 'req'], 'account_id' => ['Ledger account', 'ref:accounts', 'req', 'norel' => true], 'paid_via' => ['Paid / received via', 'enum:Cash|Bank', 'req', 'default' => 'Bank'],
        'linked_record' => ['Linked record / reference (required)', 'text', 'req', 'help' => 'Bill number, demand number, file number… No payment exists without a reference.'], 'receipt_file' => ['Receipt', 'file', 'dir' => 'finance'], 'note' => ['Note', 'text'], 'journal_id' => ['Journal', 'int', 'hide']],
    'list' => ['ref_no', 'entry_date', 'kind', 'category', 'amount', 'paid_via', 'linked_record'], 'filters' => ['kind', 'category', 'paid_via'],
    'before_save' => function (&$d, $old) { if (($d['amount'] ?? 0) <= 0) return 'Amount must be greater than zero.'; return null; },
    'after_save' => function ($id, $d, $old) {
        if ($old) return;
        $cash = $d['paid_via'] === 'Cash' ? '1000' : '1010';
        $lines = $d['kind'] === 'Expense' ? [[(int)$d['account_id'], $d['amount'], 0], [$cash, 0, $d['amount']]] : [[$cash, $d['amount'], 0], [(int)$d['account_id'], 0, $d['amount']]];
        q('UPDATE finance_entries SET journal_id=? WHERE id=?', [post_journal($d['entry_date'], $d['kind'] . ': ' . $d['category'] . ' (' . $d['linked_record'] . ')', $lines, 'finance_entry', $id), $id]);
    }]);

ent('wages', ['label' => 'Wages', 'one' => 'Wage record', 'prefix' => 'WG', 'title' => 'period', 'title_ref' => true, 'icon' => 'dollar', 'financial' => true,
    'fields' => ['worker_id' => ['Outside worker', 'ref:workers'], 'employee_id' => ['Employee', 'ref:employees'], 'period' => ['Period (YYYY-MM)', 'text', 'req', 'default' => date('Y-m')], 'days' => ['Days worked', 'number'],
        'amount' => ['Amount', 'money', 'req'], 'status' => ['Status', 'enum:Pending|Approved|Paid', 'default' => 'Pending'], 'notes' => ['Notes', 'text']],
    'list' => ['ref_no', 'period', 'worker_id', 'employee_id', 'amount', 'status'], 'filters' => ['status', 'period'],
    'before_save' => function (&$d, $old) { if (empty($d['worker_id']) && empty($d['employee_id'])) return 'Link the wage to a worker or an employee.'; return null; },
    'actions' => ['pay' => pay_action('wages', 'Pay wage', 'finance', function ($r) { return $r['status'] !== 'Paid'; }, 'out')],
    'after_save' => function ($id, $d, $old) {}]);

ent('invoices', ['label' => 'Invoices', 'one' => 'Invoice', 'prefix' => 'INV', 'icon' => 'file-text', 'financial' => true, 'bl' => 'Outside Hiring',
    'fields' => ['party' => ['Bill to', 'enum:Client|Agent|Customer|Other', 'req', 'default' => 'Client'], 'company_id' => ['Client company', 'ref:companies'], 'agent_id' => ['Agent', 'ref:agents'], 'customer_name' => ['Customer name (if not a client)', 'text'],
        'demand_id' => ['Demand', 'ref:demands'], 'issue_date' => ['Issue date', 'date', 'req', 'default' => date('Y-m-d')], 'due_date' => ['Due date', 'date'], 'status' => ['Status', 'enum:Draft|Issued|Cancelled', 'hide'],
        'total' => ['Total', 'money', 'hide'], 'estimate_id' => ['From estimate', 'ref:estimates', 'hide', 'norel' => true], 'notes' => ['Notes / terms', 'textarea'], 'journal_id' => ['Journal', 'int', 'hide']],
    'list' => ['ref_no', 'party', 'company_id', 'issue_date', 'due_date', 'total', 'status'], 'filters' => ['status', 'company_id', 'party'],
    'cols' => ['Balance' => function ($r) { $b = invoice_balance($r); return $b['balance'] > 0.005 ? '<b class="num">' . money($b['balance']) . '</b>' : ($r['total'] > 0 ? badge('Paid') : ''); }, 'Payment' => function ($r) { return badge(invoice_balance($r)['state']); }],
    'before_save' => function (&$d, $old) { if (!$old) { $d['status'] = 'Draft'; $d['total'] = 0; } if (($d['party'] ?? '') === 'Client' && empty($d['company_id'])) return 'Choose the client company.'; return null; },
    'can_edit' => function ($r) { return $r['status'] === 'Draft'; },
    'view' => function ($r) { invoice_panel($r); },
    'view_after' => function ($r, $mod, $base) { payments_panel('invoices', $r['id'], $mod, $base); },
    'actions' => [
        'issue' => ['label' => 'Issue invoice', 'icon' => 'send', 'perm' => 'finance', 'show' => function ($r) { return $r['status'] === 'Draft'; },
            'form' => function ($r) { echo '<p>Issuing locks the invoice and posts it to Accounts Receivable. Total: <b>' . money(invoice_total($r['id'])) . '</b></p><div class="form-actions"><button class="btn">Issue</button></div>'; },
            'do' => function ($r) { $t = invoice_total($r['id']); if ($t <= 0) return 'Add invoice items first.'; $j = post_journal($r['issue_date'], 'Invoice ' . $r['ref_no'], [['1100', $t, 0], ['4100', 0, $t]], 'invoice', $r['id']); upd('invoices', $r['id'], ['status' => 'Issued', 'total' => $t, 'journal_id' => $j, 'updated_at' => now()]); audit('issue', 'invoices', $r['id'], null, ['total' => $t]); return null; }],
        'pay' => pay_action('invoices', 'Receive payment', 'finance', function ($r) { return $r['status'] === 'Issued' && invoice_balance($r)['balance'] > 0.005; }),
        'cancel' => ['label' => 'Cancel', 'icon' => 'x', 'perm' => 'finance', 'show' => function ($r) { return $r['status'] === 'Draft' || ($r['status'] === 'Issued' && !paid_total('invoices', $r['id'])); }, 'confirm' => 'Cancel this invoice?',
            'form' => function ($r) { echo '<div class="fld"><label>Reason *</label><input name="reason" required></div><div class="form-actions"><button class="btn danger">Cancel invoice</button></div>'; },
            'do' => function ($r) { if (!p('reason')) return 'Reason required.'; if ($r['journal_id']) reverse_journal($r['journal_id'], p('reason')); upd('invoices', $r['id'], ['status' => 'Cancelled', 'updated_at' => now()]); audit('cancel', 'invoices', $r['id'], null, null, p('reason')); return null; }],
    ]]);
ent('invoice_items', ['label' => 'Invoice Items', 'one' => 'Invoice item', 'title' => 'description', 'icon' => 'file',
    'fields' => ['invoice_id' => ['Invoice', 'ref:invoices', 'req'], 'description' => ['Description', 'text', 'req'], 'qty' => ['Quantity', 'number', 'req', 'default' => 1], 'unit_price' => ['Unit price', 'money', 'req'], 'line_total' => ['Line total', 'money', 'hide']],
    'list' => ['invoice_id', 'description', 'qty', 'unit_price', 'line_total'],
    'before_save' => function (&$d, $old) { $inv = row('SELECT status FROM invoices WHERE id=?', [$d['invoice_id'] ?? $old['invoice_id']]); if ($inv && $inv['status'] !== 'Draft') return 'Items can only change while the invoice is a draft.'; $d['line_total'] = round(($d['qty'] ?? 0) * ($d['unit_price'] ?? 0), 2); return null; },
    'after_save' => function ($id, $d, $old) { q('UPDATE invoices SET total=? WHERE id=?', [invoice_total($d['invoice_id']), $d['invoice_id']]); }]);
ent('estimates', ['label' => 'Estimates', 'one' => 'Estimate', 'prefix' => 'EST', 'icon' => 'file-text',
    'fields' => ['party' => ['For', 'enum:Client|Customer|Other', 'req', 'default' => 'Client'], 'company_id' => ['Client company', 'ref:companies'], 'customer_name' => ['Customer name', 'text'], 'demand_id' => ['Demand', 'ref:demands'],
        'issue_date' => ['Date', 'date', 'req', 'default' => date('Y-m-d')], 'valid_until' => ['Valid until', 'date'], 'status' => ['Status', 'enum:Draft|Sent|Accepted|Declined|Converted', 'default' => 'Draft'], 'notes' => ['Notes', 'textarea']],
    'list' => ['ref_no', 'party', 'company_id', 'issue_date', 'valid_until', 'status'], 'filters' => ['status'],
    'cols' => ['Total' => function ($r) { return money(val('SELECT COALESCE(SUM(line_total),0) FROM estimate_items WHERE estimate_id=? AND archived=0', [$r['id']])); }],
    'actions' => ['convert' => ['label' => 'Convert to invoice', 'icon' => 'arrow-right', 'perm' => 'finance', 'show' => function ($r) { return $r['status'] !== 'Converted'; },
        'form' => function ($r) { echo '<p>Create a draft invoice with the same items.</p><div class="form-actions"><button class="btn">Convert</button></div>'; },
        'do' => function ($r) {
            $items = rows('SELECT * FROM estimate_items WHERE estimate_id=? AND archived=0', [$r['id']]);
            if (!$items) return 'The estimate has no items.';
            $inv = ins('invoices', ['ref_no' => next_number('INV'), 'party' => $r['party'], 'company_id' => $r['company_id'], 'customer_name' => $r['customer_name'], 'demand_id' => $r['demand_id'], 'issue_date' => today(), 'due_date' => date('Y-m-d', strtotime('+30 days')), 'status' => 'Draft', 'estimate_id' => $r['id'], 'business_line' => 'Outside Hiring', 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
            foreach ($items as $it) ins('invoice_items', ['invoice_id' => $inv, 'description' => $it['description'], 'qty' => $it['qty'], 'unit_price' => $it['unit_price'], 'line_total' => $it['line_total'], 'created_at' => now(), 'archived' => 0]);
            q('UPDATE invoices SET total=? WHERE id=?', [invoice_total($inv), $inv]);
            upd('estimates', $r['id'], ['status' => 'Converted', 'updated_at' => now()]);
            audit('convert', 'estimates', $r['id'], null, ['invoice' => $inv]);
            return ['msg' => 'Estimate converted to a draft invoice.', 'go' => ent_url('invoices', $inv)];
        }]]]);
ent('estimate_items', ['label' => 'Estimate Items', 'one' => 'Estimate item', 'title' => 'description', 'icon' => 'file',
    'fields' => ['estimate_id' => ['Estimate', 'ref:estimates', 'req'], 'description' => ['Description', 'text', 'req'], 'qty' => ['Quantity', 'number', 'req', 'default' => 1], 'unit_price' => ['Unit price', 'money', 'req'], 'line_total' => ['Line total', 'money', 'hide']],
    'list' => ['estimate_id', 'description', 'qty', 'unit_price', 'line_total'],
    'before_save' => function (&$d, $old) { $d['line_total'] = round(($d['qty'] ?? 0) * ($d['unit_price'] ?? 0), 2); return null; }]);
ent('credit_notes', ['label' => 'Credit Notes', 'one' => 'Credit note', 'prefix' => 'CN', 'icon' => 'file', 'immutable' => true, 'financial' => true,
    'fields' => ['invoice_id' => ['Invoice', 'ref:invoices', 'req'], 'note_date' => ['Date', 'date', 'req', 'default' => date('Y-m-d')], 'amount' => ['Amount', 'money', 'req'], 'reason' => ['Reason', 'text', 'req'], 'journal_id' => ['Journal', 'int', 'hide']],
    'list' => ['ref_no', 'invoice_id', 'note_date', 'amount', 'reason'],
    'before_save' => function (&$d, $old) { $inv = row('SELECT * FROM invoices WHERE id=?', [$d['invoice_id']]); if (!$inv || $inv['status'] !== 'Issued') return 'Credit notes apply to issued invoices only.'; if ($d['amount'] <= 0 || $d['amount'] > invoice_balance($inv)['balance'] + 0.005) return 'The amount must be positive and not more than the invoice balance.'; return null; },
    'after_save' => function ($id, $d, $old) { if (!$old) q('UPDATE credit_notes SET journal_id=? WHERE id=?', [post_journal($d['note_date'], 'Credit note on invoice #' . $d['invoice_id'] . ': ' . $d['reason'], [['4100', $d['amount'], 0], ['1100', 0, $d['amount']]], 'credit_note', $id), $id]); }]);
ent('invoice_returns', ['label' => 'Invoice Returns', 'one' => 'Invoice return', 'prefix' => 'RET', 'icon' => 'refresh', 'immutable' => true, 'financial' => true,
    'fields' => ['invoice_id' => ['Invoice', 'ref:invoices', 'req'], 'return_date' => ['Date', 'date', 'req', 'default' => date('Y-m-d')], 'amount' => ['Amount', 'money', 'req'], 'reason' => ['Reason', 'text', 'req'], 'journal_id' => ['Journal', 'int', 'hide']],
    'list' => ['ref_no', 'invoice_id', 'return_date', 'amount', 'reason'],
    'before_save' => function (&$d, $old) { $inv = row('SELECT * FROM invoices WHERE id=?', [$d['invoice_id']]); if (!$inv || $inv['status'] !== 'Issued') return 'Returns apply to issued invoices only.'; if ($d['amount'] <= 0 || $d['amount'] > invoice_balance($inv)['balance'] + 0.005) return 'The amount must be positive and not more than the invoice balance.'; return null; },
    'after_save' => function ($id, $d, $old) { if (!$old) q('UPDATE invoice_returns SET journal_id=? WHERE id=?', [post_journal($d['return_date'], 'Return on invoice #' . $d['invoice_id'] . ': ' . $d['reason'], [['4900', $d['amount'], 0], ['1100', 0, $d['amount']]], 'invoice_return', $id), $id]); }]);

// ---------------------------------------------------------------- calculations

function account_balance($a)
{
    $s = row('SELECT COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM journal_lines WHERE account_id=?', [$a['id']]);
    return in_array($a['type'], ['Asset', 'Expense'], true) ? $s['d'] - $s['c'] : $s['c'] - $s['d'];
}
function bank_balance($ba)
{
    $dep = (float)val("SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE bank_account_id=? AND kind='Deposit' AND archived=0", [$ba['id']]);
    $wd = (float)val("SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE bank_account_id=? AND kind='Withdrawal' AND archived=0", [$ba['id']]);
    return (float)$ba['opening_balance'] + $dep - $wd;
}
function invoice_total($id) { return (float)val('SELECT COALESCE(SUM(line_total),0) FROM invoice_items WHERE invoice_id=? AND archived=0', [$id]); }
function invoice_balance($inv)
{
    $total = $inv['status'] === 'Draft' ? invoice_total($inv['id']) : (float)$inv['total'];
    $cn = (float)val('SELECT COALESCE(SUM(amount),0) FROM credit_notes WHERE invoice_id=? AND archived=0', [$inv['id']]);
    $ret = (float)val('SELECT COALESCE(SUM(amount),0) FROM invoice_returns WHERE invoice_id=? AND archived=0', [$inv['id']]);
    $paid = paid_total('invoices', $inv['id']);
    $bal = round($total - $cn - $ret - $paid, 2);
    $state = $inv['status'] === 'Cancelled' ? 'Cancelled' : ($inv['status'] === 'Draft' ? 'Draft' : ($bal <= 0.005 ? 'Paid' : ($paid > 0 ? 'Partially paid' : ($inv['due_date'] && $inv['due_date'] < today() ? 'Overdue' : 'Issued'))));
    return compact('total', 'cn', 'ret', 'paid', 'bal', 'state') + ['balance' => $bal];
}
function invoice_panel($inv)
{
    $b = invoice_balance($inv);
    $items = rows('SELECT * FROM invoice_items WHERE invoice_id=? AND archived=0', [$inv['id']]);
    card_open(icon('file-text', 17) . ' Invoice ' . e($inv['ref_no']), '<button class="btn ghost sm no-print" onclick="window.print()">' . icon('printer', 14) . ' Print</button>');
    echo '<div class="grid g2" style="margin-bottom:12px"><div><b>' . e(setting('company_name')) . '</b><br><span class="muted small">' . e(setting('company_address')) . ' · ' . e(setting('company_phone')) . '</span></div><div class="right"><span class="muted small">Bill to</span><br><b>' . e($inv['company_id'] ? ent_title('companies', $inv['company_id']) : ($inv['agent_id'] ? ent_title('agents', $inv['agent_id']) : $inv['customer_name'])) . '</b><br><span class="small">Issued ' . e(dfmt($inv['issue_date'])) . ' · Due ' . e(dfmt($inv['due_date'])) . '</span></div></div>';
    $data = array_map(function ($i) { return [e($i['description']), num($i['qty']), money($i['unit_price']), '<span class="num">' . money($i['line_total']) . '</span>']; }, $items);
    echo simple_table(['Description', 'Qty', 'Unit price', 'Amount'], $data, 'No items yet — add items below (draft only).');
    echo '<div class="kv"><div><span>Total</span><b>' . money($b['total']) . '</b></div><div><span>Credit notes</span><b>' . money($b['cn']) . '</b></div><div><span>Returns</span><b>' . money($b['ret']) . '</b></div><div class="good"><span>Paid</span><b>' . money($b['paid']) . '</b></div><div class="' . ($b['balance'] > 0 ? 'bad' : 'good') . '"><span>Balance</span><b>' . money($b['balance']) . '</b></div><div><span>State</span><b>' . e($b['state']) . '</b></div></div>';
    card_close();
}
function budget_vs_actual($b)
{
    $al = rows('SELECT ba.*, a.name, a.type, a.code FROM budget_allocations ba JOIN accounts a ON a.id=ba.account_id WHERE ba.budget_id=? AND ba.archived=0', [$b['id']]);
    $data = [];
    foreach ($al as $x) {
        $s = row('SELECT COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c FROM journal_lines l JOIN journal_entries j ON j.id=l.entry_id WHERE l.account_id=? AND j.entry_date BETWEEN ? AND ?', [$x['account_id'], $b['period_start'], $b['period_end']]);
        $act = in_array($x['type'], ['Asset', 'Expense'], true) ? $s['d'] - $s['c'] : $s['c'] - $s['d'];
        $data[] = [e($x['code'] . ' · ' . $x['name']), money($x['amount']), money($act), money($x['amount'] - $act), progress($x['amount'] > 0 ? $act / $x['amount'] * 100 : 0)];
    }
    card_open(icon('pie', 17) . ' Budget versus actual (' . e(dfmt($b['period_start'])) . ' – ' . e(dfmt($b['period_end'])) . ')');
    echo simple_table(['Line', 'Budget', 'Actual', 'Remaining', 'Used'], $data, 'No allocations yet.');
    card_close();
}
function party_label($pm)
{
    $map = ['inside_files' => 'Inside file', 'invoices' => 'Invoice', 'agents' => 'Agent', 'travel_bookings' => 'Travel booking', 'paybook_files' => 'Paybook file', 'wages' => 'Wage', 'pos_sales' => 'POS sale', 'vendor_bills' => 'Vendor bill', 'payroll' => 'Payroll'];
    $u = ent_url($pm['entity'], $pm['entity_id']);
    $t = ($map[$pm['entity']] ?? $pm['entity']) . ' · ' . ent_title($pm['entity'], $pm['entity_id']);
    return $u ? '<a href="' . e($u) . '">' . e($t) . '</a>' : e($t);
}

// ---------------------------------------------------------------- screens

mod('finance', ['label' => 'Finance', 'icon' => 'dollar', 'area' => 'Money', 'order' => 1, 'desc' => 'Payment ledger, candidate / agent / client balances, wages, expenses and daily finance reports.',
    'tabs' => [
        'dash' => ['label' => 'Dashboard', 'fn' => function ($base, $mod) { finance_dashboard(); }],
        'ledger' => ['label' => 'Payment ledger', 'post' => function ($base, $mod) {
            if (g('a') === 'export') { require_action($mod, 'export'); $d = rows('SELECT * FROM payments ORDER BY id'); csv_out('payment-ledger-' . date('Ymd') . '.csv', ['Ref', 'Date', 'Linked to', 'Direction', 'Type', 'Method', 'Amount', 'Reversed', 'Reversal of', 'Reason'], array_map(function ($p) { return [$p['ref_no'], $p['pay_date'], $p['entity'] . ' #' . $p['entity_id'] . ' ' . ent_title($p['entity'], $p['entity_id']), $p['direction'], $p['pay_type'], $p['method'], $p['amount'], $p['reversed'] ? 'Yes' : '', $p['reversal_of'], $p['reason']]; }, $d), 'payments'); }
            if (is_post() && g('a') === 'reverse') { require_action($mod, 'finance'); $err = reverse_payment((int)p('pid'), p('reason')); flash($err ?: 'Payment reversed.', $err ? 'bad' : 'ok'); redirect($base); }
        }, 'fn' => function ($base, $mod) {
            $w = ['1=1']; $pp = [];
            if (g('dir')) { $w[] = 'direction=?'; $pp[] = g('dir'); }
            if (g('ent')) { $w[] = 'entity=?'; $pp[] = g('ent'); }
            if (g('from')) { $w[] = 'pay_date>=?'; $pp[] = g('from'); }
            if (g('to')) { $w[] = 'pay_date<=?'; $pp[] = g('to'); }
            if (g('q')) { $w[] = 'ref_no LIKE ?'; $pp[] = '%' . g('q') . '%'; }
            $list = rows('SELECT * FROM payments WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 300', $pp);
            echo '<form class="toolbar filters" method="get"><input type="hidden" name="t" value="ledger"><div class="search-in">' . icon('search', 15) . '<input name="q" value="' . e(g('q')) . '" placeholder="Receipt number"></div><select name="dir" data-autosubmit><option value="">In and out</option><option value="in"' . (g('dir') === 'in' ? ' selected' : '') . '>Money in</option><option value="out"' . (g('dir') === 'out' ? ' selected' : '') . '>Money out</option></select><select name="ent" data-autosubmit><option value="">All records</option>';
            foreach (['inside_files' => 'Inside files', 'invoices' => 'Invoices', 'agents' => 'Agents', 'travel_bookings' => 'Travel bookings', 'paybook_files' => 'Paybook', 'wages' => 'Wages', 'pos_sales' => 'POS', 'vendor_bills' => 'Vendor bills', 'payroll' => 'Payroll'] as $k => $l) echo '<option value="' . $k . '"' . (g('ent') === $k ? ' selected' : '') . '>' . $l . '</option>';
            echo '</select><input type="date" name="from" value="' . e(g('from')) . '"><input type="date" name="to" value="' . e(g('to')) . '"><button class="btn ghost sm">Filter</button>' . (can($mod, 'export') ? '<a class="btn ghost sm" href="' . e(u($base, ['a' => 'export'])) . '">' . icon('download', 14) . ' Export</a>' : '') . '</form>';
            $in = 0; $out = 0;
            $data = array_map(function ($pm) use (&$in, &$out, $base, $mod) {
                if ($pm['direction'] === 'in') $in += $pm['amount']; else $out += $pm['amount'];
                $rev = !$pm['reversed'] && !$pm['reversal_of'] && can($mod, 'finance') ? '<form method="post" action="' . e(u($base, ['a' => 'reverse'])) . '" class="inline-form" data-confirm="Reverse ' . e($pm['ref_no']) . '?">' . csrf_field() . '<input type="hidden" name="pid" value="' . $pm['id'] . '"><input name="reason" required placeholder="Reason" style="width:110px;padding:5px 8px"><button class="btn ghost sm danger">Reverse</button></form>' : '';
                return ['<b class="ref">' . e($pm['ref_no']) . '</b>', e(dfmt($pm['pay_date'])), party_label($pm), $pm['direction'] === 'in' ? badge('In') : '<span class="badge b-warn">Out</span>', e($pm['pay_type']), e($pm['method']), '<span class="num">' . money($pm['amount']) . '</span>', $pm['reversed'] ? badge('Reversed') : ($pm['reversal_of'] ? badge('Reversal') . '<br><small>' . e($pm['reason']) . '</small>' : ''), $rev];
            }, $list);
            card_open(icon('book', 17) . ' Ledger', '<span class="small">In <b>' . money($in) . '</b> · Out <b>' . money($out) . '</b></span>');
            echo simple_table(['Ref', 'Date', 'Linked record', 'Dir', 'Type', 'Method', 'Amount', 'Status', ''], $data, 'No entries.');
            card_close();
        }],
        'candidates' => ['label' => 'Candidate balances', 'fn' => function ($base, $mod) { $GLOBALS['MODS']['inside']['tabs']['dues']['fn']($base, $mod); }],
        'agents' => ['label' => 'Agent balances', 'fn' => function ($base, $mod) {
            $ag = rows('SELECT * FROM agents WHERE archived=0 ORDER BY agency_name');
            card_open(icon('globe', 17) . ' Agent balances — reconciled per agent');
            echo simple_table(['Agent', 'Due (joined × fee)', 'Paid', 'Outstanding', 'Disputes'], array_map(function ($a) { $b = agent_balance($a['id']); return ['<b>' . e($a['agency_name']) . '</b>', money($b['due']), money($b['paid']), '<b>' . money($b['outstanding']) . '</b>', $b['disputed'] ? badge($b['disputed'] . ' open') : '']; }, $ag), 'No agents.', array_map(function ($a) { return ent_url('agents', $a['id']); }, $ag));
            card_close();
        }],
        'clients' => ['label' => 'Client balances', 'fn' => function ($base, $mod) {
            $cs = rows('SELECT * FROM companies WHERE archived=0 ORDER BY display_name');
            $data = []; $links = [];
            foreach ($cs as $c) {
                $t = ['total' => 0, 'paid' => 0, 'balance' => 0, 'over' => 0];
                foreach (rows("SELECT * FROM invoices WHERE company_id=? AND status='Issued' AND archived=0", [$c['id']]) as $i) { $b = invoice_balance($i); $t['total'] += $b['total']; $t['paid'] += $b['paid']; $t['balance'] += $b['balance']; if ($b['state'] === 'Overdue') $t['over'] += $b['balance']; }
                $disp = (int)val("SELECT COUNT(*) FROM issues WHERE party='Client' AND category='Payment Dispute' AND status IN ('Open','In Progress') AND demand_id IN (SELECT id FROM demands WHERE company_id=?)", [$c['id']]);
                $data[] = ['<b>' . e($c['display_name']) . '</b>', money($t['total']), money($t['paid']), '<b>' . money($t['balance']) . '</b>', $t['over'] ? '<b style="color:var(--bad)">' . money($t['over']) . '</b>' : '—', $disp ? badge($disp . ' disputed') : ''];
                $links[] = ent_url('companies', $c['id']);
            }
            card_open(icon('building', 17) . ' Client balances — reconciled per company');
            echo simple_table(['Client', 'Invoiced', 'Paid', 'Outstanding', 'Overdue', 'Disputes'], $data, 'No clients.', $links);
            card_close();
        }],
        'wages' => 'wages', 'expenses' => 'finance_entries',
        'daily' => ['label' => 'Daily report', 'fn' => function ($base, $mod) { finance_daily(); }],
    ]]);
mod('accounting', ['label' => 'Accounting', 'icon' => 'book', 'area' => 'Money', 'order' => 2, 'desc' => 'Double-entry journals, chart of accounts, bank accounts, balance sheet and budgets.',
    'tabs' => [
        'journal' => ['label' => 'Journal', 'post' => function ($base, $mod) {
            if (!is_post()) return;
            require_action($mod, 'finance');
            if (g('a') === 'reverse') { if (!p('reason')) { flash('A reason is required.', 'bad'); redirect($base); } reverse_journal((int)p('jid'), p('reason')); flash('Journal entry reversed.'); redirect($base); }
            $lines = [];
            foreach ((array)($_POST['account'] ?? []) as $i => $acc) { $dr = (float)($_POST['debit'][$i] ?? 0); $cr = (float)($_POST['credit'][$i] ?? 0); if ($acc && ($dr || $cr)) $lines[] = [(int)$acc, $dr, $cr]; }
            try { $id = post_journal(p('entry_date'), p('memo'), $lines, 'manual'); audit('create', 'journal_entries', $id, null, ['memo' => p('memo')]); flash('Journal entry posted.'); }
            catch (RuntimeException $ex) { flash($ex->getMessage() . ' Debit must equal credit.', 'bad'); }
            redirect($base);
        }, 'fn' => function ($base, $mod) {
            if (can($mod, 'finance')) {
                $accs = rows('SELECT * FROM accounts WHERE active=1 AND archived=0 ORDER BY code');
                $opt = '<option value="">— Account —</option>';
                foreach ($accs as $a) $opt .= '<option value="' . $a['id'] . '">' . e($a['code'] . ' · ' . $a['name']) . '</option>';
                echo '<details class="card"' . (g('new') ? ' open' : '') . '><summary class="btn" style="width:max-content">' . icon('plus', 15) . ' Add New journal entry</summary><form method="post" style="margin-top:14px">' . csrf_field() . '<div class="grid-form"><div class="fld"><label>Date *</label><input type="date" name="entry_date" value="' . today() . '" required></div><div class="fld"><label>Memo *</label><input name="memo" required></div></div>'
                    . '<table class="table lines" style="margin-top:12px"><thead><tr><th>Account</th><th>Debit</th><th>Credit</th></tr></thead><tbody id="jl">';
                for ($i = 0; $i < 2; $i++) echo '<tr><td><select name="account[]">' . $opt . '</select></td><td><input type="number" step="0.01" name="debit[]"></td><td><input type="number" step="0.01" name="credit[]"></td></tr>';
                echo '</tbody></table><div class="form-actions" style="justify-content:space-between"><span><button type="button" class="btn ghost sm" data-add-line="#jl">' . icon('plus', 14) . ' Add line</button> <span id="jtotal"></span></span><button class="btn">Post entry</button></div></form></details>';
            }
            $list = rows('SELECT * FROM journal_entries ORDER BY id DESC LIMIT 100');
            $data = [];
            foreach ($list as $j) {
                $ls = rows('SELECT l.*, a.code, a.name FROM journal_lines l JOIN accounts a ON a.id=l.account_id WHERE entry_id=?', [$j['id']]);
                $txt = implode('<br>', array_map(function ($l) { return '<small>' . e($l['code'] . ' ' . $l['name']) . ($l['debit'] > 0 ? ' · Dr ' . num($l['debit']) : '') . ($l['credit'] > 0 ? ' · Cr ' . num($l['credit']) : '') . '</small>'; }, $ls));
                $rev = !$j['reversed'] && !$j['reversal_of'] && can($mod, 'finance') ? '<form method="post" action="' . e(u($base, ['a' => 'reverse'])) . '" class="inline-form" data-confirm="Reverse this journal entry?">' . csrf_field() . '<input type="hidden" name="jid" value="' . $j['id'] . '"><input name="reason" required placeholder="Reason" style="width:110px;padding:5px 8px"><button class="btn ghost sm danger">Reverse</button></form>' : '';
                $data[] = ['<b class="ref">' . e($j['ref_no']) . '</b>', e(dfmt($j['entry_date'])), e($j['memo']), $txt, money(array_sum(array_column($ls, 'debit'))), e($j['source']), $j['reversed'] ? badge('Reversed') : ($j['reversal_of'] ? badge('Reversal') : ''), $rev];
            }
            card_open(icon('book', 17) . ' Journal entries (debit always equals credit)');
            echo simple_table(['Entry', 'Date', 'Memo', 'Lines', 'Amount', 'Source', 'Status', ''], $data, 'No entries.');
            card_close();
        }],
        'accounts' => 'accounts', 'banks' => 'bank_accounts', 'banktx' => 'bank_transactions',
        'balance' => ['label' => 'Balance sheet', 'fn' => function ($base, $mod) { balance_sheet(); }],
        'trial' => ['label' => 'Trial balance', 'fn' => function ($base, $mod) {
            $data = []; $td = 0; $tc = 0;
            foreach (rows('SELECT a.*, COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c FROM accounts a LEFT JOIN journal_lines l ON l.account_id=a.id GROUP BY a.id ORDER BY a.code') as $a) { if (!$a['d'] && !$a['c']) continue; $td += $a['d']; $tc += $a['c']; $data[] = [e($a['code']), e($a['name']), e($a['type']), '<span class="num">' . money($a['d']) . '</span>', '<span class="num">' . money($a['c']) . '</span>']; }
            $data[] = ['', '<b>Total</b>', '', '<b class="num">' . money($td) . '</b>', '<b class="num">' . money($tc) . '</b>'];
            card_open(icon('book', 17) . ' Trial balance', abs($td - $tc) < 0.01 ? badge('Balanced') : badge('Not balanced'));
            echo simple_table(['Code', 'Account', 'Type', 'Debit', 'Credit'], $data);
            card_close();
        }],
        'budgets' => 'budgets', 'allocations' => 'budget_allocations',
    ]]);
mod('invoices', ['label' => 'Invoices', 'icon' => 'file-text', 'area' => 'Money', 'order' => 3, 'desc' => 'Estimates convert to invoices; invoices take partial payments, credit notes and returns.',
    'tabs' => ['invoices' => 'invoices', 'items' => 'invoice_items', 'estimates' => 'estimates', 'estimate_items' => 'estimate_items', 'credit' => 'credit_notes', 'returns' => 'invoice_returns']]);

function finance_dashboard()
{
    render_widgets(['finance', 'invoices', 'accounting']);
    inside_reconciliation();
    $byEnt = [];
    foreach (rows("SELECT entity e, SUM(amount) s FROM payments WHERE direction='in' GROUP BY entity") as $r) $byEnt[ucwords(str_replace('_', ' ', $r['e']))] = (float)$r['s'];
    $out = [];
    foreach (rows("SELECT COALESCE(pay_type,entity) e, SUM(amount) s FROM payments WHERE direction='out' GROUP BY e") as $r) $out[$r['e']] = (float)$r['s'];
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>Money in by source</h3></div>' . chart_donut($byEnt) . '</div><div class="card"><div class="card-h"><h3>Money out by type</h3></div>' . chart_bars($out, 'money') . '</div></div>';
    finance_aging();
}
function finance_aging()
{
    $b = ['Current' => 0, '1–30 days' => 0, '31–60 days' => 0, '61–90 days' => 0, '90+ days' => 0];
    $add = function ($due, $amt) use (&$b) { if ($amt <= 0) return; if (!$due || $due >= today()) { $b['Current'] += $amt; return; } $d = (time() - strtotime($due)) / 86400; $b[$d <= 30 ? '1–30 days' : ($d <= 60 ? '31–60 days' : ($d <= 90 ? '61–90 days' : '90+ days'))] += $amt; };
    foreach (rows("SELECT * FROM inside_files WHERE status='Open' AND archived=0") as $f) $add($f['next_pay_date'], file_money($f)['balance']);
    $ib = $b; $b = array_fill_keys(array_keys($b), 0);
    foreach (rows("SELECT * FROM invoices WHERE status='Issued' AND archived=0") as $i) $add($i['due_date'], invoice_balance($i)['balance']);
    $cb = $b;
    card_open(icon('clock', 17) . ' Finance aging');
    $rowsOut = [];
    foreach (array_keys($ib) as $k) $rowsOut[] = [e($k), money($ib[$k]), money($cb[$k]), '<b>' . money($ib[$k] + $cb[$k]) . '</b>'];
    echo simple_table(['Age', 'Candidate balances', 'Client invoices', 'Total'], $rowsOut);
    card_close();
}
function finance_daily()
{
    $from = g('from', date('Y-m-d', strtotime('-13 days')));
    $data = [];
    for ($d = strtotime(today()); $d >= strtotime($from); $d -= 86400) {
        $day = date('Y-m-d', $d);
        $in = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction='in' AND amount>0 AND pay_date=?", [$day]);
        $ref = -(float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE amount<0 AND pay_date=?", [$day]);
        $out = (float)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction='out' AND amount>0 AND pay_date=?", [$day]);
        $exp = (float)val("SELECT COALESCE(SUM(amount),0) FROM finance_entries WHERE kind='Expense' AND entry_date=? AND archived=0", [$day]);
        $cnt = (int)val('SELECT COUNT(*) FROM payments WHERE pay_date=?', [$day]);
        $data[] = ['<b>' . e(date('D d M', $d)) . '</b>', money($in), money($ref), money($out), money($exp), '<b>' . money($in - $ref - $out - $exp) . '</b>', $cnt];
    }
    echo '<form class="filters" method="get" style="margin-bottom:12px"><input type="hidden" name="t" value="daily"><label class="small">From <input type="date" name="from" value="' . e($from) . '" data-autosubmit></label></form>';
    card_open(icon('calendar', 17) . ' Daily collections, refunds and payments');
    echo simple_table(['Day', 'Collected', 'Refunded / reversed', 'Paid out', 'Expenses', 'Net', 'Entries'], $data);
    card_close();
    $byOff = [];
    foreach (rows("SELECT created_by u, SUM(amount) s FROM payments WHERE direction='in' AND pay_date>=? GROUP BY created_by", [$from]) as $r) $byOff[user_name($r['u']) ?: 'System'] = (float)$r['s'];
    echo '<div class="card"><div class="card-h"><h3>Collected by officer (posted by)</h3></div>' . chart_bars($byOff, 'money') . '</div>';
}
function balance_sheet()
{
    $tot = ['Asset' => 0, 'Liability' => 0, 'Equity' => 0, 'Income' => 0, 'Expense' => 0];
    $by = [];
    foreach (rows('SELECT * FROM accounts WHERE archived=0 ORDER BY code') as $a) { $b = account_balance($a); $tot[$a['type']] += $b; if (abs($b) > 0.004) $by[$a['type']][] = [$a, $b]; }
    $earn = $tot['Income'] - $tot['Expense'];
    $sec = function ($type, $extra = []) use ($by) {
        $r = [];
        foreach ($by[$type] ?? [] as $x) $r[] = [e($x[0]['code'] . ' · ' . $x[0]['name']), '<span class="num">' . money($x[1]) . '</span>'];
        foreach ($extra as $k => $v) $r[] = [e($k), '<span class="num">' . money($v) . '</span>'];
        return simple_table(['Account', 'Balance'], $r, 'None.');
    };
    echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>Assets</h3><b>' . money($tot['Asset']) . '</b></div>' . $sec('Asset') . '</div><div class="card"><div class="card-h"><h3>Liabilities &amp; equity</h3><b>' . money($tot['Liability'] + $tot['Equity'] + $earn) . '</b></div>' . $sec('Liability') . $sec('Equity', ['Current period earnings (income − expenses)' => $earn]) . '</div></div>';
    $ok = abs($tot['Asset'] - ($tot['Liability'] + $tot['Equity'] + $earn)) < 0.01;
    echo '<div class="alert ' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'The balance sheet balances: assets equal liabilities plus equity.' : 'The balance sheet does not balance — check journal entries.') . '</div>';
    echo '<div class="kv"><div class="good"><span>Income</span><b>' . money($tot['Income']) . '</b></div><div class="bad"><span>Expenses</span><b>' . money($tot['Expense']) . '</b></div><div class="hl"><span>Net profit</span><b>' . money($earn) . '</b></div></div>';
}

widget('finance', 'Collected today', function () { return money(val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction='in' AND pay_date=?", [today()])); }, 'finance.php?t=daily', 'dollar', 'green');
widget('finance', 'Collected this month', function () { return money(val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction='in' AND pay_date>=?", [date('Y-m-01')])); }, 'finance.php?t=ledger', 'bar-chart', 'blue');
widget('invoices', 'Unpaid invoices', function () { $t = 0; foreach (rows("SELECT * FROM invoices WHERE status='Issued' AND archived=0") as $i) $t += invoice_balance($i)['balance']; return money($t); }, 'invoices.php', 'file-text', 'amber');
widget('accounting', 'Bank balance', function () { $t = 0; foreach (rows('SELECT * FROM bank_accounts WHERE archived=0') as $b) $t += bank_balance($b); return money($t); }, 'accounting.php?t=banks', 'card', 'violet');

report_section('aging', 'Finance aging', 'reports', function () { finance_aging(); });
report_section('daily', 'Daily finance report', 'reports', function () { finance_daily(); });

seeder(function () {
    $b = ['created_at' => now(), 'archived' => 0];
    foreach ([['1000', 'Cash on Hand', 'Asset'], ['1010', 'Bank — Emirates NBD', 'Asset'], ['1100', 'Accounts Receivable', 'Asset'], ['1200', 'Office Equipment', 'Asset'], ['2000', 'Accounts Payable', 'Liability'], ['2100', 'Agent Payables', 'Liability'], ['2200', 'VAT Payable', 'Liability'],
        ['3000', 'Owner\'s Capital', 'Equity'], ['3100', 'Retained Earnings', 'Equity'], ['4000', 'Inside Hiring Fees', 'Income'], ['4100', 'Outside Hiring Service Income', 'Income'], ['4200', 'Travel & Tour Income', 'Income'], ['4300', 'Other Income', 'Income'], ['4900', 'Sales Returns', 'Income'],
        ['5000', 'Agent Costs', 'Expense'], ['5100', 'Wages & Salaries', 'Expense'], ['5200', 'Office & Operating Expenses', 'Expense'], ['5300', 'Marketing Expenses', 'Expense'], ['5400', 'Refunds', 'Expense'], ['5500', 'Travel Supplier Costs', 'Expense']] as $a)
        ins('accounts', $b + ['code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'active' => 1]);
    post_journal(date('Y-m-01'), 'Opening capital', [['1010', 60000, 0], ['1000', 5000, 0], ['3000', 0, 65000]], 'opening');
}, 5);
seeder(function () {
    $b = ['created_at' => now(), 'archived' => 0];
    $bank = ins('bank_accounts', $b + ['name' => 'Main operating account', 'bank' => 'Emirates NBD', 'account_no' => '1015 0000 1234', 'iban' => 'AE07 0331 2345 6789 0123 456', 'account_id' => acct('1010'), 'opening_balance' => 60000, 'currency' => 'AED']);
    foreach ([['Rent', 6500, '5200', 'Office rent November'], ['Marketing', 1350, '5300', 'Facebook ads November'], ['Utilities', 820, '5200', 'DEWA bill']] as $x) {
        $id = ins('finance_entries', $b + ['ref_no' => next_number('FIN'), 'entry_date' => date('Y-m-d', strtotime('-' . rand(1, 8) . ' days')), 'kind' => 'Expense', 'category' => $x[0], 'amount' => $x[1], 'account_id' => acct($x[2]), 'paid_via' => 'Bank', 'linked_record' => $x[3], 'business_line' => 'General']);
        q('UPDATE finance_entries SET journal_id=? WHERE id=?', [post_journal(today(), 'Expense: ' . $x[0], [[$x[2], $x[1], 0], ['1010', 0, $x[1]]], 'finance_entry', $id), $id]);
        ins('bank_transactions', $b + ['ref_no' => next_number('BTX'), 'bank_account_id' => $bank, 'tx_date' => today(), 'kind' => 'Withdrawal', 'amount' => $x[1], 'counter_account_id' => acct($x[2]), 'reference' => $x[3]]);
    }
    $gulf = (int)val("SELECT id FROM companies WHERE display_name='Gulf Construction LLC'");
    $d1 = (int)val("SELECT id FROM demands WHERE company_id=? AND position='Mason'", [$gulf]);
    $inv = ins('invoices', $b + ['ref_no' => next_number('INV'), 'party' => 'Client', 'company_id' => $gulf, 'demand_id' => $d1, 'issue_date' => date('Y-m-d', strtotime('-5 days')), 'due_date' => date('Y-m-d', strtotime('+25 days')), 'status' => 'Draft', 'notes' => '30 days', 'business_line' => 'Outside Hiring']);
    ins('invoice_items', $b + ['invoice_id' => $inv, 'description' => 'Recruitment service fee — Mason (joined)', 'qty' => 1, 'unit_price' => 1200, 'line_total' => 1200]);
    ins('invoice_items', $b + ['invoice_id' => $inv, 'description' => 'Mobilisation and visa processing', 'qty' => 2, 'unit_price' => 750, 'line_total' => 1500]);
    $t = invoice_total($inv);
    $j = post_journal(date('Y-m-d', strtotime('-5 days')), 'Invoice', [['1100', $t, 0], ['4100', 0, $t]], 'invoice', $inv);
    q("UPDATE invoices SET status='Issued', total=?, journal_id=? WHERE id=?", [$t, $j, $inv]);
    record_payment('invoices', $inv, 1500, ['method' => 'Bank Transfer', 'pay_type' => 'Client Payment', 'party' => 'Gulf Construction LLC']);
    $est = ins('estimates', $b + ['ref_no' => next_number('EST'), 'party' => 'Client', 'company_id' => (int)val("SELECT id FROM companies WHERE display_name='Emirates FM'"), 'issue_date' => today(), 'valid_until' => date('Y-m-d', strtotime('+14 days')), 'status' => 'Sent']);
    ins('estimate_items', $b + ['estimate_id' => $est, 'description' => 'Supply of 8 cleaners — service fee', 'qty' => 8, 'unit_price' => 900, 'line_total' => 7200]);
    $bud = ins('budgets', $b + ['name' => 'Q4 2026 operating budget', 'period_start' => date('Y-10-01'), 'period_end' => date('Y-12-31')]);
    foreach ([['5200', 25000], ['5300', 6000], ['5100', 45000]] as $x) ins('budget_allocations', $b + ['budget_id' => $bud, 'account_id' => acct($x[0]), 'amount' => $x[1]]);
    $ktm = (int)val("SELECT id FROM agents WHERE agency_name='Kathmandu Manpower Agency'");
    record_payment('agents', $ktm, 400, ['direction' => 'out', 'method' => 'Bank Transfer', 'pay_type' => 'Agent Payment', 'party' => 'Kathmandu Manpower Agency']);
}, 40);
