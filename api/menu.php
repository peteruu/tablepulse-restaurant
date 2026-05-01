<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function respond(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$config = tablepulse_config();
$client = tablepulse_dotypos($config);
$visibleCategoryIds = array_map('strval', $config['dotypos']['visible_category_ids'] ?? []);

if (!$client->configured()) {
    $sample = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'menu.sample.json');
    $data = json_decode($sample ?: '{}', true) ?: [];
    $data['source'] = 'sample';
    respond($data);
}

try {
    $products = $client->products($visibleCategoryIds);
    $categoryMap = [];
    $items = [];

    foreach ($products as $product) {
        $categoryId = (string) ($product['_categoryId'] ?? 'uncategorized');
        $categoryMap[$categoryId] ??= [
            'id' => $categoryId,
            'name' => 'Category ' . $categoryId,
            'visible' => true,
        ];

        $items[] = [
            'id' => (string) ($product['id'] ?? uniqid('product-', true)),
            'categoryId' => $categoryId,
            'name' => (string) ($product['name'] ?? 'Unnamed item'),
            'description' => (string) ($product['description'] ?? ''),
            'price' => (float) ($product['priceWithVat'] ?? $product['priceWithoutVat'] ?? 0),
            'dotyposProductId' => $product['id'] ?? null,
            'visible' => true,
        ];
    }

    respond([
        'source' => 'dotypos',
        'categories' => array_values($categoryMap),
        'items' => $items,
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    respond(['error' => 'Dotypos menu sync failed', 'detail' => $e->getMessage()]);
}
