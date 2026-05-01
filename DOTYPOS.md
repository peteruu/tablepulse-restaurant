# Dotykačka / Dotypos backend integration

This backend is prepared for QR table ordering in a restaurant using Dotykačka.

## What is implemented

- Dotypos API v2 auth with refresh token → access token cache.
- Products/categories sync for guest menu.
- Guest-visible category whitelist.
- Dotypos tables sync.
- QR table number → Dotypos table ID mapping.
- Order API with local durable queue in `data/orders.json`.
- POS Actions payload builder for `order/create`.
- Safe modes:
  - `dry-run`: build payload and store order locally.
  - `live`: send `order/create` to Dotypos branch POS device.
- Health endpoint for deployment checks.

## Important Dotypos details

Regular `/orders` endpoints are read/options-oriented. Creating a kitchen/POS order is done through POS Actions:

```text
POST /v2/clouds/:cloudId/branches/:branchId/pos-actions
```

Payload shape used by this app:

```json
{
  "action": "order/create",
  "external-id": "tablepulse-order-id",
  "table-id": 123456789,
  "user-id": 123456789,
  "note": "TablePulse QR order | QR table: 7 | guest note",
  "items": [
    { "id": 987654321, "qty": 2, "manual-price": 4.9 }
  ],
  "lock": false,
  "validity": 1777635000,
  "idempotency-key": "tablepulse-order-id"
}
```

The target branch device must be online. If Dotypos returns a delayed/webhook response, configure `DOTYPOS_POS_ACTION_WEBHOOK`.

## Required credentials later

When Peter sends access, create `config.php` on the PHP host, or use env vars:

```bash
DOTYPOS_CLOUD_ID=...
DOTYPOS_BRANCH_ID=...
DOTYPOS_REFRESH_TOKEN=...
DOTYPOS_EMPLOYEE_ID=...              # optional but recommended
DOTYPOS_VISIBLE_CATEGORY_IDS=123,456  # categories visible in QR menu
DOTYPOS_ORDER_MODE=dry-run            # switch to live only after testing
DOTYPOS_POS_ACTION_WEBHOOK=...        # optional
```

Never commit real values.

## Endpoints

```text
GET  /api/health.php
GET  /api/settings.php
POST /api/settings.php
GET  /api/menu.php?refresh=1
GET  /api/tables.php?refresh=1
POST /api/tables.php
GET  /api/orders.php
POST /api/orders.php
PATCH /api/orders.php?id=<order-id>
GET  /api/pos-actions.php?table=7
POST /api/pos-actions.php
```

## Runtime setup helper

Open `/config.html` on a PHP deployment to prepare visible categories, order mode, and QR table mapping without editing PHP files. Values are stored in `data/settings.json` and `data/table-map.json`.

## Table mapping

`config.example.php` has starter mapping. Runtime mapping can be saved to `data/table-map.json` through `POST /api/tables.php`:

```json
{
  "tables": {
    "7": { "dotypos_table_id": 123456789, "name": "Terrace 7" }
  }
}
```

## Testing path once credentials exist

1. Deploy to PHP hosting with writable `data/`.
2. Open `/api/health.php` and verify `configured=true`, `canSendPosActions=true`, `curl=true`.
3. Keep `DOTYPOS_ORDER_MODE=dry-run`.
4. Open `/api/menu.php?refresh=1`; verify real products appear.
5. Open `/api/tables.php?refresh=1`; map QR table numbers.
6. Submit a QR order; verify it appears in `/api/orders.php` with `status=dry_run` and correct `dotyposPayload`.
7. Switch `DOTYPOS_ORDER_MODE=live` for one test table.
8. Submit one small test order while the Dotykačka branch device is online.
