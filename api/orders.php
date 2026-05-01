<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

tp_cors(['GET', 'POST', 'PATCH', 'OPTIONS']);

function tp_orders(): array
{
    return tp_read_json('orders.json', []);
}

function tp_save_orders(array $orders): void
{
    tp_write_json('orders.json', array_values($orders));
}

function tp_validate_order(array $input): array
{
    $items = [];
    foreach (($input['items'] ?? []) as $item) {
        $qty = max(0.0, (float) ($item['qty'] ?? 0));
        if ($qty <= 0) {
            continue;
        }
        $items[] = [
            'id' => tp_clean_string($item['id'] ?? '', 80),
            'name' => tp_clean_string($item['name'] ?? '', 180),
            'price' => (float) ($item['price'] ?? 0),
            'qty' => $qty,
            'note' => tp_clean_string($item['note'] ?? '', 500),
            'dotyposProductId' => $item['dotyposProductId'] ?? null,
            'courseId' => $item['courseId'] ?? null,
            'takeAway' => $item['takeAway'] ?? null,
        ];
    }

    if ($items === []) {
        tp_respond(['error' => 'Order has no valid items'], 422);
    }

    $total = array_reduce($items, static fn ($sum, $item) => $sum + ($item['price'] * $item['qty']), 0.0);

    return [
        'id' => tp_clean_string($input['id'] ?? tp_uuid('order_'), 120),
        'table' => tp_clean_string($input['table'] ?? '1', 20),
        'note' => tp_clean_string($input['note'] ?? '', 1000),
        'items' => $items,
        'total' => round($total, 2),
        'status' => 'received',
        'createdAt' => tp_clean_string($input['createdAt'] ?? tp_now(), 40),
        'receivedAt' => tp_now(),
    ];
}

function tp_upsert_order(array $order): array
{
    $orders = tp_orders();
    $replaced = false;
    foreach ($orders as &$existing) {
        if (($existing['id'] ?? '') === ($order['id'] ?? null)) {
            $existing = array_replace_recursive($existing, $order);
            $replaced = true;
            break;
        }
    }
    unset($existing);

    if (!$replaced) {
        array_unshift($orders, $order);
    }
    tp_save_orders($orders);
    return $order;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$config = tp_config();
$client = tp_dotypos($config);

if ($method === 'GET') {
    tp_require_auth($config);
    $orders = tp_orders();
    if (($_GET['status'] ?? '') !== '') {
        $status = (string) $_GET['status'];
        $orders = array_values(array_filter($orders, fn ($order) => ($order['status'] ?? '') === $status));
    }
    tp_respond(['orders' => $orders]);
}

if ($method === 'PATCH') {
    tp_require_auth($config);
    $id = tp_clean_string($_GET['id'] ?? '', 120);
    $body = tp_body();
    $orders = tp_orders();
    $found = false;
    foreach ($orders as &$order) {
        if (($order['id'] ?? '') === $id) {
            foreach (['status', 'staffNote'] as $field) {
                if (array_key_exists($field, $body)) {
                    $order[$field] = tp_clean_string($body[$field], 1000);
                }
            }
            $order['updatedAt'] = tp_now();
            $found = true;
            break;
        }
    }
    unset($order);

    if (!$found) {
        tp_respond(['error' => 'Order not found'], 404);
    }
    tp_save_orders($orders);
    tp_respond(['ok' => true]);
}

if ($method !== 'POST') {
    tp_respond(['error' => 'Method not allowed'], 405);
}

$order = tp_validate_order(tp_body());
$tableMap = tp_table_map($config);
$runtimeSettings = tp_read_json('settings.json', []);
$mode = (string) ($runtimeSettings['orderMode'] ?? $client->orderMode());

try {
    $order['dotyposPayload'] = $client->createOrderAction($order, $tableMap);
} catch (Throwable $e) {
    $order['dotyposPayloadError'] = $e->getMessage();
}

if (!$client->canSendPosActions()) {
    $order['status'] = 'queued_missing_credentials';
    tp_upsert_order($order);
    tp_respond([
        'ok' => true,
        'mode' => 'queued_missing_credentials',
        'order' => $order,
        'message' => 'Dotypos credentials/branch are not configured yet.',
    ]);
}

if ($mode !== 'live') {
    $order['status'] = 'dry_run';
    tp_upsert_order($order);
    tp_respond([
        'ok' => true,
        'mode' => 'dry-run',
        'order' => $order,
        'dotyposPayload' => $order['dotyposPayload'] ?? null,
    ]);
}

try {
    $response = $client->sendCreateOrder($order, $tableMap);
    $order['status'] = 'sent_to_dotypos';
    $order['dotyposResponse'] = $response;
    tp_upsert_order($order);
    tp_respond(['ok' => true, 'mode' => 'live', 'order' => $order, 'dotyposResponse' => $response]);
} catch (Throwable $e) {
    $order['status'] = 'queued_send_failed';
    $order['dotyposError'] = $e->getMessage();
    tp_upsert_order($order);
    tp_respond(['ok' => true, 'mode' => 'queued_send_failed', 'order' => $order, 'warning' => $e->getMessage()], 202);
}
