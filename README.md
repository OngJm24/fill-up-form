# Profile Journey (fill-up form)

A 7-step profile form (basics → favorites → fun → dreams → selfie → moods → review) with an admin dashboard.

## Layout

```
public/            ← the ONLY folder the web server should serve
  index.php        form
  submit.php       validates + saves a submission (JSON for the form, HTML fallback)
  admin/           login, dashboard, mood JSON, auth-protected photo viewer
  assets/          app.css, form.js, admin.js
src/               bootstrap (sessions, CSRF, headers, DB, rate limits), form rules, mailer
storage/uploads/   photos (private — served only through admin/photo.php)
storage/logs/      php_error.log, mail.log
database/          schema.sql (fresh install), migrate-2026-11-security.sql (upgrade)
bin/               set-admin-password.php, test-mail.php
config.local.php   secrets (git-ignored) — copy from config.example.php
```

## Setup

```bash
cp config.example.php config.local.php     # then fill in DB + SMTP + app_secret
mysql -uroot < database/schema.sql
php bin/set-admin-password.php
php bin/test-mail.php you@example.com
```

Point the web server's document root at `public/` (EnvKit does this automatically when a `public/` folder exists).

## Security notes

- Every field is validated twice from one rule list (`src/form.php`): live in the browser, and again on the server.
- All SQL uses prepared statements; all output is HTML-escaped; the admin UI inserts user text with `textContent` only.
- Photos are checked with `finfo` + `getimagesize`, then **re-encoded** to JPEG with random names. This blocks disguised scripts and strips EXIF/GPS data.
- CSRF tokens on every form; strict Content-Security-Policy and other security headers; `HttpOnly`/`SameSite` session cookies.
- Admin: bcrypt password hash, 5 failed logins → 15-minute lockout, session ID regenerated on login, 30-minute idle timeout, POST-only logout.
- Submissions: honeypot field, 20 per 10 minutes per network, 1 per minute per email.
