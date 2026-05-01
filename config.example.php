<?php

return [
    // Dotypos/Dotykačka API v2. Never commit real secrets.
    'dotypos' => [
        'base_url' => getenv('DOTYPOS_BASE_URL') ?: 'https://api.dotykacka.cz/v2',
        'cloud_id' => getenv('DOTYPOS_CLOUD_ID') ?: '',
        'refresh_token' => getenv('DOTYPOS_REFRESH_TOKEN') ?: '',
        // Categories that guests may see/order from. Use Dotykačka category IDs.
        'visible_category_ids' => array_filter(explode(',', getenv('DOTYPOS_VISIBLE_CATEGORY_IDS') ?: '')),
    ],

    // QR table number => Dotykačka table/location id mapping.
    'tables' => [
        '1' => ['dotypos_table_id' => null, 'name' => 'Table 1'],
        '2' => ['dotypos_table_id' => null, 'name' => 'Table 2'],
        '7' => ['dotypos_table_id' => null, 'name' => 'Table 7'],
    ],
];
