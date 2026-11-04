# Module 2 — Outside Hiring & Finance (session "RH-2 Outside Hiring & Finance")

Read first: `../CLAUDE.md`, `docs/core-api.md`, then spec sections 4, 9, 10, 11.2, 11.3,
13, 17 (demand progress tables), 19, 20, 22 (items 5, 6, 10, 11).

**Start only after the Foundation (Module 1, step 1) is on `main`.** Use the core helpers
(`record_payment()`, `reverse_payment()`, `balance_for()`, `advance_stage()`, `audit()`,
`next_number()`, permissions, layout). Do not edit core files — write needed core changes
in `docs/requests.md`. Register everything through `inc/modules/outside.php`.

## Step 1 — Companies & demands (spec 9, stages 1–3)

Company master (legal/display name, industry, branches/sites, contacts, contract, account
manager, billing terms, approved roles, status). New demand (demand number, position,
quantity, JD, skills, salary/benefits, location, deadline, interview/test/training,
documents, contract/PO, priority, owner). Demand approval with approved quantity,
replacement policy, rates, contract version and amendments.

## Step 2 — Agents, allocation, batches, workers (spec 10, stage 4)

Agent/agency master, demand allocation (target qty, deadline, checklist, coordinator),
multiple batches per agent per demand, workers individually traceable to batch, agent,
demand and position; submission date, submitted by, CV/documents, client response.

## Step 3 — Outside Ops (spec 9, stages 5–10, 9.1, 9.2)

Interviews/tests/training (location, date, assessor, score, pass/fail/pending, reschedule
reason), selection/rejection with reason, documents & authorisation, visa/permit
milestones and expiry, mobilisation and joining, **travel status on both sides**
(worker + company side: travelled / pending / did-not-travel + reason + replacement),
issue cases (severity, owner, due date, replacement), demand closure only when every slot
is categorised. Direct client dashboard, demand progress tables (client side + agent
side), agent communications, agent performance, alerts.

## Step 4 — Client Portal & Agent Portal (spec 11.2, 11.3)

`client_portal.php`: client sees only their company's demands, submitted profiles and CVs,
interview outcomes, document/visa/deployment progress, headcount progress, raise issues.
`agent_portal.php`: agent sees only own submissions, documents, progress, issues, payment
terms and receipts. Demo: `gulf / client123` (Gulf Construction LLC), `kathmandu / agent123`
(Kathmandu Manpower Agency). Client/agent users managed by staff.

## Step 5 — Finance (spec 13)

`finance.php`: payment ledger (every payment linked to file / demand / agent / client),
candidate balances (reads the shared payment logic — must match Module 1 views), agent and
client balances (due, paid, outstanding, disputed), wages, daily finance reports and
finance dashboard (collected, due, overdue, disputed, refunded by day/officer/file/type),
agent payments, client invoices/reconciliation.

## Step 6 — Accounting & Invoices (spec 13)

`accounting.php`: chart of accounts, double-entry journals (debit must equal credit),
bank accounts and transactions (update balance), balance sheet, budgets and allocations.
`invoices.php`: estimates → invoices, items, partial payments, credit notes, invoice
returns. Corrections only by reversal; every export, status change and reversal audited.

**Done when:** spec Section 22 items 5, 6 (client/agent part), 10 and the finance parts of
4 and 11 pass. Merge to `main`.
