# Progress log & handover

Read this first in every new session. Update it at the end of every piece of work.

## Current version: 1.2.0 (12 Nov 2026)

Everything below is built, on `main`, and tested on **SQLite and MySQL (MariaDB 10.11)**.

### Built
- **Whole specification v1.0**: Reception → HR → Inside file & fees → Deployment → Candidate portal; Outside hiring
  (companies, demands, agents, batches, workers, ops, closure) + Client and Agent portals; Finance, double-entry
  accounting, invoices; HRM, CRM, POS, ERP, Projects, IT & Digital, Travel & Tour, Marriage Bureau (paybook);
  WhatsApp webhook & inbox; messenger, calendar, meetings, CMS, KB, support, goals; reports; CSV import/export; audit.
- **v1.1**: password reset by email for staff + all portals (single-use 60-min links, hashed tokens, rate limited);
  admin can send reset links; SMTP mailer (Hostinger), branded HTML emails, email log; beep + pop-up live
  notifications; Reception "Ready for deployment" tab with inline assign; HR dropdown; HR interview → file confirm;
  candidate gets the portal activation email when Deployment is assigned; **private candidate chat**
  (deployment team + Super Admin only, admin grants others; views audited; attachments protected).
- **v1.2**:
  - **MySQL / MariaDB support** (same code for SQLite). `inc/config.php` (from `inc/config.sample.php`) picks the driver.
  - **Live mode**: with a config file the system starts empty; `setup.php` creates the first Super Admin (then locks).
  - **Security**: CSP with nonces, no `X-Powered-By`, frame/nosniff/referrer/permissions headers, HSTS on HTTPS,
    generic session cookie, idle sign-out (`SESSION_IDLE_MINUTES`), IP-based login throttling (staff + portals),
    10-character passwords, upload content check (real file type), SMTP password & WhatsApp token **encrypted**
    with `APP_KEY`, SQL-injection fix (`datecol`), open-redirect fix, `.git` / `inc` / `data` / `docs` blocked.
  - **Sectioned Super Admin dashboard** (`index.php?s=`): Overview, Inside Hiring, Outside Hiring, Travel & Tour,
    Marketing, Marriage Bureau, IT & Business Dev, Accounts, Staff & Attendance — live tiles (refresh every 30 s and
    on every new alert), animated charts, income / expense / profit per section, team performance and attendance.
  - **Section accounts**: every payment carries its section (`payments.section`); Finance → *Section accounts*
    shows each section's income, expenses and profit and has "Add income / Add expense" for that section.
  - **Staff teams** (`users.team`, Staff & Roles) — used by section dashboards and attendance.
  - **HR no longer sees staff attendance** (HR role: candidates, inside, fees, recruitment only); HR sees
    "My progress this month" on My Dashboard (entries, interviews, files, sent to deployment).
  - **Marketing → Candidate sources** report (through whom / by source / per day) — also in Reports.
  - **Notification sounds**: Admin → *Notification sounds*: per alert type on/off, sound (8 choices, Test button),
    icon. New-entry alerts for the Super Admin and for the team concerned (leads, demands, travel enquiries, tickets…).

### How to test (both databases)
- SQLite: `php -S localhost:8000 router.php` (no config file → demo data). Logins in README.
- MySQL: create a database + user, then either write `inc/config.php` or start PHP with env vars:
  `RH_DB_DRIVER=mysql RH_DB_HOST=127.0.0.1 RH_DB_NAME=rh_test RH_DB_USER=rh RH_DB_PASS=... RH_DEMO_DATA=1 php -S localhost:8000 router.php`
- Last results (both DBs): crawl of ~1,565 pages with 0 errors; acceptance suite 36/36; reset + workflow + chat
  privacy 47/48 (the 1 is a test-script quirk, behaviour verified by hand); live-mode + security checks all pass.

### Not done yet / ideas for next sessions
- Per-user permission overrides (today: roles; duplicate a role per person if needed).
- Two-factor login (spec says not included; can be added later).
- Automatic MySQL backup download inside the app (use hPanel → Backups / phpMyAdmin export).
- Chairman Services service list (waiting for the owner).
- WhatsApp sending through a provider (replies are notes today).
- Demo-data cleanup tool (live installs start empty, so only needed for old demo databases).

## Change log
- 2026-11-12 · v1.2.0 · MySQL support, live mode + setup, security hardening, sectioned dashboard, section accounts,
  staff teams, HR restrictions, candidate source report, notification sounds.
- 2026-11-11 · v1.1.0 · Password reset & SMTP, live alerts, workflow changes, private chat.
- 2026-11-05 · v1.0.0 · Full first build from the specification.
