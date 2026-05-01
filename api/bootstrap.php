<?php

declare(strict_types=1);

const TABLEPULSE_ROOT = __DIR__ . DIRECTORY_SEPARATOR . '..';
const TABLEPULSE_DATA = TABLEPULSE_ROOT . DIRECTORY_SEPARATOR . 'data';

function tp_cors(array $methods = ['GET', 'POST', 'PATCH', 'OPTIONS']): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function tp_config(): array
{
    $local = TABLEPULSE_ROOT . DIRECTORY_SEPARATOR . 'config.php';
    $example = TABLEPULSE_ROOT . DIRECTORY_SEPARATOR . 'config.example.php';
    return require file_exists($local) ? $local : $example;
}

function tp_respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function tp_body(): array
{
    $decoded = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function tp_clean_string(mixed $value, int $limit = 500): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
}

function tp_data_file(string $name): string
{
    if (!is_dir(TABLEPULSE_DATA)) {
        mkdir(TABLEPULSE_DATA, 0775, true);
    }
    return TABLEPULSE_DATA . DIRECTORY_SEPARATOR . $name;
}

function tp_read_json(string $name, array $fallback = []): array
{
    $path = tp_data_file($name);
    if (!file_exists($path)) {
        return $fallback;
    }

    $decoded = json_decode(file_get_contents($path) ?: '', true);
    return is_array($decoded) ? $decoded : $fallback;
}

function tp_write_json(string $name, array $payload): void
{
    $path = tp_data_file($name);
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
        tp_respond(['error' => 'Unable to write ' . $name], 500);
    }
}

function tp_append_json(string $name, array $entry): array
{
    $items = tp_read_json($name, []);
    array_unshift($items, $entry);
    tp_write_json($name, $items);
    return $entry;
}

function tp_uuid(string $prefix = ''): string
{
    try {
        return $prefix . bin2hex(random_bytes(16));
    } catch (Throwable) {
        return $prefix . uniqid('', true);
    }
}

function tp_now(): string
{
    return gmdate('c');
}

function tp_table_map(array $config): array
{
    $configured = $config['tables'] ?? [];
    $runtime = tp_read_json('table-map.json', []);
    return array_replace_recursive(is_array($configured) ? $configured : [], is_array($runtime) ? $runtime : []);
}

function tp_cache_get(string $name, int $ttlSeconds): ?array
{
    $cache = tp_read_json($name, []);
    if (($cache['cachedAt'] ?? 0) + $ttlSeconds < time()) {
        return null;
    }
    return is_array($cache['data'] ?? null) ? $cache['data'] : null;
}

function tp_cache_set(string $name, array $data): array
{
    tp_write_json($name, ['cachedAt' => time(), 'data' => $data]);
    return $data;
}
