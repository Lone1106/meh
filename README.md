# Meh

A tiny, single-person feedback board. People rate you 1–5 stars, leave a short
comment, and say how they know you (friend / acquaintance / coworker / other).
No framework, no build step — plain PHP + SQLite.

## Features

- Public feedback grid (`/`) and a submission form (`/feedback`)
- Read-only JSON API at `/api` (for pulling the data into other pages)
- Spam protection: CSRF token, honeypot field, minimum fill-time check,
  per-IP cooldown + daily cap
- SQLite storage, no database server required
- Optional email notification on new feedback, sent via a dependency-free
  SMTP client (`lib/mailer.php`)
- Open Graph / Twitter card meta tags with a baked social-share image, so
  links look right when pasted into chats (Instagram, iMessage, etc.)

## Requirements

- PHP 8.1+ with `pdo_sqlite`
- A webserver whose **document root is `public/`** (not the project root) —
  this is what keeps `config.php` and the database out of reach

## Local development

This project is set up for [DDEV](https://ddev.com):

```sh
ddev start
```

The site will be available at `https://meh-feedback.ddev.site`.

## Project structure

```
config.php              DB connection, session, CSRF, rate limiting
config.smtp.php         SMTP credentials (gitignored, not committed)
config.smtp.php.example template for config.smtp.php
lib/                    app code with no business being in public/
  mailer.php            dependency-free SMTP client + notification email
  mail/                 email templates (plain-text + HTML)
data/                   feedback.sqlite (created automatically, gitignored)
public/                 webserver document root
  index.php             feedback grid
  submit.php            submission form + handler
  api.php               read-only JSON API
  assets/               CSS, logo, self-hosted fonts, og-image.png
  favicon/              favicon + web manifest files
design/                 source files, not web-accessible
  generate-og-image.py  regenerates public/assets/og-image.png
```

## API

```
GET /api?limit=20&offset=0
```

Returns:

```json
{
  "count": 7,
  "average": 3.7,
  "limit": 20,
  "offset": 0,
  "items": [
    { "id": 1, "name": "...", "rating": 5, "comment": "...", "relationship": "freund", "relationship_label": "Freund(in)", "created_at": "2026-11-22T10:00:00+00:00" }
  ]
}
```

`limit` is capped at 100. CORS is open (`Access-Control-Allow-Origin: *`) so
it can be fetched from other pages/origins.

## Email notifications

Each new feedback submission can trigger a notification email over SMTP.

```sh
cp config.smtp.php.example config.smtp.php
```

Fill in `config.smtp.php` with your SMTP host, port, encryption (`tls` for
STARTTLS, `ssl` for implicit TLS), credentials, and the `to` address.
`config.smtp.php` is gitignored — it holds real credentials and must never be
committed. If it doesn't exist, notifications are silently skipped.

## Social link previews

`index.php` and `submit.php` carry Open Graph/Twitter meta tags pointing at
`public/assets/og-image.png`, a pre-baked 1200x630 PNG with an opaque
background. Without these tags, chat apps that unfurl links (Instagram DMs,
iMessage, …) fell back to scraping the header `<img>` — the site logo SVG,
which has a *transparent* background and black artwork, so it rendered as a
near-invisible blurry smear once flattened onto a dark card background.

To change the image (e.g. after a logo update), edit and rerun:

```sh
python3 design/generate-og-image.py
```

Requires Pillow (`pip install Pillow`) and the macOS system fonts Arial /
Arial Bold.

## Deployment notes

- Point your webserver's document root at `public/`.
- On Apache, `.htaccess` files handle the clean URLs (`/feedback`, `/api`)
  and lock down everything outside `public/`. On nginx, mirror the rewrite
  rules in `.ddev/nginx_full/nginx-site.conf`.
- Make sure `data/` is writable by the webserver user.
- Change `RELATIONSHIPS` in `config.php` if you want different relationship
  options.
- Update `SITE_URL` in `config.php` if the site ever moves to a different
  domain — it's used for the Open Graph/Twitter absolute URLs.
