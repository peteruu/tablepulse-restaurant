<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

final class DotyposClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function configured(): bool
    {
        return $this->cloudId() !== '' && $this->refreshToken() !== '';
    }

    public function canSendPosActions(): bool
    {
        return $this->configured() && $this->branchId() !== '';
    }

    public function cloudId(): string
    {
        return (string) ($this->config['cloud_id'] ?? '');
    }

    public function branchId(): string
    {
        return (string) ($this->config['branch_id'] ?? '');
    }

    public function orderMode(): string
    {
        return (string) ($this->config['order_mode'] ?? 'dry-run');
    }

    public function accessToken(): string
    {
        $cache = tp_read_json('dotypos-token.json', []);
        if (($cache['accessToken'] ?? '') !== '' && (int) ($cache['expiresAt'] ?? 0) > time() + 60) {
            return (string) $cache['accessToken'];
        }

        $response = $this->rawRequest('POST', '/signin/token', [
            '_cloudId' => (int) $this->cloudId(),
        ], 'User ' . $this->refreshToken());

        if (!isset($response['accessToken'])) {
            throw new RuntimeException('Dotypos token response did not contain accessToken');
        }

        $ttl = (int) ($response['expiresIn'] ?? 1200);
        tp_write_json('dotypos-token.json', [
            'accessToken' => (string) $response['accessToken'],
            'expiresAt' => time() + max(300, $ttl - 60),
        ]);

        return (string) $response['accessToken'];
    }

    public function products(array $query = []): array
    {
        return $this->pagedGet('/clouds/' . rawurlencode($this->cloudId()) . '/products', $query);
    }

    public function categories(array $query = []): array
    {
        return $this->pagedGet('/clouds/' . rawurlencode($this->cloudId()) . '/categories', $query);
    }

    public function tables(array $query = []): array
    {
        return $this->pagedGet('/clouds/' . rawurlencode($this->cloudId()) . '/tables', $query);
    }

    public function openOrders(?string $tableId = null): array
    {
        $payload = ['action' => 'order/list'];
        if ($tableId !== null && $tableId !== '') {
            $payload['table-id'] = (int) $tableId;
        }
        return $this->posAction($payload);
    }

    public function createOrderAction(array $order, array $tableMap): array
    {
        $table = (string) ($order['table'] ?? '');
        $mappedTable = $tableMap[$table]['dotypos_table_id'] ?? null;
        $noteParts = array_filter([
            'TablePulse QR order',
            $table !== '' ? 'QR table: ' . $table : null,
            (string) ($order['note'] ?? ''),
        ]);

        $payload = [
            'action' => 'order/create',
            'external-id' => (string) ($order['id'] ?? tp_uuid('tp_')),
            'note' => implode(' | ', $noteParts),
            'items' => array_values(array_map(fn (array $item): array => $this->mapOrderItem($item), $order['items'] ?? [])),
            'lock' => false,
            'idempotency-key' => (string) ($order['id'] ?? tp_uuid('tp_')),
            'validity' => time() + 120,
        ];

        if ($mappedTable !== null && $mappedTable !== '') {
            $payload['table-id'] = (int) $mappedTable;
        }
        if (($this->config['employee_id'] ?? '') !== '') {
            $payload['user-id'] = (int) $this->config['employee_id'];
        }
        if (($this->config['webhook_url'] ?? '') !== '') {
            $payload['webhook'] = (string) $this->config['webhook_url'];
        }

        return $payload;
    }

    public function sendCreateOrder(array $order, array $tableMap): array
    {
        return $this->posAction($this->createOrderAction($order, $tableMap));
    }

    public function posAction(array $payload): array
    {
        if (!$this->canSendPosActions()) {
            throw new RuntimeException('Dotypos cloud_id, branch_id or refresh_token is missing');
        }

        return $this->request(
            'POST',
            '/clouds/' . rawurlencode($this->cloudId()) . '/branches/' . rawurlencode($this->branchId()) . '/pos-actions',
            $payload
        );
    }

    public function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        return $this->rawRequest($method, $path, $payload, 'Bearer ' . $this->accessToken(), $query);
    }

    private function pagedGet(string $path, array $query = []): array
    {
        $query = array_merge(['limit' => 100], $query);
        $all = [];
        $page = (int) ($query['page'] ?? 1);

        do {
            $query['page'] = $page;
            $response = $this->request('GET', $path, null, $query);
            $items = $response['data'] ?? $response;
            if (!is_array($items)) {
                return [];
            }
            $all = array_merge($all, array_values($items));
            $hasNext = isset($response['page'], $response['pages']) && (int) $response['page'] < (int) $response['pages'];
            $page++;
        } while ($hasNext && $page < 50);

        return $all;
    }

    private function mapOrderItem(array $item): array
    {
        $productId = $item['dotyposProductId'] ?? $item['_productId'] ?? $item['id'] ?? null;
        $mapped = [
            'id' => (int) $productId,
            'qty' => (float) ($item['qty'] ?? 1),
        ];

        if (($item['note'] ?? '') !== '') {
            $mapped['note'] = tp_clean_string($item['note'], 500);
        }
        if (($item['price'] ?? null) !== null) {
            $mapped['manual-price'] = (float) $item['price'];
        }
        if (($item['courseId'] ?? null) !== null) {
            $mapped['course-id'] = (int) $item['courseId'];
        }
        if (($item['takeAway'] ?? null) !== null) {
            $mapped['take-away'] = (bool) $item['takeAway'];
        }

        return $mapped;
    }

    private function rawRequest(string $method, string $path, ?array $payload = null, ?string $authorization = null, array $query = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Dotypos API calls');
        }

        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.dotykacka.cz/v2'), '/') . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

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
            CURLOPT_TIMEOUT => 21,
            CURLOPT_HEADER => false,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $status >= 400) {
            throw new RuntimeException('Dotypos API request failed: ' . ($error ?: 'HTTP ' . $status . ' ' . (string) $raw));
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : ['raw' => (string) $raw];
    }

    private function refreshToken(): string
    {
        return (string) ($this->config['refresh_token'] ?? '');
    }
}

function tp_dotypos(array $config): DotyposClient
{
    return new DotyposClient($config['dotypos'] ?? []);
}
