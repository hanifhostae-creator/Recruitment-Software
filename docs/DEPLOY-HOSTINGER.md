# Deploy on Hostinger (hPanel) — A to Z

Goal: `https://crm.<your-domain>` runs the system, connected to GitHub, and every push to `main` goes live.

## 1. Subdomain and PHP
1. hPanel → **Websites → Manage** → **Domains → Subdomains**: `crm` (already created). Note its folder (e.g. `public_html/crm`).
2. **Advanced → PHP Configuration**: PHP **8.2**. Extensions: **pdo_mysql**, **mysqli**, **openssl**, **fileinfo**, **mbstring** on.
   Options: `upload_max_filesize` = 10M, `post_max_size` = 12M.
3. **Security → SSL**: install / enable SSL for the subdomain.

## 2. MySQL database
1. hPanel → **Databases → MySQL Databases** → *Create a new MySQL database and user*.
   - Database name e.g. `rhcrm` → hPanel shows the full name like `u123456789_rhcrm`.
   - User e.g. `rhuser` → full name `u123456789_rhuser`. Use a long random password. Save all three.
2. Nothing to import. The system creates all tables itself on the first visit.

## 3. Connect GitHub (auto deploy)
1. Make the repository **private** on GitHub (Settings → General → Danger zone → Change visibility).
2. hPanel → subdomain → **Advanced → Git** → *Create a new repository*:
   - Private repo: hPanel shows an **SSH key** → GitHub repo → Settings → **Deploy keys** → *Add deploy key* (read-only).
     Repository address: `git@github.com:<owner>/<repo>.git`
   - Branch: `main` · Install path: leave empty (the subdomain folder must be empty first).
3. Press **Deploy**. Then **Auto deployment** → copy the webhook URL → GitHub repo → Settings → **Webhooks** → *Add webhook*:
   payload URL = that URL, content type `application/json`, *Just the push event*, Active.
4. From now on every push to `main` is pulled by Hostinger automatically (1–2 minutes).

## 4. Configuration file (once, by hand — never in GitHub)
1. hPanel → **File Manager** → subdomain folder → `inc/` → copy `config.sample.php` to **`config.php`**.
2. Edit `inc/config.php`: `DB_NAME`, `DB_USER`, `DB_PASS` from step 2, `DB_HOST` = `localhost`,
   `APP_KEY` = a long random secret (60+ letters/numbers, **never change it later**), `DEMO_DATA` = `false`.
3. Git deploys never touch `inc/config.php`, `data/` or `uploads/`.

## 5. First visit
1. Open `https://crm.<your-domain>/` → the **Create the Super Admin** screen appears (only once).
2. Fill company name, your name, email, username, a strong password, System URL `https://crm.<your-domain>`.
3. You land on the dashboard.

## 6. Email (password resets, candidate portal links)
1. hPanel → **Emails → Email Accounts** → create `no-reply@<your-domain>`.
2. System → **Settings → Email**: server `smtp.hostinger.com`, port `465`, security SSL, username = full mailbox,
   password = mailbox password, from = same address → Save → **Send a test email**.

## 7. Your team
1. **Staff & Roles → Add New staff**: name, username, email, role, **team / section**, temporary password
   (they must change it at first login). Roles decide which screens and actions each person has.
2. **Admin Control Centre → Notification sounds**: choose sounds/icons and which alerts ring.

## 8. Checks after going live
- `https://crm.<your-domain>/inc/config.php` must show nothing / 403. `/.git/config` and `/data/` → 403/404.
- Log in, out, and try "Forgot your password?" once.
- Backups: hPanel → **Backups** (daily on Business plan) + monthly phpMyAdmin export of the database + download `uploads/`.

## Daily work
Changes are made in a Claude Code session → pushed to `main` → Hostinger pulls → live. Database changes are
applied automatically on the next visit; data is never removed by an update.
