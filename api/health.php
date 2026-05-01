<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

tp_cors(['GET', 'OPTIONS']);

$config = tp_config();
$client = tp_dotypos($config);
$result = [
    'ok' => true,
    'php' => PHP_VERSION,
    'curl' => function_exists('curl_init'),
    'dotypos' => [
        'configured' => $client->configured(),
        'canSendPosActions' => $client->canSendPosActions(),
        'orderMode' => $client->orderMode(),
        'cloudIdSet' => ($config['dotypos']['cloud_id'] ?? '') !== '',
        'branchIdSet' => ($config['dotypos']['branch_id'] ?? '') !== '',
        'refreshTokenSet' => ($config['dotypos']['refresh_token'] ?? '') !== '',
        'employeeIdSet' => ($config['dotypos']['employee_id'] ?? '') !== '',
        'visibleCategoryCount' => count($config['dotypos']['visible_category_ids'] ?? []),
    ],
    'storage' => [
        'dataDir' => is_dir(TABLEPULSE_DATA) || mkdir(TABLEPULSE_DATA, 0775, true),
        'writable' => is_writable(TABLEPULSE_DATA),
    ],
];

tp_respond($result);
