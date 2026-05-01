<?php

return [
    'auth' => [
        'enabled' => (bool) (getenv('TABLEPULSE_AUTH_ENABLED') ?: false),
        'username' => getenv('TABLEPULSE_ADMIN_USER') ?: '',
        // Generate with: php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
        'password_hash' => getenv('TABLEPULSE_ADMIN_PASSWORD_HASH') ?: '',
    ],

    'app' => [
        'currency' => getenv('TABLEPULSE_CURRENCY') ?: 'EUR',
        'timezone' => getenv('TABLEPULSE_TIMEZONE') ?: 'Europe/Bratislava',
    ],

    // Dotypos/Dotykačka API v2. Never commit real secrets.
    'dotypos' => [
        'base_url' => getenv('DOTYPOS_BASE_URL') ?: 'https://api.dotykacka.cz/v2',
        'cloud_id' => getenv('DOTYPOS_CLOUD_ID') ?: '',
        'branch_id' => getenv('DOTYPOS_BRANCH_ID') ?: '',
        'refresh_token' => getenv('DOTYPOS_REFRESH_TOKEN') ?: '',
        'employee_id' => getenv('DOTYPOS_EMPLOYEE_ID') ?: '',

        // dry-run = build payload and store locally only
        // live = send POS Action order/create to Dotykačka branch device
        'order_mode' => getenv('DOTYPOS_ORDER_MODE') ?: 'dry-run',

        // Optional webhook where Dotypos sends async POS action response.
        // Leave empty to use default synchronous-ish response behaviour.
        'webhook_url' => getenv('DOTYPOS_POS_ACTION_WEBHOOK') ?: '',

        // Guest-visible Dotykačka category IDs. Empty = all visible products.
        'visible_category_ids' => array_values(array_filter(array_map('trim', explode(',', getenv('DOTYPOS_VISIBLE_CATEGORY_IDS') ?: '')))),

        // Cache menu/tables locally to avoid the API limit: 150 requests / 30 minutes.
        'cache_ttl_seconds' => (int) (getenv('DOTYPOS_CACHE_TTL_SECONDS') ?: 900),
    ],

    // QR table number => Dotykačka table/location id mapping.
    // Can be overridden at runtime in data/table-map.json via api/tables.php.
    'tables' => [
        '1' => ['dotypos_table_id' => null, 'name' => 'Table 1'],
        '2' => ['dotypos_table_id' => null, 'name' => 'Table 2'],
        '7' => ['dotypos_table_id' => null, 'name' => 'Table 7'],
    ],
];
