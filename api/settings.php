<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

tp_cors(['GET', 'POST', 'OPTIONS']);

$config = tp_config();
$client = tp_dotypos($config);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function tp_runtime_settings(array $config): array
{
    $runtime = tp_read_json('settings.json', []);
    $dotypos = $config['dotypos'] ?? [];

    return array_replace_recursive([
        'visibleCategoryIds' => array_values(array_map('strval', $dotypos['visible_category_ids'] ?? [])),
        'orderMode' => (string) ($dotypos['order_mode'] ?? 'dry-run'),
    ], is_array($runtime) ? $runtime : []);
}

function tp_safe_settings(array $settings): array
{
    return [
        'visibleCategoryIds' => array_values(array_map('strval', $settings['visibleCategoryIds'] ?? [])),
        'orderMode' => in_array(($settings['orderMode'] ?? 'dry-run'), ['dry-run', 'live'], true) ? $settings['orderMode'] : 'dry-run',
    ];
}

if ($method === 'POST') {
    $body = tp_body();
    $settings = tp_safe_settings([
        'visibleCategoryIds' => $body['visibleCategoryIds'] ?? [],
        'orderMode' => $body['orderMode'] ?? 'dry-run',
    ]);
    tp_write_json('settings.json', $settings);
    tp_respond(['ok' => true, 'settings' => $settings]);
}

$health = [
    'configured' => $client->configured(),
    'canSendPosActions' => $client->canSendPosActions(),
    'orderMode' => $client->orderMode(),
    'cloudIdSet' => ($config['dotypos']['cloud_id'] ?? '') !== '',
    'branchIdSet' => ($config['dotypos']['branch_id'] ?? '') !== '',
    'refreshTokenSet' => ($config['dotypos']['refresh_token'] ?? '') !== '',
];

tp_respond([
    'settings' => tp_runtime_settings($config),
    'health' => $health,
]);
