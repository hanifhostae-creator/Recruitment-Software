# Module 1 — Core & Inside Hiring (session "RH-1 Core & Inside Hiring")

Read first: `../CLAUDE.md`, then spec sections 3, 4, 5, 7, 8, 11.1, 12, 16–20, 22, Appendix A–C.

This session owns the **Foundation** that the other two sessions build on. Finish and merge
step 1 to `main` before anything else — sessions 2 and 3 are waiting on it.

## Step 1 — Foundation (merge to `main` first)

- `inc/db.php`: PDO SQLite connection to `data/rh.sqlite`, creates `data/` on first run,
  friendly page if `pdo_sqlite` is missing, runs every `inc/schema/*.php` (idempotent,
  data-preserving migrations), seeds demo data once.
- `inc/functions.php` shared helpers, documented with a one-line comment each:
  `e()`, `csrf_field()`, `csrf_check()`, `redirect()`, `flash()`, `audit($action, $table, $id, $old, $new, $reason)`,
  `next_number($prefix)` (e.g. `CAN-2026-00001`), `setting($key)`, `option_list($list)`
  (configurable dropdowns with "Other"), `money()`, `date_fmt()`, `upload_file()` (type/size
  checks, PDF/DOC/DOCX/JPG/PNG, 10 MB), `paginate()`, `csv_export()` (logged), `csv_import_preview()`.
- **Shared money logic** (used by Modules 1 and 2): `payments` table + `record_payment()`,
  `reverse_payment()`, `balance_for($entity, $id)`. One function, so file view,
  deployment view and Finance never disagree.
- **Shared stage logic**: `advance_stage($entity, $id, $to, $reason)` — forward-only, audited.
- `inc/auth.php`: staff login (username + password, `password_hash`), sessions, forced
  password change on first login, `require_login()`, `require_module($key)`,
  `require_action($key, $action)`; separate portal sessions (candidate/client/agent) that can
  never open staff pages.
- Roles & permissions: module access + action permissions (add, edit, delete, approve,
  assign, export, finance posting, configuration) stored per role; duplicate role.
  Seed roles from spec Section 5 with the modules their job needs (Super Admin, Reception,
  HR, Deployment, Finance, Marketing, Travel Team, IT/Digital).
- `inc/registry.php` + module registry API (menu, permissions, widgets, search providers,
  report sections, number prefixes) — see `../CLAUDE.md`.
- `inc/layout.php` + `assets/app.css` + `assets/app.js`: the redesigned UI kit — sidebar,
  top bar, cards, stat tiles, tables (filter, sort, paginate, row click → detail), badges,
  buttons, forms (slide-in / modal, "Other" reveal), tabs, action-button bar, timeline,
  confirm dialog, toasts, SVG charts, dark mode, mobile layout.
- Pages: `login.php`, `logout.php`, `index.php` (redirect), `mydash.php` (My Dashboard),
  Dashboard shell with widgets from the registry, `staff.php` (Staff & Roles),
  `admin.php` (Admin Control Centre: custom options, custom fields, archive & restore,
  role duplication, action permissions — Super Admin only), `settings.php`, `audit.php`,
  `search.php` (global search over registered providers), `tasks.php` (Tasks & Alerts with
  severity, SLA, overdue), `notifications.php`, `reports.php` (framework + saved views),
  `delete.php` (controlled delete / archive), `file.php` (authenticated download).
- **Staff attendance** (spec 12): check-in / check-out box component usable on any staff
  page; `staff_attendance` with unique (user, date).
- `data/.htaccess`, `uploads/.htaccess` (deny all), `uploads/cv/`.
- Demo staff users from Appendix C (admin, hr, deploy, finance, reception — `rh@123`).
- A short `docs/core-api.md` listing every shared helper and the registry API so
  sessions 2 and 3 can use them without reading core code.

**Done when:** fresh install creates the DB, all demo logins work, password change is forced,
a role without a module is refused that page, `/data/rh.sqlite` returns 403, empty module
registry shows a clean dashboard. Then merge to `main` and tell the owner
"Foundation is on main — sessions 2 and 3 can start".

## Step 2 — Reception & Candidates (spec 7.1, 7.2)

Candidate master (`CAN-`), entry form with required CV upload, visa type list
(Visit, Cancel, Employment, Family, Sponsor, Other), Through Whom, Assign to HR (sets stage
HR Assigned), duplicate detection (phone/email/document). Reception queues as rows:
today's entries, HR-assign queue, ready-for-deployment queue, manual attendance box; each
row shows Interview status, File status, Paid, Remaining, Next Pay Date.
Self-registration page `candidate_register.php`.

## Step 3 — HR interview, file decision, Inside files & fees (spec 7.3, 8.3, 8.4)

Interview outcome (Pending/Selected/Rejected/On Hold + notes); File Decision Yes → shared
`create_inside_file()` (`IN-FILE-`), No → reason. Fee plans (named, versioned), receipts
(`RCT-`, partial payments via `record_payment()`), refunds/waivers only via reversal,
due dates, payment promises, reminders, closure outcomes with reason. HR productivity report.

## Step 4 — Deployment (spec 7.4, 7.5)

Teams, My Candidates, auto portal login + registration link on assignment, interview
location sequence (1st, 2nd, 3rd…), Final Outcome + reason, deployment payment (shared
logic), File Close (terminal, banner, Inside file Closed). Candidate detail page with all
action buttons and the Candidate Journey box.

## Step 5 — Candidate Portal (spec 11.1)

`portal.php`: login by email (+ legacy slug), legacy file number + phone login, 32-char
registration token valid 7 days, email shown read-only. Profile, file, timeline,
"My Interview Locations" (Selected / Not Selected / Issue + note), payment summary,
issues & help centre. Cannot change official status, fees or see internal notes.

## Step 6 — Recruitment & Marketing (spec 6, 8.1, 8.2)

Recruitment: pipeline dashboard, offers, onboarding, assessments, interview rounds, job
locations, custom questions, checklists. Marketing: inside vacancies (`IN-REQ-`) on the
vacancy board, campaign posts, enquiries linked to posts ("Other / Unknown"), invitations,
office visits, funnel reports per post/campaign/platform/marketer/vacancy.

## Step 7 — Final integration & UAT (after sessions 2 and 3 have merged)

Merge `main`, run spec Section 22 items 1–14 on a fresh install and an upgraded install,
fix core issues, list module issues in `docs/requests.md` for the owning session.
