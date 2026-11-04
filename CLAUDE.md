# RH Management — Integrated Business Management System

Internal management system (staff dashboards + three portals) for
**RH Business Solutions & Marketing Management**, Dubai — recruitment & manpower supply,
HR, CRM, ERP, finance, IT & digital, travel & tour, marriage paybook.

- Production domain: `crm.rhmanagements.com`
- Full specification: `docs/RH-Management-Complete-Specification.pdf` (v1.0, Nov 2026).
  **Read the sections for your module before writing code.** The spec is the source of
  truth; this file only splits the work and fixes shared rules.
- There is **no public website here.** The public site is a separate repo (`RH-HR-Web`).
  This repo is only: staff login + dashboards/modules + Candidate, Client and Agent portals.
- This is a **redesign / fresh rebuild**: same business, same activities, same modules and
  workflows as the spec — new clean code and a new professional look (see "Design").

## How the work is split between sessions

The system is built in **three separate Claude Code sessions**, one per module, all in
this same repo. Each session stays inside its own module.

| # | Session name | Module | Spec sections | Plan |
|---|---|---|---|---|
| 1 | **RH-1 Core & Inside Hiring** | Foundation (DB, login, roles, layout, audit, attendance, admin, search, tasks, notifications, reports framework) + Reception, Candidates, HR, Deployment, Inside Hiring, Fees, Recruitment, Marketing & Vacancies, **Candidate Portal** | 3, 4, 5, 7, 8, 11.1, 12, 16 (tasks/notifications), 17 (framework), 18, 19, 20 | `docs/module-1-core-inside-hiring.md` |
| 2 | **RH-2 Outside Hiring & Finance** | Outside Hiring, Outside Ops, **Client Portal**, **Agent Portal**, Finance, Accounting, Invoices | 9, 10, 11.2, 11.3, 13 | `docs/module-2-outside-hiring-finance.md` |
| 3 | **RH-3 Business Ops & Communication** | HRM, CRM, ERP, POS, Projects, IT & Digital, Travel & Tour, Marriage Paybook, WhatsApp, Messenger, Calendar, Meetings, Email/Notification templates, CMS, Media, Knowledge Base, FAQ, Newsletter, Support, Company Goals | 12 (HRM register), 14, 15, 16 | `docs/module-3-business-ops-communication.md` |

### Order of work

1. **Session 1 builds the Foundation first** (step 1 in its plan) and merges it to `main`.
   Sessions 2 and 3 must not start coding before the Foundation is on `main` — they build
   on its database helper, login, permissions, layout and shared functions.
2. After that, all three sessions can work at the same time.
3. At the end, Session 1 runs the full acceptance checklist (spec Section 22) on a fresh
   install and on an upgraded install.

### Git

- `main` is the integration branch. Each session works on its own branch, keeps commits
  small, pulls `main` often (`git pull origin main`) and merges back to `main` when a step
  is finished and tested.
- Never rewrite `main`. Never push another session's half-finished work.

## File ownership (prevents merge conflicts)

Each module owns its own files. A session edits only its own files plus the **registry
file of its own module**. Shared core files are changed only by Session 1; other sessions
ask for a change in their notes (`docs/requests.md`) instead of editing core.

| Owner | Files |
|---|---|
| Core (S1) | `inc/db.php`, `inc/functions.php`, `inc/auth.php`, `inc/layout.php`, `inc/registry.php`, `inc/schema/core.php`, `assets/app.css`, `assets/app.js`, `login.php`, `logout.php`, `index.php`, `mydash.php`, `admin.php`, `settings.php`, `staff.php`, `audit.php`, `search.php`, `tasks.php`, `notifications.php`, `reports.php`, `delete.php`, `file.php` (download handler), `data/.htaccess`, `uploads/.htaccess` |
| Module 1 (S1) | `inc/modules/inside.php`, `inc/schema/inside.php`, `reception.php`, `candidates.php`, `candidate_register.php`, `deployment.php`, `inside.php`, `fees.php`, `recruitment.php`, `marketing.php`, `portal.php` |
| Module 2 (S2) | `inc/modules/outside.php`, `inc/schema/outside.php`, `outside.php`, `outside_ops.php`, `client_portal.php`, `agent_portal.php`, `finance.php`, `accounting.php`, `invoices.php` |
| Module 3 (S3) | `inc/modules/business.php`, `inc/schema/business.php`, `hrm.php`, `crm.php`, `erp.php`, `pos.php`, `projects.php`, `it.php`, `travel.php`, `paybook.php`, `whatsapp.php`, `webhook.php`, `messenger.php`, `calendar.php`, `meetings.php`, `cms.php`, `media.php`, `kb.php`, `support.php` |

