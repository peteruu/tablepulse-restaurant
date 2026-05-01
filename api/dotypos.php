<?php

declare(strict_types=1);

final class DotyposClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $cloudId,
        private readonly string $refreshToken,
    ) {
    }

    public function configured(): bool
    {
        return $this->cloudId !== '' && $this->refreshToken !== '';
    }

    public function accessToken(): string
    {
        $response = $this->request('POST', '/signin/token', [
            '_cloudId' => (int) $this->cloudId,
        ], 'User ' . $this->refreshToken);

        if (!isset($response['accessToken'])) {
            throw new RuntimeException('Dotypos token response did not contain accessToken');
        }

        return (string) $response['accessToken'];
    }

    public function products(array $visibleCategoryIds = []): array
    {
        $token = $this->accessToken();
        $products = $this->request('GET', '/clouds/' . rawurlencode($this->cloudId) . '/products', null, 'Bearer ' . $token);
        $items = $products['data'] ?? $products;

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static function (array $product) use ($visibleCategoryIds): bool {
            if (($product['deleted'] ?? false) || ($product['display'] ?? true) === false) {
                return false;
            }
            if ($visibleCategoryIds === []) {
                return true;
            }
            return in_array((string) ($product['_categoryId'] ?? ''), $visibleCategoryIds, true);
        }));
    }

    public function createOrderPayload(array $order, array $tableMap): array
    {
        // Dotypos order write endpoints/validation can differ by license and POS setup.
        // Keep this payload centralized so we can adapt it after testing with real credentials.
        $table = (string) ($order['table'] ?? '');
        $mappedTable = $tableMap[$table]['dotypos_table_id'] ?? null;

        return [
            'externalId' => (string) ($order['id'] ?? uniqid('tablepulse-', true)),
            'table' => $table,
            '_tableId' => $mappedTable,
            'note' => (string) ($order['note'] ?? ''),
            'items' => array_map(static fn (array $item): array => [
                '_productId' => $item['dotyposProductId'] ?? null,
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => (float) ($item['qty'] ?? 1),
                'priceWithVat' => (float) ($item['price'] ?? 0),
            ], $order['items'] ?? []),
        ];
    }

    public function request(string $method, string $path, ?array $payload = null, ?string $authorization = null): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $headers = [
            'Accept: application/json; charset=UTF-8',
            'Content-Type: application/json; charset=UTF-8',
        ];
        if ($authorization !== null) {
            $headers[] = 'Authorization: ' . $authorization;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 12,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $status >= 400) {
            throw new RuntimeException('Dotypos API request failed: ' . ($error ?: 'HTTP ' . $status));
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

function tablepulse_config(): array
{
    $local = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';
    $example = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.example.php';
    return require file_exists($local) ? $local : $example;
}

function tablepulse_dotypos(array $config): DotyposClient
{
    return new DotyposClient(
        (string) ($config['dotypos']['base_url'] ?? 'https://api.dotykacka.cz/v2'),
        (string) ($config['dotypos']['cloud_id'] ?? ''),
        (string) ($config['dotypos']['refresh_token'] ?? ''),
    );
}
