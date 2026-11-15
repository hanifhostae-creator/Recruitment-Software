# Progress log & handover

Read this first in every new session. Update it at the end of every piece of work.

## Current version: 1.5.0 (15 Nov 2026)

### First message to paste in a new session
> Read CLAUDE.md and docs/PROGRESS.md fully, then continue the work. Talk to me in Roman Urdu.

### Live deployment status (owner, Hostinger)
- Hostinger hPanel → "Deploy from GitHub" for `crm.rhmanagements.com`, folder `public_html`, branch **`main`** (not the claude/* branch).
- After deploy the owner must create `public_html/inc/config.php` from `inc/config.sample.php` (MySQL name/user/password,
  `DB_HOST=localhost`, long random `APP_KEY`, `DEMO_DATA=false`) **before** the first visit; then `setup.php` creates the Super Admin.
- Not yet confirmed: whether Hostinger's GitHub deploy keeps untracked files (`inc/config.php`, `uploads/`) on each redeploy — check after the first auto-deploy.
- The owner wants the GitHub repo **private**. Early commits (before 12 Nov) contain tool attribution lines; rewriting history needs the owner's explicit OK (force-push to `main`).

### What the owner asked for in the chat (keep these decisions)
- Same business & spec, professional redesign; English-only system; mobile-friendly everywhere, especially the portals.
- Workflow: Reception enters candidate → "Assign to HR" (HR names only) → HR gets beep → HR interview Selected → file
  confirm (advance now, rest later) → Reception notified ("Ready for deployment" tab) → Reception or HR assigns the
  Deployment team/officer → candidate gets the portal email at the same moment → private chat candidate ↔ deployment team.
- Chat is private: only the deployment team (and Super Admin); admin grants others; views are audited.
- Password reset by email for every login (staff, candidate, client, agent) + admin can send reset links.
- MySQL on Hostinger; strong security; nothing may reveal the technology or who built it.
- Super Admin dashboard per section (Inside, Outside, Travel & Tour, Marketing, Marriage Bureau, IT & Business Dev,
  Accounts, Staff & Attendance); income/expense/profit per section; accountant enters income/expense per section.
- Staff teams per section; per-staff performance & attendance for the admin. HR must NOT see staff attendance.
- Marketing report: candidates by "through whom" and by source, per day.
- Configurable notification sounds and icons; beeps on new entries.
- The Super Admin (owner) does **not** check in/out; attendance is for staff only.
- Dashboard heading for the Super Admin is simply "Welcome to Super Admin Panel".
- Very professional, animated, attractive UI (elevated shadows, hover/click effects, smooth sidebar).
- Keep the daily flow SIMPLE: reception enters basics (CV optional, camera for paper CVs); HR sees simple data + CV preview;
  HR file = agreed total + paid now + remaining date (no fee plans); spoken alerts; deployment works from one screen
  (chat + details + CV + actions); locations also go into the chat; chat links clickable.

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
- Last results (15 Nov, both DBs): crawl of ~1,565 pages with 0 errors; acceptance suite 36/36; reset + workflow + chat
  privacy + v1.4 + v1.5 checks 83/85 (A1 counted twice) (the 1 is a test-script quirk, behaviour verified by hand); live-mode + security checks all pass.

- **v1.3** (15 Nov): Super Admin hero "Welcome to Super Admin Panel"; no check-in/out for the Super Admin (excluded from
  attendance counts, manual box and staff tables); elevated tiles with coloured shadows, 3D tilt + light glare, click
  ripple, money counters animate, cards slide in when scrolled into view (charts start then), sidebar sliding highlight,
  glowing active item and staggered entry; section tabs no longer clip.

- **v1.4** (15 Nov, simplified daily flow):
  - Reception form: main fields first, the rest under "More details (optional)"; **CV optional**; **"Take photo of paper CV"**
    (phone/tablet/webcam camera, several pages joined into one image; needs HTTPS on the live site).
  - HR gets a **spoken announcement** ("New candidate entered: …") plus the pop-up; voice per alert type can be switched
    in Admin → Notification sounds (Voice column). Reception hears "Candidate selected: … Please assign the deployment
    team." (no money mentioned); the deployment team hears "New file ready for deployment: …".
  - HR and Deployment do **not** see "through whom" / source fields (`hide_roles` field option in the engine).
  - **CV preview** on the candidate page (PDF / photo shown inline; Word files download).
  - **File done** form without fee plans: agreed total payment, paid now (today's date saved automatically), remaining
    amount shown live, remaining payment date. Fee plans are no longer required anywhere.
  - **Deployment workspace** = Candidate Chat with a side panel: basic details, CV, interview locations and the actions
    (send location, final outcome, payment received, full record, close file). "My Candidates" and alerts open it.
  - A sent interview location is also **posted in the chat** with a clickable map link; **links in chat are clickable**.
  - Empty related lists are hidden on detail pages (simpler screens).

- **v1.5** (15 Nov, simpler again + voice + app-like portal):
  - **HR simple screen** (`candidate_simple()` / `candidate_simple_screen()` in inside.php, engine option `simple` + action flag
    `simple_hide`): basic details + CV preview + ONE box "Interview result & payment" → result (Selected / Not selected /
    On hold), agreed total, Paid now? Yes/No, amount, pays later (live), later date → **File done** (hidden action `hrdone`).
    Then the same screen shows **Assign to deployment team** (HR or Reception). "Show all details" (`&full=1`) opens the full record.
  - **Spoken messages editable by the Super Admin**: Admin → Notification sounds → "Spoken messages" (`VOICE_MSGS`,
    `voice_text()`, `notify_msg()` in functions.php, setting `voice_msgs`). Placeholders like {name} {position} {amount}.
  - New alerts: Reception "File done: … assign the deployment team" (no money), Super Admin "New file opened … Payment
    received" (the separate payment alert is skipped for that receipt), Super Admin "New enquiry/lead received", "Payment
    received: amount from party", "Deal done" (CRM deal → Won); deployment: "activated the portal", new message, interview reply.
  - **Repeat voice**: pop-up has ↻ repeat; opening an alert shows a bar with "Repeat voice"; notifications list has Repeat.
  - **Deployment chat**: header with WhatsApp (wa.me, UAE 05x → 9715x) and call buttons; "Start the conversation" empty
    state; until the candidate registers: "Portal not active" strip with Send link by WhatsApp / Copy / Email (same 7-day
    link, not regenerated); message box opens after registration; "Portal link" button hidden once registered.
  - Candidates without email: portal username = phone digits; candidates can sign in with email or phone.
  - **Message box** (`chat_composer()`): attach, camera photo, send my location (Google Maps link), auto-grow; photos show inline.
  - **Candidate portal = phone app**: bottom tab bar (Chat, Interviews, Payments, Profile), home = status card + pending
    interview cards (Open map, quick result) + chat. Permissions-Policy now allows geolocation=(self).

### Not done yet / ideas for next sessions
- Per-user permission overrides (today: roles; duplicate a role per person if needed).
- Two-factor login (spec says not included; can be added later).
- Automatic MySQL backup download inside the app (use hPanel → Backups / phpMyAdmin export).
- Chairman Services service list (waiting for the owner).
- WhatsApp sending through a provider (replies are notes today).
- Demo-data cleanup tool (live installs start empty, so only needed for old demo databases).

## Change log
- 2026-11-15 · v1.5.0 · HR one-box File done, assign from the same screen, admin-editable spoken messages, repeat voice, admin alerts (file/payment/lead/deal), deployment chat with portal link + WhatsApp + camera/location, phone-app candidate portal.
- 2026-11-15 · v1.4.0 · Simplified reception/HR/deployment flow: CV optional + camera, voice alerts, CV preview, plan-free file form, deployment workspace, locations in chat.
- 2026-11-15 · v1.3.0 · Welcome banner, no owner check-in, premium dashboard / sidebar animations.
- 2026-11-12 · v1.2.0 · MySQL support, live mode + setup, security hardening, sectioned dashboard, section accounts,
  staff teams, HR restrictions, candidate source report, notification sounds.
- 2026-11-11 · v1.1.0 · Password reset & SMTP, live alerts, workflow changes, private chat.
- 2026-11-05 · v1.0.0 · Full first build from the specification.