Module-specific CSS/JS, if really needed, goes in `assets/<module>.css` / `assets/<module>.js`.

### Module registry (how a module plugs in)

`inc/registry.php` (core) loads every `inc/modules/*.php`. A module file registers, without
touching core files:

- its **schema** (`inc/schema/<module>.php` — `CREATE TABLE IF NOT EXISTS` + data-preserving
  `ALTER` migrations, run on every boot, idempotent),
- its **seed / demo data**,
- its **menu items** (module key, label, icon, page, area),
- its **module keys and action permissions** (used by Staff & Roles),
- its **dashboard widgets** (live counters computed from records),
- its **search providers** (for global search),
- its **report sections** (for `reports.php`),
- its **record-number prefixes** (e.g. `CAN-`, `IN-FILE-`, `RCT-`).

## Stack (fixed by the spec — Section 3)

- Plain PHP 7.4+ (production PHP 8.1/8.2). No Composer, no framework, no Node build.
- SQLite through PDO, one file `data/rh.sqlite`, created and seeded on first visit.
- If `pdo_sqlite` is missing, show a plain-English message telling the owner to enable it.
- Uploads in `uploads/` (CVs in `uploads/cv/`), downloaded only through `file.php`.
- `data/` and `uploads/` blocked by `.htaccess` (direct `/data/rh.sqlite` → 403).
- CSS + vanilla JS only; charts drawn with SVG/canvas, no chart library.
- Local test: `php -S localhost:8000` from the repo root.

## Shared rules (spec Section 4 + 20 — every module, every screen)

- **English only** in every label, button, message, email, export.
- **Add New on every list.** Lists are rows; clicking a row opens View/Edit. Forms open only
  on "Add New" — never a wall of open forms.
- **Detail pages show action buttons**; only the selected action's form is rendered.
- **"Other" option** on every major dropdown, revealing a required detail field.
- **One unique record number** per entity (Section 19). Duplicate check before creating.
- **Audit everything**: who created / viewed / updated / approved / deleted / restored, with
  old and new value. Use the core `audit()` helper.
- **Configurable lists** (positions, statuses, payment types, document types…) come from
  admin settings, not hard-coded arrays.
- **Money is never hard-deleted or edited in place** — reversal entries with reason + audit.
  Records with financial/legal links are archived, not deleted.
- **Confirm** every destructive action.
- **Forward-only stages** through one stage-advance function; no "go back" button.
- **Permissions checked on every page and every action** (`require_module()`,
  `require_action()`), not just hidden from the menu.
- **Portals see only their own data**; never internal notes or other parties' money.
- **CSRF token** on every state-changing form; escape all output with `e()`.
- **Live counters** on dashboards — computed from records, never typed in.
- **Mobile-friendly** (phones at 390px, no horizontal scroll).

## Design (redesign)

Same brand as the public website, built for long daily use:

- Colours: navy `#0b1430` / `#12204a` (sidebar, headers), brand blue `#3d6bff`,
  gold `#dcaa48` (highlights), success `#22c08a`; light content area for readability with a
  dark-mode toggle. Font: Plus Jakarta Sans (system-ui fallback).
- Logo: `assets/img/rh-logo.png`.
- Layout: collapsible left sidebar grouped by area, top bar (global search, notifications,
  check-in/out, user menu), card-based dashboards, clean data tables with filters and
  pagination, slide-in or modal forms, subtle CSS transitions, respect
  `prefers-reduced-motion`.
- All design tokens live on `:root` in `assets/app.css`; modules reuse the core components
  (cards, tables, badges, buttons, forms, tabs, timeline, stat tiles, charts).

## Do not invent (spec Section 23)

- Chairman Services: build the business line, tagging and filters only — no service list
  until the owner supplies it.
- WhatsApp: replies are stored as notes; no provider sending.
- Not included: MFA, SSO, encryption at rest, malware scanning, scheduled reports,
  dynamic custom-field rendering, per-field masking.
- Demo accounts (Appendix C) must force a password change on first login.
