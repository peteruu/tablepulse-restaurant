<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

tp_cors(['GET', 'POST', 'OPTIONS']);

$config = tp_config();
$client = tp_dotypos($config);

if (!$client->canSendPosActions()) {
    tp_respond(['ok' => false, 'error' => 'Dotypos cloud_id, branch_id or refresh_token is missing'], 400);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $table = tp_clean_string($_GET['table'] ?? '', 20);
        $tableMap = tp_table_map($config);
        $dotyposTableId = $table !== '' ? (string) ($tableMap[$table]['dotypos_table_id'] ?? '') : '';
        tp_respond(['ok' => true, 'result' => $client->openOrders($dotyposTableId)]);
    }

    $body = tp_body();
    if (($body['action'] ?? '') === '') {
        tp_respond(['error' => 'Missing action'], 422);
    }

    tp_respond(['ok' => true, 'result' => $client->posAction($body)]);
} catch (Throwable $e) {
    tp_respond(['ok' => false, 'error' => $e->getMessage()], 502);
}
