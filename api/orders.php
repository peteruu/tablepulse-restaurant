<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array
{
    $decoded = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function storeOrder(array $order): void
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'orders.json';
    $orders = [];
    if (file_exists($path)) {
        $orders = json_decode(file_get_contents($path) ?: '[]', true) ?: [];
    }
    array_unshift($orders, $order);
    file_put_contents($path, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$order = body();
if (($order['items'] ?? []) === []) {
    respond(['error' => 'Order has no items'], 422);
}

$order['receivedAt'] = gmdate('c');
$config = tablepulse_config();
$client = tablepulse_dotypos($config);

// Always store locally first. If Dotykačka integration fails, staff can still see/recover the order.
storeOrder($order);

if (!$client->configured()) {
    respond(['ok' => true, 'mode' => 'stored-only', 'message' => 'Dotypos credentials are not configured yet.']);
}

try {
    $payload = $client->createOrderPayload($order, $config['tables'] ?? []);

    // Integration placeholder: Dotypos supports order/POS processing, but the exact write endpoint
    // must be validated against the restaurant license and real POS setup before enabling live kitchen posting.
    // Keep the normalized payload in the response for safe testing without accidentally creating real orders.
    respond(['ok' => true, 'mode' => 'dry-run', 'dotyposPayload' => $payload]);
} catch (Throwable $e) {
    respond(['ok' => true, 'mode' => 'stored-fallback', 'warning' => $e->getMessage()]);
}
