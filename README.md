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
config.php         DB connection, session, CSRF, rate limiting
data/               feedback.sqlite (created automatically, gitignored)
public/             webserver document root
  index.php         feedback grid
  submit.php        submission form + handler
  api.php           read-only JSON API
  assets/           CSS, logo, self-hosted fonts
  favicon/          favicon + web manifest files
design/             original logo/favicon source files (not web-accessible)
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

## Deployment notes

- Point your webserver's document root at `public/`.
- On Apache, `.htaccess` files handle the clean URLs (`/feedback`, `/api`)
  and lock down everything outside `public/`. On nginx, mirror the rewrite
  rules in `.ddev/nginx_full/nginx-site.conf`.
- Make sure `data/` is writable by the webserver user.
- Change `RELATIONSHIPS` in `config.php` if you want different relationship
  options.
