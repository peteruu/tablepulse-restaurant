# U Marienky QR objednávka

A lightweight PHP + JavaScript ordering module prepared for `restauraciaumarienky.sk`: guests scan a QR/table link, order from selected menu categories, and the backend sends the order into Dotykačka open accounts via POS Actions.

It is intentionally simple: no database, no build step, no framework. It works as a static local demo using `localStorage`, and syncs to a PHP JSON backend when hosted with PHP. The backend is prepared for Dotykačka/Dotypos API menu sync and live POS order posting.

## What it does

- QR order page for U Marienky where the guest selects menu items for their mapped table, including per-item notes.
- Optional guest page for table-specific requests: service, payment, cleaning, order issue, feedback.
- Lightweight request dashboard for non-order requests/feedback only.
- Printable QR generator for table links.
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
http://localhost:8080/order.html?t=7
http://localhost:8080/config.php
http://localhost:8080/qr.html
http://localhost:8080/?t=7
http://localhost:8080/dashboard.html
```

Run the backend smoke test:

```bash
node scripts/smoke-test.mjs http://localhost:8080
```

The repo includes `public/api/tickets.php` as a tiny proxy to the real API, so the PHP built-in server works with the command above. On shared hosting, deploy the full repo and keep `data/` writable.

## File structure

```text
public/index.html       Guest/table request page
public/order.html       QR table ordering page
public/config.php       Protected setup helper entry point
public/config.html      Setup helper UI for categories/table mapping/order mode
public/qr.html          Printable QR code generator for table links
public/dashboard.html   Optional request/feedback dashboard
public/app.js           Request frontend logic
public/order.js         Ordering frontend logic
public/menu.sample.json Demo menu for static hosting/GitHub Pages
public/styles.css       UI styles
api/bootstrap.php       Shared config/storage/helpers
api/tickets.php         JSON ticket API
api/menu.php            Menu API with Dotykačka fallback/cache
api/settings.php        Runtime category/order-mode settings
api/tables.php          Dotykačka table sync + QR table mapping
api/orders.php          Order API with durable fallback queue + POS Actions sending
api/pos-actions.php     Low-level POS Actions proxy for tests
api/health.php          Deployment/config health check
api/dotypos.php         Dotykačka API client
public/api/*.php        Web-root proxies for PHP APIs
data/tickets.json       Storage file
config.example.php      Dotykačka credentials/table/category config template
DOTYPOS.md              Backend integration notes
```

## restauraciaumarienky.sk integration target

Recommended deployment shape later:

```text
https://restauraciaumarienky.sk/order/?t=7
https://restauraciaumarienky.sk/order/config.php
https://restauraciaumarienky.sk/order/qr.html
```

The QR generator defaults GitHub Pages preview cards to the final `https://restauraciaumarienky.sk/order/` base URL so printed codes can already be prepared for the restaurant domain.

## GitHub Pages demo

Static demo is available after GitHub Pages deploys:

```text
https://peteruu.github.io/tablepulse-restaurant/order.html?t=7
https://peteruu.github.io/tablepulse-restaurant/qr.html
```

Because GitHub Pages cannot run PHP, it uses `menu.sample.json` and browser storage. A PHP host is needed for real Dotykačka integration.

## Dotykačka setup notes

The backend is prepared for Dotykačka/Dotypos API v2 and POS Actions. See [`DOTYPOS.md`](DOTYPOS.md) for the full integration notes.

Minimum server-side values later:

```bash
DOTYPOS_CLOUD_ID=...
DOTYPOS_BRANCH_ID=...
DOTYPOS_REFRESH_TOKEN=...
DOTYPOS_EMPLOYEE_ID=...
DOTYPOS_VISIBLE_CATEGORY_IDS=123,456
DOTYPOS_ORDER_MODE=dry-run
```

For shared hosting, copy `config.example.php` to `config.php`, fill only server-side values, enable auth, and never commit it.

Admin auth example:

```php
$config['auth'] = [
    'enabled' => true,
    'username' => 'admin-name',
    'password_hash' => password_hash('change-me', PASSWORD_DEFAULT),
];
```

Use `/config.php` for the protected setup page. `/config.html` is only the static UI shell.

Order posting is intentionally in safe `dry-run` mode by default. Switching `DOTYPOS_ORDER_MODE=live` sends `order/create` through:

```text
POST /v2/clouds/:cloudId/branches/:branchId/pos-actions
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

This is a practical U Marienky QR-ordering module: small enough to deploy on ordinary PHP hosting, useful enough for real table testing, and easy to evolve into a Symfony/MySQL version later if needed.
