<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

tp_cors(['GET', 'POST', 'OPTIONS']);

$config = tp_config();
tp_require_auth($config);
$client = tp_dotypos($config);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $body = tp_body();
    $map = [];
    foreach (($body['tables'] ?? []) as $qrTable => $table) {
        $qr = tp_clean_string($qrTable, 20);
        if ($qr === '') {
            continue;
        }
        $map[$qr] = [
            'dotypos_table_id' => $table['dotypos_table_id'] ?? null,
            'name' => tp_clean_string($table['name'] ?? ('Table ' . $qr), 180),
        ];
    }
    tp_write_json('table-map.json', $map);
    tp_respond(['ok' => true, 'tables' => tp_table_map($config)]);
}

$ttl = (int) ($config['dotypos']['cache_ttl_seconds'] ?? 900);
$refresh = ($_GET['refresh'] ?? '') === '1';
$dotyposTables = [];
$source = 'configured';

if ($client->configured()) {
    try {
        if (!$refresh && ($cached = tp_cache_get('tables-cache.json', $ttl)) !== null) {
            $dotyposTables = $cached;
            $source = 'dotypos-cache';
        } else {
            $dotyposTables = tp_cache_set('tables-cache.json', $client->tables());
            $source = 'dotypos';
        }
    } catch (Throwable $e) {
        $source = 'configured-fallback';
    }
}

tp_respond([
    'source' => $source,
    'mappedTables' => tp_table_map($config),
    'dotyposTables' => $dotyposTables,
]);
