# TablePulse Restaurant

A tiny PHP + JavaScript app for restaurants: guests scan a QR/table link, send quick service requests or feedback, and staff can manage everything from a lightweight dashboard.

It is intentionally simple: no database, no build step, no framework. It works as a static local demo using `localStorage`, and automatically syncs to the PHP JSON backend when hosted with PHP.

## What it does

- Guest page for table-specific requests: service, payment, cleaning, order issue, feedback.
- Staff dashboard with live queue, status changes, filters, stats, and export.
- QR/link generator for table numbers.
- PHP API storing tickets in `data/tickets.json`.
- Offline/demo fallback via browser `localStorage`.

## Quick local test without PHP

Open this file directly in a browser:

```text
public/index.html?t=7
```

Then open:

```text
public/dashboard.html
```

The demo stores data in the browser. This is enough to test the workflow on this machine even when PHP is not installed.

## Run with PHP backend

If PHP is available:

```bash
php -S localhost:8080 -t public
```

Then visit:

```text
http://localhost:8080/?t=7
http://localhost:8080/dashboard.html
```

The repo includes `public/api/tickets.php` as a tiny proxy to the real API, so the PHP built-in server works with the command above. On shared hosting, deploy the full repo and keep `data/` writable.

## File structure

```text
public/index.html       Guest/table request page
public/dashboard.html   Staff dashboard
public/app.js           Frontend app logic
public/styles.css       UI styles
api/tickets.php         JSON ticket API
public/api/tickets.php  Web-root proxy for the PHP API
data/tickets.json       Storage file
```

## API

`GET /api/tickets.php` lists tickets.

`POST /api/tickets.php` creates a ticket:

```json
{
  "table": "7",
  "type": "payment",
  "message": "We would like to pay by card"
}
```

`PATCH /api/tickets.php?id=<ticket-id>` updates status:

```json
{ "status": "done" }
```

## Why this app

This is a practical weekend-sized restaurant tool: small enough to actually ship, useful enough for real testing, and easy to extend into a Symfony/MySQL version later.
