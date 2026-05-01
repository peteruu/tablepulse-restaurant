# TablePulse Restaurant

A tiny PHP + JavaScript app for restaurants: guests scan a QR/table link, send quick service requests or feedback, order from a selected menu, and staff can manage everything from a lightweight dashboard.

It is intentionally simple: no database, no build step, no framework. It works as a static local demo using `localStorage`, and automatically syncs to the PHP JSON backend when hosted with PHP. It also includes a Dotykačka/Dotypos API connector skeleton for menu sync and future live POS order posting.

## What it does

- Guest page for table-specific requests: service, payment, cleaning, order issue, feedback.
- QR order page where the guest selects menu items for their mapped table.
- Staff dashboard with live queue, status changes, filters, stats, and export.
- QR/link generator for table numbers.
- PHP API storing tickets/orders in JSON files.
- Dotykačka/Dotypos API v2 connector skeleton for products/categories and table mapping.
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
public/order.html       QR table ordering page
public/dashboard.html   Staff dashboard
public/app.js           Request frontend logic
public/order.js         Ordering frontend logic
public/menu.sample.json Demo menu for static hosting/GitHub Pages
public/styles.css       UI styles
api/tickets.php         JSON ticket API
api/menu.php            Menu API with Dotykačka fallback
api/orders.php          Order API / safe Dotykačka dry-run
api/dotypos.php         Dotykačka API client skeleton
public/api/*.php        Web-root proxies for PHP APIs
data/tickets.json       Storage file
config.example.php      Dotykačka credentials/table/category config template
```

## GitHub Pages demo

Static demo is available after GitHub Pages deploys:

```text
https://peteruu.github.io/tablepulse-restaurant/order.html?t=7
```

Because GitHub Pages cannot run PHP, it uses `menu.sample.json` and browser storage. A PHP host is needed for real Dotykačka integration.

## Dotykačka setup notes

Dotykačka API v2 needs a registered client app, `cloudId`, and refresh token. The app is prepared for environment variables:

```bash
DOTYPOS_CLOUD_ID=...
DOTYPOS_REFRESH_TOKEN=...
DOTYPOS_VISIBLE_CATEGORY_IDS=123,456
```

For shared hosting, copy `config.example.php` to `config.php`, fill only server-side values, and never commit it.

Table mapping is in `config.example.php` under `tables`: QR table number → Dotykačka table/location id.

Order posting is intentionally in safe `dry-run` mode until tested against the real restaurant license/POS endpoint, so it cannot accidentally create kitchen orders while we are still developing.

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
