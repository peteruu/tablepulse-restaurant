<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'dotypos.php';

tp_cors(['GET', 'OPTIONS']);

function tp_sample_menu(): array
{
    $sample = file_get_contents(TABLEPULSE_ROOT . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'menu.sample.json');
    $data = json_decode($sample ?: '{}', true) ?: [];
    $data['source'] = 'sample';
    return $data;
}

function tp_normalize_menu(array $products, array $categories, array $visibleCategoryIds): array
{
    $categoryNames = [];
    foreach ($categories as $category) {
        $id = (string) ($category['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $categoryNames[$id] = (string) ($category['name'] ?? ('Category ' . $id));
    }

    $categoryMap = [];
    $items = [];

    foreach ($products as $product) {
        if (($product['deleted'] ?? false) || ($product['display'] ?? true) === false) {
            continue;
        }

        $categoryId = (string) ($product['_categoryId'] ?? 'uncategorized');
        if ($visibleCategoryIds !== [] && !in_array($categoryId, $visibleCategoryIds, true)) {
            continue;
        }

        $categoryMap[$categoryId] ??= [
            'id' => $categoryId,
            'name' => $categoryNames[$categoryId] ?? ('Category ' . $categoryId),
            'visible' => true,
        ];

        $price = $product['priceWithVat'] ?? $product['priceWithoutVat'] ?? $product['price'] ?? 0;
        $items[] = [
            'id' => (string) ($product['id'] ?? tp_uuid('product_')),
            'categoryId' => $categoryId,
            'name' => (string) ($product['name'] ?? 'Unnamed item'),
            'description' => (string) ($product['description'] ?? $product['subtitle'] ?? ''),
            'price' => (float) $price,
            'dotyposProductId' => $product['id'] ?? null,
            'visible' => true,
            'vat' => $product['vat'] ?? null,
            'unit' => $product['unit'] ?? null,
        ];
    }

    usort($items, fn ($a, $b) => strcmp($a['name'], $b['name']));

    return [
        'source' => 'dotypos',
        'categories' => array_values($categoryMap),
        'items' => $items,
    ];
}

$config = tp_config();
$runtimeSettings = tp_read_json('settings.json', []);
$client = tp_dotypos($config);
$ttl = (int) ($config['dotypos']['cache_ttl_seconds'] ?? 900);
$refresh = ($_GET['refresh'] ?? '') === '1';

if (!$client->configured()) {
    tp_respond(tp_sample_menu());
}

if (!$refresh && ($cached = tp_cache_get('menu-cache.json', $ttl)) !== null) {
    $cached['cache'] = 'hit';
    tp_respond($cached);
}

try {
    $visibleCategoryIds = array_map('strval', $runtimeSettings['visibleCategoryIds'] ?? $config['dotypos']['visible_category_ids'] ?? []);
    $data = tp_normalize_menu($client->products(), $client->categories(), $visibleCategoryIds);
    $data['cache'] = 'fresh';
    tp_respond(tp_cache_set('menu-cache.json', $data));
} catch (Throwable $e) {
    $fallback = tp_cache_get('menu-cache.json', 365 * 24 * 60 * 60);
    if ($fallback !== null) {
        $fallback['source'] = 'dotypos-cache-fallback';
        $fallback['warning'] = $e->getMessage();
        tp_respond($fallback);
    }

    $sample = tp_sample_menu();
    $sample['warning'] = 'Dotypos menu sync failed: ' . $e->getMessage();
    tp_respond($sample, 200);
}
