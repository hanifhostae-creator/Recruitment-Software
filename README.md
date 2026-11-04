# RH Management — Integrated Business Management System

Staff dashboards and Candidate / Client / Agent portals for RH Business Solutions &
Marketing Management (Dubai). Plain PHP + SQLite — runs on ordinary shared hosting, no build step.

## Run locally
```
php -S localhost:8000 router.php
```
Open http://localhost:8000/login.php. The database (`data/rh.sqlite`) is created and filled with demo data on the first visit.

## Demo logins (every password must be changed at first login)
| Access | Login |
|---|---|
| Super Admin | `admin` / `rh@123` |
| HR, Deployment, Finance, Reception | `hr`, `deploy`, `finance`, `reception` / `rh@123` |
| Marketing, Outside Operations, Travel, IT | `marketing`, `ops`, `travel`, `it` / `rh@123` |
| Client portal (Gulf Construction LLC) | `client_portal.php` — `gulf` / `client123` |
| Agent portal (Kathmandu Manpower Agency) | `agent_portal.php` — `kathmandu` / `agent123` |
| Candidate portal (legacy) | `portal.php` — file `IN-FILE-2026-00001` + phone `0521112233` |
| Candidate portal (email) | `ali.khan@example.com` / `Candidate@123` |

## Install on hosting (crm.rhmanagements.com)
1. PHP 8.1 or 8.2 with `pdo_sqlite` enabled.
2. Upload all files to the subdomain folder (so `login.php` is at the root URL). Never overwrite a live `data/` folder.
3. Make `data/` and `uploads/` writable (775).
4. Visit the site — the database is created automatically. Change every demo password.
5. Backup = download `data/rh.sqlite` + the `uploads/` folder (Admin Control Centre → Backup).

## Docs
- Specification: `docs/RH-Management-Complete-Specification.pdf`
- How work is split into sessions: `CLAUDE.md` · Core API: `docs/core-api.md`
